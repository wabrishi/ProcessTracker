# TODO - Customizable Workflow System

## Phase 1: Configuration & Core Functions ✅
- [x] Create config/workflow.json with default workflow configuration
- [x] Create includes/workflow.php with workflow management functions
- [x] Update includes/helpers.php with workflow helper functions

## Phase 2: Workflow Engine ✅
- [x] Update includes/candidate.php to use dynamic workflow
- [x] Implement dynamic step validation
- [x] Implement dynamic email sending based on step config

## Phase 3: Admin Interface ✅
- [x] Create admin/workflow_manager.php for workflow configuration
- [x] Add workflow management to admin sidebar menu
- [x] Implement step CRUD (Create, Read, Update, Delete)
- [x] Implement form field configuration per step

## Phase 4: Dynamic Forms (HR Interface) ✅
- [x] Update hr/candidate_details.php for dynamic form rendering
- [x] Create email template editor per step

## Phase 5: Testing & Documentation
- [x] Update README with workflow documentation (section added)
- [x] Create default email templates for all steps

## Phase 6: Bug Fixes & Enhancements (Completed)
- [x] Fix syntax error in admin/workflow_manager.php (malformed `</html>` tag)
- [x] Standardize email template placeholders to `{NAME}`, `{POSITION}`, `{LOCATION}` format
- [x] Add email template preview modal in workflow manager
- [x] Add step reordering with up/down buttons
- [x] Add workflow copy/duplicate functionality
- [x] Update renderTemplate function to support both legacy and new placeholder formats

## Status: COMPLETED ✅

---

## Summary of Changes

### Files Modified:
1. **admin/workflow_manager.php** - Added template preview, step reordering, workflow copy
2. **includes/mailer.php** - Updated renderTemplate for both placeholder formats
3. **mail_templates/confirmation.html** - Standardized placeholders
4. **mail_templates/cancellation.html** - Standardized placeholders
5. **mail_templates/interview_result.html** - Standardized placeholders
6. **mail_templates/profile_selected.html** - Standardized placeholders
7. **mail_templates/interview_schedule.html** - Standardized placeholders

### New Features:
✅ **Email Template Preview** - Click "👁️ Preview" button to see how emails will look
✅ **Step Reordering** - Use ⬆️ ⬇️ buttons to reorder workflow steps
✅ **Workflow Copy** - Click "📋 Copy Workflow" to duplicate the current workflow
✅ **Backward Compatibility** - Supports both `{{name}}` and `{NAME}` placeholder formats

### Placeholder Standard:
All templates now use uppercase placeholders in braces:
- `{NAME}` - Candidate's full name
- `{POSITION}` - Applied position
- `{LOCATION}` - Work location
- `{EMAIL}` - Candidate's email
- `{PHONE}` - Candidate's phone
- `{DATE}` - Interview date
- `{TIME}` - Interview time
- `{MODE}` - Interview mode (Online/Offline)
- `{INTERVIEWER}` - Interviewer name
- `{RESULT}` - Pass/Fail result
- `{REMARKS}` - Interview remarks

---

## Usage Instructions

### Email Template Preview
1. Go to Admin → Workflow Manager
2. When adding/editing a step, select an email template
3. A "👁️ Preview" button appears next to the dropdown
4. Click it to see the template with sample data

### Step Reordering
1. In the "Manage Steps" section
2. Use ⬆️ button to move a step up
3. Use ⬇️ button to move a step down
4. Steps will be renumbered automatically

### Copy Workflow
1. Click "📋 Copy Workflow" in the Workflow Overview
2. Enter a name for the new workflow
3. A copy is created with all steps preserved

