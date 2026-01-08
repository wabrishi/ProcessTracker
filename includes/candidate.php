<?php
// Candidate Workflow Logic

include 'helpers.php';
include 'upload.php';
include 'mailer.php';

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

function createCandidate(array $data): ?string {
    $name = $data['name'];
    $email = $data['email'];
    $phone = $data['phone'];
    $position = $data['position'];
    $resumeFile = uploadResume($_FILES['resume'] ?? []);

    if (!$resumeFile) {
        return null; // Invalid resume
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
        'position' => $position,
        'current_step' => 1,
        'status' => 'IN_PROGRESS',
        'documents' => [],
        'interviews' => [],
        'resume' => $resumeFile
    ];

    saveCandidates($candidates);
    logRecruitmentAction($id, 'Profile Created', $_SESSION['user_id']);

    // Send profile selected email
    sendTemplatedMail($id, 'profile_selected', [], [__DIR__ . '/../uploads/resumes/' . $resumeFile]);

    return $id;
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
            if (isset($_FILES['letter'])) {
                $attachments[] = uploadLetter($_FILES['letter']);
            }
            sendTemplatedMail($id, $choice, [], $attachments ? [__DIR__ . '/../uploads/letters/' . $attachments[0]] : []);
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
            break;
        case 5:
            // 1st interview result
            $result = $data['result']; // 'selected' or 'rejected'
            $candidates[$id]['interviews']['1st']['result'] = $result;
            $candidates[$id]['interviews']['1st']['remarks'] = $data['remarks'];
            if ($result === 'rejected') {
                $candidates[$id]['status'] = 'CANCELLED';
            }
            // Send result email
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
            break;
        case 7:
            // 2nd interview result
            $result = $data['result'];
            $candidates[$id]['interviews']['2nd']['result'] = $result;
            $candidates[$id]['interviews']['2nd']['remarks'] = $data['remarks'];
            $candidates[$id]['status'] = $result === 'selected' ? 'COMPLETED' : 'CANCELLED';
            // Send final email
            break;
    }

    saveCandidates($candidates);
    logRecruitmentAction($id, STEPS[$step], $_SESSION['user_id']);
    return true;
}
?>