<?php
// Mail Sender Helper Functions
// Provides functionality to send custom emails with placeholders

error_log('Loading mail_sender.php');

include_once __DIR__ . '/helpers.php';
include_once __DIR__ . '/mailer.php';

/**
 * Get all available email templates
 * @return array List of template names
 */
function getAvailableTemplates(): array {
    $templates = [];
    $templateDir = __DIR__ . '/../mail_templates/';
    
    if (is_dir($templateDir)) {
        $files = glob($templateDir . '*.html');
        foreach ($files as $file) {
            $templates[] = basename($file, '.html');
        }
    }
    
    return $templates;
}

/**
 * Get template display name from template file
 * @param string $templateName
 * @return string Formatted template name
 */
function getTemplateDisplayName(string $templateName): string {
    $displayNames = [
        'profile_selected' => 'Profile Selected',
        'offer_letter' => 'Offer Letter',
        'interview_schedule' => 'Interview Schedule',
        'interview_result' => 'Interview Result',
        'confirmation' => 'Confirmation Letter',
        'cancellation' => 'Cancellation Letter'
    ];
    
    return $displayNames[$templateName] ?? ucwords(str_replace('_', ' ', $templateName));
}

/**
 * Get placeholder definitions for a template
 * @param string $templateName
 * @return array Placeholder definitions with type, label, required status
 */
function getTemplatePlaceholderDefs(string $templateName): array {
    // Standard placeholders from candidate data
    $standardPlaceholders = [
        'NAME' => [
            'key' => 'NAME',
            'label' => 'Candidate Name',
            'type' => 'text',
            'required' => true,
            'source' => 'candidate'
        ],
        'POSITION' => [
            'key' => 'POSITION',
            'label' => 'Position',
            'type' => 'text',
            'required' => true,
            'source' => 'candidate'
        ]
    ];
    
    // Template-specific placeholders
    $templatePlaceholders = [

        'profile_selected' => [
            'WORK_LOCATION' => [
                'key' => 'WORK_LOCATION',
                'label' => 'Work Location',
                'type' => 'text',
                'required' => true,
                'source' => 'custom'
            ],

            'SALARY' => [
                'key' => 'SALARY',
                'label' => 'Monthly Salary',
                'type' => 'text',
                'required' => true,
                'source' => 'custom',
                'default' => '₹27,500'
            ],

            'JOB_TYPE' => [
                'key' => 'JOB_TYPE',
                'label' => 'Job Type',
                'type' => 'select',
                'options' => ['Permanent', 'Temporary', 'Contract', '60% Permanent'],
                'required' => true,
                'source' => 'custom',
                'default' => 'Permanent'
            ],

            'WORK_HOURS' => [
                'key' => 'WORK_HOURS',
                'label' => 'Working Hours',
                'type' => 'text',
                'required' => true,
                'source' => 'custom',
                'default' => '8 hours per day'
            ],

            'WORKING_DAYS' => [
                'key' => 'WORKING_DAYS',
                'label' => 'Working Days',
                'type' => 'text',
                'required' => true,
                'source' => 'custom',
                'default' => 'Monday to Friday'
            ],

            'WEEKLY_OFF' => [
                'key' => 'WEEKLY_OFF',
                'label' => 'Weekly Off Days',
                'type' => 'text',
                'required' => false,
                'source' => 'custom',
                'default' => 'Saturday and Sunday'
            ],

            'FACILITY_DETAILS' => [
                'key' => 'FACILITY_DETAILS',
                'label' => 'Accommodation & Cab Facility',
                'type' => 'select',
                'options' => ['Provided', 'Not Provided'],
                'required' => true,
                'source' => 'custom',
                'default' => 'Provided'
            ],

            'HR_CONTACT' => [
                'key' => 'HR_CONTACT',
                'label' => 'HR Contact Number',
                'type' => 'text',
                'required' => true,
                'source' => 'custom'
            ]

        ],

        'interview_schedule' => [
            'DATE' => [
                'key' => 'DATE',
                'label' => 'Interview Date',
                'type' => 'date',
                'required' => true,
                'source' => 'custom'
            ],
            'TIME' => [
                'key' => 'TIME',
                'label' => 'Interview Time',
                'type' => 'time',
                'required' => true,
                'source' => 'custom'
            ],
            'MODE' => [
                'key' => 'MODE',
                'label' => 'Interview Mode',
                'type' => 'select',
                'options' => ['In-Person', 'Video Call', 'Phone Call'],
                'required' => true,
                'source' => 'custom',
                'default' => 'Phone Call'
            ]
        ],
        'interview_result' => [
            'RESULT' => [
                'key' => 'RESULT',
                'label' => 'Interview Result',
                'type' => 'select',
                'options' => ['Selected', 'Rejected', 'Pending', 'Passed', 'Failed', 'Waitlisted'],
                'required' => true,
                'source' => 'custom'
            ],
            'REMARKS' => [
                'key' => 'REMARKS',
                'label' => 'Remarks/Feedback',
                'type' => 'textarea',
                'required' => false,
                'source' => 'custom'
            ]
        ],
        'offer_letter' => [
            'SALARY' => [
                'key' => 'SALARY',
                'label' => 'Salary',
                'type' => 'text',
                'required' => false,
                'source' => 'custom'
            ],
            'JOINING_DATE' => [
                'key' => 'JOINING_DATE',
                'label' => 'Joining Date',
                'type' => 'date',
                'required' => false,
                'source' => 'custom'
            ],
            'COMPANY_NAME' => [
                'key' => 'COMPANY_NAME',
                'label' => 'Company Name',
                'type' => 'text',
                'required' => false,
                'source' => 'custom',
                'default' => 'Aakasha Services'
            ]
        ],
        'confirmation' => [
            'COMPANY_NAME' => [
                'key' => 'COMPANY_NAME',
                'label' => 'Company Name',
                'type' => 'text',
                'required' => false,
                'source' => 'custom',
                'default' => 'Aakasha Services'
            ]
        ],
        'cancellation' => [
            'REASON' => [
                'key' => 'REASON',
                'label' => 'Cancellation Reason',
                'type' => 'textarea',
                'required' => true,
                'source' => 'custom',
                'default' => 'Document Incomplete'
            ],
            'COMPANY_NAME' => [
                'key' => 'COMPANY_NAME',
                'label' => 'Company Name',
                'type' => 'text',
                'required' => true,
                'source' => 'custom',
                'default' => 'Aakasha Services'
            ]
        ]
    ];
    
    // Merge standard with template-specific
    $placeholders = $standardPlaceholders;
    
    if (isset($templatePlaceholders[$templateName])) {
        $placeholders = array_merge($placeholders, $templatePlaceholders[$templateName]);
    }
    
    return $placeholders;
}

/**
 * Extract placeholders from template content
 * @param string $templateContent
 * @return array List of placeholder keys found
 */
function extractTemplatePlaceholders(string $templateContent): array {
    $placeholders = [];
    
    // Match both {{PLACEHOLDER}} and {PLACEHOLDER} formats
    if (preg_match_all('/\{\{?([A-Z_]+)\}\}?/', $templateContent, $matches)) {
        $placeholders = array_unique($matches[1]);
    }
    
    return $placeholders;
}

/**
 * Parse template with replacements
 * @param string $templateName
 * @param array $replacements Key-value pairs for replacement
 * @return string Parsed template content
 */
function parseTemplate(string $templateName, array $replacements): string {
    $templatePath = __DIR__ . '/../mail_templates/' . $templateName . '.html';
    
    if (!file_exists($templatePath)) {
        error_log("Template not found: $templatePath");
        return 'Template not found';
    }
    
    $content = file_get_contents($templatePath);
    
    // Convert double brace to single brace format first
    $content = preg_replace('/\{\{([A-Z_]+)\}\}/i', '{$1}', $content);
    
    // Replace all placeholders
    foreach ($replacements as $key => $value) {
        $content = str_replace('{' . strtoupper($key) . '}', $value, $content);
        // Also handle lowercase version
        $content = str_replace('{' . strtolower($key) . '}', $value, $content);
    }
    
    return $content;
}

/**
 * Generate email subject for template
 * @param string $templateName
 * @param array $replacements
 * @return string Subject line
 */
function generateEmailSubject(string $templateName, array $replacements = []): string {
    $subjectTemplates = [
        'profile_selected' => 'Welcome to Our Recruitment Process - {POSITION}',
        'offer_letter' => 'Offer Letter - {POSITION}',
        'interview_schedule' => 'Interview Schedule - {POSITION}',
        'interview_result' => '{POSITION} Interview Result - {RESULT}',
        'confirmation' => 'Confirmation of Employment - {POSITION}',
        'cancellation' => 'Regarding Your Application - {POSITION}'
    ];
    
    $subject = $subjectTemplates[$templateName] ?? ucfirst($templateName) . ' - {POSITION}';
    
    // Replace placeholders
    foreach ($replacements as $key => $value) {
        $subject = str_replace('{' . strtoupper($key) . '}', $value, $subject);
        $subject = str_replace('{' . strtolower($key) . '}', $value, $subject);
    }
    
    return $subject;
}

/**
 * Generate unique mail log ID
 * @return string
 */
function generateMailLogId(): string {
    $logs = readJsonFile(__DIR__ . '/../database/mail_logs.json');
    $count = count($logs) + 1;
    return 'ML' . str_pad($count, 4, '0', STR_PAD_LEFT);
}

/**
 * Log mail to database
 * @param array $data Mail log data
 * @return bool
 */
function logMail(array $data): bool {
    $logEntry = [
        'id' => $data['id'] ?? generateMailLogId(),
        'candidate_id' => $data['candidate_id'] ?? '',
        'candidate_name' => $data['candidate_name'] ?? '',
        'candidate_email' => $data['candidate_email'] ?? '',
        'template_name' => $data['template_name'] ?? '',
        'subject' => $data['subject'] ?? '',
        'to_email' => $data['to_email'] ?? '',
        'placeholders' => $data['placeholders'] ?? [],
        'sent_by' => $data['sent_by'] ?? '',
        'sent_by_name' => $data['sent_by_name'] ?? '',
        'sent_at' => $data['sent_at'] ?? date('Y-m-d H:i:s'),
        'status' => $data['status'] ?? 'SENT',
        'error_message' => $data['error_message'] ?? null
    ];
    
    return appendToJsonArray(__DIR__ . '/../database/mail_logs.json', $logEntry);
}

/**
 * Get mail logs, optionally filtered by candidate
 * @param string|null $candidateId
 * @return array
 */
function getMailLogs(?string $candidateId = null): array {
    $logs = readJsonFile(__DIR__ . '/../database/mail_logs.json');
    
    if ($candidateId) {
        $logs = array_filter($logs, fn($log) => ($log['candidate_id'] ?? '') === $candidateId);
        $logs = array_values($logs);
    }
    
    return $logs;
}

/**
 * Send custom email to candidate
 * @param string $candidateId
 * @param string $templateName
 * @param array $customPlaceholders Additional/custom placeholders
 * @param string|null $customSubject Override subject
 * @param array $attachments
 * @return array Result with success status and message
 */
function sendCustomMail(
    string $candidateId, 
    string $templateName, 
    array $customPlaceholders = [], 
    ?string $customSubject = null,
    array $attachments = []
): array {
    // Get candidate data
    $candidates = getCandidates();
    if (!isset($candidates[$candidateId])) {
        return ['success' => false, 'message' => 'Candidate not found'];
    }
    
    $candidate = $candidates[$candidateId];
    
    // Build replacements from candidate data
    $replacements = [
        'name' => $candidate['name'],
        'position' => $candidate['position']
    ];
    
    // Merge custom placeholders
    $replacements = array_merge($replacements, $customPlaceholders);
    
    // Generate subject
    $subject = $customSubject ?? generateEmailSubject($templateName, $replacements);
    
    // Parse template
    $body = parseTemplate($templateName, $replacements);
    
    // If this template should include a confirmation/offer PDF, generate and attach it
    if (in_array($templateName, ['confirmation','offer_letter'])) {
        $pdfPath = generateConfirmationPDF($candidate, $candidateId);
        if ($pdfPath) {
            $attachments[] = $pdfPath;
        } else {
            error_log('Failed to generate confirmation PDF for candidate: ' . ($candidate['name'] ?? 'unknown'));
        }
    }
    
    // Send email
    $sent = sendMail($candidate['email'], $subject, $body, $attachments);
    
    // Log the email
    $userId = $_SESSION['user_id'] ?? 'system';
    $userName = $_SESSION['user_name'] ?? 'System';
    
    $logData = [
        'candidate_id' => $candidateId,
        'candidate_name' => $candidate['name'],
        'candidate_email' => $candidate['email'],
        'template_name' => $templateName,
        'subject' => $subject,
        'to_email' => $candidate['email'],
        'placeholders' => $customPlaceholders,
        'sent_by' => $userId,
        'sent_by_name' => $userName,
        'status' => $sent ? 'SENT' : 'FAILED',
        'error_message' => $sent ? null : 'Failed to send email'
    ];
    
    logMail($logData);
    
    // Also log to recruitment action log
    if ($sent) {
        logRecruitmentAction($candidateId, "Custom Email: $templateName", $userId);
    }
    
    if ($sent) {
        error_log("Custom email sent successfully to {$candidate['email']} using template $templateName");
        return ['success' => true, 'message' => 'Email sent successfully'];
    } else {
        error_log("Failed to send email to {$candidate['email']} using template $templateName");
        return ['success' => false, 'message' => 'Failed to send email'];
    }
}

/**
 * Get candidates list for dropdown (filtered by HR if provided)
 * @param string|null $hrId
 * @return array
 */
function getCandidatesForMail(?string $hrId = null): array {
    $candidates = getCandidates();
    $filtered = [];
    
    foreach ($candidates as $id => $cand) {
        // HR can only send to their assigned candidates
        // Admin can send to all
        if ($hrId === null || ($cand['assigned_to'] ?? '') === $hrId || ($cand['created_by'] ?? '') === $hrId) {
            $filtered[$id] = $cand;
        }
    }
    
    return $filtered;
}

/**
 * Build form HTML for placeholder values
 * @param string $templateName
 * @param array $candidate
 * @return string HTML form fields
 */
function buildPlaceholderForm(string $templateName, array $candidate = []): string {
    $placeholders = getTemplatePlaceholderDefs($templateName);
    
    $html = '';
    
    foreach ($placeholders as $key => $def) {
        $label = $def['label'];
        $type = $def['type'];
        $required = $def['required'] ?? false;
        $options = $def['options'] ?? [];
        $source = $def['source'] ?? 'custom';
        $defaultValue = $def['default'] ?? '';
        
        // Get value from candidate data if source is candidate
        $value = '';
        if ($source === 'candidate') {
            $value = match($key) {
                'NAME' => $candidate['name'] ?? '',
                'POSITION' => $candidate['position'] ?? '',
                default => ''
            };
        } else {
            $value = $defaultValue;
        }
        
        $fieldName = 'placeholder_' . $key;
        $requiredAttr = $required ? ' required' : '';
        
        $html .= '<div class="form-group">';
        $html .= '<label>' . htmlspecialchars($label);
        if ($required) {
            $html .= ' <span class="required">*</span>';
        }
        $html .= '</label>';
        
        switch ($type) {
            case 'text':
                $html .= '<input type="text" name="' . htmlspecialchars($fieldName) . '" value="' . htmlspecialchars($value) . '"' . $requiredAttr . '>';
                break;
                
            case 'email':
                $html .= '<input type="email" name="' . htmlspecialchars($fieldName) . '" value="' . htmlspecialchars($value) . '"' . $requiredAttr . '>';
                break;
                
            case 'date':
                $html .= '<input type="date" name="' . htmlspecialchars($fieldName) . '" value="' . htmlspecialchars($value) . '"' . $requiredAttr . '>';
                break;
                
            case 'time':
                $html .= '<input type="time" name="' . htmlspecialchars($fieldName) . '" value="' . htmlspecialchars($value) . '"' . $requiredAttr . '>';
                break;
                
            case 'textarea':
                $html .= '<textarea name="' . htmlspecialchars($fieldName) . '" rows="3"' . $requiredAttr . '>' . htmlspecialchars($value) . '</textarea>';
                break;
                
            case 'select':
                $html .= '<select name="' . htmlspecialchars($fieldName) . '"' . $requiredAttr . '>';
                foreach ($options as $opt) {
                    $selected = ($value === $opt) ? ' selected' : '';
                    $html .= '<option value="' . htmlspecialchars($opt) . '"' . $selected . '>' . htmlspecialchars($opt) . '</option>';
                }
                $html .= '</select>';
                break;
        }
        
        $html .= '</div>';
    }
    
    return $html;
}

/**
 * Get all placeholders from form submission
 * @return array
 */
function getPlaceholdersFromForm(): array {
    $placeholders = [];
    
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'placeholder_') === 0) {
            $placeholderKey = strtoupper(str_replace('placeholder_', '', $key));
            $placeholders[$placeholderKey] = $value;
        }
    }
    
    return $placeholders;
}

/**
 * Get mail statistics
 * @return array
 */
function getMailStats(): array {
    $logs = readJsonFile(__DIR__ . '/../database/mail_logs.json');
    
    $total = count($logs);
    $sent = count(array_filter($logs, fn($l) => ($l['status'] ?? '') === 'SENT'));
    $failed = count(array_filter($logs, fn($l) => ($l['status'] ?? '') === 'FAILED'));
    
    // Group by template
    $byTemplate = [];
    foreach ($logs as $log) {
        $template = $log['template_name'] ?? 'unknown';
        if (!isset($byTemplate[$template])) {
            $byTemplate[$template] = 0;
        }
        $byTemplate[$template]++;
    }
    
    return [
        'total' => $total,
        'sent' => $sent,
        'failed' => $failed,
        'by_template' => $byTemplate
    ];
}
?>

