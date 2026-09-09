<?php

function handleDoctorVerificationPhoto(array $file): array
{
    $result = [
        'success' => false,
        'filename' => '',
        'mime_type' => '',
        'error' => ''
    ];

    // No file uploaded
    if (
        !isset($file) ||
        $file['error'] === UPLOAD_ERR_NO_FILE
    ) {
        $result['error'] =
            "Please upload your medical license or ID photo.";

        return $result;
    }

    // Upload error
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $result['error'] =
            "Unable to upload the verification photo.";

        return $result;
    }

    // Maximum 5 MB
    if ($file['size'] > 5 * 1024 * 1024) {
        $result['error'] =
            "Verification photo must be smaller than 5 MB.";

        return $result;
    }

    // Allowed MIME types
    $allowedTypes = [
        'image/jpeg',
        'image/png',
        'image/webp'
    ];

    // Detect actual MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);

    if (!$finfo) {
        $result['error'] =
            "Unable to validate the verification photo.";

        return $result;
    }

    $detectedType = finfo_file(
        $finfo,
        $file['tmp_name']
    );

    finfo_close($finfo);

    // Check MIME type
    if (!in_array($detectedType, $allowedTypes, true)) {

        $result['error'] =
            "Invalid verification photo. "
            . "Only JPG, PNG, and WEBP files are allowed.";

        return $result;
    }

    // Make sure it is actually an image
    if (!@getimagesize($file['tmp_name'])) {

        $result['error'] =
            "The uploaded verification file is not a valid image.";

        return $result;
    }

    // MIME type → extension
    $extensionMap = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp'
    ];

    $extension = $extensionMap[$detectedType];

    // Temporary storage directory
    $tempDirectory =
        dirname(__DIR__) . '/storage/temp_verification';

    if (!is_dir($tempDirectory)) {

        if (!mkdir($tempDirectory, 0750, true)) {

            $result['error'] =
                "Unable to prepare photo storage.";

            return $result;
        }
    }

    // Generate random filename
    $temporaryFilename =
        bin2hex(random_bytes(32))
        . '.'
        . $extension;

    $temporaryPath =
        $tempDirectory
        . DIRECTORY_SEPARATOR
        . $temporaryFilename;

    // Move uploaded file
    if (!move_uploaded_file(
        $file['tmp_name'],
        $temporaryPath
    )) {

        $result['error'] =
            "Unable to save the verification photo.";

        return $result;
    }

    // Success
    $result['success'] = true;
    $result['filename'] = $temporaryFilename;
    $result['mime_type'] = $detectedType;

    return $result;
}