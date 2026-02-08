# TODO: Dynamic Step Transition with Email Details

## Task: When moving from Step 4 to Step 5, ask for email template details

### Objective:
- When current stage is Document Verification (4) and user selects 1st Round Interview (5)
- Show form fields required for Step 5 email template: Date, Time, Mode, Interviewer

### Files to Update:
1. [x] includes/workflow.php - Add getTargetStepFormHtml() function
2. [x] hr/get_step_fields.php - Create API endpoint for dynamic form fields
3. [x] hr/candidate_details.php - Add JavaScript for dynamic form field updates

### Implementation Status:

#### ✅ Step 1: includes/workflow.php
- Added `getTargetStepFormHtml()` function
- Returns form HTML with all required fields for target step
- Includes email notice for steps that send emails

#### ✅ Step 2: hr/get_step_fields.php
- Created API endpoint
- Returns JSON with step name, form HTML, email details

#### ✅ Step 3: hr/candidate_details.php
- Added hidden container for dynamic step fields
- Added JavaScript to detect step dropdown changes
- Added AJAX call to fetch form fields when step changes
- Shows loading indicator while fetching

### How It Works:
1. User is on Document Verification (Step 4)
2. User changes "Select Next Step" dropdown to "1st Round Interview (5)"
3. JavaScript detects the change and triggers AJAX call
4. API returns form fields: Date, Time, Mode, Interviewer
5. Dynamic form section appears showing these fields
6. User fills in the interview details
7. When submitted, email is sent with all template placeholders filled

### Email Template Fields by Step:
- Step 5 (1st Round Interview): {DATE}, {TIME}, {MODE}, {INTERVIEWER}
- Step 6 (1st Round Result): {RESULT}, {REMARKS}
- Step 7 (2nd Round Interview): {DATE}, {TIME}, {MODE}, {INTERVIEWER}
- Step 8 (Final Selection): {RESULT}, {REMARKS}

