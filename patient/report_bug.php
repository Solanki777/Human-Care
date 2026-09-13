<?php

require_once __DIR__ . '/../config/config.php';
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
    // PHOTO VALIDATION
    // =========================================

    $photos = $_FILES['bug_photos'] ?? null;

    $uploaded_files = [];

    $allowed_types = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    $max_file_size = 5 * 1024 * 1024; // 5 MB
    $max_photos = 10;

    if (empty($error) && $photos) {

        // Count successfully selected files
        $photo_count = count($photos['name']);

        if ($photo_count > $max_photos) {
            $error = "You can upload a maximum of {$max_photos} photos.";
        }

        // Validate every photo
        if (empty($error)) {

            $finfo = finfo_open(FILEINFO_MIME_TYPE);

            for ($i = 0; $i < $photo_count; $i++) {

                // Skip empty file inputs
                if ($photos['error'][$i] === UPLOAD_ERR_NO_FILE) {
                    continue;
                }

                // Upload error
                if ($photos['error'][$i] !== UPLOAD_ERR_OK) {
                    $error = 'One of the photos could not be uploaded.';
                    break;
                }

                // File size
                if ($photos['size'][$i] > $max_file_size) {
                    $error = 'Each photo must be less than 5 MB.';
                    break;
                }

                // Detect real MIME type
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

            // Generate random filename
            $filename = 'bug_' .
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
    // SUCCESS
    // =========================================

    if (empty($error)) {

        $success =
            'Bug report submitted successfully. ' .
            'Thank you for helping us improve Human Care.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

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
                    <div class="bug-icon">🐞</div>

                    <h1>Report a Bug</h1>

                    <p>
                        Found something that isn't working correctly?
                        Let us know so we can fix it.
                    </p>
                </div>

                <?php if (!empty($success)): ?>

                    <div class="bug-success">
                        ✓ <?php echo htmlspecialchars($success); ?>
                    </div>

                <?php endif; ?>

                <?php if (!empty($error)): ?>

                    <div class="bug-error">
                        ⚠ <?php echo htmlspecialchars($error); ?>
                    </div>

                <?php endif; ?>

                <?php if (empty($success)): ?>

                    <form
                        method="POST"
                        enctype="multipart/form-data"
                        class="bug-report-form"
                    >

                        <?php echo csrf_field(); ?>

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


                        <div class="form-group">

                            <label for="bug_photo">
                                Bug Photo / Screenshots
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
                                JPG, PNG or WEBP — Maximum 5 MB per photo, up to 10 photos.
                            </small>

                        </div>


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