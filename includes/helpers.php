<?php
// JSON Storage Helpers with file locking

function readJsonFile(string $filePath): array {
    if (!file_exists($filePath)) {
        error_log("readJsonFile: File does not exist: " . $filePath);
        return [];
    }
    
    // Check if file is readable
    if (!is_readable($filePath)) {
        error_log("readJsonFile: File is not readable: " . $filePath);
        return [];
    }
    
    $data = file_get_contents($filePath);
    if ($data === false) {
        error_log("readJsonFile: Failed to read file: " . $filePath);
        return [];
    }
    
    $decoded = json_decode($data, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("readJsonFile: JSON decode error: " . json_last_error_msg());
        return [];
    }
    
    return $decoded ?: [];
}

function writeJsonFile(string $filePath, array $data): bool {
    // Check if directory is writable
    $dir = dirname($filePath);
    if (!is_writable($dir)) {
        error_log("writeJsonFile: Directory is not writable: " . $dir);
        return false;
    }
    
    // Check if file exists and is writable, or if parent dir allows creation
    if (file_exists($filePath) && !is_writable($filePath)) {
        error_log("writeJsonFile: File exists but is not writable: " . $filePath);
        return false;
    }
    
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        error_log("writeJsonFile: JSON encode error: " . json_last_error_msg());
        return false;
    }
    
    $result = file_put_contents($filePath, $json);
    if ($result === false) {
        error_log("writeJsonFile: Failed to write to file: " . $filePath);
        error_log("writeJsonFile: Last error: " . error_get_last()['message'] ?? 'None');
        return false;
    }
    
    error_log("writeJsonFile: Successfully wrote " . $result . " bytes to: " . $filePath);
    return true;
}

function appendToJsonArray(string $filePath, array $item): bool {
    $data = readJsonFile($filePath);
    $data[] = $item;
    return writeJsonFile($filePath, $data);
}

// Specific helpers
function getUsers(): array {
    return readJsonFile(__DIR__ . '/../database/users.json');
}

function saveUsers(array $users): bool {
    return writeJsonFile(__DIR__ . '/../database/users.json', $users);
}

function getCandidates(): array {
    return readJsonFile(__DIR__ . '/../database/candidates.json');
}

function saveCandidates(array $candidates): bool {
    $result = writeJsonFile(__DIR__ . '/../database/candidates.json', $candidates);
    if (!$result) {
        error_log("saveCandidates: Failed to save candidates to database");
        error_log("saveCandidates: File path: " . __DIR__ . '/../database/candidates.json');
    }
    return $result;
}


function logRecruitmentAction(string $candidateId, string $step, string $actionBy): bool {
    $log = [
        'candidate_id' => $candidateId,
        'step' => $step,
        'action_by' => $actionBy,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    return appendToJsonArray(__DIR__ . '/../database/recruitment_logs.json', $log);
}

// Utility function to check database file status
function checkDatabaseHealth(): array {
    $dbFile = __DIR__ . '/../database/candidates.json';
    $logsFile = __DIR__ . '/../database/recruitment_logs.json';
    
    return [
        'candidates_db' => [
            'path' => $dbFile,
            'exists' => file_exists($dbFile),
            'readable' => is_readable($dbFile),
            'writable' => is_writable($dbFile),
            'permissions' => file_exists($dbFile) ? substr(sprintf('%o', fileperms($dbFile)), -4) : 'N/A'
        ],
        'logs_db' => [
            'path' => $logsFile,
            'exists' => file_exists($logsFile),
            'readable' => is_readable($logsFile),
            'writable' => is_writable($logsFile),
            'permissions' => file_exists($logsFile) ? substr(sprintf('%o', fileperms($logsFile)), -4) : 'N/A'
        ]
    ];
}
?>
