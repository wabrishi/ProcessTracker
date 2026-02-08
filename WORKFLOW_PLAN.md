# Plan: Fully Customizable Workflow System

## Current System Limitations
1. **Hardcoded Steps**: `STEPS` constant with fixed 7 steps
2. **Hardcoded Logic**: `moveToStep()` function with switch cases for each step
3. **Fixed Email Templates**: Templates cannot be assigned per step dynamically
4. **No Custom Fields**: Each step has predefined form inputs

## Proposed Solution

### 1. Workflow Configuration File
Create `config/workflow.json`:
```json
{
  "steps": [
    {
      "step_number": 1,
      "name": "Profile Selection",
      "description": "Create candidate profile",
      "send_email": true,
      "email_template": "profile_selected",
      "email_subject": "Welcome to Our Recruitment Process",
      "has_form": true,
      "form_type": "candidate_creation",
      "auto_advance": false,
      "can_cancel": true
    },
    {
      "step_number": 2,
      "name": "Offer Letter",
      "description": "Send offer confirmation or rejection",
      "send_email": true,
      "email_template": "offer_letter",
      "email_subject": "Offer Letter - {POSITION}",
      "has_form": true,
      "form_type": "choice",
      "form_fields": [
        {"name": "choice", "type": "select", "options": ["confirmation", "cancellation"], "required": true},
        {"name": "letter", "type": "file", "accept": ".pdf", "required": false}
      ],
      "auto_advance": false,
      "can_cancel": true,
      "cancel_status": "CANCELLED"
    }
  ],
  "settings": {
    "default_step": 1,
    "enable_workflow_history": true,
    "require_email_for_all_steps": false
  }
}
```

### 2. New Database Schema Updates
- Add `workflow_config` to candidates for tracking custom fields per step
- Add `step_data` JSON field to store step-specific data

### 3. Core Function Changes
- `getWorkflowConfig()` - Load workflow from JSON
- `getStepConfig($stepNumber)` - Get specific step config
- `getCurrentStepConfig($candidate)` - Get current step with form fields
- `moveToStep($id, $step, $data)` - Dynamic form handling

### 4. Admin UI Pages
- **Workflow Manager**: Create, edit, delete workflow steps
- **Drag & Drop Reordering**: Reorder steps easily
- **Template Assignment**: Assign email templates to each step
- **Form Builder**: Configure custom fields per step

### 5. Dynamic Form Rendering
Update candidate_details.php to render forms based on step config:
- Text inputs
- Select dropdowns
- File uploads
- Radio buttons
- Textareas
- Date/time pickers

### 6. Email Template System
- Each step can have its own template
- Subject line customizable
- Dynamic placeholders support

## Files to Create/Modify

### New Files:
1. `config/workflow.json` - Default workflow configuration
2. `includes/workflow.php` - Workflow management functions
3. `admin/workflow_manager.php` - Admin UI for workflow configuration

### Modified Files:
1. `includes/candidate.php` - Use dynamic workflow config
2. `includes/helpers.php` - Add workflow helper functions
3. `hr/candidate_details.php` - Dynamic form rendering
4. `admin/dashboard.php` - Add workflow management menu item

## Implementation Steps
- [ ] Create workflow.json config file
- [ ] Create includes/workflow.php with dynamic functions
- [ ] Update includes/candidate.php to use dynamic workflow
- [ ] Create admin/workflow_manager.php for configuration
- [ ] Update hr/candidate_details.php for dynamic forms
- [ ] Update admin dashboard menu
- [ ] Create default email templates for new steps
- [ ] Update README with workflow documentation

## Benefits
1. **Flexible**: Add/remove/reorder steps as needed
2. **Customizable**: Each step can have unique form fields
3. **Email Control**: Per-step email templates and subjects
4. **Scalable**: Support any number of workflow steps
5. **User Friendly**: Admin UI for non-technical users

