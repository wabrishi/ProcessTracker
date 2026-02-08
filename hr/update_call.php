<?php
session_start();

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/candidate.php';

header('Content-Type: application/json');

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Invalid request method"]);
    exit;
}

// Validate input
$candidateId = $_POST['id'] ?? null;
$result      = $_POST['result'] ?? null;
$remarks     = $_POST['remarks'] ?? '';
$hr          = $_SESSION['user_id'] ?? 'system';

if (!$candidateId || !$result) {
    echo json_encode(["success" => false, "message" => "Missing candidate ID or result"]);
    exit;
}

// Load candidates JSON
$candidates = getCandidates();

if (!isset($candidates[$candidateId])) {
    echo json_encode(["success" => false, "message" => "Candidate not found"]);
    exit;
}

// Create call log entry
$callLogEntry = [
    "timestamp" => date("Y-m-d H:i:s"),
    "result"    => $result,
    "remarks"   => $remarks,
    "called_by" => $hr
];

// Init call_logs array
if (!isset($candidates[$candidateId]['call_logs'])) {
    $candidates[$candidateId]['call_logs'] = [];
}

// Add latest call on top
array_unshift($candidates[$candidateId]['call_logs'], $callLogEntry);

// Update summary fields
$candidates[$candidateId]['last_call']   = $callLogEntry['timestamp'];
$candidates[$candidateId]['call_result'] = $result;

// Save JSON safely
if (!saveCandidates($candidates)) {
    echo json_encode(["success" => false, "message" => "Failed to save JSON"]);
    exit;
}

// Optional audit log
if (function_exists('logRecruitmentAction')) {
    logRecruitmentAction($candidateId, "Call logged: $result", $hr);
}

// Success response
echo json_encode(["success" => true]);
exit;
