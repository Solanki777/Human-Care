<?php
/**
 * =====================================================================
 * Nexora Security Dashboard
 * File: C:\xampp\htdocs\vscode\admin\security_dashboard.php
 * =====================================================================
 * Displays all security data from security_logs_db:
 *  - Summary cards (totals, active threats)
 *  - Recent login attempts, threat events, blocked IPs
 *  - Attack type distribution chart
 *  - Threat timeline chart
 *  - Top attacking IPs
 *  - Nexora module status
 *  - Unblock functionality
 *  - Live auto-refresh every 8 seconds
 * =====================================================================
 */

session_start();

// ── Optional: guard admin access ──────────────────────────────────────
// Uncomment if your admin area uses session-based auth:
// if (!isset($_SESSION['admin_logged_in'])) {
//     header('Location: ../login.php'); exit;
// }

// ── DB Connection (security_logs_db) ─────────────────────────────────
$dbOk = false;
$conn = null;
$dbError = '';
try {
    $conn = @new mysqli('localhost', 'root', '', 'security_logs_db');
    if ($conn->connect_error) {
        $dbError = $conn->connect_error;
    } else {
        $conn->set_charset('utf8mb4');
        $dbOk = true;
    }
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

// ── AJAX: Unblock IP ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'unblock') {
    header('Content-Type: application/json');
    if (!$dbOk) { echo json_encode(['ok' => false, 'msg' => 'DB unavailable']); exit; }
    $id = (int)($_POST['id'] ?? 0);
    if ($id < 1) { echo json_encode(['ok' => false, 'msg' => 'Invalid ID']); exit; }
    $stmt = $conn->prepare("UPDATE blocked_ips SET is_active = 0 WHERE id = ?");
    $stmt->bind_param('i', $id);
    $ok = $stmt->execute();
    $stmt->close();
    echo json_encode(['ok' => $ok, 'msg' => $ok ? 'IP unblocked.' : 'Update failed.']);
    exit;
}

// ── Helper: safe query ────────────────────────────────────────────────
function q(mysqli $c, string $sql): array {
    $res = $c->query($sql);
    if (!$res) return [];
    $rows = [];
    while ($row = $res->fetch_assoc()) $rows[] = $row;
    $res->free();
    return $rows;
}
function qv(mysqli $c, string $sql): mixed {
    $res = $c->query($sql);
    if (!$res) return null;
    $row = $res->fetch_row();
    $res->free();
    return $row[0] ?? null;
}

// ── Fetch summary stats ───────────────────────────────────────────────
$totalAttempts   = $dbOk ? (int)qv($conn, "SELECT COUNT(*) FROM login_attempts") : 0;
$totalThreats    = $dbOk ? (int)qv($conn, "SELECT COUNT(*) FROM threat_events") : 0;
$totalBlocked    = $dbOk ? (int)qv($conn, "SELECT COUNT(*) FROM blocked_ips") : 0;
$activeBlocked   = $dbOk ? (int)qv($conn, "SELECT COUNT(*) FROM blocked_ips WHERE is_active=1 AND (expires_at IS NULL OR expires_at > NOW())") : 0;
$failedAttempts  = $dbOk ? (int)qv($conn, "SELECT COUNT(*) FROM login_attempts WHERE status='failed'") : 0;
$successAttempts = $dbOk ? (int)qv($conn, "SELECT COUNT(*) FROM login_attempts WHERE status='success'") : 0;

// ── Recent data (last 50 rows) ────────────────────────────────────────
$recentAttempts = $dbOk ? q($conn,
    "SELECT ip_address, email, user_type, status, threat_detected, attempted_at
     FROM login_attempts ORDER BY attempted_at DESC LIMIT 50") : [];

$recentThreats = $dbOk ? q($conn,
    "SELECT ip_address, email, threat_type, reason, detected_by, detected_at, action_taken
     FROM threat_events ORDER BY detected_at DESC LIMIT 50") : [];

$blockedIPs = $dbOk ? q($conn,
    "SELECT id, ip_address, threat_type, reason, blocked_by, blocked_at, expires_at, is_active
     FROM blocked_ips ORDER BY blocked_at DESC LIMIT 50") : [];

// ── Charts data ───────────────────────────────────────────────────────
$attackDist = $dbOk ? q($conn,
    "SELECT threat_type, COUNT(*) as cnt FROM threat_events
     GROUP BY threat_type ORDER BY cnt DESC") : [];

$timelineRaw = $dbOk ? q($conn,
    "SELECT DATE_FORMAT(detected_at,'%Y-%m-%d %H:00') as hr, COUNT(*) as cnt
     FROM threat_events
     WHERE detected_at >= NOW() - INTERVAL 24 HOUR
     GROUP BY hr ORDER BY hr ASC") : [];

$topIPs = $dbOk ? q($conn,
    "SELECT ip_address, COUNT(*) as cnt FROM login_attempts
     WHERE status='failed'
     GROUP BY ip_address ORDER BY cnt DESC LIMIT 10") : [];

// ── Nexora module status checks ───────────────────────────────────────
$nexoraScript = realpath(__DIR__ . '/../security/login_threat_detector.py');
$nexoraScriptExists = $nexoraScript && file_exists($nexoraScript);

$gmailServiceExists = file_exists(__DIR__ . '/../../Nexora-Autonomous-AI-Cybersecurity-Agent-main/gmail_service.py')
                   || file_exists('M:/Nexora-Autonomous-AI-Cybersecurity-Agent-main/gmail_service.py');

$phishingDetectorExists = file_exists(__DIR__ . '/../../Nexora-Autonomous-AI-Cybersecurity-Agent-main/phishing_detector.py')
                        || file_exists('M:/Nexora-Autonomous-AI-Cybersecurity-Agent-main/phishing_detector.py');

$nexoraLogFile = __DIR__ . '/../security_logs/nexora_run.log';
$lastNexoraRun = '';
if (file_exists($nexoraLogFile)) {
    $lines = array_filter(array_map('trim', file($nexoraLogFile)));
    if (!empty($lines)) {
        $lastLine = end($lines);
        // Extract timestamp from log line like "2026-06-21 22:17:40,123 [Nexora] INFO: ..."
        if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $lastLine, $m)) {
            $lastNexoraRun = $m[1];
        } else {
            $lastNexoraRun = 'Recently';
        }
    }
}

// JSON for charts
$attackDistJson  = json_encode(array_column($attackDist, 'threat_type'));
$attackCntJson   = json_encode(array_map('intval', array_column($attackDist, 'cnt')));
$timelineLabels  = json_encode(array_column($timelineRaw, 'hr'));
$timelineCounts  = json_encode(array_map('intval', array_column($timelineRaw, 'cnt')));
$topIPLabels     = json_encode(array_column($topIPs, 'ip_address'));
$topIPCounts     = json_encode(array_map('intval', array_column($topIPs, 'cnt')));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Nexora Security Dashboard – Human Care</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<style>
/* ── Reset & base ───────────────────────────────────────────────── */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
:root {
    --bg:       #0d0f1a;
    --surface:  #151829;
    --surface2: #1c2038;
    --border:   rgba(255,255,255,0.07);
    --accent:   #5b6bff;
    --accent2:  #a259ff;
    --red:      #ff4d6d;
    --orange:   #ff8c42;
    --green:    #34c98a;
    --yellow:   #f5c542;
    --text:     #e4e6f0;
    --muted:    rgba(228,230,240,0.45);
    --radius:   12px;
    --font:     'Segoe UI', system-ui, sans-serif;
}
body { background: var(--bg); color: var(--text); font-family: var(--font); font-size: 14px; min-height: 100vh; }

/* ── Layout ─────────────────────────────────────────────────────── */
.topbar {
    background: var(--surface);
    border-bottom: 1px solid var(--border);
    padding: 0 28px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 100;
}
.topbar-logo { display: flex; align-items: center; gap: 10px; font-size: 17px; font-weight: 700; }
.topbar-logo span { color: var(--accent); }
.topbar-right { display: flex; align-items: center; gap: 16px; }
.refresh-badge {
    font-size: 12px;
    color: var(--muted);
    background: rgba(91,107,255,0.12);
    border: 1px solid rgba(91,107,255,0.25);
    padding: 4px 12px;
    border-radius: 20px;
}
.db-status {
    font-size: 12px;
    padding: 4px 12px;
    border-radius: 20px;
    font-weight: 600;
}
.db-ok   { background: rgba(52,201,138,0.15); color: var(--green); border: 1px solid rgba(52,201,138,0.3); }
.db-fail { background: rgba(255,77,109,0.15); color: var(--red);   border: 1px solid rgba(255,77,109,0.3); }
.back-link {
    font-size: 12px;
    color: var(--muted);
    text-decoration: none;
    padding: 5px 12px;
    border: 1px solid var(--border);
    border-radius: 8px;
    transition: border-color .2s;
}
.back-link:hover { border-color: var(--accent); color: var(--text); }

.content { padding: 28px; max-width: 1400px; margin: 0 auto; }

/* ── Section headings ────────────────────────────────────────────── */
.section-title {
    font-size: 13px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--muted);
    margin: 32px 0 14px;
}
.section-title:first-child { margin-top: 0; }

/* ── Summary cards ───────────────────────────────────────────────── */
.cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 16px; }
.card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 20px 22px;
    position: relative;
    overflow: hidden;
}
.card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: var(--card-accent, var(--accent));
}
.card-label { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.07em; color: var(--muted); margin-bottom: 10px; }
.card-value { font-size: 32px; font-weight: 800; color: var(--text); line-height: 1; }
.card-sub   { font-size: 12px; color: var(--muted); margin-top: 6px; }
.card-icon  { position: absolute; top: 16px; right: 18px; font-size: 22px; opacity: 0.5; }

/* ── Module status cards ─────────────────────────────────────────── */
.module-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 14px; }
.module-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 16px 18px;
    display: flex;
    align-items: center;
    gap: 14px;
}
.module-icon { font-size: 26px; }
.module-info { flex: 1; }
.module-name { font-size: 13px; font-weight: 600; color: var(--text); }
.module-detail { font-size: 11px; color: var(--muted); margin-top: 3px; }
.pill {
    font-size: 11px;
    font-weight: 700;
    padding: 3px 9px;
    border-radius: 20px;
}
.pill-green  { background: rgba(52,201,138,0.15); color: var(--green); border: 1px solid rgba(52,201,138,0.3); }
.pill-red    { background: rgba(255,77,109,0.15);  color: var(--red);   border: 1px solid rgba(255,77,109,0.3); }
.pill-yellow { background: rgba(245,197,66,0.15);  color: var(--yellow); border: 1px solid rgba(245,197,66,0.3); }

/* ── Charts ──────────────────────────────────────────────────────── */
.charts { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 18px; }
.chart-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    padding: 20px 22px;
}
.chart-card h3 { font-size: 13px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 16px; }
.chart-wrap { position: relative; height: 220px; }

/* ── Tables ──────────────────────────────────────────────────────── */
.table-card {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius);
    overflow: hidden;
    margin-bottom: 18px;
}
.table-card-header {
    padding: 16px 20px;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.table-card-header h3 { font-size: 14px; font-weight: 700; }
.table-card-header span { font-size: 12px; color: var(--muted); }
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; font-size: 13px; }
th {
    padding: 10px 14px;
    text-align: left;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--muted);
    background: var(--surface2);
    border-bottom: 1px solid var(--border);
    white-space: nowrap;
}
td {
    padding: 10px 14px;
    border-bottom: 1px solid var(--border);
    color: var(--text);
    vertical-align: middle;
}
tr:last-child td { border-bottom: none; }
tr:hover td { background: rgba(255,255,255,0.025); }
.empty-row td { text-align: center; color: var(--muted); padding: 28px; font-size: 13px; }

/* ── Badges ──────────────────────────────────────────────────────── */
.badge {
    display: inline-block;
    padding: 2px 9px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    white-space: nowrap;
}
.b-red    { background: rgba(255,77,109,0.15);  color: var(--red);    border: 1px solid rgba(255,77,109,0.3); }
.b-green  { background: rgba(52,201,138,0.15);  color: var(--green);  border: 1px solid rgba(52,201,138,0.3); }
.b-orange { background: rgba(255,140,66,0.15);  color: var(--orange); border: 1px solid rgba(255,140,66,0.3); }
.b-purple { background: rgba(162,89,255,0.15);  color: var(--accent2);border: 1px solid rgba(162,89,255,0.3); }
.b-blue   { background: rgba(91,107,255,0.15);  color: #8899ff;       border: 1px solid rgba(91,107,255,0.3); }
.b-gray   { background: rgba(255,255,255,0.07); color: var(--muted);  border: 1px solid var(--border); }

/* ── Unblock button ──────────────────────────────────────────────── */
.btn-unblock {
    padding: 4px 12px;
    border: 1px solid rgba(52,201,138,0.4);
    background: rgba(52,201,138,0.1);
    color: var(--green);
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    transition: background .2s;
}
.btn-unblock:hover { background: rgba(52,201,138,0.22); }
.btn-unblock:disabled { opacity: 0.4; cursor: not-allowed; }

/* ── Toast ───────────────────────────────────────────────────────── */
#toast {
    position: fixed;
    bottom: 28px; right: 28px;
    background: var(--surface2);
    border: 1px solid var(--border);
    color: var(--text);
    padding: 12px 20px;
    border-radius: 10px;
    font-size: 13px;
    box-shadow: 0 4px 24px rgba(0,0,0,0.5);
    opacity: 0;
    transform: translateY(10px);
    transition: opacity .3s, transform .3s;
    z-index: 9999;
    pointer-events: none;
}
#toast.show { opacity: 1; transform: translateY(0); }

/* ── Responsive ──────────────────────────────────────────────────── */
@media (max-width: 600px) {
    .content { padding: 16px; }
    .topbar  { padding: 0 16px; }
    .cards   { grid-template-columns: 1fr 1fr; }
}
</style>
</head>
<body>

<!-- ── Top bar ─────────────────────────────────────────────────────── -->
<div class="topbar">
    <div class="topbar-logo">
        🛡️ <span>Nexora</span> Security Dashboard
        <small style="color:var(--muted);font-weight:400;font-size:12px;margin-left:6px;">/ Human Care</small>
    </div>
    <div class="topbar-right">
        <span class="refresh-badge" id="refreshBadge">⟳ Refreshing every 8s</span>
        <span class="db-status <?= $dbOk ? 'db-ok' : 'db-fail' ?>">
            <?= $dbOk ? '● DB Connected' : '● DB Error' ?>
        </span>
        <a class="back-link" href="../index.php">← Back to Human Care</a>
    </div>
</div>

<div class="content">

<!-- ── Summary cards ───────────────────────────────────────────────── -->
<div class="section-title">Security Overview</div>
<div class="cards">
    <div class="card" style="--card-accent:#5b6bff">
        <div class="card-icon">📊</div>
        <div class="card-label">Total Login Attempts</div>
        <div class="card-value"><?= number_format($totalAttempts) ?></div>
        <div class="card-sub"><?= $successAttempts ?> success · <?= $failedAttempts ?> failed</div>
    </div>
    <div class="card" style="--card-accent:#ff4d6d">
        <div class="card-icon">🚨</div>
        <div class="card-label">Total Threat Events</div>
        <div class="card-value"><?= number_format($totalThreats) ?></div>
        <div class="card-sub">Across all detectors</div>
    </div>
    <div class="card" style="--card-accent:#ff8c42">
        <div class="card-icon">🚫</div>
        <div class="card-label">Total Blocked IPs</div>
        <div class="card-value"><?= number_format($totalBlocked) ?></div>
        <div class="card-sub"><?= $activeBlocked ?> currently active</div>
    </div>
    <div class="card" style="--card-accent:#f5c542">
        <div class="card-icon">⚡</div>
        <div class="card-label">Active Threats</div>
        <div class="card-value"><?= number_format($activeBlocked) ?></div>
        <div class="card-sub">Live blocked IPs</div>
    </div>
    <div class="card" style="--card-accent:#34c98a">
        <div class="card-icon">✅</div>
        <div class="card-label">Successful Logins</div>
        <div class="card-value"><?= number_format($successAttempts) ?></div>
        <div class="card-sub">Legitimate sessions</div>
    </div>
    <div class="card" style="--card-accent:#a259ff">
        <div class="card-icon">❌</div>
        <div class="card-label">Failed Logins</div>
        <div class="card-value"><?= number_format($failedAttempts) ?></div>
        <div class="card-sub">Potential attacks</div>
    </div>
</div>

<!-- ── Nexora module status ─────────────────────────────────────────── -->
<div class="section-title">Nexora Module Status</div>
<div class="module-cards">
    <div class="module-card">
        <div class="module-icon">🗄️</div>
        <div class="module-info">
            <div class="module-name">Database Connection</div>
            <div class="module-detail">security_logs_db</div>
        </div>
        <span class="pill <?= $dbOk ? 'pill-green' : 'pill-red' ?>">
            <?= $dbOk ? 'Online' : 'Offline' ?>
        </span>
    </div>
    <div class="module-card">
        <div class="module-icon">🔐</div>
        <div class="module-info">
            <div class="module-name">Login Threat Detector</div>
            <div class="module-detail"><?= $lastNexoraRun ? 'Last run: ' . htmlspecialchars($lastNexoraRun) : 'Auto-triggered on failed logins' ?></div>
        </div>
        <span class="pill <?= $nexoraScriptExists ? 'pill-green' : 'pill-yellow' ?>">
            <?= $nexoraScriptExists ? 'Active' : 'Script OK' ?>
        </span>
    </div>
    <div class="module-card">
        <div class="module-icon">📧</div>
        <div class="module-info">
            <div class="module-name">Gmail Scanner</div>
            <div class="module-detail">Nexora AI Agent (Streamlit)</div>
        </div>
        <span class="pill <?= $gmailServiceExists ? 'pill-green' : 'pill-yellow' ?>">
            <?= $gmailServiceExists ? 'Linked' : 'Standalone' ?>
        </span>
    </div>
    <div class="module-card">
        <div class="module-icon">🌐</div>
        <div class="module-info">
            <div class="module-name">URL Analyzer</div>
            <div class="module-detail">phishing_detector.py</div>
        </div>
        <span class="pill <?= $phishingDetectorExists ? 'pill-green' : 'pill-yellow' ?>">
            <?= $phishingDetectorExists ? 'Linked' : 'Standalone' ?>
        </span>
    </div>
</div>

<!-- ── Charts ───────────────────────────────────────────────────────── -->
<div class="section-title">Security Analytics</div>
<div class="charts">
    <div class="chart-card">
        <h3>Attack Type Distribution</h3>
        <div class="chart-wrap"><canvas id="chartAttack"></canvas></div>
    </div>
    <div class="chart-card">
        <h3>Threat Timeline (Last 24h)</h3>
        <div class="chart-wrap"><canvas id="chartTimeline"></canvas></div>
    </div>
    <div class="chart-card">
        <h3>Top Attacking IPs</h3>
        <div class="chart-wrap"><canvas id="chartTopIPs"></canvas></div>
    </div>
</div>

<!-- ── Login Attempts table ─────────────────────────────────────────── -->
<div class="section-title">Recent Login Attempts</div>
<div class="table-card">
    <div class="table-card-header">
        <h3>Login Attempts</h3>
        <span>Last 50 entries</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>IP Address</th>
                    <th>Email</th>
                    <th>User Type</th>
                    <th>Status</th>
                    <th>Threat Detected</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentAttempts)): ?>
                    <tr class="empty-row"><td colspan="6">No login attempts recorded yet.</td></tr>
                <?php else: foreach ($recentAttempts as $row): ?>
                    <tr>
                        <td><code style="font-size:12px"><?= htmlspecialchars($row['ip_address']) ?></code></td>
                        <td><?= htmlspecialchars($row['email'] ?? '—') ?></td>
                        <td><?= htmlspecialchars($row['user_type'] ?? '—') ?></td>
                        <td>
                            <?php if ($row['status'] === 'success'): ?>
                                <span class="badge b-green">✓ Success</span>
                            <?php else: ?>
                                <span class="badge b-red">✗ Failed</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($row['threat_detected']): ?>
                                <span class="badge b-orange"><?= htmlspecialchars($row['threat_detected']) ?></span>
                            <?php else: ?>
                                <span style="color:var(--muted)">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="color:var(--muted);white-space:nowrap"><?= htmlspecialchars($row['attempted_at']) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Threat Events table ──────────────────────────────────────────── -->
<div class="section-title">Recent Threat Events</div>
<div class="table-card">
    <div class="table-card-header">
        <h3>Threat Events</h3>
        <span>Last 50 entries</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>IP Address</th>
                    <th>Threat Type</th>
                    <th>Reason</th>
                    <th>Detected By</th>
                    <th>Action Taken</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentThreats)): ?>
                    <tr class="empty-row"><td colspan="6">No threat events recorded yet.</td></tr>
                <?php else: foreach ($recentThreats as $row): ?>
                    <tr>
                        <td><code style="font-size:12px"><?= htmlspecialchars($row['ip_address']) ?></code></td>
                        <td>
                            <?php
                            $tClass = match($row['threat_type']) {
                                'brute_force'         => 'b-red',
                                'credential_stuffing' => 'b-orange',
                                'password_spraying'   => 'b-purple',
                                'sql_injection'       => 'b-red',
                                default               => 'b-gray',
                            };
                            ?>
                            <span class="badge <?= $tClass ?>"><?= htmlspecialchars($row['threat_type']) ?></span>
                        </td>
                        <td style="max-width:280px;word-break:break-word;font-size:12px"><?= htmlspecialchars($row['reason']) ?></td>
                        <td>
                            <?php if (strtolower($row['detected_by']) === 'nexora'): ?>
                                <span class="badge b-purple">🤖 Nexora</span>
                            <?php else: ?>
                                <span class="badge b-blue">🛡️ PHP</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($row['action_taken'] ?? '—') ?></td>
                        <td style="color:var(--muted);white-space:nowrap"><?= htmlspecialchars($row['detected_at']) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── Blocked IPs table ────────────────────────────────────────────── -->
<div class="section-title">Blocked IP Addresses</div>
<div class="table-card">
    <div class="table-card-header">
        <h3>Blocked IPs</h3>
        <span>Last 50 entries</span>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>IP Address</th>
                    <th>Threat Type</th>
                    <th>Reason</th>
                    <th>Blocked By</th>
                    <th>Block Time</th>
                    <th>Expiry</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($blockedIPs)): ?>
                    <tr class="empty-row"><td colspan="8">No blocked IPs recorded yet.</td></tr>
                <?php else: foreach ($blockedIPs as $row):
                    $isActive = $row['is_active'] == 1 && ($row['expires_at'] === null || strtotime($row['expires_at']) > time());
                ?>
                    <tr>
                        <td><code style="font-size:12px"><?= htmlspecialchars($row['ip_address']) ?></code></td>
                        <td><span class="badge b-red"><?= htmlspecialchars($row['threat_type']) ?></span></td>
                        <td style="max-width:220px;word-break:break-word;font-size:12px"><?= htmlspecialchars($row['reason']) ?></td>
                        <td>
                            <?php if (strtolower($row['blocked_by']) === 'nexora'): ?>
                                <span class="badge b-purple">🤖 Nexora</span>
                            <?php else: ?>
                                <span class="badge b-blue">🛡️ PHP</span>
                            <?php endif; ?>
                        </td>
                        <td style="color:var(--muted);white-space:nowrap"><?= htmlspecialchars($row['blocked_at']) ?></td>
                        <td style="color:var(--muted);white-space:nowrap"><?= $row['expires_at'] ? htmlspecialchars($row['expires_at']) : '<span style="color:#ff4d6d">Permanent</span>' ?></td>
                        <td>
                            <?php if ($isActive): ?>
                                <span class="badge b-red">● Active</span>
                            <?php else: ?>
                                <span class="badge b-gray">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($isActive): ?>
                                <button class="btn-unblock" data-id="<?= (int)$row['id'] ?>" onclick="unblockIP(this)">
                                    Unblock
                                </button>
                            <?php else: ?>
                                <span style="color:var(--muted);font-size:12px">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

</div><!-- /content -->

<!-- ── Toast ─────────────────────────────────────────────────────────── -->
<div id="toast"></div>

<!-- ── Charts JS ─────────────────────────────────────────────────────── -->
<script>
const CHART_COLORS = ['#ff4d6d','#ff8c42','#f5c542','#34c98a','#5b6bff','#a259ff','#00d4ff'];

// Attack distribution (doughnut)
const attackLabels = <?= $attackDistJson ?>;
const attackCounts = <?= $attackCntJson ?>;
if (attackLabels.length > 0) {
    new Chart(document.getElementById('chartAttack'), {
        type: 'doughnut',
        data: {
            labels: attackLabels,
            datasets: [{ data: attackCounts, backgroundColor: CHART_COLORS, borderWidth: 0 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { color: '#e4e6f0', boxWidth: 12, padding: 14, font: { size: 12 } } }
            }
        }
    });
} else {
    document.getElementById('chartAttack').parentElement.innerHTML = '<p style="text-align:center;color:rgba(228,230,240,0.4);padding-top:80px">No threat data yet</p>';
}

// Timeline (line)
const tlLabels = <?= $timelineLabels ?>;
const tlCounts = <?= $timelineCounts ?>;
if (tlLabels.length > 0) {
    new Chart(document.getElementById('chartTimeline'), {
        type: 'line',
        data: {
            labels: tlLabels,
            datasets: [{
                label: 'Threats',
                data: tlCounts,
                borderColor: '#ff4d6d',
                backgroundColor: 'rgba(255,77,109,0.1)',
                borderWidth: 2,
                tension: 0.4,
                fill: true,
                pointRadius: 3,
                pointBackgroundColor: '#ff4d6d'
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { color: 'rgba(228,230,240,0.45)', maxTicksLimit: 6, font: { size: 11 } }, grid: { color: 'rgba(255,255,255,0.05)' } },
                y: { ticks: { color: 'rgba(228,230,240,0.45)', font: { size: 11 } }, grid: { color: 'rgba(255,255,255,0.05)' }, beginAtZero: true }
            }
        }
    });
} else {
    document.getElementById('chartTimeline').parentElement.innerHTML = '<p style="text-align:center;color:rgba(228,230,240,0.4);padding-top:80px">No threats in last 24h</p>';
}

// Top IPs (horizontal bar)
const ipLabels = <?= $topIPLabels ?>;
const ipCounts = <?= $topIPCounts ?>;
if (ipLabels.length > 0) {
    new Chart(document.getElementById('chartTopIPs'), {
        type: 'bar',
        data: {
            labels: ipLabels,
            datasets: [{
                label: 'Failed Attempts',
                data: ipCounts,
                backgroundColor: 'rgba(91,107,255,0.6)',
                borderColor: 'rgba(91,107,255,0.9)',
                borderWidth: 1,
                borderRadius: 4
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { ticks: { color: 'rgba(228,230,240,0.45)', font: { size: 11 } }, grid: { color: 'rgba(255,255,255,0.05)' }, beginAtZero: true },
                y: { ticks: { color: 'rgba(228,230,240,0.45)', font: { size: 11 } }, grid: { display: false } }
            }
        }
    });
} else {
    document.getElementById('chartTopIPs').parentElement.innerHTML = '<p style="text-align:center;color:rgba(228,230,240,0.4);padding-top:80px">No attacking IPs yet</p>';
}

// ── Unblock ─────────────────────────────────────────────────────────────
function unblockIP(btn) {
    const id = btn.dataset.id;
    btn.disabled = true;
    btn.textContent = '...';

    fetch(window.location.href, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=unblock&id=' + encodeURIComponent(id)
    })
    .then(r => r.json())
    .then(data => {
        showToast(data.ok ? '✅ IP unblocked successfully.' : '❌ ' + data.msg);
        if (data.ok) {
            // Update the row visually without waiting for refresh
            const row = btn.closest('tr');
            row.querySelector('.badge.b-red')?.replaceWith((() => {
                const s = document.createElement('span');
                s.className = 'badge b-gray';
                s.textContent = 'Inactive';
                return s;
            })());
            btn.parentElement.innerHTML = '<span style="color:var(--muted);font-size:12px">—</span>';
        } else {
            btn.disabled = false;
            btn.textContent = 'Unblock';
        }
    })
    .catch(() => {
        showToast('❌ Network error.');
        btn.disabled = false;
        btn.textContent = 'Unblock';
    });
}

// ── Toast helper ─────────────────────────────────────────────────────────
function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3500);
}

// ── Live refresh (every 8 seconds, full page reload) ─────────────────────
let countdown = 8;
const badge = document.getElementById('refreshBadge');
setInterval(() => {
    countdown--;
    badge.textContent = '⟳ Refreshing in ' + countdown + 's';
    if (countdown <= 0) {
        window.location.reload();
    }
}, 1000);
</script>
</body>
</html>