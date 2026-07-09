<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/vendor/autoload.php';

function send_security_alert($ip, $attackType, $details, $riskScore = 100)
{
    $mail = new PHPMailer(true);

    try {

        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'solankimaheshkhash230@gmail.com';
        $mail->Password   = 'pegaczgrngmusfze';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        // Email Information
        $mail->setFrom('solankimaheshkhash230@gmail.com', 'Nexora Security System');
        $mail->addAddress('solankimaheshkhash7@gmail.com');

        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->Subject = "🚨 Nexora Security Alert | {$attackType}";

        $time = date("d M Y, h:i:s A");
        

        $mail->Body = "
        <html>
        <head>
            <style>
                body{
                    font-family:Arial,Helvetica,sans-serif;
                    background:#f4f6f9;
                    margin:0;
                    padding:20px;
                }

                .container{
                    max-width:700px;
                    margin:auto;
                    background:#ffffff;
                    border-radius:10px;
                    overflow:hidden;
                    box-shadow:0 3px 12px rgba(0,0,0,.15);
                }

                .header{
                    background:#dc3545;
                    color:white;
                    padding:20px;
                    text-align:center;
                }

                .header h2{
                    margin:0;
                }

                .content{
                    padding:25px;
                }

                table{
                    width:100%;
                    border-collapse:collapse;
                    margin-top:15px;
                }

                td{
                    border:1px solid #ddd;
                    padding:12px;
                }

                td:first-child{
                    font-weight:bold;
                    background:#f8f9fa;
                    width:35%;
                }

                .footer{
                    text-align:center;
                    background:#f8f9fa;
                    padding:15px;
                    color:#666;
                    font-size:13px;
                }

                .warning{
                    color:#dc3545;
                    font-weight:bold;
                    font-size:18px;
                }

                .score{
                    color:#0d6efd;
                    font-weight:bold;
                }
            </style>
        </head>

        <body>

        <div class='container'>

            <div class='header'>
                <h2>🚨 Nexora Security Alert</h2>
                <p>Automated Threat Detection System</p>
            </div>

            <div class='content'>

                <p class='warning'>
                    A security threat has been detected and the source IP has been blocked automatically.
                </p>

                <table>

                    <tr>
                        <td>Attack Type</td>
                        <td>{$attackType}</td>
                    </tr>

                    <tr>
                        <td>Blocked IP</td>
                        <td>{$ip}</td>
                    </tr>

                    <tr>
                        <td>Risk Score</td>
                        <td class='score'>{$riskScore}/100</td>
                    </tr>

                    <tr>
                        <td>Detection Time</td>
                        <td>{$time}</td>
                    </tr>

                    <tr>
                        <td>Action Taken</td>
                        <td>IP Blocked Automatically</td>
                    </tr>

                    <tr>
                        <td>Attack Details</td>
                        <td>{$details}</td>
                    </tr>

                </table>

                <br>

                <b>Recommendation</b>

                <ul>
                    <li>Review the security dashboard.</li>
                    <li>Verify whether the attack is legitimate.</li>
                    <li>Keep the IP blocked if malicious activity continues.</li>
                    <li>Investigate repeated attacks from the same network.</li>
                </ul>

            </div>

            <div class='footer'>
                Nexora Security Monitoring System<br>
                Automated Security Notification
            </div>

        </div>

        </body>
        </html>";

        $mail->AltBody =
"🚨 Nexora Security Alert

Attack Type : {$attackType}

Blocked IP  : {$ip}

Risk Score  : {$riskScore}/100

Detection Time:
{$time}

Reason:
{$details}

Action Taken:
IP blocked automatically.

Nexora Security Monitoring System";

        $mail->send();

        return true;

    } catch (Exception $e) {

        error_log("Mail Error: " . $mail->ErrorInfo);

        return false;
    }
}