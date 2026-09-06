<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Dashboard dynamic threat query replacements.
 *
 * Use these blocks to replace dashboard queries that currently hardcode:
 * brute_force, sql_injection, suspicious_rate.
 *
 * They read attack types dynamically from threat_events, so new attacks
 * such as credential_stuffing and password_spraying appear automatically.
 */

// All attack counters/stat cards by type.
$attackCountersSql = "
    SELECT
        threat_type,
        COUNT(*) AS total_attacks,
        COUNT(DISTINCT ip_address) AS unique_ips,
        MAX(detected_at) AS last_seen
    FROM threat_events
    GROUP BY threat_type
    ORDER BY total_attacks DESC, threat_type ASC
";

// Chart.js labels and values.
$attackChartSql = "
    SELECT threat_type, COUNT(*) AS total_attacks
    FROM threat_events
    GROUP BY threat_type
    ORDER BY total_attacks DESC, threat_type ASC
";

// Recent attack list.
$recentAttacksSql = "
    SELECT ip_address, email, threat_type, reason, detected_by, detected_at, action_taken
    FROM threat_events
    ORDER BY detected_at DESC
    LIMIT 10
";

// Threat summary over time for Chart.js.
$threatSummarySql = "
    SELECT
        DATE(detected_at) AS attack_date,
        threat_type,
        COUNT(*) AS total_attacks
    FROM threat_events
    WHERE detected_at >= (NOW() - INTERVAL 30 DAY)
    GROUP BY DATE(detected_at), threat_type
    ORDER BY attack_date ASC, threat_type ASC
";

// Risk statistics based on blocked IPs and threat events.
$riskStatsSql = "
    SELECT
        te.threat_type,
        COUNT(*) AS total_events,
        COUNT(DISTINCT te.ip_address) AS unique_attackers,
        SUM(CASE WHEN bi.id IS NOT NULL AND bi.is_active = 1 THEN 1 ELSE 0 END) AS active_blocks
    FROM threat_events te
    LEFT JOIN blocked_ips bi
        ON bi.ip_address = te.ip_address
       AND bi.threat_type = te.threat_type
       AND bi.is_active = 1
       AND (bi.expires_at IS NULL OR bi.expires_at > NOW())
    GROUP BY te.threat_type
    ORDER BY total_events DESC, te.threat_type ASC
";

// Optional display helper for labels. This is not a hardcoded filter;
// it only formats database threat_type values for dashboard display.
function dashboard_threat_label(string $threatType): string {
    return ucwords(str_replace('_', ' ', $threatType));
}

// Chart.js example data preparation:
//
// $labels = [];
// $data = [];
// $result = $conn->query($attackChartSql);
// while ($row = $result->fetch_assoc()) {
//     $labels[] = dashboard_threat_label($row['threat_type']);
//     $data[] = (int)$row['total_attacks'];
// }
//
// Then keep your existing Chart.js styling and inject json_encode($labels)
// and json_encode($data) in the same places your dashboard currently
// writes Chart.js labels and data arrays.

?>

