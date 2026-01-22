<?php
session_start();
include_once __DIR__ . '/../includes/helpers.php';
include_once __DIR__ . '/../includes/candidate.php';

header('Content-Type: application/json');

// Enable error logging for debugging
error_log("=== update_call.php called ===");
error_log("POST data: " . print_r($_POST, true));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$payload = $_POST;
$candidateId = $payload['id'] ?? null;
$result = $payload['result'] ?? null; // 'interested' | 'not_interested'
$remarks = $payload['remarks'] ?? '';
$hr = $_SESSION['user_id'] ?? 'system';

error_log("Candidate ID: " . ($candidateId ?? 'null'));
error_log("Result: " . ($result ?? 'null'));
error_log("HR: " . $hr);

if (!$candidateId || !$result) {
    error_log("Missing parameters: id=" . ($candidateId ?? 'null') . ", result=" . ($result ?? 'null'));
    echo json_encode(['success' => false, 'message' => 'Missing parameters: id=' . ($candidateId ?? 'null') . ', result=' . ($result ?? 'null')]);
    exit;
}

$candidates = getCandidates();
error_log("Total candidates loaded: " . count($candidates));
error_log("Candidate IDs available: " . implode(', ', array_keys($candidates)));

if (!isset($candidates[$candidateId])) {
    error_log("Candidate not found: " . $candidateId);
    echo json_encode(['success' => false, 'message' => 'Candidate not found: ' . $candidateId]);
    exit;
}

// Update call info - store as history
$callLogEntry = [
    'timestamp' => date('Y-m-d H:i:s'),
    'result' => $result,
    'remarks' => $remarks,
    'called_by' => $hr
];

// Initialize call_logs array if it doesn't exist
if (!isset($candidates[$candidateId]['call_logs'])) {
    $candidates[$candidateId]['call_logs'] = [];
}

// Add new call log to the beginning of the array (most recent first)
array_unshift($candidates[$candidateId]['call_logs'], $callLogEntry);

// Also update last_call for quick reference in lists
$candidates[$candidateId]['last_call'] = $callLogEntry['timestamp'];
$candidates[$candidateId]['last_called_by'] = $hr;
$candidates[$candidateId]['call_result'] = $result;

error_log("Added call log entry for candidate: " . $candidateId);
error_log("Total call logs: " . count($candidates[$candidateId]['call_logs']));

error_log("Updated candidate data for ID: " . $candidateId);
error_log("New call data: last_call=" . $candidates[$candidateId]['last_call'] . ", result=" . $result);

$saved = saveCandidates($candidates);
if (!$saved) {
    error_log("Failed to save candidates for ID: " . $candidateId);
    echo json_encode(['success' => false, 'message' => 'Failed to save candidates']);
    exit;
}

error_log("Candidates saved successfully for ID: " . $candidateId);

$logResult = logRecruitmentAction($candidateId, 'Call: ' . $result, $hr);
error_log("Log recruitment action result: " . ($logResult ? 'success' : 'failed'));

// Return the saved call details so frontend can update UI
echo json_encode([
    'success' => true, 
    'message' => 'Call logged successfully', 
    'data' => [
        'id' => $candidateId,
        'result' => $result,
        'remarks' => $remarks,
        'last_call' => $candidates[$candidateId]['last_call'],
        'call_logs' => $candidates[$candidateId]['call_logs']
    ]
]);
