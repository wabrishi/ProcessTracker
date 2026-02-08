<?php
include_once __DIR__ . '/../includes/candidate.php';
include_once __DIR__ . '/../includes/helpers.php';

$message = '';
$currentUserRole = $_SESSION['role'] ?? '';
$currentUserId = $_SESSION['user_id'] ?? null;
$currentUserName = $_SESSION['user_name'] ?? 'User';

// Determine back URL based on role
$backUrl = ($currentUserRole === 'ADMIN') ? 'index.php?page=admin&menu=candidates' : 'index.php?page=hr&menu=candidates';

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

// Get mail logs for this candidate
$mailLogs = getCandidateMailLogs($candidateId);

// Get call logs
$callLogs = $candidate['call_logs'] ?? [];
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
                <div class="<?php echo strpos($message, 'successfully') !== false || strpos($message, 'cancelled') !== false || strpos($message, 'deleted') !== false ? 'message' : 'error'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- Quick Actions Panel -->
            <div class="quick-actions-panel">
                <h3>⚡ Quick Actions</h3>
                <div class="action-buttons">
                    <a href="index.php?page=hr&menu=create&edit=<?php echo $candidateId; ?>" class="action-btn">
                        <span class="action-icon">✏️</span>
                        <span>Edit Profile</span>
                    </a>
                    <a href="index.php?page=hr&menu=send_mail&id=<?php echo $candidateId; ?>" class="action-btn">
                        <span class="action-icon">📧</span>
                        <span>Send Email</span>
                    </a>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to cancel this candidate?');">
                        <button type="submit" name="cancel_candidate" class="action-btn action-danger">
                            <span class="action-icon">❌</span>
                            <span>Cancel Candidate</span>
                        </button>
                    </form>
                </div>
            </div>

            <div class="details-grid">
                <!-- Left Column -->
                <div class="details-column">
                    <!-- Profile Card -->
                    <div class="content-card profile-card">
                        <h2>📋 Profile Information</h2>
                        <div class="info-grid">
                            <div class="info-item">
                                <label>Candidate ID</label>
                                <div class="info-value"><?php echo $candidateId; ?></div>
                            </div>
                            <div class="info-item">
                                <label>Full Name</label>
                                <div class="info-value"><?php echo htmlspecialchars($candidate['name']); ?></div>
                            </div>
                            <div class="info-item">
                                <label>Email Address</label>
                                <div class="info-value"><?php echo htmlspecialchars($candidate['email']); ?></div>
                            </div>
                            <div class="info-item">
                                <label>Phone Number</label>
                                <div class="info-value"><?php echo $candidate['phone']; ?></div>
                            </div>
                            <div class="info-item">
                                <label>Position</label>
                                <div class="info-value"><?php echo htmlspecialchars($candidate['position']); ?></div>
                            </div>
                            <div class="info-item">
                                <label>Location</label>
                                <div class="info-value"><?php echo htmlspecialchars($candidate['location'] ?? '-'); ?></div>
                            </div>
                            <div class="info-item">
                                <label>Status</label>
                                <div class="info-value">
                                    <span class="status-badge status-<?php echo strtolower($candidate['status']); ?>">
                                        <?php echo $candidate['status']; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="info-item">
                                <label>Created Date</label>
                                <div class="info-value"><?php echo date('d M Y', strtotime($candidate['created_at'] ?? 'now')); ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Call Log Section -->
                    <div class="content-card">
                        <h2>📞 Call History</h2>
                        <?php if (!empty($callLogs)): ?>
                            <div class="logs-list call-logs-list">
                                <?php foreach ($callLogs as $log): ?>
                                    <div class="log-entry <?php echo $log['result'] ?? ''; ?>">
                                        <div class="log-header">
                                            <span class="log-icon">
                                                <?php 
                                                if (($log['result'] ?? '') === 'interested') echo '✓';
                                                elseif (($log['result'] ?? '') === 'not_pick') echo '📵';
                                                else echo '✗';
                                                ?>
                                            </span>
                                            <span class="log-title">
                                                <?php 
                                                if (($log['result'] ?? '') === 'interested') echo 'Interested';
                                                elseif (($log['result'] ?? '') === 'not_pick') echo 'Not Pick';
                                                else echo 'Not Interested';
                                                ?>
                                            </span>
                                            <span class="log-date"><?php echo date('d M, h:i A', strtotime($log['timestamp'])); ?></span>
                                        </div>
                                        <?php if (!empty($log['remarks'])): ?>
                                            <div class="log-body">"<?php echo htmlspecialchars($log['remarks']); ?>"</div>
                                        <?php endif; ?>
                                        <div class="log-footer">By: <?php echo htmlspecialchars($log['called_by'] ?? 'Unknown'); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="no-data">No call logs recorded yet</p>
                        <?php endif; ?>
                        
                        <div class="log-form-section">
                            <h3>📝 Log New Call</h3>
                            <form method="post">
                                <div class="form-group">
                                    <label>Call Result *</label>
                                    <div class="radio-group">
                                        <label class="radio-label interested">
                                            <input type="radio" name="result" value="interested" required> 
                                            <span>✓ Interested</span>
                                        </label>
                                        <label class="radio-label not-interested">
                                            <input type="radio" name="result" value="not_interested"> 
                                            <span>✗ Not Interested</span>
                                        </label>
                                        <label class="radio-label not-pick">
                                            <input type="radio" name="result" value="not_pick"> 
                                            <span>📵 Not Pick</span>
                                        </label>
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
                </div>

                <!-- Right Column -->
                <div class="details-column">
                    <!-- Documents Section -->
                    <div class="content-card">
                        <h2>📄 Documents</h2>
                        
                        <!-- Resume -->
                        <div class="doc-section">
                            <h3>📃 Resume</h3>
                            <?php if (!empty($candidate['resume'])): ?>
                                <a href="../uploads/resumes/<?php echo $candidate['resume']; ?>" target="_blank" class="btn btn-primary">
                                    📄 View Resume
                                </a>
                            <?php else: ?>
                                <p class="no-data">No resume uploaded</p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Additional Documents -->
                        <div class="doc-section">
                            <h3>📎 Additional Documents</h3>
                            <?php if (!empty($candidate['documents'])): ?>
                                <ul class="docs-list">
                                    <?php foreach ($candidate['documents'] as $index => $doc): ?>
                                        <li>
                                            <a href="../uploads/documents/<?php echo $doc; ?>" target="_blank" class="doc-link">
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
                        
                        <!-- Upload Form -->
                        <div class="upload-section">
                            <h3>📤 Upload Document</h3>
                            <form method="post" enctype="multipart/form-data" class="upload-form">
                                <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png" required>
                                <button type="submit" name="upload_document" class="btn btn-primary">Upload</button>
                            </form>
                            <p class="help-text">Allowed: PDF, JPG, PNG (max 5MB each)</p>
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

                    <!-- Mail Logs Section -->
                    <div class="content-card">
                        <h2>📧 Email History</h2>
                        <?php if (!empty($mailLogs)): ?>
                            <div class="logs-list mail-logs-list">
                                <?php foreach ($mailLogs as $log): ?>
                                    <div class="log-entry mail-log-entry">
                                        <div class="log-header">
                                            <span class="log-icon">📧</span>
                                            <span class="log-title">
                                                <?php 
                                                $templateName = $log['template_name'] ?? 'Unknown';
                                                echo ucwords(str_replace('_', ' ', $templateName));
                                                ?>
                                            </span>
                                            <span class="log-status status-<?php echo strtolower($log['status'] ?? ''); ?>">
                                                <?php echo $log['status'] ?? 'UNKNOWN'; ?>
                                            </span>
                                        </div>
                                        <div class="log-subject"><?php echo htmlspecialchars($log['subject'] ?? ''); ?></div>
                                        <div class="log-meta">
                                            <span class="log-date"><?php echo date('d M Y, h:i A', strtotime($log['sent_at'] ?? 'now')); ?></span>
                                            <?php if (!empty($log['sent_by_name'])): ?>
                                                <span class="log-sender">by <?php echo htmlspecialchars($log['sent_by_name']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($log['error_message'])): ?>
                                            <div class="log-error">Error: <?php echo htmlspecialchars($log['error_message']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <p class="no-data">No emails sent to this candidate yet</p>
                        <?php endif; ?>
                        
                        <div class="send-mail-cta">
                            <a href="index.php?page=hr&menu=send_mail&id=<?php echo $candidateId; ?>" class="btn btn-secondary">
                                📧 Send New Email
                            </a>
                        </div>
                    </div>
                </div>
            </div>
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
        /* Quick Actions Panel */
        .quick-actions-panel {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            color: #fff;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .quick-actions-panel h3 {
            margin: 0 0 15px 0;
            color: #fff;
            font-size: 1.1em;
        }
        
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .action-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            background: rgba(255, 255, 255, 0.2);
            color: #fff;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9em;
            font-weight: 500;
            transition: all 0.2s ease;
            border: 1px solid rgba(255, 255, 255, 0.3);
            cursor: pointer;
        }
        
        .action-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }
        
        .action-danger {
            background: rgba(231, 76, 60, 0.3);
            border-color: rgba(231, 76, 60, 0.5);
        }
        
        .action-danger:hover {
            background: rgba(231, 76, 60, 0.5);
        }
        
        .action-icon {
            font-size: 1.1em;
        }
        
        /* Two Column Grid Layout */
        .details-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
        }
        
        @media (max-width: 992px) {
            .details-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .details-column {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }
        
        /* Profile Card Styles */
        .profile-card {
            background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        @media (max-width: 576px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .info-item label {
            display: block;
            font-size: 0.75em;
            color: #7f8c8d;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 0.95em;
            font-weight: 500;
            color: #2c3e50;
            word-break: break-word;
        }
        
        /* Logs List Styles */
        .logs-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .log-entry {
            padding: 15px;
            border-radius: 8px;
            background: #f8f9fa;
            border-left: 4px solid #ddd;
            transition: all 0.2s ease;
        }
        
        .log-entry:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .log-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }
        
        .log-icon {
            font-size: 1.2em;
        }
        
        .log-title {
            font-weight: 600;
            color: #2c3e50;
            flex: 1;
        }
        
        .log-date {
            font-size: 0.8em;
            color: #7f8c8d;
        }
        
        .log-body {
            font-size: 0.9em;
            color: #555;
            font-style: italic;
            margin-bottom: 8px;
            padding-left: 25px;
        }
        
        .log-footer {
            font-size: 0.75em;
            color: #95a5a6;
            padding-left: 25px;
        }
        
        /* Call Log Specific Styles */
        .call-logs-list .log-entry.interested {
            background: #e8f6ef;
            border-left-color: #27ae60;
        }
        
        .call-logs-list .log-entry.not-interested,
        .call-logs-list .log-entry.rejected {
            background: #fdf0ed;
            border-left-color: #e74c3c;
        }
        
        .call-logs-list .log-entry.not_pick {
            background: #fef9e7;
            border-left-color: #f39c12;
        }
        
        /* Mail Log Specific Styles */
        .mail-log-entry {
            background: #eef2ff;
            border-left-color: #667eea;
        }
        
        .log-subject {
            font-size: 0.85em;
            color: #555;
            margin-bottom: 6px;
            padding-left: 28px;
            word-break: break-word;
        }
        
        .log-meta {
            display: flex;
            gap: 15px;
            font-size: 0.75em;
            color: #7f8c8d;
            padding-left: 28px;
            flex-wrap: wrap;
        }
        
        .log-sender {
            color: #667eea;
        }
        
        .log-error {
            margin-top: 8px;
            padding: 8px 12px;
            background: #fdf0ed;
            border-radius: 4px;
            color: #e74c3c;
            font-size: 0.8em;
            margin-left: 28px;
        }
        
        .log-status {
            font-size: 0.7em;
            padding: 3px 8px;
            border-radius: 12px;
            font-weight: 600;
        }
        
        .log-status.status-sent {
            background: #d4edda;
            color: #155724;
        }
        
        .log-status.status-failed {
            background: #f8d7da;
            color: #721c24;
        }
        
        .log-status.status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        /* Radio Group Styles */
        .radio-group {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .radio-label {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 15px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 2px solid transparent;
        }
        
        .radio-label:hover {
            background: #f8f9fa;
        }
        
        .radio-label input[type="radio"] {
            margin: 0;
        }
        
        .radio-label.interested {
            background: #e8f6ef;
            color: #27ae60;
        }
        
        .radio-label.not-interested {
            background: #fdf0ed;
            color: #e74c3c;
        }
        
        .radio-label.not-pick {
            background: #fef9e7;
            color: #f39c12;
        }
        
        .radio-label input:checked + span {
            font-weight: 600;
        }
        
        /* Document Section Styles */
        .doc-section {
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .doc-section:last-of-type {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .doc-section h3 {
            font-size: 0.95em;
            margin-bottom: 12px;
            color: #667eea;
        }
        
        .docs-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .docs-list li {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .docs-list li:last-child {
            border-bottom: none;
        }
        
        .doc-link {
            color: #3498db;
            text-decoration: none;
            flex: 1;
            word-break: break-all;
            min-width: 150px;
        }
        
        .doc-link:hover {
            text-decoration: underline;
        }
        
        /* Upload Form Styles */
        .upload-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .upload-section h3 {
            font-size: 0.95em;
            margin-bottom: 12px;
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
            width: 100%;
        }
        
        /* Interview Card Styles */
        .interview-card {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .interview-card:last-child {
            margin-bottom: 0;
        }
        
        .interview-card h4 {
            margin: 0 0 12px 0;
            color: #667eea;
            font-size: 0.95em;
        }
        
        .interview-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }
        
        .interview-grid > div label {
            display: block;
            font-size: 0.7em;
            color: #7f8c8d;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        
        .interview-grid > div {
            font-size: 0.9em;
        }
        
        /* Log Form Section */
        .log-form-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .log-form-section h3 {
            font-size: 1em;
            margin-bottom: 15px;
        }
        
        /* Send Mail CTA */
        .send-mail-cta {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
        }
        
        /* No Data Message */
        .no-data {
            color: #999;
            font-style: italic;
            text-align: center;
            padding: 20px;
        }
        
        /* Status Badge Override */
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75em;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        /* Mobile Responsive */
        @media (max-width: 480px) {
            .quick-actions-panel {
                padding: 15px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-btn {
                justify-content: center;
            }
            
            .radio-group {
                flex-direction: column;
                gap: 10px;
            }
            
            .radio-label {
                justify-content: center;
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
            
            .log-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
            
            .log-subject,
            .log-meta,
            .log-body,
            .log-footer {
                padding-left: 0;
            }
            
            .log-meta {
                flex-direction: column;
                gap: 5px;
            }
        }
        
        /* Content Card Override */
        .content-card {
            background: #fff;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            border: 1px solid #e1e5e9;
        }
        
        .content-card h2 {
            margin-top: 0;
            margin-bottom: 20px;
            color: #2c3e50;
            padding-bottom: 12px;
            border-bottom: 2px solid #f0f2f5;
            font-size: 1.2em;
        }
        
        .content-card h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #2c3e50;
            font-size: 1em;
        }
        
        /* Success/Error Text */
        .success-text {
            color: #27ae60;
            font-weight: 600;
        }
        
        .error-text {
            color: #e74c3c;
            font-weight: 600;
        }
    </style>
</body>
</html>

