<?php
/**
 * =====================================================================
 * Human Care - Security Functions Layer
 * =====================================================================
 * Drop-in helper library used by login.php. Provides:
 *
 *   - is_ip_blocked($ip)            -> bool
 *   - check_login_threat($ip, $email, $password) -> array|null
 *   - block_ip($ip, $threatType, $reason, $blockedBy = 'php') -> bool
 *   - log_security_event(...)       -> void
 *
 * DESIGN GOALS (read before editing):
 *   1. FAIL-SAFE: if security_logs_db is unreachable or any query
 *      fails, every function here degrades to a harmless default
 *      (no block, no crash) so the LOGIN SYSTEM NEVER BREAKS.
 *   2. ISOLATED: uses its own mysqli connection to security_logs_db.
 *      Never touches human_care_patients / human_care_doctors.
 *   3. FAST ONLY: checks here run synchronously inside the login
 *      request, so only cheap, single-table queries are used
 *      (brute force count, SQL-injection pattern match, blocklist
 *      lookup). Heavier cross-account analysis (credential stuffing,
 *      password spraying) is left to the Nexora Python analyzer,
 *      which runs in the background and writes to blocked_ips too.
 *
 * CHANGE LOG:
 *   - log_security_event() now calls trigger_nexora_analysis()
 *     (via nexora_trigger.php) after every failed login, so Nexora
 *     runs automatically without any manual command.
 *   - All other functions are UNCHANGED.
 * =====================================================================
 */

// Auto-trigger helper (fail-safe include)
require_once __DIR__ . '/nexora_trigger.php';

// ---------------------------------------------------------------------
// Connection (isolated, lazy, fail-safe)
// ---------------------------------------------------------------------
function _security_db_connect() {
    static $conn = null;
    static $attempted = false;

    if ($conn !== null) {
        return $conn;
    }
    if ($attempted) {
        return null;
    }
    $attempted = true;

    try {
        $c = @new mysqli('localhost', 'root', '', 'security_logs_db');
        if ($c->connect_error) {
            error_log('[security_functions] DB connect failed: ' . $c->connect_error);
            return null;
        }
        $conn = $c;
        return $conn;
    } catch (\Throwable $e) {
        error_log('[security_functions] DB connect exception: ' . $e->getMessage());
        return null;
    }
}

// ---------------------------------------------------------------------
// is_ip_blocked($ip): bool
// ---------------------------------------------------------------------
function is_ip_blocked(string $ip): bool {
    try {
        $conn = _security_db_connect();
        if (!$conn) {
            return false;
        }

        $stmt = $conn->prepare(
            "SELECT id FROM blocked_ips
             WHERE ip_address = ? AND is_active = 1
               AND (expires_at IS NULL OR expires_at > NOW())
             LIMIT 1"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('s', $ip);
        $stmt->execute();
        $stmt->store_result();
        $blocked = $stmt->num_rows > 0;
        $stmt->close();

        return $blocked;
    } catch (\Throwable $e) {
        error_log('[security_functions] is_ip_blocked error: ' . $e->getMessage());
        return false;
    }
}

// ---------------------------------------------------------------------
// get_block_details($ip): array|null
// Returns full block record for the blocked-IP page.
// NEW function added for the improved block page in login.php.
// ---------------------------------------------------------------------
function get_block_details(string $ip): ?array {
    try {
        $conn = _security_db_connect();
        if (!$conn) {
            return null;
        }

        $stmt = $conn->prepare(
            "SELECT threat_type, reason, blocked_by, blocked_at, expires_at
             FROM blocked_ips
             WHERE ip_address = ? AND is_active = 1
               AND (expires_at IS NULL OR expires_at > NOW())
             ORDER BY blocked_at DESC
             LIMIT 1"
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('s', $ip);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    } catch (\Throwable $e) {
        error_log('[security_functions] get_block_details error: ' . $e->getMessage());
        return null;
    }
}

// ---------------------------------------------------------------------
// block_ip($ip, $threatType, $reason, $blockedBy = 'php'): bool
// ---------------------------------------------------------------------
function block_ip(string $ip, string $threatType, string $reason, string $blockedBy = 'php'): bool {
    try {
        $conn = _security_db_connect();
        if (!$conn) {
            return false;
        }

        $stmt = $conn->prepare(
            "INSERT INTO blocked_ips (ip_address, threat_type, reason, blocked_by, blocked_at, is_active)
             VALUES (?, ?, ?, ?, NOW(), 1)
             ON DUPLICATE KEY UPDATE
                threat_type = VALUES(threat_type),
                reason      = VALUES(reason),
                blocked_by  = VALUES(blocked_by),
                blocked_at  = NOW(),
                is_active   = 1"
        );
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('ssss', $ip, $threatType, $reason, $blockedBy);
        $ok = $stmt->execute();
        $stmt->close();

        log_threat_event($ip, null, $threatType, $reason, $blockedBy, 'ip_blocked');

        return $ok;
    } catch (\Throwable $e) {
        error_log('[security_functions] block_ip error: ' . $e->getMessage());
        return false;
    }
}

// ---------------------------------------------------------------------
// check_login_threat($ip, $email, $password): array|null
// UNCHANGED from original.
// ---------------------------------------------------------------------
function check_login_threat(string $ip, string $email, string $password): ?array {
    try {
        if (is_ip_blocked($ip)) {
            return [
                'type'   => 'blocked_ip_reuse',
                'label'  => 'Blocked IP Reuse',
                'reason' => "IP $ip attempted login while already blocked.",
            ];
        }

        $sqlPatterns = [
            "/(\bUNION\b.*\bSELECT\b)/i",
            "/(\bOR\b\s+\d+\s*=\s*\d+)/i",
            "/(\bSELECT\b.*\bFROM\b)/i",
            "/(--|#|\/\*)/",
            "/(\bDROP\b\s+\bTABLE\b)/i",
            "/(\bINSERT\b\s+\bINTO\b)/i",
            "/('\s*OR\s*'1'\s*=\s*'1)/i",
            "/(;\s*(DROP|DELETE|UPDATE)\b)/i",
        ];
        foreach ($sqlPatterns as $pattern) {
            if (preg_match($pattern, $email) || preg_match($pattern, $password)) {
                return [
                    'type'   => 'sql_injection',
                    'label'  => 'SQL Injection Attempt',
                    'reason' => "Suspicious SQL-like pattern detected in login input from IP $ip.",
                ];
            }
        }

        $conn = _security_db_connect();
        if (!$conn) {
            return null;
        }

        $bruteForceWindow    = 1;
        $bruteForceThreshold = 15;

        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS cnt FROM login_attempts
             WHERE ip_address = ? AND status = 'failed'
               AND attempted_at >= (NOW() - INTERVAL ? MINUTE)"
        );
        if ($stmt) {
            $stmt->bind_param('si', $ip, $bruteForceWindow);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $stmt->close();

            if ($row && (int)$row['cnt'] >= $bruteForceThreshold) {
                return [
                    'type'   => 'brute_force',
                    'label'  => 'Brute Force Attack',
                    'reason' => "IP $ip made {$row['cnt']} failed login attempts in the last {$bruteForceWindow} minutes.",
                ];
            }
        }

        $rateWindowSeconds = 20;
        $rateThreshold     = 10;

        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS cnt FROM login_attempts
             WHERE ip_address = ?
               AND attempted_at >= (NOW() - INTERVAL ? SECOND)"
        );
        if ($stmt) {
            $stmt->bind_param('si', $ip, $rateWindowSeconds);
            $stmt->execute();
            $res = $stmt->get_result();
            $row = $res->fetch_assoc();
            $stmt->close();

            if ($row && (int)$row['cnt'] >= $rateThreshold) {
                return [
                    'type'   => 'suspicious_rate',
                    'label'  => 'Suspicious Login Activity',
                    'reason' => "IP $ip made {$row['cnt']} login attempts within {$rateWindowSeconds} seconds.",
                ];
            }
        }

        return null;

    } catch (\Throwable $e) {
        error_log('[security_functions] check_login_threat error: ' . $e->getMessage());
        return null;
    }
}

// ---------------------------------------------------------------------
// log_security_event(...): void
// CHANGE: now calls trigger_nexora_analysis() after logging a failed
// attempt, so Nexora runs automatically in the background.
// ---------------------------------------------------------------------
function log_security_event(string $ip, ?string $email, string $status, ?string $userType = null, ?string $threatType = null): void {
    try {
        $conn = _security_db_connect();
        if (!$conn) {
            return;
        }

        $stmt = $conn->prepare(
            "INSERT INTO login_attempts (ip_address, email, user_type, status, threat_detected, attempted_at)
             VALUES (?, ?, ?, ?, ?, NOW())"
        );
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('sssss', $ip, $email, $userType, $status, $threatType);
        $stmt->execute();
        $stmt->close();

        // -------------------------------------------------------
        // AUTO-TRIGGER NEXORA: run background analysis after every
        // failed login attempt (Nexora detects credential stuffing
        // and password spraying patterns across attempts).
        // trigger_nexora_analysis() is fire-and-forget and
        // fail-safe — it never affects the login flow.
        // -------------------------------------------------------
        if ($status === 'failed') {
            trigger_nexora_analysis();
        }

    } catch (\Throwable $e) {
        error_log('[security_functions] log_security_event error: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------
// log_threat_event(...): internal helper — UNCHANGED
// ---------------------------------------------------------------------
function log_threat_event(string $ip, ?string $email, string $threatType, string $reason, string $detectedBy = 'php', ?string $actionTaken = null): void {
    try {
        $conn = _security_db_connect();
        if (!$conn) {
            return;
        }

        $stmt = $conn->prepare(
            "INSERT INTO threat_events (ip_address, email, threat_type, reason, detected_by, detected_at, action_taken)
             VALUES (?, ?, ?, ?, ?, NOW(), ?)"
        );
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('ssssss', $ip, $email, $threatType, $reason, $detectedBy, $actionTaken);
        $stmt->execute();
        $stmt->close();
    } catch (\Throwable $e) {
        error_log('[security_functions] log_threat_event error: ' . $e->getMessage());
    }
}