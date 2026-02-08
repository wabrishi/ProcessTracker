<?php
include_once __DIR__ . '/../includes/candidate.php';
include_once __DIR__ . '/../includes/helpers.php';

$message = '';
$currentUserRole = $_SESSION['role'] ?? '';
$currentUserId = $_SESSION['user_id'] ?? null;
$currentUserName = $_SESSION['user_name'] ?? 'User';

// Determine back URL based on role
$backUrl = ($currentUserRole === 'ADMIN') ? 'index.php?page=admin&menu=candidates' : 'index.php?page=hr&menu=candidates';
$dashboardUrl = ($currentUserRole === 'ADMIN') ? 'index.php?page=admin&menu=dashboard' : 'index.php?page=hr&menu=dashboard';

if (!isset($_GET['id'])) {
    header('Location: ' . $backUrl);
    exit;
}

$candidateId = $_GET['id'];
$candidate = getCandidate($candidateId);

if (!$candidate) {
    header('Location: ' . $backUrl);
    exit;
}

// Get workflow configuration
$workflowConfig = getWorkflowConfig();
$settings = $workflowConfig['settings'] ?? [];
$showStepDescription = $settings['show_step_description'] ?? true;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Handle call logging
    if (isset($_POST['log_call'])) {
        $result = $_POST['result'] ?? null;
        $remarks = $_POST['remarks'] ?? '';
        $hr = $currentUserId ?? 'system';
        
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
                $candidate = getCandidate($candidateId);
            } else {
                $message = 'Failed to save call log';
            }
        }
    }
    
    // Handle step movement
    elseif (isset($_POST['move_step'])) {
        $step = (int)$_POST['step'];
        $data = $_POST;
        
        // Remove form-specific fields
        unset($data['move_step'], $data['step']);
        
        if (moveToStep($candidateId, $step, $data)) {
            $message = 'Moved to step ' . $step . ' successfully';
            $candidate = getCandidate($candidateId);
        } else {
            $message = 'Cannot move to step ' . $step;
        }
    }
    
    // Handle candidate cancellation
    elseif (isset($_POST['cancel_candidate'])) {
        $candidates = getCandidates();
        if (isset($candidates[$candidateId])) {
            $candidates[$candidateId]['status'] = 'CANCELLED';
            if (saveCandidates($candidates)) {
                $message = 'Candidate cancelled';
                logRecruitmentAction($candidateId, 'Cancelled', $currentUserId ?? 'system');
                $candidate = getCandidate($candidateId);
            }
        }
    }
    
    // Handle document upload
    elseif (isset($_POST['upload_document'])) {
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
                $candidate = getCandidate($candidateId);
                logRecruitmentAction($candidateId, 'Document Uploaded: ' . $_FILES['document']['name'], $currentUserId ?? 'system');
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
        $filePath = __DIR__ . '/../uploads/documents/' . $docToDelete;
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        unset($candidates[$candidateId]['documents'][$docIndex]);
        $candidates[$candidateId]['documents'] = array_values($candidates[$candidateId]['documents']);
        saveCandidates($candidates);
        $message = 'Document deleted successfully';
        $candidate = getCandidate($candidateId);
        logRecruitmentAction($candidateId, 'Document Deleted: ' . $docToDelete, $currentUserId ?? 'system');
    }
    header('Location: index.php?page=candidate_details&id=' . $candidateId);
    exit;
}

// Get current step configuration
$currentStep = $candidate['current_step'] ?? 1;
$stepConfig = getStepConfig($currentStep);
$totalSteps = getTotalSteps();
$nextStep = getNextStepNumber($candidate);
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Candidate Details - <?php echo htmlspecialchars($candidate['name']); ?></title>
    <link rel="stylesheet" href="../styles.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>ProcessTracker</h2>
                <div class="user-info">Welcome, <?php echo htmlspecialchars($currentUserName); ?></div>
            </div>
            
            <ul class="menu">
                <?php if ($currentUserRole === 'ADMIN'): ?>
                <!-- Admin Menu -->
                <li class="menu-item">
                    <a href="index.php?page=admin&menu=dashboard" class="menu-link">
                        <span class="icon">📊</span>
                        Dashboard
                    </a>
                </li>
                <li class="menu-item">
                    <a href="index.php?page=admin&menu=users" class="menu-link">
                        <span class="icon">👥</span>
                        User Management
                    </a>
                </li>
                <li class="menu-item">
                    <a href="index.php?page=admin&menu=assign" class="menu-link">
                        <span class="icon">📋</span>
                        Assign Candidates
                    </a>
                </li>
                <li class="menu-item">
                    <a href="index.php?page=admin&menu=candidates" class="menu-link active">
                        <span class="icon">📝</span>
                        All Candidates
                    </a>
                </li>
                <li class="menu-item">
                    <a href="index.php?page=admin&menu=workflow" class="menu-link">
                        <span class="icon">🔄</span>
                        Workflow Manager
                    </a>
                </li>
                <li class="menu-item">
                    <a href="index.php?page=admin&menu=smtp" class="menu-link">
                        <span class="icon">⚙️</span>
                        Configuration
                    </a>
                </li>
                <?php else: ?>
                <!-- HR Menu -->
                <li class="menu-item">
                    <a href="index.php?page=hr&menu=dashboard" class="menu-link">
                        <span class="icon">📊</span>
                        Dashboard
                    </a>
                </li>
                <li class="menu-item">
                    <a href="index.php?page=hr&menu=create" class="menu-link">
                        <span class="icon">➕</span>
                        Create Candidate
                    </a>
                </li>
                <li class="menu-item">
                    <a href="index.php?page=hr&menu=candidates" class="menu-link active">
                        <span class="icon">👥</span>
                        My Candidates
                    </a>
                </li>
                <li class="menu-item">
                    <a href="index.php?page=profile" class="menu-link">
                        <span class="icon">👤</span>
                        My Profile
                    </a>
                </li>
                <?php endif; ?>
                
                <li class="menu-item">
                    <a href="../index.php?page=logout" class="menu-link">
                        <span class="icon">🚪</span>
                        Logout
                    </a>
                </li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Mobile Toggle -->
            <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
            <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

            <div class="page-header">
                <h1>👤 <?php echo htmlspecialchars($candidate['name']); ?></h1>
                <div class="header-actions">
                    <a href="<?php echo $backUrl; ?>" class="btn btn-secondary">← Back to Candidates</a>
                    <span class="status-badge status-<?php echo strtolower($candidate['status']); ?>">
                        <?php echo $candidate['status']; ?>
                    </span>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="<?php echo strpos($message, 'successfully') !== false || strpos($message, 'cancelled') !== false ? 'message' : 'error'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- Workflow Progress -->
            <div class="content-card">
                <h2>🔄 Recruitment Progress</h2>
                <?php echo renderWorkflowSteps($candidate, true); ?>
                
                <?php if ($candidate['status'] === 'IN_PROGRESS'): ?>
                    <div class="current-step-info">
                        <h3>Current Step: <?php echo $currentStep; ?> - <?php echo htmlspecialchars($stepConfig['name'] ?? 'Step ' . $currentStep); ?></h3>
                        <?php if ($showStepDescription && !empty($stepConfig['description'])): ?>
                            <p><?php echo htmlspecialchars($stepConfig['description']); ?></p>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <p class="<?php echo $candidate['status'] === 'COMPLETED' ? 'success-text' : 'error-text'; ?>">
                        Recruitment process is <?php echo strtolower($candidate['status']); ?>.
                    </p>
                <?php endif; ?>
            </div>

            <!-- Basic Information -->
            <div class="content-card">
                <h2>📋 Basic Information</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Candidate ID</label>
                        <div><?php echo $candidateId; ?></div>
                    </div>
                    <div class="info-item">
                        <label>Email</label>
                        <div><?php echo htmlspecialchars($candidate['email']); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Phone</label>
                        <div><?php echo $candidate['phone']; ?></div>
                    </div>
                    <div class="info-item">
                        <label>Position</label>
                        <div><?php echo htmlspecialchars($candidate['position']); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Location</label>
                        <div><?php echo htmlspecialchars($candidate['location'] ?? '-'); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Current Step</label>
                        <div><?php echo $currentStep . ' - ' . htmlspecialchars($stepConfig['name'] ?? ''); ?></div>
                    </div>
                </div>
            </div>

            <!-- Documents Section -->
            <div class="content-card">
                <h2>📄 Documents</h2>
                <div class="docs-grid">
                    <div>
                        <h3>Resume</h3>
                        <?php if (!empty($candidate['resume'])): ?>
                            <a href="../uploads/resumes/<?php echo $candidate['resume']; ?>" target="_blank" class="btn btn-secondary">
                                📄 View Resume
                            </a>
                        <?php else: ?>
                            <p class="no-data">No resume uploaded</p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3>Additional Documents</h3>
                        <?php if (!empty($candidate['documents'])): ?>
                            <ul class="docs-list">
                                <?php foreach ($candidate['documents'] as $index => $doc): ?>
                                    <li>
                                        <a href="../uploads/documents/<?php echo $doc; ?>" target="_blank">
                                            📎 <?php echo basename($doc); ?>
                                        </a>
                                        <a href="index.php?page=candidate_details&id=<?php echo $candidateId; ?>&delete_doc=<?php echo $index; ?>" 
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Delete this document?')">Delete</a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <p class="no-data">No additional documents</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="upload-section">
                    <h3>Upload Document</h3>
                    <form method="post" enctype="multipart/form-data" class="upload-form">
                        <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required>
                        <button type="submit" name="upload_document" class="btn btn-primary">📤 Upload</button>
                    </form>
                    <p class="help-text">Allowed: PDF, JPG, PNG (max 5MB each)</p>
                </div>
            </div>

            <!-- Call Log Section -->
            <div class="content-card">
                <h2>📞 Call History</h2>
                <?php if (!empty($candidate['call_logs'])): ?>
                    <div class="call-logs">
                        <?php foreach ($candidate['call_logs'] as $log): ?>
                            <div class="call-log-entry <?php echo $log['result'] === 'interested' ? 'interested' : ($log['result'] === 'not_pick' ? 'not-pick' : 'not-interested'); ?>">
                                <div class="call-log-header">
                                    <strong><?php 
                                        if ($log['result'] === 'interested') echo '✓ Interested';
                                        elseif ($log['result'] === 'not_pick') echo '📵 Not Pick';
                                        else echo '✗ Not Interested';
                                    ?></strong>
                                    <span><?php echo date('d M Y, h:i A', strtotime($log['timestamp'])); ?></span>
                                </div>
                                <?php if (!empty($log['remarks'])): ?>
                                    <div class="call-remarks">"<?php echo htmlspecialchars($log['remarks']); ?>"</div>
                                <?php endif; ?>
                                <div class="call-by">Called by: <?php echo htmlspecialchars($log['called_by'] ?? 'Unknown'); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="no-data">No call logs recorded yet</p>
                <?php endif; ?>
                
                <div class="log-call-section">
                    <h3>Log New Call</h3>
                    <form method="post">
                        <div class="form-group">
                            <label>Call Result *</label>
                            <div class="radio-group">
                                <label><input type="radio" name="result" value="interested" required> ✓ Interested</label>
                                <label><input type="radio" name="result" value="not_interested"> ✗ Not Interested</label>
                                <label><input type="radio" name="result" value="not_pick"> 📵 Not Pick</label>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>Remarks</label>
                            <textarea name="remarks" rows="3" placeholder="Enter call notes..."></textarea>
                        </div>
                        <button type="submit" name="log_call" class="btn btn-success">💾 Save Call Log</button>
                    </form>
                </div>
            </div>

            <!-- Interview History -->
            <?php if (!empty($candidate['interviews'])): ?>
            <div class="content-card">
                <h2>📅 Interview History</h2>
                <?php foreach ($candidate['interviews'] as $round => $interview): ?>
                    <div class="interview-card">
                        <h4><?php echo ucfirst($round); ?> Interview</h4>
                        <div class="interview-grid">
                            <div><label>Date</label><div><?php echo $interview['date']; ?></div></div>
                            <div><label>Time</label><div><?php echo $interview['time']; ?></div></div>
                            <div><label>Mode</label><div><?php echo $interview['mode']; ?></div></div>
                            <div><label>Interviewer</label><div><?php echo $interview['interviewer']; ?></div></div>
                            <?php if (isset($interview['result'])): ?>
                            <div><label>Result</label><div><?php echo ucfirst($interview['result']); ?></div></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Current Step Action Section -->
            <?php if ($candidate['status'] === 'IN_PROGRESS' && $stepConfig): ?>
            <div class="content-card action-card">
                <h2>🎯 <?php echo htmlspecialchars($stepConfig['name']); ?></h2>
                <?php if ($showStepDescription && !empty($stepConfig['description'])): ?>
                    <p><?php echo htmlspecialchars($stepConfig['description']); ?></p>
                <?php endif; ?>
                
                <?php if ($stepConfig['has_form']): ?>
                    <?php echo getStepFormHtml($currentStep, $candidate); ?>
                <?php else: ?>
                    <form method="post">
                        <input type="hidden" name="step" value="<?php echo $nextStep ?? ($currentStep + 1); ?>">
                        <button type="submit" name="move_step" class="btn btn-primary">✅ Proceed to Next Step</button>
                    </form>
                <?php endif; ?>
                
                <!-- Cancel button if allowed -->
                <?php if ($stepConfig['can_cancel'] ?? false): ?>
                    <form method="post" style="margin-top: 15px;">
                        <input type="hidden" name="step" value="<?php echo $currentStep; ?>">
                        <button type="submit" name="cancel_candidate" class="btn btn-danger" onclick="return confirm('Are you sure you want to cancel this candidate?');">❌ Cancel Candidate</button>
                    </form>
                <?php endif; ?>
            </div>
            <?php elseif ($candidate['status'] === 'COMPLETED'): ?>
            <div class="content-card">
                <h2>🎉 Recruitment Complete</h2>
                <p class="success-text">Congratulations! This candidate has successfully completed the recruitment process.</p>
            </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loader"></div>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebarOverlay').classList.toggle('open');
        }

        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                document.getElementById('loadingOverlay').style.display = 'flex';
            });
        });
    </script>
    
    <style>
        /* Mobile-friendly styles */
        .info-grid, .docs-grid, .interview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 15px;
        }
        
        .info-item label, .interview-grid label {
            display: block;
            font-size: 0.75em;
            color: #7f8c8d;
            margin-bottom: 3px;
        }
        
        .info-item div, .interview-grid > div {
            font-size: 0.95em;
            font-weight: 500;
        }
        
        .docs-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .docs-list li {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .docs-list a:first-child {
            color: #3498db;
            text-decoration: none;
            word-break: break-all;
        }
        
        .upload-form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: center;
        }
        
        .upload-form input[type="file"] {
            flex: 1;
            min-width: 150px;
        }
        
        .help-text {
            font-size: 0.8em;
            color: #7f8c8d;
            margin-top: 8px;
        }
        
        .no-data {
            color: #999;
            font-style: italic;
        }
        
        .call-logs {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .call-log-entry {
            padding: 12px;
            border-radius: 6px;
            border-left: 4px solid;
        }
        
        .call-log-entry.interested {
            background: #e8f6ef;
            border-color: #27ae60;
        }
        
        .call-log-entry.not-interested {
            background: #fdf0ed;
            border-color: #e74c3c;
        }
        
        .call-log-entry.not-pick {
            background: #fef9e7;
            border-color: #f39c12;
        }
        
        .call-log-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
            flex-wrap: wrap;
            gap: 5px;
        }
        
        .call-log-header strong {
            font-size: 0.9em;
        }
        
        .call-log-header span {
            font-size: 0.75em;
            color: #7f8c8d;
        }
        
        .call-remarks {
            font-size: 0.85em;
            color: #555;
            margin-bottom: 4px;
        }
        
        .call-by {
            font-size: 0.7em;
            color: #95a5a6;
        }
        
        .radio-group {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .radio-group label {
            display: flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
        }
        
        .interview-card {
            padding: 12px;
            background: #f8f9fa;
            border-radius: 6px;
            margin-bottom: 12px;
        }
        
        .interview-card h4 {
            margin: 0 0 12px 0;
            color: #667eea;
            font-size: 0.95em;
        }
        
        .action-card {
            border-left: 4px solid #667eea;
        }
        
        .success-text {
            color: #27ae60;
            font-weight: 600;
            font-size: 1.1em;
        }
        
        .error-text {
            color: #e74c3c;
            font-weight: 600;
            font-size: 1.1em;
        }
        
        .log-call-section, .upload-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .log-call-section h3, .upload-section h3 {
            font-size: 1em;
            margin-bottom: 15px;
        }
        
        .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        /* Workflow styles */
        .workflow-steps {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 20px;
        }
        
        .workflow-step {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 15px;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 3px solid #ddd;
        }
        
        .workflow-step.completed {
            background: #e8f6ef;
            border-left-color: #27ae60;
        }
        
        .workflow-step.current {
            background: #eef2ff;
            border-left-color: #667eea;
        }
        
        .step-indicator {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: #ddd;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.85em;
        }
        
        .workflow-step.completed .step-indicator {
            background: #27ae60;
        }
        
        .workflow-step.current .step-indicator {
            background: #667eea;
        }
        
        .step-info {
            flex: 1;
        }
        
        .step-name {
            font-weight: 500;
            color: #2c3e50;
        }
        
        .step-desc {
            font-size: 0.8em;
            color: #7f8c8d;
        }
        
        .current-badge {
            font-size: 0.7em;
            padding: 2px 8px;
            background: #667eea;
            color: #fff;
            border-radius: 10px;
        }
        
        .current-step-info {
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
        }
        
        .current-step-info h3 {
            margin: 0 0 8px 0;
            color: #667eea;
        }
        
        .current-step-info p {
            margin: 0;
            color: #7f8c8d;
        }
        
        @media (max-width: 480px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            
            .page-header h1 {
                font-size: 1.3em;
            }
            
            .header-actions {
                width: 100%;
            }
            
            .info-grid, .docs-grid, .interview-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .radio-group {
                flex-direction: column;
                gap: 10px;
            }
            
            .upload-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .upload-form input[type="file"] {
                width: 100%;
            }
            
            .docs-list li {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .docs-list li .btn {
                align-self: flex-start;
            }
        }
    </style>
</body>
</html>

