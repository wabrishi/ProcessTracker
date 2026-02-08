# Workflow System - Implementation Plan

## Current State Analysis
The ProcessTracker has a comprehensive workflow system with:
- 7 configurable steps in `config/workflow.json`
- Full workflow management in `includes/workflow.php`
- Admin UI in `admin/workflow_manager.php`
- Dynamic forms and email integration in `hr/candidate_details.php`

## Issues Identified
1. ❌ Syntax error in `admin/workflow_manager.php` (malformed `</html>` tag)
2. ❌ Inconsistent email template placeholders (`{NAME}` vs `{{name}}`)
3. ❌ Missing email template preview in workflow manager
4. ❌ Step reordering UI not user-friendly
5. ❌ No workflow copy/duplicate functionality

## Implementation Plan

### Phase 1: Fix Critical Issues
- [ ] Fix `</html>` syntax error in `admin/workflow_manager.php`
- [ ] Standardize all email templates to use `{NAME}`, `{POSITION}`, `{LOCATION}` placeholders

### Phase 2: Enhance Workflow Manager UI
- [ ] Add email template preview modal in workflow manager
- [ ] Improve step reordering with visual drag-and-drop interface
- [ ] Add workflow copy/duplicate functionality
- [ ] Add workflow statistics (candidates per step)

### Phase 3: Test & Validate
- [ ] Test workflow step CRUD operations
- [ ] Test email template rendering
- [ ] Verify placeholders work correctly
- [ ] Test step reordering functionality

---

## Detailed Tasks

### Task 1: Fix Syntax Error in admin/workflow_manager.php
**File**: `admin/workflow_manager.php`
**Issue**: Malformed `</html>` tag in JavaScript section (line ~350)
**Fix**: Remove the extra `</html>` closing tag

### Task 2: Standardize Email Template Placeholders
**Files**: All templates in `mail_templates/`
**Standard Format**: `{NAME}`, `{POSITION}`, `{LOCATION}`, `{EMAIL}`, `{PHONE}`, `{DATE}`, `{TIME}`, `{MODE}`, `{INTERVIEWER}`, `{RESULT}`, `{REMARKS}`

**Templates to Update**:
- [ ] `confirmation.html` - Convert `{{name}}` → `{NAME}`
- [ ] `cancellation.html` - Convert `{{name}}` → `{NAME}`
- [ ] `interview_result.html` - Convert `{{name}}`, `{{position}}`, `{{result}}`, `{{remarks}}`
- [ ] `profile_selected.html` - Convert `{{name}}`, `{{position}}`
- [ ] Keep `offer_letter.html` as is (already uses `{NAME}`, `{POSITION}`)
- [ ] Keep `interview_schedule.html` as is (already uses `{name}}`, `{position}}`, `{date}}`, `{time}}`, `{mode}}`, `{interviewer}}`)

### Task 3: Add Template Preview Feature
**File**: `admin/workflow_manager.php`
**Feature**: Modal to preview how email templates will look with sample data
**Implementation**:
- Add "Preview" button next to email template dropdown
- Create JavaScript function to render template preview
- Show sample candidate data (Name, Position, Location, etc.)

### Task 4: Improve Step Reordering UI
**File**: `admin/workflow_manager.php`
**Enhancement**: Visual drag-and-drop or up/down buttons
**Implementation**:
- Add up/down arrows for each step
- Show current order clearly
- Save order on change

### Task 5: Add Workflow Copy Functionality
**File**: `admin/workflow_manager.php`
**Feature**: Duplicate current workflow with new name
**Implementation**:
- Add "Copy Workflow" button
- Prompt for new workflow name
- Create copy with modified name

---

## Implementation Notes

### Placeholder Standardization Pattern
```html
<!-- Before (inconsistent) -->
{{name}}, {{position}}

<!-- After (standardized) -->
{NAME}, {POSITION}
```

### Template Preview Data Structure
```php
$sampleData = [
    'NAME' => 'John Doe',
    'POSITION' => 'Software Engineer',
    'LOCATION' => 'Mumbai, India',
    'EMAIL' => 'john@example.com',
    'PHONE' => '+91-9876543210',
    'DATE' => '2025-01-20',
    'TIME' => '10:00 AM',
    'MODE' => 'Online',
    'INTERVIEWER' => 'Jane Smith',
    'RESULT' => 'Pass',
    'REMARKS' => 'Excellent technical skills demonstrated'
];
```

---

## Status: IN PROGRESS

