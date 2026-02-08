<?php
// Candidate Workflow Logic - Dynamic Workflow System

include_once 'helpers.php';
include_once 'upload.php';
include_once 'mailer.php';
include_once 'workflow.php';

/**
 * Backward compatibility - Get STEPS constant as array
 * This maintains compatibility with existing code
 */
// Note: getStepsArray() is defined in workflow.php

// Define STEPS constant for backward compatibility
if (!defined('STEPS')) {
    // Get steps from workflow config and create constant array
    $steps = getWorkflowSteps();
    $stepsArray = [];
    foreach ($steps as $step) {
        $stepsArray[$step['step_number']] = $step['name'];
    }
    define('STEPS', $stepsArray);
}

function generateCandidateId(): string {
    // Try to use sequence-based ID generation first
    include_once __DIR__ . '/sequence.php';
    $sequenceId = generateCandidateIdFromSequence();
    
    // If we got a sequence-based ID, use it
    if (!empty($sequenceId)) {
        return $sequenceId;
    }
    
    // Fallback to random ID if no active sequence
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
        'resume' => $resumeFile,
        'step_data' => []
    ];

    saveCandidates($candidates);
    logRecruitmentAction($id, 'Profile Created', $_SESSION['user_id'] ?? 'system');

    // Send profile selected email if configured
    $step1Config = getStepConfig(1);
    if ($step1Config && $step1Config['send_email'] && $step1Config['email_template']) {
        $attachments = [];
        if ($resumeFile) {
            $attachments[] = __DIR__ . '/../uploads/resumes/' . $resumeFile;
        }
        sendTemplatedMail($id, $step1Config['email_template'], buildEmailVariables($candidates[$id]), $attachments);
    }

    return ['id' => $id, 'message' => 'Candidate created successfully'];
}

function getCandidate(string $id): ?array {
    $candidates = getCandidates();
    return $candidates[$id] ?? null;
}

function canMoveToStep(array $candidate, int $targetStep): bool {
    $currentStep = $candidate['current_step'] ?? 1;
    $settings = getWorkflowConfig()['settings'] ?? [];
    
    // Check if target is the next step (or any step if skipping allowed)
    if (!$settings['allow_skip_steps']) {
        if ($targetStep != $currentStep + 1) {
            return false;
        }
    }
    
    // Can only advance if in progress
    if ($candidate['status'] !== 'IN_PROGRESS') {
        return false;
    }
    
    // Target step must exist
    return getStepConfig($targetStep) !== null;
}

function moveToStep(string $id, int $step, array $data = []): bool {
    return processStepMove($id, $step, $data);
}

/**
 * Get form HTML for a specific step
 */
function getStepFormHtml(int $stepNumber, array $candidate = [], string $formId = 'stepForm'): string {
    $stepConfig = getStepConfig($stepNumber);
    if (!$stepConfig || !$stepConfig['has_form']) {
        return '<p class="no-data">No form required for this step</p>';
    }
    
    $formFields = $stepConfig['form_fields'] ?? [];
    $formType = $stepConfig['form_type'] ?? 'default';
    $totalSteps = getTotalSteps();
    $currentStep = $candidate['current_step'] ?? 1;
    
    $html = '<form method="post" enctype="multipart/form-data" id="' . $formId . '">';
    
    // Step selection dropdown - only show if there are forward steps available
    $forwardSteps = [];
    for ($s = $currentStep + 1; $s <= $totalSteps; $s++) {
        $forwardStepConfig = getStepConfig($s);
        if ($forwardStepConfig) {
            $forwardSteps[] = [
                'step_number' => $s,
                'name' => $forwardStepConfig['name']
            ];
        }
    }
    
    if (!empty($forwardSteps)) {
        $html .= '<div class="form-group">';
        $html .= '<label>Select Next Step <span class="required">*</span></label>';
        $html .= '<select name="step" required>';
        // Default to next immediate step
        $defaultStep = $currentStep + 1;
        $html .= '<option value="' . $defaultStep . '">' . htmlspecialchars(getStepConfig($defaultStep)['name'] ?? 'Step ' . $defaultStep) . '</option>';
        // Add other forward steps
        foreach ($forwardSteps as $fs) {
            if ($fs['step_number'] != $defaultStep) {
                $html .= '<option value="' . $fs['step_number'] . '">Step ' . $fs['step_number'] . ': ' . htmlspecialchars($fs['name']) . '</option>';
            }
        }
        $html .= '</select>';
        $html .= '</div>';
    } else {
        $html .= '<input type="hidden" name="step" value="' . $currentStep . '">';
    }
    
    foreach ($formFields as $field) {
        $name = $field['name'];
        $label = $field['label'] ?? $name;
        $type = $field['type'] ?? 'text';
        $required = $field['required'] ?? false;
        $value = $candidate[$name] ?? $field['default'] ?? '';
        $rows = $field['rows'] ?? 3;
        $accept = $field['accept'] ?? '';
        
        $html .= '<div class="form-group">';
        $html .= '<label>' . htmlspecialchars($label);
        if ($required) {
            $html .= ' <span class="required">*</span>';
        }
        $html .= '</label>';
        
        switch ($type) {
            case 'text':
                $html .= '<input type="text" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '"';
                if ($required) $html .= ' required';
                $html .= '>';
                break;
                
            case 'email':
                $html .= '<input type="email" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '"';
                if ($required) $html .= ' required';
                $html .= '>';
                break;
                
            case 'date':
                $html .= '<input type="date" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '"';
                if ($required) $html .= ' required';
                $html .= '>';
                break;
                
            case 'time':
                $html .= '<input type="time" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($value) . '"';
                if ($required) $html .= ' required';
                $html .= '>';
                break;
                
            case 'textarea':
                $html .= '<textarea name="' . htmlspecialchars($name) . '" rows="' . $rows . '"';
                if ($required) $html .= ' required';
                $html .= '>' . htmlspecialchars($value) . '</textarea>';
                break;
                
            case 'select':
                $html .= '<select name="' . htmlspecialchars($name) . '"';
                if ($required) $html .= ' required';
                $html .= '>';
                foreach ($field['options'] as $option) {
                    $optValue = $option['value'] ?? '';
                    $optLabel = $option['label'] ?? $optValue;
                    $selected = ($value == $optValue) ? ' selected' : '';
                    $html .= '<option value="' . htmlspecialchars($optValue) . '"' . $selected . '>' . htmlspecialchars($optLabel) . '</option>';
                }
                $html .= '</select>';
                break;
                
            case 'file':
                $html .= '<input type="file" name="' . htmlspecialchars($name) . '"';
                if ($accept) $html .= ' accept="' . htmlspecialchars($accept) . '"';
                if ($required) $html .= ' required';
                $html .= '>';
                break;
                
            case 'file_multiple':
                $html .= '<input type="file" name="' . htmlspecialchars($name) . '[]"';
                if ($accept) $html .= ' accept="' . htmlspecialchars($accept) . '"';
                $html .= ' multiple>';
                break;
                
            case 'radio':
                foreach ($field['options'] as $option) {
                    $optValue = $option['value'] ?? '';
                    $optLabel = $option['label'] ?? $optValue;
                    $checked = ($value == $optValue) ? ' checked' : '';
                    $html .= '<label class="radio-label">';
                    $html .= '<input type="radio" name="' . htmlspecialchars($name) . '" value="' . htmlspecialchars($optValue) . '"' . $checked;
                    if ($required && $optValue === $field['options'][0]['value']) {
                        $html .= ' required';
                    }
                    $html .= '> ' . htmlspecialchars($optLabel) . '</label>';
                }
                break;
        }
        
        $html .= '</div>';
    }
    
    $html .= '<button type="submit" name="move_step" class="btn btn-primary">✅ Submit</button>';
    $html .= '</form>';
    
    return $html;
}

/**
 * Get step navigation buttons
 */
function getStepNavigationHtml(array $candidate): string {
    $currentStep = $candidate['current_step'] ?? 1;
    $totalSteps = getTotalSteps();
    $stepConfig = getStepConfig($currentStep);
    
    $html = '<div class="step-navigation">';
    
    // Previous steps progress
    $html .= '<div class="step-progress">';
    $html .= '<span class="step-label">Step ' . $currentStep . ' of ' . $totalSteps . ': ' . htmlspecialchars($stepConfig['name'] ?? '') . '</span>';
    
    // Progress bar
    $progress = (($currentStep - 1) / ($totalSteps - 1)) * 100;
    $html .= '<div class="progress-bar">';
    $html .= '<div class="progress-fill" style="width: ' . $progress . '%"></div>';
    $html .= '</div>';
    $html .= '</div>';
    
    // Cancel button if allowed
    if ($stepConfig['can_cancel'] ?? false) {
        $html .= '<form method="post" style="display:inline;">';
        $html .= '<input type="hidden" name="step" value="' . $currentStep . '">';
        $html .= '<input type="hidden" name="choice" value="cancellation">';
        $html .= '<button type="submit" name="cancel_candidate" class="btn btn-danger" onclick="return confirm(\'Cancel this candidate?\')">❌ Cancel Candidate</button>';
        $html .= '</form>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Render workflow steps visualization
 */
function renderWorkflowSteps(array $candidate = [], bool $showActions = false): string {
    $currentStep = $candidate['current_step'] ?? 1;
    $steps = getWorkflowSteps();
    
    $html = '<div class="workflow-steps">';
    
    foreach ($steps as $index => $step) {
        $stepNum = $step['step_number'];
        $isCompleted = $currentStep > $stepNum;
        $isCurrent = $currentStep == $stepNum;
        
        $class = 'workflow-step';
        if ($isCompleted) $class .= ' completed';
        if ($isCurrent) $class .= ' current';
        
        $html .= '<div class="' . $class . '">';
        $html .= '<div class="step-indicator">';
        if ($isCompleted) {
            $html .= '✓';
        } else {
            $html .= $stepNum;
        }
        $html .= '</div>';
        $html .= '<div class="step-info">';
        $html .= '<div class="step-name">' . htmlspecialchars($step['name']) . '</div>';
        if ($step['description'] ?? '') {
            $html .= '<div class="step-desc">' . htmlspecialchars($step['description']) . '</div>';
        }
        $html .= '</div>';
        
        if ($showActions && $isCurrent) {
            $html .= '<span class="current-badge">Current</span>';
        }
        
        $html .= '</div>';
    }
    
    $html .= '</div>';
    
    return $html;
}

