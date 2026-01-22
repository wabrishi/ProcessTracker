<?php
// File Upload Handler with validation

function uploadFile(array $file, string $targetDir, array $allowedTypes = ['pdf', 'doc', 'docx'], int $maxSize = 5 * 1024 * 1024): ?string {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        error_log("Upload error: " . $file['error'] . " for file " . ($file['name'] ?? 'unknown'));
        return null; // Upload error
    }

    $fileName = basename($file['name']);
    $fileSize = $file['size'];
    $fileTmp = $file['tmp_name'];
    $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    // Validate extension
    if (!in_array($fileExt, $allowedTypes)) {
        error_log("Invalid file type: $fileExt for $fileName");
        return null;
    }

    // Validate size
    if ($fileSize > $maxSize) {
        error_log("File too large: $fileSize > $maxSize for $fileName");
        return null;
    }

    // Generate unique name
    $uniqueName = uniqid() . '.' . $fileExt;
    $targetPath = $targetDir . '/' . $uniqueName;

    if (move_uploaded_file($fileTmp, $targetPath)) {
        return $uniqueName;
    }

    error_log("Failed to move uploaded file to $targetPath");
    return null;
}

// Specific upload functions
function uploadResume(array $file): ?string {
    return uploadFile($file, __DIR__ . '/../uploads/resumes', ['pdf', 'doc', 'docx']);
}

function uploadDocument(array $file): ?string {
    return uploadFile($file, __DIR__ . '/../uploads/documents', ['pdf', 'jpg', 'png']);
}

function uploadLetter(array $file): ?string {
    return uploadFile($file, __DIR__ . '/../uploads/letters', ['pdf', 'doc', 'docx']);
}
?>