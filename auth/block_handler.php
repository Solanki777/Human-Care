<?php

require_once __DIR__ . '/../security/security_functions.php';

$ip = $_SERVER['REMOTE_ADDR'];

if (is_ip_blocked($ip)) {

    $blockDetails = get_block_details($ip);

    $threatTypeRaw  = $blockDetails['threat_type'] ?? 'Unknown';
    $reason         = $blockDetails['reason']      ?? 'Suspicious activity detected.';
    $blockedBy      = $blockDetails['blocked_by']  ?? 'Security System';
    $blockedAtRaw   = $blockDetails['blocked_at']  ?? '';
    $expiresAtRaw   = $blockDetails['expires_at']  ?? null;

    $threatLabels = [
        'brute_force'         => 'Brute Force Attack',
        'sql_injection'       => 'SQL Injection Attempt',
        'suspicious_rate'     => 'Suspicious Login Rate',
        'blocked_ip_reuse'    => 'Blocked IP Reuse',
        'credential_stuffing' => 'Credential Stuffing Attack',
        'password_spraying'   => 'Password Spraying Attack',
    ];

    $attackType = $threatLabels[$threatTypeRaw]
        ?? ucwords(str_replace('_', ' ', $threatTypeRaw));

    $blockedByLabel = match (strtolower($blockedBy)) {
        'php'    => 'PHP Security Layer',
        'nexora' => 'Nexora AI',
        default  => htmlspecialchars($blockedBy),
    };

    $blockedAtLabel = $blockedAtRaw
        ? date('d M Y, H:i:s', strtotime($blockedAtRaw))
        : 'Unknown';

    $expiresAtLabel = $expiresAtRaw
        ? date('d M Y, H:i:s', strtotime($expiresAtRaw))
        : 'Permanent';

    ?>

    <!DOCTYPE html>
    <html lang="en">

    <head>

        <meta charset="UTF-8">

        <meta
            name="viewport"
            content="width=device-width, initial-scale=1.0"
        >

        <title>Access Blocked – Human Care</title>

        <link rel="stylesheet" href="styles/login.css">

    </head>

    <body>

        <div class="block-card">

            <div class="block-icon">🚫</div>

            <h1>Access Blocked</h1>

            <p class="subtitle">
                Your IP address has been flagged and blocked by the security system.
            </p>

            <div class="detail-grid">

                <div class="detail-row">

                    <span class="detail-label">
                        IP Address
                    </span>

                    <span class="detail-value">
                        <span class="badge badge-red">
                            <?= htmlspecialchars($ip) ?>
                        </span>
                    </span>

                </div>

                <hr class="block-divider">

                <div class="detail-row">

                    <span class="detail-label">
                        Attack Type
                    </span>

                    <span class="detail-value">
                        <span class="badge badge-red">
                            <?= htmlspecialchars($attackType) ?>
                        </span>
                    </span>

                </div>

                <hr class="block-divider">

                <div class="detail-row">

                    <span class="detail-label">
                        Reason
                    </span>

                    <span class="detail-value">
                        <?= htmlspecialchars($reason) ?>
                    </span>

                </div>

                <hr class="block-divider">

                <div class="detail-row">

                    <span class="detail-label">
                        Blocked By
                    </span>

                    <span class="detail-value">

                        <?php if (strtolower($blockedBy) === 'nexora'): ?>

                            <span class="badge badge-purple">
                                🤖 <?= $blockedByLabel ?>
                            </span>

                        <?php else: ?>

                            <span class="badge badge-blue">
                                🛡️ <?= $blockedByLabel ?>
                            </span>

                        <?php endif; ?>

                    </span>

                </div>

                <hr class="block-divider">

                <div class="detail-row">

                    <span class="detail-label">
                        Blocked At
                    </span>

                    <span class="detail-value">
                        <?= htmlspecialchars($blockedAtLabel) ?>
                    </span>

                </div>

                <hr class="block-divider">

                <div class="detail-row">

                    <span class="detail-label">
                        Expires At
                    </span>

                    <span class="detail-value">
                        <?= htmlspecialchars($expiresAtLabel) ?>
                    </span>

                </div>

            </div>

            <p class="footer-note">

                If you believe this is a mistake, please contact your system administrator.<br>

                <a href="mailto:admin@humancare.local">
                    admin@humancare.local
                </a>

            </p>

            <div class="nexora-badge">

                🛡️ Protected by

                <strong style="color:#a0aaff; margin: 0 3px;">
                    Nexora
                </strong>

                Autonomous AI Security

            </div>

        </div>

    </body>

    </html>

    <?php

    exit;
}