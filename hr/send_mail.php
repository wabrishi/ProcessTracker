<?php
// HR Send Mail Page
// Allows HR to send custom emails to candidates

include_once __DIR__ . '/../includes/helpers.php';
include_once __DIR__ . '/../includes/mail_sender.php';

$message = '';
$messageType = ''; // 'success' or 'error'
$previewHtml = '';

$currentUserId = $_SESSION['user_id'] ?? null;
$currentUserName = $_SESSION['user_name'] ?? 'HR User';

// Get candidates assigned to current HR
$candidates = getCandidatesForMail($currentUserId);
$templates = getAvailableTemplates();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'preview') {
        // Generate preview
        $candidateId = $_POST['candidate_id'] ?? '';
        $templateName = $_POST['template_name'] ?? '';
        
        if (empty($candidateId)) {
            $message = 'Please select a candidate';
            $messageType = 'error';
        } elseif (empty($templateName)) {
            $message = 'Please select a template';
            $messageType = 'error';
        } else {
            // Get candidate data
            $candidate = $candidates[$candidateId] ?? null;
            if (!$candidate) {
                $message = 'Candidate not found';
                $messageType = 'error';
            } else {
                // Get placeholders from form
                $placeholders = getPlaceholdersFromForm();
                
                // Generate preview
                $replacements = [
                    'name' => $candidate['name'],
                    'position' => $candidate['position']
                ] + $placeholders;
                
                $subject = generateEmailSubject($templateName, $replacements);
                $previewHtml = parseTemplate($templateName, $replacements);
                
                // Store in session for sending
                $_SESSION['mail_preview'] = [
                    'candidate_id' => $candidateId,
                    'template_name' => $templateName,
                    'placeholders' => $placeholders,
                    'subject' => $subject
                ];
                
                $message = 'Preview generated. Review and click Send to send the email.';
                $messageType = 'success';
            }
        }
    } elseif ($action === 'send') {
        // Send the email
        $previewData = $_SESSION['mail_preview'] ?? null;
        
        if (!$previewData) {
            $message = 'Please generate a preview first';
            $messageType = 'error';
        } else {
            $result = sendCustomMail(
                $previewData['candidate_id'],
                $previewData['template_name'],
                $previewData['placeholders'],
                $previewData['subject']
            );
            
            if ($result['success']) {
                $message = '✅ Email sent successfully!';
                $messageType = 'success';
                unset($_SESSION['mail_preview']);
                $previewHtml = '';
            } else {
                $message = '❌ Failed to send email: ' . $result['message'];
                $messageType = 'error';
            }
        }
    }
}

// Get selected candidate for form repopulation
$selectedCandidateId = $_POST['candidate_id'] ?? ($_GET['candidate_id'] ?? '');
$selectedTemplate = $_POST['template_name'] ?? '';
$selectedCandidate = $selectedCandidateId && isset($candidates[$selectedCandidateId]) ? $candidates[$selectedCandidateId] : null;

?>
<style>
    .mail-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    
    @media (max-width: 992px) {
        .mail-container {
            grid-template-columns: 1fr;
        }
    }
    
    .preview-panel {
        background: #fff;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 15px rgba(0,0,0,0.08);
        position: sticky;
        top: 20px;
        max-height: calc(100vh - 180px);
        overflow-y: auto;
    }
    
    .preview-panel h3 {
        margin-top: 0;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
        color: #2c3e50;
    }
    
    .preview-content {
        font-family: Arial, sans-serif;
        line-height: 1.6;
        color: #333;
    }
    
    .preview-content h1 {
        color: #667eea;
        font-size: 1.5em;
        text-align: center;
        margin: 20px 0;
    }
    
    .preview-content p {
        margin: 10px 0;
    }
    
    .placeholder-preview {
        background: #f8f9fa;
        padding: 2px 6px;
        border-radius: 4px;
        border: 1px dashed #667eea;
        color: #667eea;
        font-weight: 500;
    }
    
    .placeholder-filled {
        background: #e8f5e9;
        padding: 2px 6px;
        border-radius: 4px;
        color: #2e7d32;
        font-weight: 500;
    }
    
    .send-actions {
        display: flex;
        gap: 10px;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #eee;
    }
    
    .email-meta {
        background: #f0f4ff;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    
    .email-meta-item {
        display: flex;
        margin: 5px 0;
    }
    
    .email-meta-label {
        width: 80px;
        font-weight: 600;
        color: #667eea;
    }
    
    .email-meta-value {
        color: #333;
    }
    
    .candidate-info-card {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        border-left: 4px solid #667eea;
    }
    
    .candidate-info-card h4 {
        margin: 0 0 10px 0;
        color: #2c3e50;
    }
    
    .candidate-info-card p {
        margin: 5px 0;
        font-size: 0.9em;
        color: #666;
    }
    
    .form-section {
        margin-top: 25px;
        padding-top: 20px;
        border-top: 1px solid #eee;
    }
    
    .form-section h4 {
        margin-bottom: 15px;
        color: #2c3e50;
    }
</style>

<div class="page-header">
    <h1>📧 Send Email to Candidate</h1>
</div>

<?php if ($message): ?>
    <div class="message <?php echo $messageType; ?>">
        <?php echo htmlspecialchars($message); ?>
    </div>
<?php endif; ?>

<div class="mail-container">
    <!-- Left Panel: Form -->
    <div class="form-panel">
        <div class="content-card">
            <h3>📝 Email Details</h3>
            
            <form method="post" id="mailForm">
                <!-- Candidate Selection -->
                <div class="form-group">
                    <label>Select Candidate <span class="required">*</span></label>
                    <select name="candidate_id" id="candidateSelect" required onchange="this.form.submit()">
                        <option value="">-- Select Candidate --</option>
                        <?php foreach ($candidates as $id => $cand): ?>
                            <option value="<?php echo $id; ?>" <?php echo ($selectedCandidateId === $id) ? 'selected' : ''; ?>>
                                <?php echo $id . ' - ' . htmlspecialchars($cand['name']); ?>
                                (<?php echo htmlspecialchars($cand['email']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if ($selectedCandidate): ?>
                    <div class="candidate-info-card">
                        <h4><?php echo htmlspecialchars($selectedCandidate['name']); ?></h4>
                        <p><strong>Email:</strong> <?php echo htmlspecialchars($selectedCandidate['email']); ?></p>
                        <p><strong>Position:</strong> <?php echo htmlspecialchars($selectedCandidate['position']); ?></p>
                        <p><strong>Current Step:</strong> <?php echo $selectedCandidate['current_step'] ?? 1; ?> - <?php echo STEPS[$selectedCandidate['current_step'] ?? 1] ?? ''; ?></p>
                        <p><strong>Status:</strong> <span class="status-badge status-<?php echo strtolower($selectedCandidate['status'] ?? 'in_progress'); ?>"><?php echo $selectedCandidate['status'] ?? 'IN_PROGRESS'; ?></span></p>
                    </div>
                <?php endif; ?>
                
                <!-- Template Selection -->
                <div class="form-group">
                    <label>Select Template <span class="required">*</span></label>
                    <select name="template_name" id="templateSelect" required onchange="this.form.submit()">
                        <option value="">-- Select Template --</option>
                        <?php foreach ($templates as $tpl): ?>
                            <option value="<?php echo $tpl; ?>" <?php echo ($selectedTemplate === $tpl) ? 'selected' : ''; ?>>
                                <?php echo getTemplateDisplayName($tpl); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Placeholder Fields -->
                <?php if ($selectedCandidateId && $selectedTemplate): ?>
                    <div class="form-section">
                        <h4>📋 Fill Placeholder Values</h4>
                        <?php echo buildPlaceholderForm($selectedTemplate, $selectedCandidate); ?>
                    </div>
                <?php endif; ?>
                
                <!-- Actions -->
                <div class="form-actions" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
                    <button type="submit" name="action" value="preview" class="btn btn-secondary">
                        👁️ Generate Preview
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Right Panel: Preview -->
    <div class="preview-panel">
        <?php if ($previewHtml): ?>
            <h3>👁️ Email Preview</h3>
            
            <div class="email-meta">
                <div class="email-meta-item">
                    <span class="email-meta-label">To:</span>
                    <span class="email-meta-value"><?php echo htmlspecialchars($selectedCandidate['email'] ?? ''); ?></span>
                </div>
                <div class="email-meta-item">
                    <span class="email-meta-label">Subject:</span>
                    <span class="email-meta-value"><?php echo htmlspecialchars($_SESSION['mail_preview']['subject'] ?? ''); ?></span>
                </div>
            </div>
            
            <div class="preview-content">
                <?php echo $previewHtml; ?>
            </div>
            
            <div class="send-actions">
                <form method="post" style="flex: 1;">
                    <button type="submit" name="action" value="send" class="btn btn-success" style="width: 100%;" onclick="return confirm('Are you sure you want to send this email?');">
                        📧 Send Email
                    </button>
                </form>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 40px 20px; color: #999;">
                <div style="font-size: 3em; margin-bottom: 15px;">📧</div>
                <p>Select a candidate and template, fill in the placeholder values, then click "Generate Preview" to see the email content.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    // Form submission handler
    document.getElementById('mailForm').addEventListener('submit', function(e) {
        const action = e.submitter?.value;
        if (action === 'preview' || action === 'send') {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }
    });
</script>

