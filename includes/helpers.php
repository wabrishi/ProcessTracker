<?php
// JSON Storage Helpers with file locking

function readJsonFile(string $filePath): array {
    if (!file_exists($filePath)) {
        return [];
    }
    $data = file_get_contents($filePath);
    return json_decode($data, true) ?: [];
}

function writeJsonFile(string $filePath, array $data): bool {
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    return file_put_contents($filePath, $json, LOCK_EX) !== false;
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
    return writeJsonFile(__DIR__ . '/../database/candidates.json', $candidates);
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
?>