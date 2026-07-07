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
 *   3. PHP-ONLY: all login threat detection runs synchronously after
 *      every login attempt is logged. No Python analyzer is required.
 *
 * CHANGE LOG:
 *   - Removed nexora_trigger.php / Python analyzer dependency.
 *   - Added real-time Credential Stuffing detection.
 *   - Added real-time Password Spraying detection.
 * =====================================================================
 */

// ---------------------------------------------------------------------
// Nexora PHP-only detection settings
// Keep these values aligned with the old nexora_config.py values.
// ---------------------------------------------------------------------
if (!defined('NEXORA_ANALYSIS_WINDOW_MINUTES')) {
    define('NEXORA_ANALYSIS_WINDOW_MINUTES', 15);
}
if (!defined('NEXORA_CRED_STUFFING_DISTINCT_EMAIL_THRESHOLD')) {
    define('NEXORA_CRED_STUFFING_DISTINCT_EMAIL_THRESHOLD', 10);
}
if (!defined('NEXORA_PASSWORD_SPRAY_MIN_ACCOUNTS')) {
    define('NEXORA_PASSWORD_SPRAY_MIN_ACCOUNTS', 10);
}
if (!defined('NEXORA_PASSWORD_SPRAY_MAX_ATTEMPTS_PER_ACCOUNT')) {
    define('NEXORA_PASSWORD_SPRAY_MAX_ATTEMPTS_PER_ACCOUNT', 3);
}

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
// Detects cheap per-request threats before the login attempt is recorded.
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
// Logs every login attempt, then immediately runs cross-account PHP
// detectors so credential stuffing and password spraying are real-time.
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

        run_realtime_login_threat_detection($ip);
    } catch (\Throwable $e) {
        error_log('[security_functions] log_security_event error: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------
// run_realtime_login_threat_detection($ip): void
// Central PHP-only detection engine called after every login attempt.
// ---------------------------------------------------------------------
function run_realtime_login_threat_detection(string $ip): void {
    try {
        detect_credential_stuffing($ip);
        detect_password_spraying($ip);
    } catch (\Throwable $e) {
        error_log('[security_functions] realtime detector error: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------
// detect_credential_stuffing($ip): void
// Detects one IP attempting logins against many distinct email accounts
// within the configured analysis window.
// ---------------------------------------------------------------------
function detect_credential_stuffing(string $ip): void {
    try {
        if (is_ip_blocked($ip)) {
            return;
        }

        $conn = _security_db_connect();
        if (!$conn) {
            return;
        }

        $window = (int)NEXORA_ANALYSIS_WINDOW_MINUTES;
        $threshold = (int)NEXORA_CRED_STUFFING_DISTINCT_EMAIL_THRESHOLD;

        $stmt = $conn->prepare(
            "SELECT COUNT(DISTINCT email) AS distinct_emails
             FROM login_attempts
             WHERE ip_address = ?
               AND attempted_at >= (NOW() - INTERVAL ? MINUTE)
               AND email IS NOT NULL AND email != ''"
        );
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('si', $ip, $window);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        $distinctEmails = $row ? (int)$row['distinct_emails'] : 0;
        if ($distinctEmails < $threshold) {
            return;
        }

        $reason = "IP $ip attempted logins with $distinctEmails different email accounts within $window minutes.";
        $riskScore = calculate_attack_risk_score('credential_stuffing', $distinctEmails, $threshold);

        block_ip($ip, 'credential_stuffing', $reason, 'php');
        send_attack_security_alert($ip, 'Credential Stuffing Attack', $reason, $riskScore);
    } catch (\Throwable $e) {
        error_log('[security_functions] detect_credential_stuffing error: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------
// detect_password_spraying($ip): void
// Detects one IP targeting many accounts where each account has only a
// low number of failed attempts within the configured analysis window.
// ---------------------------------------------------------------------
function detect_password_spraying(string $ip): void {
    try {
        if (is_ip_blocked($ip)) {
            return;
        }

        $conn = _security_db_connect();
        if (!$conn) {
            return;
        }

        $window = (int)NEXORA_ANALYSIS_WINDOW_MINUTES;
        $minAccounts = (int)NEXORA_PASSWORD_SPRAY_MIN_ACCOUNTS;
        $maxAttempts = (int)NEXORA_PASSWORD_SPRAY_MAX_ATTEMPTS_PER_ACCOUNT;

        $stmt = $conn->prepare(
            "SELECT email, COUNT(*) AS attempts
             FROM login_attempts
             WHERE ip_address = ?
               AND attempted_at >= (NOW() - INTERVAL ? MINUTE)
               AND email IS NOT NULL AND email != ''
               AND status = 'failed'
             GROUP BY email"
        );
        if (!$stmt) {
            return;
        }
        $stmt->bind_param('si', $ip, $window);
        $stmt->execute();
        $res = $stmt->get_result();

        $distinctAccounts = 0;
        $lowAndSlow = true;
        while ($row = $res->fetch_assoc()) {
            $distinctAccounts++;
            if ((int)$row['attempts'] > $maxAttempts) {
                $lowAndSlow = false;
            }
        }
        $stmt->close();

        if ($distinctAccounts < $minAccounts || !$lowAndSlow) {
            return;
        }

        $reason = "IP $ip attempted logins against $distinctAccounts different accounts (<= $maxAttempts attempts each) within $window minutes -- pattern consistent with password spraying.";
        $riskScore = calculate_attack_risk_score('password_spraying', $distinctAccounts, $minAccounts);

        block_ip($ip, 'password_spraying', $reason, 'php');
        send_attack_security_alert($ip, 'Password Spraying Attack', $reason, $riskScore);
    } catch (\Throwable $e) {
        error_log('[security_functions] detect_password_spraying error: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------
// calculate_attack_risk_score($threatType, $observed, $threshold): int
// Reuses the existing calculate_risk_score() helper when available and
// provides a fail-safe fallback score when it is not loaded.
// ---------------------------------------------------------------------
function calculate_attack_risk_score(string $threatType, int $observed, int $threshold): int {
    try {
        if (function_exists('calculate_risk_score')) {
            return (int)calculate_risk_score($threatType, $observed, $threshold);
        }

        $ratio = $threshold > 0 ? ($observed / $threshold) : 1;
        return min(100, max(80, (int)round(70 + ($ratio * 10))));
    } catch (\Throwable $e) {
        error_log('[security_functions] calculate_attack_risk_score error: ' . $e->getMessage());
        return 90;
    }
}

// ---------------------------------------------------------------------
// send_attack_security_alert($ip, $label, $reason, $riskScore): void
// Reuses the existing send_security_alert() helper when available and
// keeps login flow fail-safe if email delivery fails.
// ---------------------------------------------------------------------
function send_attack_security_alert(string $ip, string $label, string $reason, int $riskScore): void {
    try {
        if (!function_exists('send_security_alert')) {
            return;
        }

        send_security_alert($ip, $label, $reason, $riskScore);
    } catch (\Throwable $e) {
        error_log('[security_functions] send_attack_security_alert error: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------
// log_threat_event(...): internal helper
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
