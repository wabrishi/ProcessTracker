<?php
// Workflow Manager - Content Only (to be embedded in dashboard)
include_once __DIR__ . '/../includes/helpers.php';
include_once __DIR__ . '/../includes/workflow.php';

$message = '';
$currentUserRole = $_SESSION['role'] ?? '';
$currentUserId = $_SESSION['user_id'] ?? null;

if ($currentUserRole !== 'ADMIN') {
    header('Location: index.php?page=hr&menu=dashboard');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Add new step
    if (isset($_POST['add_step'])) {
        $stepData = [
            'name' => $_POST['name'] ?? 'New Step',
            'description' => $_POST['description'] ?? '',
            'send_email' => isset($_POST['send_email']),
            'email_template' => $_POST['email_template'] ?? null,
            'email_subject' => $_POST['email_subject'] ?? '',
            'has_form' => true,
            'form_type' => $_POST['form_type'] ?? 'default',
            'auto_advance' => isset($_POST['auto_advance']),
            'can_cancel' => isset($_POST['can_cancel']),
            'cancel_status' => $_POST['cancel_status'] ?? 'CANCELLED',
            'form_fields' => []
        ];
        
        // Add form fields
        $fieldNames = $_POST['field_name'] ?? [];
        $fieldLabels = $_POST['field_label'] ?? [];
        $fieldTypes = $_POST['field_type'] ?? [];
        $fieldRequired = $_POST['field_required'] ?? [];
        $fieldOptions = $_POST['field_options'] ?? [];
        $fieldDefaults = $_POST['field_default'] ?? [];
        
        foreach ($fieldNames as $index => $fieldName) {
            if (!empty($fieldName)) {
                $field = [
                    'name' => $fieldName,
                    'label' => $fieldLabels[$index] ?? $fieldName,
                    'type' => $fieldTypes[$index] ?? 'text',
                    'required' => in_array($index, $fieldRequired),
                    'options' => []
                ];
                
                // Handle select options
                if ($field['type'] === 'select' || $field['type'] === 'radio') {
                    $options = explode("\n", $fieldOptions[$index] ?? '');
                    foreach ($options as $option) {
                        $option = trim($option);
                        if (!empty($option)) {
                            $field['options'][] = ['value' => strtolower(str_replace(' ', '_', $option)), 'label' => $option];
                        }
                    }
                }
                
                // Handle default value
                if (!empty($fieldDefaults[$index])) {
                    $field['default'] = $fieldDefaults[$index];
                }
                
                $stepData['form_fields'][] = $field;
            }
        }
        
        if (addWorkflowStep($stepData)) {
            $message = 'Step added successfully';
        } else {
            $message = 'Failed to add step';
        }
    }
    
    // Update step
    if (isset($_POST['update_step'])) {
        $stepNumber = (int)$_POST['step_number'];
        $stepData = [
            'name' => $_POST['name'] ?? 'Step ' . $stepNumber,
            'description' => $_POST['description'] ?? '',
            'send_email' => isset($_POST['send_email']),
            'email_template' => $_POST['email_template'] ?? null,
            'email_subject' => $_POST['email_subject'] ?? '',
            'auto_advance' => isset($_POST['auto_advance']),
            'can_cancel' => isset($_POST['can_cancel']),
            'cancel_status' => $_POST['cancel_status'] ?? 'CANCELLED'
        ];
        
        // Update form fields
        $fieldNames = $_POST['field_name'] ?? [];
        $fieldLabels = $_POST['field_label'] ?? [];
        $fieldTypes = $_POST['field_type'] ?? [];
        $fieldRequired = $_POST['field_required'] ?? [];
        $fieldOptions = $_POST['field_options'] ?? [];
        $fieldDefaults = $_POST['field_default'] ?? [];
        
        $formFields = [];
        foreach ($fieldNames as $index => $fieldName) {
            if (!empty($fieldName)) {
                $field = [
                    'name' => $fieldName,
                    'label' => $fieldLabels[$index] ?? $fieldName,
                    'type' => $fieldTypes[$index] ?? 'text',
                    'required' => in_array($index, $fieldRequired)
                ];
                
                if ($field['type'] === 'select' || $field['type'] === 'radio') {
                    $options = explode("\n", $fieldOptions[$index] ?? '');
                    foreach ($options as $option) {
                        $option = trim($option);
                        if (!empty($option)) {
                            $field['options'][] = ['value' => strtolower(str_replace(' ', '_', $option)), 'label' => $option];
                        }
                    }
                }
                
                if (!empty($fieldDefaults[$index])) {
                    $field['default'] = $fieldDefaults[$index];
                }
                
                $formFields[] = $field;
            }
        }
        $stepData['form_fields'] = $formFields;
        
        if (updateWorkflowStep($stepNumber, $stepData)) {
            $message = 'Step updated successfully';
        } else {
            $message = 'Failed to update step';
        }
    }
    
    // Delete step
    if (isset($_POST['delete_step'])) {
        $stepNumber = (int)$_POST['step_number'];
        if (deleteWorkflowStep($stepNumber)) {
            $message = 'Step deleted successfully';
        } else {
            $message = 'Failed to delete step';
        }
    }
    
    // Reorder steps
    if (isset($_POST['reorder_steps'])) {
        $order = $_POST['step_order'] ?? [];
        if (reorderWorkflowSteps($order)) {
            $message = 'Steps reordered successfully';
        } else {
            $message = 'Failed to reorder steps';
        }
    }
    
    // Update workflow settings
    if (isset($_POST['update_settings'])) {
        $config = getWorkflowConfig();
        $config['name'] = $_POST['workflow_name'] ?? $config['name'];
        $config['description'] = $_POST['workflow_description'] ?? '';
        $config['settings'] = [
            'default_step' => (int)($_POST['default_step'] ?? 1),
            'enable_workflow_history' => isset($_POST['enable_workflow_history']),
            'require_email_for_all_steps' => isset($_POST['require_email_for_all_steps']),
            'allow_skip_steps' => isset($_POST['allow_skip_steps']),
            'show_step_description' => isset($_POST['show_step_description'])
        ];
        if (saveWorkflowConfig($config)) {
            $message = 'Settings updated successfully';
        } else {
            $message = 'Failed to update settings';
        }
    }
    
    // Import workflow
    if (isset($_POST['import_workflow'])) {
        $json = $_POST['workflow_json'] ?? '';
        if (importWorkflowConfig($json)) {
            $message = 'Workflow imported successfully';
        } else {
            $message = 'Invalid workflow JSON';
        }
    }
}

// Get current workflow config
$workflowConfig = getWorkflowConfig();
$steps = getWorkflowSteps();
$totalSteps = count($steps);

// Get available email templates
$templateFiles = glob(__DIR__ . '/../mail_templates/*.html');
$templates = [];
foreach ($templateFiles as $file) {
    $templates[] = basename($file, '.html');
}

$backUrl = 'index.php?page=admin&menu=dashboard';
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Workflow Manager - ProcessTracker</title>
    <link rel="stylesheet" href="../styles.css">
</head>
<body>
    <div class="app-container">


        <!-- Main Content -->
        <main>
            <!-- Mobile Toggle -->
            <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
            <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

            <div class="page-header">
                <h1>🔄 Workflow Manager</h1>
                <div class="header-actions">
                    <a href="<?php echo $backUrl; ?>" class="btn btn-secondary">← Back to Dashboard</a>
                </div>
            </div>
            
            <?php if ($message): ?>
                <div class="message"><?php echo $message; ?></div>
            <?php endif; ?>

            <!-- Workflow Overview -->
            <div class="content-card">
                <h2>📋 Workflow Overview</h2>
                <div class="workflow-actions-bar">
                    <button type="button" onclick="exportWorkflow()" class="btn btn-secondary">📤 Export Workflow</button>
                    <button type="button" onclick="copyWorkflow()" class="btn btn-secondary">📋 Copy Workflow</button>
                </div>
                <div class="workflow-info">
                    <div class="info-item">
                        <label>Workflow Name</label>
                        <div><?php echo htmlspecialchars($workflowConfig['name'] ?? 'Default Workflow'); ?></div>
                    </div>
                    <div class="info-item">
                        <label>Total Steps</label>
                        <div><?php echo $totalSteps; ?></div>
                    </div>
                    <div class="info-item">
                        <label>Description</label>
                        <div><?php echo htmlspecialchars($workflowConfig['description'] ?? 'No description'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Workflow Visualization -->
            <div class="content-card">
                <h2>📊 Current Workflow Steps</h2>
                <?php echo renderWorkflowSteps([], false); ?>
            </div>

            <!-- Add New Step -->
            <div class="content-card">
                <h2>➕ Add New Step</h2>
                <form method="post">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Step Name *</label>
                            <input type="text" name="name" required placeholder="e.g., Technical Interview">
                        </div>
                        <div class="form-group">
                            <label>Form Type</label>
                            <select name="form_type">
                                <option value="default">Default</option>
                                <option value="choice">Choice (Confirm/Cancel)</option>
                                <option value="interview_schedule">Interview Schedule</option>
                                <option value="interview_result">Interview Result</option>
                                <option value="document_verification">Document Verification</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" rows="2" placeholder="Describe this step..."></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Email Template</label>
                            <select name="email_template">
                                <option value="">No Email</option>
                                <?php foreach ($templates as $tpl): ?>
                                    <option value="<?php echo $tpl; ?>"><?php echo ucfirst(str_replace('_', ' ', $tpl)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Email Subject</label>
                            <input type="text" name="email_subject" placeholder="e.g., {NAME}, your interview is scheduled">
                        </div>
                    </div>
                    
                    <div class="checkbox-group">
                        <label><input type="checkbox" name="send_email"> Send Email to Candidate</label>
                        <label><input type="checkbox" name="auto_advance"> Auto-advance to next step</label>
                        <label><input type="checkbox" name="can_cancel"> Allow Cancellation</label>
                    </div>
                    
                    <div class="form-group">
                        <label>Cancel Status</label>
                        <select name="cancel_status">
                            <option value="CANCELLED">Cancelled</option>
                            <option value="REJECTED">Rejected</option>
                            <option value="WITHDRAWN">Withdrawn</option>
                        </select>
                    </div>
                    
                    <h3>📝 Form Fields</h3>
                    <div id="formFieldsContainer">
                        <div class="form-field-row">
                            <input type="text" name="field_name[]" placeholder="Field Name (e.g., date)" class="field-name">
                            <input type="text" name="field_label[]" placeholder="Label (e.g., Interview Date)" class="field-label">
                            <select name="field_type[]" class="field-type">
                                <option value="text">Text</option>
                                <option value="email">Email</option>
                                <option value="date">Date</option>
                                <option value="time">Time</option>
                                <option value="textarea">Textarea</option>
                                <option value="select">Dropdown</option>
                                <option value="radio">Radio Buttons</option>
                                <option value="file">File Upload</option>
                                <option value="file_multiple">Multiple Files</option>
                            </select>
                            <input type="text" name="field_options[]" placeholder="Options (one per line, for select/radio)" class="field-options">
                            <input type="text" name="field_default[]" placeholder="Default value" class="field-default">
                            <label class="checkbox-label"><input type="checkbox" name="field_required[]" value="1"> Required</label>
                        </div>
                    </div>
                    <button type="button" onclick="addFormField()" class="btn btn-secondary btn-sm">+ Add Field</button>
                    
                    <div class="form-actions">
                        <button type="submit" name="add_step" class="btn btn-primary">➕ Add Step</button>
                    </div>
                </form>
            </div>

            <!-- Existing Steps -->
            <div class="content-card">
                <h2>📝 Manage Steps</h2>
                <p class="help-text">Use ⬆️ ⬇️ buttons to reorder steps. Click Edit to modify step details.</p>
                <div class="steps-list">
                    <?php foreach ($steps as $index => $step): ?>
                        <div class="step-item" id="step-<?php echo $step['step_number']; ?>">
                            <div class="step-header">
                                <div class="step-reorder">
                                    <?php if ($index > 0): ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="reorder_steps" value="1">
                                            <input type="hidden" name="step_order[]" value="<?php echo $step['step_number']; ?>">
                                            <?php foreach ($steps as $i => $s): ?>
                                                <?php if ($i < $index): ?>
                                                    <input type="hidden" name="step_order[]" value="<?php echo $s['step_number']; ?>">
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                            <input type="hidden" name="step_order[]" value="<?php echo $steps[$index - 1]['step_number']; ?>">
                                            <?php foreach ($steps as $i => $s): ?>
                                                <?php if ($i > $index): ?>
                                                    <input type="hidden" name="step_order[]" value="<?php echo $s['step_number']; ?>">
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                            <button type="submit" class="btn btn-sm btn-secondary" title="Move Up">⬆️</button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($index < count($steps) - 1): ?>
                                        <form method="post" style="display:inline;">
                                            <input type="hidden" name="reorder_steps" value="1">
                                            <?php foreach ($steps as $i => $s): ?>
                                                <?php if ($i < $index): ?>
                                                    <input type="hidden" name="step_order[]" value="<?php echo $s['step_number']; ?>">
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                            <input type="hidden" name="step_order[]" value="<?php echo $steps[$index + 1]['step_number']; ?>">
                                            <input type="hidden" name="step_order[]" value="<?php echo $step['step_number']; ?>">
                                            <?php foreach ($steps as $i => $s): ?>
                                                <?php if ($i > $index + 1): ?>
                                                    <input type="hidden" name="step_order[]" value="<?php echo $s['step_number']; ?>">
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                            <button type="submit" class="btn btn-sm btn-secondary" title="Move Down">⬇️</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                                <span class="step-number"><?php echo $step['step_number']; ?></span>
                                <div class="step-details">
                                    <h3><?php echo htmlspecialchars($step['name']); ?></h3>
                                    <p><?php echo htmlspecialchars($step['description'] ?? ''); ?></p>
                                    <div class="step-meta">
                                        <?php if ($step['send_email'] ?? false): ?>
                                            <span class="badge email-badge">📧 Email: <?php echo htmlspecialchars($step['email_template'] ?? 'Custom'); ?></span>
                                        <?php else: ?>
                                            <span class="badge no-email-badge">🚫 No Email</span>
                                        <?php endif; ?>
                                        <?php if ($step['can_cancel'] ?? false): ?>
                                            <span class="badge cancel-badge">❌ Can Cancel</span>
                                        <?php endif; ?>
                                        <?php if ($step['auto_advance'] ?? false): ?>
                                            <span class="badge auto-badge">⚡ Auto-Advance</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="step-actions">
                                    <button type="button" onclick="editStep(<?php echo $step['step_number']; ?>)" class="btn btn-sm btn-secondary">✏️ Edit</button>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="step_number" value="<?php echo $step['step_number']; ?>">
                                        <button type="submit" name="delete_step" class="btn btn-sm btn-danger" onclick="return confirm('Delete this step?')">🗑️ Delete</button>
                                    </form>
                                </div>
                            </div>
                            
                            <!-- Edit Form (hidden by default) -->
                            <div class="step-edit-form" id="edit-form-<?php echo $step['step_number']; ?>" style="display:none;">
                                <form method="post">
                                    <input type="hidden" name="step_number" value="<?php echo $step['step_number']; ?>">
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Step Name *</label>
                                            <input type="text" name="name" value="<?php echo htmlspecialchars($step['name']); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Description</label>
                                            <textarea name="description" rows="2"><?php echo htmlspecialchars($step['description'] ?? ''); ?></textarea>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group">
                                            <label>Email Template</label>
                                            <select name="email_template">
                                                <option value="">No Email</option>
                                                <?php foreach ($templates as $tpl): ?>
                                                    <option value="<?php echo $tpl; ?>" <?php echo ($step['email_template'] ?? '') === $tpl ? 'selected' : ''; ?>><?php echo ucfirst(str_replace('_', ' ', $tpl)); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Email Subject</label>
                                            <input type="text" name="email_subject" value="<?php echo htmlspecialchars($step['email_subject'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="checkbox-group">
                                        <label><input type="checkbox" name="send_email" <?php echo ($step['send_email'] ?? false) ? 'checked' : ''; ?>> Send Email</label>
                                        <label><input type="checkbox" name="auto_advance" <?php echo ($step['auto_advance'] ?? false) ? 'checked' : ''; ?>> Auto-Advance</label>
                                        <label><input type="checkbox" name="can_cancel" <?php echo ($step['can_cancel'] ?? false) ? 'checked' : ''; ?>> Allow Cancellation</label>
                                    </div>
                                    <div class="form-group">
                                        <label>Cancel Status</label>
                                        <select name="cancel_status">
                                            <option value="CANCELLED" <?php echo ($step['cancel_status'] ?? '') === 'CANCELLED' ? 'selected' : ''; ?>>Cancelled</option>
                                            <option value="REJECTED" <?php echo ($step['cancel_status'] ?? '') === 'REJECTED' ? 'selected' : ''; ?>>Rejected</option>
                                            <option value="WITHDRAWN" <?php echo ($step['cancel_status'] ?? '') === 'WITHDRAWN' ? 'selected' : ''; ?>>Withdrawn</option>
                                        </select>
                                    </div>
                                    <button type="submit" name="update_step" class="btn btn-primary">💾 Save Changes</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Workflow Settings -->
            <div class="content-card">
                <h2>⚙️ Workflow Settings</h2>
                <form method="post">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Workflow Name</label>
                            <input type="text" name="workflow_name" value="<?php echo htmlspecialchars($workflowConfig['name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Default Starting Step</label>
                            <select name="default_step">
                                <?php for ($i = 1; $i <= $totalSteps; $i++): ?>
                                    <option value="<?php echo $i; ?>" <?php echo (($workflowConfig['settings']['default_step'] ?? 1) == $i) ? 'selected' : ''; ?>><?php echo $i; ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="workflow_description" rows="2"><?php echo htmlspecialchars($workflowConfig['description'] ?? ''); ?></textarea>
                    </div>
                    <div class="checkbox-group">
                        <label><input type="checkbox" name="enable_workflow_history" <?php echo ($workflowConfig['settings']['enable_workflow_history'] ?? true) ? 'checked' : ''; ?>> Enable Workflow History</label>
                        <label><input type="checkbox" name="show_step_description" <?php echo ($workflowConfig['settings']['show_step_description'] ?? true) ? 'checked' : ''; ?>> Show Step Descriptions</label>
                    </div>
                    <button type="submit" name="update_settings" class="btn btn-primary">💾 Update Settings</button>
                </form>
            </div>

            <!-- Export/Import -->
            <div class="content-card">
                <h2>📤 Export / 📥 Import Workflow</h2>
                <div class="export-import">
                    <div class="export-section">
                        <h3>Export</h3>
                        <button type="button" onclick="exportWorkflow()" class="btn btn-secondary">📤 Export Workflow JSON</button>
                    </div>
                    <div class="import-section">
                        <h3>Import</h3>
                        <form method="post">
                            <textarea name="workflow_json" rows="5" placeholder="Paste workflow JSON here..."></textarea>
                            <button type="submit" name="import_workflow" class="btn btn-secondary">📥 Import Workflow</button>
                        </form>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebarOverlay').classList.toggle('open');
        }

        function addFormField() {
            const container = document.getElementById('formFieldsContainer');
            const newRow = document.createElement('div');
            newRow.className = 'form-field-row';
            newRow.innerHTML = `
                <input type="text" name="field_name[]" placeholder="Field Name" class="field-name">
                <input type="text" name="field_label[]" placeholder="Label" class="field-label">
                <select name="field_type[]" class="field-type">
                    <option value="text">Text</option>
                    <option value="email">Email</option>
                    <option value="date">Date</option>
                    <option value="time">Time</option>
                    <option value="textarea">Textarea</option>
                    <option value="select">Dropdown</option>
                    <option value="radio">Radio Buttons</option>
                    <option value="file">File Upload</option>
                    <option value="file_multiple">Multiple Files</option>
                </select>
                <input type="text" name="field_options[]" placeholder="Options (one per line)" class="field-options">
                <input type="text" name="field_default[]" placeholder="Default value" class="field-default">
                <label class="checkbox-label"><input type="checkbox" name="field_required[]" value="1"> Required</label>
                <button type="button" onclick="this.parentElement.remove()" class="btn btn-sm btn-danger">✕</button>
            `;
            container.appendChild(newRow);
        }

        function editStep(stepNumber) {
            const editForm = document.getElementById('edit-form-' + stepNumber);
            if (editForm) {
                editForm.style.display = editForm.style.display === 'none' ? 'block' : 'none';
            }
        }

        function exportWorkflow() {
            const json = <?php echo json_encode(exportWorkflowConfig()); ?>;
            const blob = new Blob([json], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'workflow_config.json';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }

        function previewTemplate(templateName) {
            const sampleData = {
                'NAME': 'John Doe',
                'POSITION': 'Software Engineer',
                'LOCATION': 'Mumbai, India',
                'EMAIL': 'john@example.com',
                'PHONE': '+91-9876543210',
                'DATE': '2025-01-20',
                'TIME': '10:00 AM',
                'MODE': 'Online',
                'INTERVIEWER': 'Jane Smith',
                'RESULT': 'Pass',
                'REMARKS': 'Excellent technical skills demonstrated'
            };
            
            // Fetch template content
            fetch('../mail_templates/' + templateName + '.html')
                .then(response => response.text())
                .then(html => {
                    // Replace placeholders
                    let previewHtml = html;
                    for (const [key, value] of Object.entries(sampleData)) {
                        const regex = new RegExp('\\{' + key + '\\}', 'g');
                        previewHtml = previewHtml.replace(regex, value);
                    }
                    
                    // Show in modal
                    document.getElementById('templatePreviewContent').innerHTML = previewHtml;
                    document.getElementById('templatePreviewModal').style.display = 'flex';
                })
                .catch(err => {
                    alert('Error loading template: ' + err);
                });
        }

        function closePreviewModal() {
            document.getElementById('templatePreviewModal').style.display = 'none';
        }

        function moveStep(direction, stepNumber) {
            const form = document.createElement('form');
            form.method = 'post';
            form.style.display = 'none';
            
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'reorder_steps';
            input.value = '1';
            form.appendChild(input);
            
            const orderInput = document.createElement('input');
            orderInput.type = 'hidden';
            orderInput.name = 'step_order[]';
            orderInput.value = stepNumber;
            form.appendChild(orderInput);
            
            document.body.appendChild(form);
            form.submit();
        }

        function copyWorkflow() {
            const newName = prompt('Enter name for the copied workflow:', 'Copy of <?php echo htmlspecialchars($workflowConfig['name'] ?? 'Workflow'); ?>');
            if (newName) {
                // Get current config
                const config = <?php echo json_encode($workflowConfig); ?>;
                config.name = newName;
                config.description = 'Copy of ' + config.description;
                
                // Submit as import
                const form = document.createElement('form');
                form.method = 'post';
                form.style.display = 'none';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'workflow_json';
                input.value = JSON.stringify(config);
                form.appendChild(input);
                
                const importInput = document.createElement('input');
                importInput.type = 'hidden';
                importInput.name = 'import_workflow';
                importInput.value = '1';
                form.appendChild(importInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Email template selector change handler
        document.addEventListener('DOMContentLoaded', function() {
            const emailTemplateSelects = document.querySelectorAll('select[name="email_template"]');
            emailTemplateSelects.forEach(select => {
                select.addEventListener('change', function() {
                    const previewBtn = this.parentElement.querySelector('.preview-template-btn');
                    if (this.value) {
                        if (!previewBtn) {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'btn btn-sm btn-secondary preview-template-btn';
                            btn.innerHTML = '👁️ Preview';
                            btn.onclick = function() { previewTemplate(select.value); };
                            this.parentElement.appendChild(btn);
                        }
                    } else {
                        if (previewBtn) {
                            previewBtn.remove();
                        }
                    }
                });
                // Trigger change to show/hide existing preview buttons
                select.dispatchEvent(new Event('change'));
            });
        });
    </script>

    <style>
        .workflow-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .info-item label {
            display: block;
            font-size: 0.75em;
            color: #7f8c8d;
            margin-bottom: 5px;
        }
        
        .info-item div {
            font-weight: 500;
        }
        
        .workflow-steps {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .workflow-step {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #ddd;
        }
        
        .workflow-step.completed {
            border-left-color: #27ae60;
            background: #e8f6ef;
        }
        
        .workflow-step.current {
            border-left-color: #667eea;
            background: #eef2ff;
        }
        
        .step-indicator {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #ddd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #fff;
            flex-shrink: 0;
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
            font-weight: 600;
            color: #2c3e50;
        }
        
        .step-desc {
            font-size: 0.85em;
            color: #7f8c8d;
        }
        
        .steps-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .step-item {
            border: 1px solid #e1e5e9;
            border-radius: 10px;
            overflow: hidden;
        }
        
        .step-header {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 20px;
            background: #fff;
        }
        
        .step-number {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: #667eea;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            flex-shrink: 0;
        }
        
        .step-details {
            flex: 1;
        }
        
        .step-details h3 {
            margin: 0 0 5px 0;
            color: #2c3e50;
        }
        
        .step-details p {
            margin: 0 0 10px 0;
            color: #7f8c8d;
        }
        
        .step-meta {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .badge {
            font-size: 0.75em;
            padding: 3px 8px;
            border-radius: 4px;
        }
        
        .email-badge {
            background: #e8f6ef;
            color: #27ae60;
        }
        
        .no-email-badge {
            background: #fdf0ed;
            color: #e74c3c;
        }
        
        .cancel-badge {
            background: #fef9e7;
            color: #f39c12;
        }
        
        .auto-badge {
            background: #e8f4fd;
            color: #3498db;
        }
        
        .step-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .step-edit-form {
            padding: 20px;
            background: #f8f9fa;
            border-top: 1px solid #e1e5e9;
        }
        
        .form-field-row {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            margin-bottom: 10px;
            padding: 10px;
            background: #fff;
            border-radius: 6px;
        }
        
        .field-name, .field-label, .field-type, .field-options, .field-default {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            min-width: 120px;
        }
        
        .field-name { flex: 1; min-width: 120px; }
        .field-label { flex: 1.5; min-width: 150px; }
        .field-type { width: 130px; }
        .field-options { flex: 1; min-width: 200px; }
        .field-default { width: 120px; }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 0.85em;
            white-space: nowrap;
        }
        
        .checkbox-group {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin: 15px 0;
        }
        
        .checkbox-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .form-actions {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .export-import {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .export-section, .import-section {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .export-section h3, .import-section h3 {
            margin-top: 0;
            color: #2c3e50;
        }
        
        .import-section textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: monospace;
            margin-bottom: 10px;
        }
        
        .step-reorder {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .step-reorder button {
            padding: 2px 8px;
            font-size: 0.8em;
            min-width: 28px;
        }
        
        .preview-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        
        .preview-modal-content {
            background: #fff;
            border-radius: 10px;
            max-width: 650px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            padding: 30px;
            position: relative;
        }
        
        .preview-modal-close {
            position: absolute;
            top: 15px;
            right: 20px;
            font-size: 1.5em;
            cursor: pointer;
            color: #999;
        }
        
        .preview-modal-close:hover {
            color: #333;
        }
        
        .workflow-actions-bar {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        @media (max-width: 768px) {
            .export-import {
                grid-template-columns: 1fr;
            }
            
            .form-field-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .field-name, .field-label, .field-type, .field-options, .field-default {
                width: 100%;
            }
            
            .step-header {
                flex-direction: column;
            }
            
            .step-actions {
                width: 100%;
                justify-content: flex-start;
            }
        }
    </style>
    
    <!-- Template Preview Modal -->
    <div class="preview-modal" id="templatePreviewModal">
        <div class="preview-modal-content">
            <span class="preview-modal-close" onclick="closePreviewModal()">&times;</span>
            <h2>📧 Email Template Preview</h2>
            <div id="templatePreviewContent"></div>
        </div>
    </div>
</body>
</html>

