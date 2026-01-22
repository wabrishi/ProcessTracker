<?php
// Candidate Workflow Logic

include_once 'helpers.php';
include_once 'upload.php';
include_once 'mailer.php';

const STEPS = [
    1 => 'Profile Selection',
    2 => 'Confirmation Letter OR Cancellation Letter',
    3 => 'Document Verification',
    4 => '1st Round Interview – Schedule',
    5 => '1st Round Interview – Result',
    6 => '2nd Round Interview – Schedule',
    7 => '2nd Round Interview – Result'
];

function generateCandidateId(): string {
    return 'CAND' . str_pad(mt_rand(1, 999), 3, '0', STR_PAD_LEFT);
}

function createCandidate(array $data): ?array {
    $name = $data['name'];
    $email = $data['email'];
    $phone = $data['phone'];
    $position = $data['position'] ?? '';
    $location = $data['location'] ?? '';
    $resumeFile = null;
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] !== UPLOAD_ERR_NO_FILE) {
        $resumeFile = uploadResume($_FILES['resume']);
        if (!$resumeFile) {
            return null; // Invalid resume if provided
        }
    }

    $candidates = getCandidates();
    $id = generateCandidateId();
    while (isset($candidates[$id])) {
        $id = generateCandidateId();
    }

    $candidates[$id] = [
        'name' => $name,
        'email' => $email,
        'phone' => $phone,
        'position' => $position ?: 'Not Specified',
        'location' => $location ?: 'Not Specified',
        'current_step' => 1,
        'status' => 'IN_PROGRESS',
        'documents' => [],
        'interviews' => [],
        'resume' => $resumeFile
    ];

    saveCandidates($candidates);
    logRecruitmentAction($id, 'Profile Created', $_SESSION['user_id'] ?? 'system');

    // Send profile selected email with resume attachment if available
    $attachments = [];
    if ($resumeFile) {
        $attachments[] = __DIR__ . '/../uploads/resumes/' . $resumeFile;
    }
    sendTemplatedMail($id, 'profile_selected', [], $attachments);

    return ['id' => $id, 'message' => 'Candidate created successfully'];
}

function getCandidate(string $id): ?array {
    $candidates = getCandidates();
    return $candidates[$id] ?? null;
}

function canMoveToStep(array $candidate, int $targetStep): bool {
    return $candidate['current_step'] + 1 === $targetStep && $candidate['status'] === 'IN_PROGRESS';
}

function moveToStep(string $id, int $step, array $data = []): bool {
    $candidates = getCandidates();
    if (!isset($candidates[$id]) || !canMoveToStep($candidates[$id], $step)) {
        return false;
    }

    $candidates[$id]['current_step'] = $step;

    // Handle step-specific logic
    switch ($step) {
        case 2:
            // Confirmation or Cancellation
            $choice = $data['choice']; // 'confirmation' or 'cancellation'
            $attachments = [];
            $tempPdf = null;
            if (isset($_FILES['letter']) && $_FILES['letter']['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadedLetter = uploadLetter($_FILES['letter']);
                if ($uploadedLetter) {
                    $attachments[] = $uploadedLetter;
                }
            }
            if ($choice === 'confirmation') {
                if (!function_exists('generateConfirmationPDF')) {
                    error_log('generateConfirmationPDF function not found');
                    $tempPdf = null;
                } else {
                    $tempPdf = generateConfirmationPDF($candidates[$id]);
                }
                if ($tempPdf) {
                    $attachments[] = $tempPdf;
                }
            }
            $attachmentPaths = [];
            foreach ($attachments as $att) {
                if (strpos($att, DIRECTORY_SEPARATOR) !== false) {
                    // full path, like temp PDF
                    $attachmentPaths[] = $att;
                } else {
                    // filename, like uploaded letter
                    $attachmentPaths[] = __DIR__ . '/../uploads/letters/' . $att;
                }
            }
            sendTemplatedMail($id, $choice, [], $attachmentPaths);
            error_log("Sent $choice email to candidate $id");
            if ($tempPdf) @unlink($tempPdf);
            if ($choice === 'cancellation') {
                $candidates[$id]['status'] = 'CANCELLED';
            }
            break;
        case 3:
            // Document Verification
            $docs = [];
            if (isset($_FILES['documents'])) {
                foreach ($_FILES['documents']['name'] as $key => $name) {
                    if ($_FILES['documents']['error'][$key] === UPLOAD_ERR_OK) {
                        $file = [
                            'name' => $_FILES['documents']['name'][$key],
                            'type' => $_FILES['documents']['type'][$key],
                            'tmp_name' => $_FILES['documents']['tmp_name'][$key],
                            'error' => $_FILES['documents']['error'][$key],
                            'size' => $_FILES['documents']['size'][$key]
                        ];
                        $uploaded = uploadDocument($file);
                        if ($uploaded) {
                            $docs[] = $uploaded;
                        }
                    }
                }
            }
            $candidates[$id]['documents'] = array_merge($candidates[$id]['documents'], $docs);
            $candidates[$id]['verification_status'] = $data['verification'] ?? 'Pending';
            break;
        case 4:
            // Schedule 1st interview
            $candidates[$id]['interviews']['1st'] = [
                'date' => $data['date'],
                'time' => $data['time'],
                'mode' => $data['mode'],
                'interviewer' => $data['interviewer']
            ];
            sendTemplatedMail($id, 'interview_schedule', [
                'date' => $data['date'],
                'time' => $data['time'],
                'mode' => $data['mode'],
                'interviewer' => $data['interviewer']
            ]);
            error_log("Sent interview_schedule email for step 4 to candidate $id");
            break;
        case 5:
            // 1st interview result
            $result = $data['result']; // 'pass' or 'fail'
            $internalResult = $result === 'pass' ? 'selected' : 'rejected';
            $candidates[$id]['interviews']['1st']['result'] = $internalResult;
            $candidates[$id]['interviews']['1st']['remarks'] = $data['remarks'];
            if ($internalResult === 'rejected') {
                $candidates[$id]['status'] = 'CANCELLED';
            }
            // Send result email
            sendTemplatedMail($id, 'interview_result', [
                'result' => ucfirst($result),
                'remarks' => $data['remarks']
            ]);
            error_log("Sent interview_result email for step 5 to candidate $id");
            break;
        case 6:
            // Schedule 2nd interview (similar to 4)
            $candidates[$id]['interviews']['2nd'] = [
                'date' => $data['date'],
                'time' => $data['time'],
                'mode' => $data['mode'],
                'interviewer' => $data['interviewer']
            ];
            sendTemplatedMail($id, 'interview_schedule', [
                'date' => $data['date'],
                'time' => $data['time'],
                'mode' => $data['mode'],
                'interviewer' => $data['interviewer']
            ]);
            error_log("Sent interview_schedule email for step 6 to candidate $id");
            break;
        case 7:
            // 2nd interview result
            $result = $data['result'];
            $internalResult = $result === 'pass' ? 'selected' : 'rejected';
            $candidates[$id]['interviews']['2nd']['result'] = $internalResult;
            $candidates[$id]['interviews']['2nd']['remarks'] = $data['remarks'];
            $candidates[$id]['status'] = $internalResult === 'selected' ? 'COMPLETED' : 'CANCELLED';
            // Send final email
            sendTemplatedMail($id, 'interview_result', [
                'result' => ucfirst($result),
                'remarks' => $data['remarks']
            ]);
            error_log("Sent interview_result email for step 7 to candidate $id");
            break;
    }

    saveCandidates($candidates);
    logRecruitmentAction($id, STEPS[$step], $_SESSION['user_id']);
    return true;
}
?>