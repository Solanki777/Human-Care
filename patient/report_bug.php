<?php

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Mail configuration
$mailConfig = require __DIR__ . '/../config/mail_config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // =========================================
    // CSRF VALIDATION
    // =========================================

    if (!csrf_validate()) {
        $error = 'Invalid security token. Please refresh the page and try again.';
    }

    // =========================================
    // BUG DESCRIPTION
    // =========================================

    $description = trim($_POST['description'] ?? '');

    if (empty($error) && empty($description)) {
        $error = 'Please describe the bug.';
    }

    // =========================================
    // PHOTO SETTINGS
    // =========================================

    $photos = $_FILES['bug_photos'] ?? null;

    $allowed_types = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    $max_file_size = 5 * 1024 * 1024; // 5 MB
    $max_photos = 10;

    $uploaded_files = [];

    // =========================================
    // VALIDATE PHOTOS
    // =========================================

    if (empty($error) && $photos) {

        $photo_count = count($photos['name']);

        if ($photo_count > $max_photos) {
            $error = "You can upload a maximum of {$max_photos} photos.";
        }

        if (empty($error)) {

            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            for ($i = 0; $i < $photo_count; $i++) {

                if ($photos['error'][$i] === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                if ($photos['error'][$i] !== UPLOAD_ERR_OK) {
                    $error = 'One of the photos could not be uploaded.';
                    break;
                }

                if ($photos['size'][$i] > $max_file_size) {
                    $error = 'Each photo must be less than 5 MB.';
                    break;
                }

                $mime_type = finfo_file(
                    $finfo,
                    $photos['tmp_name'][$i]
                );

                if (!isset($allowed_types[$mime_type])) {
                    $error = 'Only JPG, PNG, and WEBP images are allowed.';
                    break;
                }
            }

            finfo_close($finfo);
        }
    }

    // =========================================
    // SAVE PHOTOS
    // =========================================

    if (empty($error)) {

        $upload_dir = __DIR__ . '/../uploads/bug_reports/';

        if (!is_dir($upload_dir)) {

            if (!mkdir($upload_dir, 0755, true)) {
                $error = 'Unable to create the upload directory.';
            }
        }
    }

    if (empty($error) && $photos) {

        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        $photo_count = count($photos['name']);

        for ($i = 0; $i < $photo_count; $i++) {

            if ($photos['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $mime_type = finfo_file(
                $finfo,
                $photos['tmp_name'][$i]
            );

            $extension = $allowed_types[$mime_type];

            $filename =
                'bug_' .
                bin2hex(random_bytes(16)) .
                '.' .
                $extension;

            $destination = $upload_dir . $filename;

            if (!move_uploaded_file(
                $photos['tmp_name'][$i],
                $destination
            )) {

                $error = 'Unable to save one of the uploaded photos.';
                break;
            }

            $uploaded_files[] = $destination;
        }

        finfo_close($finfo);
    }

    // =========================================
    // SEND EMAIL
    // =========================================

    if (empty($error)) {

        try {

            $mail = new PHPMailer(true);

            // SMTP configuration
            $mail->isSMTP();

            $mail->Host = $mailConfig['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $mailConfig['username'];
            $mail->Password = $mailConfig['password'];

            $mail->Port = $mailConfig['port'];

            if ($mailConfig['encryption'] === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($mailConfig['encryption'] === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }

            // Sender
            $mail->setFrom(
                $mailConfig['from_email'],
                $mailConfig['from_name']
            );

            // Receiver
            $mail->addAddress(ADMIN_EMAIL);

            // Email subject
            $mail->Subject = '🐞 Human Care - Bug Report';

            // Email body
            $mail->isHTML(true);

            $safe_description = nl2br(
                htmlspecialchars(
                    $description,
                    ENT_QUOTES,
                    'UTF-8'
                )
            );

            $mail->Body = "
                <h2>🐞 Human Care Bug Report</h2>

                <h3>Bug Description</h3>

                <p>
                    {$safe_description}
                </p>

                <hr>

                <p>
                    <strong>Photos attached:</strong>
                    " . count($uploaded_files) . "
                </p>

                <p>
                    This bug report was submitted from the Human Care website.
                </p>
            ";

            // Plain text fallback
            $mail->AltBody =
                "Human Care Bug Report\n\n" .
                "Bug Description:\n" .
                $description .
                "\n\nPhotos attached: " .
                count($uploaded_files);

            // Attach every uploaded photo
            foreach ($uploaded_files as $file) {

                $mail->addAttachment($file);
            }

            // Send
            $mail->send();

            // =========================================
            // DELETE TEMPORARY UPLOADS
            // =========================================

            foreach ($uploaded_files as $file) {

                if (file_exists($file)) {
                    unlink($file);
                }
            }

            $success =
                'Bug report submitted successfully. ' .
                'Thank you for helping us improve Human Care.';

        } catch (Exception $e) {

            $error =
                'Unable to send the bug report right now. ' .
                'Please try again later.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Report a Bug - Human Care</title>

    <link rel="stylesheet" href="styles/main.css">
    <link rel="stylesheet" href="styles/sidebar.css">
    <link rel="stylesheet" href="styles/footer.css">
    <link rel="stylesheet" href="styles/report_bug.css">

</head>

<body>

    <?php $active_page = 'report_bug'; ?>

    <?php include 'includes/public_sidebar.php'; ?>


    <main class="bug-report-page">

        <div class="container">

            <div class="bug-report-card">

                <div class="bug-report-header">

                    

                    <h1>Report a Bug</h1>

                    <p>
                        Found something that isn't working correctly?
                        Let us know so we can fix it.
                    </p>

                </div>


                <?php if (!empty($success)): ?>

                    <div class="bug-success">

                        ✓
                        <?php echo htmlspecialchars($success); ?>

                    </div>

                <?php endif; ?>


                <?php if (!empty($error)): ?>

                    <div class="bug-error">

                        ⚠
                        <?php echo htmlspecialchars($error); ?>

                    </div>

                <?php endif; ?>


                <?php if (empty($success)): ?>

                    <form
                        method="POST"
                        enctype="multipart/form-data"
                        class="bug-report-form"
                    >

                        <?php echo csrf_field(); ?>


                        <!-- Bug Description -->

                        <div class="form-group">

                            <label for="description">
                                Bug Description
                            </label>

                            <textarea
                                id="description"
                                name="description"
                                rows="7"
                                placeholder="Please describe the problem you found..."
                                required
                            ><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>

                        </div>


                        <!-- Bug Photos -->

                        <div class="form-group">

                            <label for="bug_photo">
                                Bug Photos / Screenshots
                                <span>(Optional)</span>
                            </label>

                            <input
                                type="file"
                                id="bug_photo"
                                name="bug_photos[]"
                                accept="image/jpeg,image/png,image/webp"
                                multiple
                            >

                            <small>
                                JPG, PNG or WEBP —
                                Maximum 5 MB per photo,
                                up to 10 photos.
                            </small>

                        </div>


                        <!-- Submit -->

                        <button
                            type="submit"
                            class="btn-primary bug-submit-btn"
                        >
                            🐞 Submit Bug Report
                        </button>

                    </form>

                <?php endif; ?>

            </div>

        </div>

    </main>


    <?php include 'includes/footer.php'; ?>

</body>

</html>