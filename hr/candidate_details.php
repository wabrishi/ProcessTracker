<?php
// Determine dashboard URL based on user role
$currentUserRole = $_SESSION['role'] ?? '';
$backUrl = ($currentUserRole === 'ADMIN') ? '../index.php?page=admin' : '../index.php?page=hr';

include_once __DIR__ . '/../includes/candidate.php';

$message = '';

if (!isset($_GET['id'])) {
    header('Location: dashboard.php');
    exit;
}

$candidateId = $_GET['id'];
$candidate = getCandidate($candidateId);

if (!$candidate) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['log_call'])) {
        // Handle call logging from detail page
        $result = $_POST['result'] ?? null;
        $remarks = $_POST['remarks'] ?? '';
        $hr = $_SESSION['user_id'] ?? 'system';
        
        if (!$result) {
            $message = 'Please select call result';
        } else {
            $candidates = getCandidates();
            $callLogEntry = [
                'timestamp' => date('Y-m-d H:i:s'),
                'result' => $result,
                'remarks' => $remarks,
                'called_by' => $hr
            ];
            
            if (!isset($candidates[$candidateId]['call_logs'])) {
                $candidates[$candidateId]['call_logs'] = [];
            }
            
            array_unshift($candidates[$candidateId]['call_logs'], $callLogEntry);
            $candidates[$candidateId]['last_call'] = $callLogEntry['timestamp'];
            $candidates[$candidateId]['call_result'] = $result;
            
            if (saveCandidates($candidates)) {
                $message = 'Call logged successfully';
                logRecruitmentAction($candidateId, 'Call: ' . $result, $hr);
                // Refresh candidate data
                $candidate = getCandidate($candidateId);
            } else {
                $message = 'Failed to save call log';
            }
        }
    } elseif (isset($_POST['move_step'])) {
        $step = (int)$_POST['step'];
        $data = $_POST;
        if (moveToStep($candidateId, $step, $data)) {
            $message = 'Moved to step ' . $step . ' successfully';
            // Refresh candidate data
            $candidate = getCandidate($candidateId);
        } else {
            $message = 'Cannot move to step ' . $step;
        }
    } elseif (isset($_POST['upload_document'])) {
        if (isset($_FILES['document']) && $_FILES['document']['error'] !== UPLOAD_ERR_NO_FILE) {
            $uploadedDoc = uploadDocument($_FILES['document']);
            if ($uploadedDoc) {
                $candidates = getCandidates();
                if (!isset($candidates[$candidateId]['documents'])) {
                    $candidates[$candidateId]['documents'] = [];
                }
                $candidates[$candidateId]['documents'][] = $uploadedDoc;
                saveCandidates($candidates);
                $message = 'Document uploaded successfully';
                // Refresh candidate data
                $candidate = getCandidate($candidateId);
                logRecruitmentAction($candidateId, 'Document Uploaded: ' . $_FILES['document']['name'], $_SESSION['user_id'] ?? 'system');
            } else {
                $message = 'Failed to upload document. Please check file type (PDF/JPG/PNG) and size (max 5MB).';
            }
        } else {
            $message = 'Please select a document to upload.';
        }
    }
}

// Handle document deletion
if (isset($_GET['delete_doc'])) {
    $docIndex = (int)$_GET['delete_doc'];
    $candidates = getCandidates();
    if (isset($candidates[$candidateId]['documents'][$docIndex])) {
        $docToDelete = $candidates[$candidateId]['documents'][$docIndex];
        // Delete file from server
        $filePath = __DIR__ . '/../uploads/documents/' . $docToDelete;
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        // Remove from array
        unset($candidates[$candidateId]['documents'][$docIndex]);
        $candidates[$candidateId]['documents'] = array_values($candidates[$candidateId]['documents']); // Reindex
        saveCandidates($candidates);
        $message = 'Document deleted successfully';
        // Refresh candidate data
        $candidate = getCandidate($candidateId);
        logRecruitmentAction($candidateId, 'Document Deleted: ' . $docToDelete, $_SESSION['user_id'] ?? 'system');
    }
    // Redirect to remove query parameter
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?page=candidate_details&id=' . $candidateId);
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidate Details - <?php echo $candidate['name']; ?></title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        .candidate-details-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .candidate-details-header h1 {
            margin: 0;
            font-size: 1.8em;
        }

        .candidate-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-card {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .info-card h3 {
            margin-top: 0;
            color: #333;
            border-bottom: 2px solid #667eea;
            padding-bottom: 10px;
        }

        .info-item {
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .info-label {
            font-weight: bold;
            color: #666;
        }

        .info-value {
            color: #333;
        }

        .action-section {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }

        .action-section h3 {
            margin-top: 0;
            color: #333;
        }

        .back-btn {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin-bottom: 20px;
        }

        .back-btn:hover {
            background: #5a6268;
        }

        .documents-list {
            list-style: none;
            padding: 0;
        }

        .documents-list li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .documents-list li:last-child {
            border-bottom: none;
        }

        .document-link {
            color: #3498db;
            text-decoration: none;
        }

        .document-link:hover {
            text-decoration: underline;
        }

        .document-actions {
            display: flex;
            gap: 10px;
        }

        .delete-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 4px 8px;
            border-radius: 3px;
            cursor: pointer;
            font-size: 0.8em;
            text-decoration: none;
        }

        .delete-btn:hover {
            background: #c0392b;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="<?php echo $backUrl; ?>" class="back-btn">← Back to Dashboard</a>

        <div class="candidate-details-header">
            <h1><?php echo $candidate['name']; ?> <span style="font-size: 0.6em; opacity: 0.8;">(<?php echo $candidateId; ?>)</span></h1>
            <span class="status-badge status-<?php echo strtolower($candidate['status']); ?>"><?php echo $candidate['status']; ?></span>
        </div>

        <?php if ($message) echo "<p class='message'>$message</p>"; ?>

        <div class="candidate-info-grid">
            <!-- Basic Information -->
            <div class="info-card">
                <h3>Basic Information</h3>
                <div class="info-item">
                    <span class="info-label">Name:</span>
                    <span class="info-value"><?php echo $candidate['name']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Email:</span>
                    <span class="info-value"><?php echo $candidate['email']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Phone:</span>
                    <span class="info-value"><?php echo $candidate['phone']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Position:</span>
                    <span class="info-value"><?php echo $candidate['position']; ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Current Step:</span>
                    <span class="info-value"><?php echo $candidate['current_step'] . ' - ' . STEPS[$candidate['current_step']]; ?></span>
                </div>
            </div>

            <!-- Documents -->
            <div class="info-card">
                <h3>Documents</h3>
                <?php if (!empty($candidate['resume'])): ?>
                    <div class="info-item">
                        <span class="info-label">Resume:</span>
                        <span class="info-value"><a href="../uploads/resumes/<?php echo $candidate['resume']; ?>" target="_blank" class="document-link">View Resume</a></span>
                    </div>
                <?php else: ?>
                    <p style="color: #999; font-style: italic;">No resume uploaded</p>
                <?php endif; ?>

                <?php if (!empty($candidate['documents'])): ?>
                    <h4>Additional Documents:</h4>
                    <ul class="documents-list">
                        <?php foreach ($candidate['documents'] as $index => $doc): ?>
                            <li>
                                <a href="../uploads/documents/<?php echo $doc; ?>" target="_blank" class="document-link"><?php echo basename($doc); ?></a>
                                <div class="document-actions">
                                    <a href="?page=candidate_details&id=<?php echo $candidateId; ?>&delete_doc=<?php echo $index; ?>" 
                                       class="delete-btn" 
                                       onclick="return confirm('Are you sure you want to delete this document?')">Delete</a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p style="color: #999; font-style: italic;">No additional documents uploaded</p>
                <?php endif; ?>
            </div>

            <!-- Interview History -->
            <?php if (!empty($candidate['interviews'])): ?>
            <div class="info-card">
                <h3>Interview History</h3>
                <?php foreach ($candidate['interviews'] as $round => $interview): ?>
                    <div style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 4px;">
                        <h4 style="margin: 0 0 10px 0; color: #667eea;"><?php echo ucfirst($round); ?> Interview</h4>
                        <div class="info-item">
                            <span class="info-label">Date:</span>
                            <span class="info-value"><?php echo $interview['date']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Time:</span>
                            <span class="info-value"><?php echo $interview['time']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Mode:</span>
                            <span class="info-value"><?php echo $interview['mode']; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Interviewer:</span>
                            <span class="info-value"><?php echo $interview['interviewer']; ?></span>
                        </div>
                        <?php if (isset($interview['result'])): ?>
                        <div class="info-item">
                            <span class="info-label">Result:</span>
                            <span class="info-value"><?php echo ucfirst($interview['result']); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if (isset($interview['remarks'])): ?>
                        <div class="info-item">
                            <span class="info-label">Remarks:</span>
                            <span class="info-value"><?php echo $interview['remarks']; ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Call Log History Section -->
        <div class="info-card" style="margin-bottom: 20px;">
            <h3 style="border-bottom: 2px solid #28a745;">Call Log History</h3>
            <?php if (!empty($candidate['call_logs'])): ?>
                <div class="call-logs-list">
                    <?php foreach ($candidate['call_logs'] as $index => $log): ?>
                        <div class="call-log-entry" style="padding: 12px; margin-bottom: 10px; border-radius: 4px; background: <?php echo $log['result'] === 'interested' ? '#d4edda' : '#f8d7da'; ?>; border-left: 4px solid <?php echo $log['result'] === 'interested' ? '#28a745' : '#dc3545'; ?>;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                                <div>
                                    <strong style="color: <?php echo $log['result'] === 'interested' ? '#155724' : '#721c24'; ?>;">
                                        <?php if ($log['result'] === 'interested'): ?>
                                            ✓ Interested
                                        <?php else: ?>
                                            ✗ Not Interested
                                        <?php endif; ?>
                                    </strong>
                                </div>
                                <div style="font-size: 0.85em; color: #666;">
                                    <?php echo date('d M Y, h:i A', strtotime($log['timestamp'])); ?>
                                </div>
                            </div>
                            <?php if (!empty($log['remarks'])): ?>
                                <div style="margin-bottom: 6px; font-size: 0.9em; color: #333;">
                                    <em>"<?php echo htmlspecialchars($log['remarks']); ?>"</em>
                                </div>
                            <?php endif; ?>
                            <div style="font-size: 0.8em; color: #666;">
                                Called by: <?php echo htmlspecialchars($log['called_by'] ?? 'Unknown'); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="color: #999; font-style: italic; padding: 20px; text-align: center;">No call logs recorded yet</p>
            <?php endif; ?>
        </div>

        <!-- Log Call Form -->
        <div class="action-section" style="margin-bottom: 20px;">
            <h3 style="color: #28a745;">Log a Call</h3>
            <p style="color: #666; margin-bottom: 15px;">Record a new call with this candidate</p>
            <form method="post">
                <div style="margin-bottom: 15px;">
                    <label style="font-weight: bold; display: block; margin-bottom: 8px;">Call Result:</label>
                    <label style="display: inline-flex; align-items: center; gap: 6px; margin-right: 20px;">
                        <input type="radio" name="result" value="interested" required> 
                        <span style="color: #28a745; font-weight: bold;">✓ Interested</span>
                    </label>
                    <label style="display: inline-flex; align-items: center; gap: 6px;">
                        <input type="radio" name="result" value="not_interested"> 
                        <span style="color: #dc3545; font-weight: bold;">✗ Not Interested</span>
                    </label>
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="font-weight: bold; display: block; margin-bottom: 8px;">Remarks:</label>
                    <textarea name="remarks" rows="3" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;" placeholder="Enter call notes or remarks..."></textarea>
                </div>
                <button name="log_call" class="action-btn" style="background: #28a745;">Save Call Log</button>
            </form>
        </div>

        <!-- Document Upload Section -->
        <div class="action-section">
            <h3>Upload Additional Documents</h3>
            <p style="color: #666; margin-bottom: 15px;">Upload additional documents for this candidate (PDF, JPG, PNG - max 5MB each)</p>
            <form method="post" enctype="multipart/form-data">
                <div class="form-row">
                    <label>Document: <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required></label>
                </div>
                <button name="upload_document" class="action-btn" style="background: #28a745;">Upload Document</button>
            </form>
        </div>

        <!-- Step 1: Profile Selection -->
        <?php if ($candidate['status'] === 'IN_PROGRESS' && $candidate['current_step'] === 1): ?>
        <div class="action-section">
            <h3>Step 1 - Profile Selection</h3>
            <p style="color: #666; margin-bottom: 15px;">Review the candidate profile information above. Once you confirm the profile is complete and correct, click "Start Process" to proceed to the next step.</p>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="candidate_id" value="<?php echo $candidateId; ?>">
                <input type="hidden" name="step" value="2">
                <button name="move_step" class="action-btn">Start Process - Move to Step 2</button>
            </form>
        </div>
        <?php endif; ?>

        <!-- Action Section -->
        <?php if ($candidate['status'] === 'IN_PROGRESS' && $candidate['current_step'] > 1 && $candidate['current_step'] < 7): ?>
        <div class="action-section">
            <h3>Next Action: Step <?php echo $candidate['current_step'] + 1; ?> - <?php echo STEPS[$candidate['current_step'] + 1]; ?></h3>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="candidate_id" value="<?php echo $candidateId; ?>">
                <input type="hidden" name="step" value="<?php echo $candidate['current_step'] + 1; ?>">
                <?php
                $nextStep = $candidate['current_step'] + 1;
                if ($nextStep == 2) {
                    echo '<div class="form-row"><label><input type="radio" name="choice" value="confirmation" required> Send Confirmation</label></div>';
                    echo '<div class="form-row"><label><input type="radio" name="choice" value="cancellation"> Send Cancellation</label></div>';
                    echo '<div class="form-row"><label>Letter (optional): <input type="file" name="letter"></label></div>';
                } elseif ($nextStep == 3) {
                    echo '<div class="form-row"><label>Additional Documents: <input type="file" name="documents[]" multiple></label></div>';
                    echo '<div class="form-row"><label>Verification Status: <select name="verification"><option>Pending</option><option>Verified</option><option>Rejected</option></select></label></div>';
                } elseif ($nextStep == 4 || $nextStep == 6) {
                    echo '<div class="form-row"><label>Interview Date: <input type="date" name="date" required></label></div>';
                    echo '<div class="form-row"><label>Interview Time: <input type="time" name="time" required></label></div>';
                    echo '<div class="form-row"><label>Interview Mode: <select name="mode"><option>Online</option><option>Offline</option></select></label></div>';
                    echo '<div class="form-row"><label>Interviewer: <input name="interviewer" required></label></div>';
                } elseif ($nextStep == 5 || $nextStep == 7) {
                    echo '<div class="form-row"><label>Interview Result: <select name="result"><option value="pass">Pass</option><option value="fail">Fail</option></select></label></div>';
                    echo '<div class="form-row"><label>Remarks: <textarea name="remarks" required></textarea></label></div>';
                }
                ?>
                <button name="move_step" class="action-btn">Submit Action</button>
            </form>
        </div>
        <?php else: ?>
        <div class="action-section">
            <h3>Process Status</h3>
            <p style="color: <?php echo $candidate['status'] === 'COMPLETED' ? '#27ae60' : '#e74c3c'; ?>; font-weight: bold;">
                This candidate's recruitment process is <?php echo strtolower($candidate['status']); ?>.
            </p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Loading overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loader"></div>
    </div>

    <script>
        // Show loader on form submission
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function() {
                    document.getElementById('loadingOverlay').style.display = 'flex';
                });
            });
        });
    </script>
</body>
</html>