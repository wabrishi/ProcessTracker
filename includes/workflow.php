<?php
// Workflow Configuration Management

/**
 * Load workflow configuration from JSON file
 */
function getWorkflowConfig(): array {
    $configPath = __DIR__ . '/../config/workflow.json';
    if (!file_exists($configPath)) {
        return getDefaultWorkflowConfig();
    }
    $config = json_decode(file_get_contents($configPath), true);
    return $config ?: getDefaultWorkflowConfig();
}

/**
 * Get default workflow configuration
 */
function getDefaultWorkflowConfig(): array {
    return [
        'name' => 'Default Recruitment Workflow',
        'description' => 'Standard recruitment process',
        'steps' => [],
        'settings' => [
            'default_step' => 1,
            'enable_workflow_history' => true,
            'require_email_for_all_steps' => false,
            'allow_skip_steps' => false,
            'show_step_description' => true
        ]
    ];
}

/**
 * Save workflow configuration to JSON file
 */
function saveWorkflowConfig(array $config): bool {
    $configPath = __DIR__ . '/../config/workflow.json';
    return file_put_contents($configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false;
}

/**
 * Get all workflow steps
 */
function getWorkflowSteps(): array {
    $config = getWorkflowConfig();
    return $config['steps'] ?? [];
}

/**
 * Get step configuration by step number
 */
function getStepConfig(int $stepNumber): ?array {
    $steps = getWorkflowSteps();
    foreach ($steps as $step) {
        if ($step['step_number'] == $stepNumber) {
            return $step;
        }
    }
    return null;
}

/**
 * Get step by order index (0-based)
 */
function getStepByOrder(int $order): ?array {
    $steps = getWorkflowSteps();
    return $steps[$order] ?? null;
}

/**
 * Get current step configuration for a candidate
 */
function getCurrentStepConfig(array $candidate): ?array {
    $currentStep = $candidate['current_step'] ?? 1;
    return getStepConfig($currentStep);
}

/**
 * Get next step number
 */
function getNextStepNumber(array $candidate): ?int {
    $currentStep = $candidate['current_step'] ?? 1;
    $steps = getWorkflowSteps();
    
    foreach ($steps as $index => $step) {
        if ($step['step_number'] == $currentStep) {
            return $steps[$index + 1]['step_number'] ?? null;
        }
    }
    return null;
}

/**
 * Get total number of steps
 */
function getTotalSteps(): int {
    return count(getWorkflowSteps());
}

/**
 * Get workflow step name by number
 */
function getStepName(int $stepNumber): string {
    $step = getStepConfig($stepNumber);
    return $step ? $step['name'] : "Step $stepNumber";
}

/**
 * Check if candidate can move to next step
 */
function canAdvanceStep(array $candidate): bool {
    return getNextStepNumber($candidate) !== null && $candidate['status'] === 'IN_PROGRESS';
}

/**
 * Add a new step to workflow
 */
function addWorkflowStep(array $stepData): bool {
    $config = getWorkflowConfig();
    $steps = $config['steps'] ?? [];
    
    $maxStep = 0;
    foreach ($steps as $step) {
        $maxStep = max($maxStep, $step['step_number']);
    }
    $stepData['step_number'] = $maxStep + 1;
    
    $steps[] = $stepData;
    $config['steps'] = $steps;
    
    return saveWorkflowConfig($config);
}

/**
 * Update an existing step
 */
function updateWorkflowStep(int $stepNumber, array $stepData): bool {
    $config = getWorkflowConfig();
    $steps = $config['steps'] ?? [];
    
    foreach ($steps as &$step) {
        if ($step['step_number'] == $stepNumber) {
            $step = array_merge($step, $stepData);
            $config['steps'] = $steps;
            return saveWorkflowConfig($config);
        }
    }
    return false;
}

/**
 * Delete a step from workflow
 */
function deleteWorkflowStep(int $stepNumber): bool {
    $config = getWorkflowConfig();
    $steps = $config['steps'] ?? [];
    
    $newSteps = [];
    $newNumber = 1;
    foreach ($steps as $step) {
        if ($step['step_number'] != $stepNumber) {
            $step['step_number'] = $newNumber++;
            $newSteps[] = $step;
        }
    }
    
    $config['steps'] = $newSteps;
    return saveWorkflowConfig($config);
}

/**
 * Reorder workflow steps
 */
function reorderWorkflowSteps(array $stepOrder): bool {
    $config = getWorkflowConfig();
    $steps = $config['steps'] ?? [];
    
    $stepMap = [];
    foreach ($steps as $step) {
        $stepMap[$step['step_number']] = $step;
    }
    
    $newSteps = [];
    $newNumber = 1;
    foreach ($stepOrder as $oldNumber) {
        if (isset($stepMap[$oldNumber])) {
            $step = $stepMap[$oldNumber];
            $step['step_number'] = $newNumber++;
            $newSteps[] = $step;
        }
    }
    
    $config['steps'] = $newSteps;
    return saveWorkflowConfig($config);
}

/**
 * Get form fields for a specific step
 */
function getStepFormFields(int $stepNumber): array {
    $step = getStepConfig($stepNumber);
    return $step['form_fields'] ?? [];
}

/**
 * Check if step sends email
 */
function stepSendsEmail(int $stepNumber): bool {
    $step = getStepConfig($stepNumber);
    return $step['send_email'] ?? false;
}

/**
 * Get email template for step
 */
function getStepEmailTemplate(int $stepNumber): ?string {
    $step = getStepConfig($stepNumber);
    return $step['email_template'] ?? null;
}

/**
 * Get email subject for step
 */
function getStepEmailSubject(int $stepNumber): string {
    $step = getStepConfig($stepNumber);
    return $step['email_subject'] ?? 'Notification';
}

/**
 * Build email variables from candidate and step data
 */
function buildEmailVariables(array $candidate, array $stepData = []): array {
    $variables = [
        'NAME' => $candidate['name'] ?? '',
        'EMAIL' => $candidate['email'] ?? '',
        'PHONE' => $candidate['phone'] ?? '',
        'POSITION' => $candidate['position'] ?? '',
        'LOCATION' => $candidate['location'] ?? '',
        'ID' => $candidate['id'] ?? '',
    ];
    
    // Add step data variables (RESULT, REMARKS, DATE, TIME, MODE, INTERVIEWER)
    foreach ($stepData as $key => $value) {
        if (is_string($value)) {
            $variables[strtoupper($key)] = $value;
        }
    }
    
    // Handle RESULT and REMARKS specifically for interview result templates
    if (isset($stepData['result'])) {
        $variables['RESULT'] = $stepData['result'];
    }
    if (isset($stepData['remarks'])) {
        $variables['REMARKS'] = $stepData['remarks'];
    }
    
    // Handle interview scheduling variables
    if (isset($stepData['date'])) {
        $variables['DATE'] = $stepData['date'];
    }
    if (isset($stepData['time'])) {
        $variables['TIME'] = $stepData['time'];
    }
    if (isset($stepData['mode'])) {
        $variables['MODE'] = $stepData['mode'];
    }
    if (isset($stepData['interviewer'])) {
        $variables['INTERVIEWER'] = $stepData['interviewer'];
    }
    
    // Also support stored interview data from candidate record
    if (isset($candidate['interviews'])) {
        foreach ($candidate['interviews'] as $round => $interview) {
            $variables[strtoupper($round . '_DATE')] = $interview['date'] ?? '';
            $variables[strtoupper($round . '_TIME')] = $interview['time'] ?? '';
            $variables[strtoupper($round . '_MODE')] = $interview['mode'] ?? '';
            $variables[strtoupper($round . '_INTERVIEWER')] = $interview['interviewer'] ?? '';
        }
    }
    
    return $variables;
}

/**
 * Process step move with dynamic logic
 */
function processStepMove(string $candidateId, int $targetStep, array $data): bool {
    $candidates = getCandidates();
    
    if (!isset($candidates[$candidateId])) {
        return false;
    }
    
    $candidate = $candidates[$candidateId];
    $currentStep = $candidate['current_step'] ?? 1;
    
    // Only allow moving forward (to same step or higher)
    if ($targetStep < $currentStep) {
        return false;
    }
    
    if ($candidate['status'] !== 'IN_PROGRESS') {
        return false;
    }
    
    $stepConfig = getStepConfig($targetStep);
    if (!$stepConfig) {
        return false;
    }
    
    $candidates[$candidateId]['current_step'] = $targetStep;
    
    if (!isset($candidates[$candidateId]['step_data'])) {
        $candidates[$candidateId]['step_data'] = [];
    }
    $candidates[$candidateId]['step_data'][$targetStep] = $data;
    
    if (isset($data['choice'])) {
        if ($data['choice'] === 'cancellation') {
            $candidates[$candidateId]['status'] = $stepConfig['cancel_status'] ?? 'CANCELLED';
        }
    }
    
    if (isset($data['result'])) {
        if ($data['result'] === 'fail' || $data['result'] === 'rejected') {
            $candidates[$candidateId]['status'] = $stepConfig['cancel_status'] ?? 'CANCELLED';
        } elseif ($stepConfig['auto_advance'] && $targetStep == getTotalSteps()) {
            $candidates[$candidateId]['status'] = 'COMPLETED';
        }
    }
    
    if (isset($stepConfig['form_type']) && strpos($stepConfig['form_type'], 'interview') !== false) {
        $round = ($targetStep <= 5) ? '1st' : '2nd';
        $candidates[$candidateId]['interviews'][$round] = [
            'date' => $data['date'] ?? '',
            'time' => $data['time'] ?? '',
            'mode' => $data['mode'] ?? '',
            'interviewer' => $data['interviewer'] ?? ''
        ];
    }
    
    if (isset($data['documents'])) {
        $docs = [];
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
        $candidates[$candidateId]['documents'] = array_merge($candidates[$candidateId]['documents'] ?? [], $docs);
    }
    
    if (isset($data['verification'])) {
        $candidates[$candidateId]['verification_status'] = $data['verification'];
    }
    
    if ($stepConfig['send_email'] && $stepConfig['email_template']) {
        $emailData = buildEmailVariables($candidates[$candidateId], $data);
        sendTemplatedMail($candidateId, $stepConfig['email_template'], $emailData);
    }
    
    saveCandidates($candidates);
    logRecruitmentAction($candidateId, getStepName($targetStep), $_SESSION['user_id'] ?? 'system');
    
    return true;
}

/**
 * Validate form data for a step
 */
function validateStepFormData(int $stepNumber, array $data): array {
    $errors = [];
    $formFields = getStepFormFields($stepNumber);
    
    foreach ($formFields as $field) {
        $name = $field['name'];
        $required = $field['required'] ?? false;
        
        if ($required && empty($data[$name])) {
            $errors[$name] = "{$field['label']} is required";
            continue;
        }
        
        if (!empty($data[$name])) {
            switch ($field['type']) {
                case 'email':
                    if (!filter_var($data[$name], FILTER_VALIDATE_EMAIL)) {
                        $errors[$name] = "Invalid email format";
                    }
                    break;
                case 'date':
                    if (!strtotime($data[$name])) {
                        $errors[$name] = "Invalid date format";
                    }
                    break;
            }
        }
    }
    
    return $errors;
}

/**
 * Export workflow configuration
 */
function exportWorkflowConfig(): string {
    return json_encode(getWorkflowConfig(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

/**
 * Import workflow configuration
 */
function importWorkflowConfig(string $json): bool {
    $config = json_decode($json, true);
    if (!$config || !isset($config['steps'])) {
        return false;
    }
    return saveWorkflowConfig($config);
}

