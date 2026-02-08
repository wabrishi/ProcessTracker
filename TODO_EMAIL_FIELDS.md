# TODO: Update Workflow Email Fields

## Task: Check the workflow and update the field in workflow according to the Email template Place holder

### Files to Update:
1. [x] config/workflow.json - Update email subjects with placeholders
2. [x] includes/workflow.php - Enhance buildEmailVariables() function

### Progress:
- [x] Step 1: Update config/workflow.json email subjects
- [x] Step 2: Update includes/workflow.php buildEmailVariables function
- [x] Step 3: Verify all templates work correctly

### Email Template Placeholders Found:
- interview_result.html: {NAME}, {POSITION}, {RESULT}, {REMARKS}
- interview_schedule.html: {NAME}, {POSITION}, {DATE}, {TIME}, {MODE}, {INTERVIEWER}
- offer_letter.html: {NAME}, {POSITION}
- profile_selected.html: {NAME}, {POSITION}
- confirmation.html: {NAME}, {POSITION}
- cancellation.html: {NAME}, {POSITION}

### Changes Made:

**config/workflow.json:**
- Step 5 (1st Round Interview): Updated email_subject to "Interview Schedule - {POSITION} - 1st Round"
- Step 5: Added form_fields for date, time, mode, interviewer
- Step 6 (1st Round Result): Updated email_subject to "{POSITION} 1st Round Result - {RESULT}"
- Step 6: Added form_fields for result and remarks
- Step 7 (2nd Round Interview): Updated email_subject to "Interview Schedule - {POSITION} - 2nd Round"
- Step 7: Added form_fields for date, time, mode, interviewer
- Step 8 (Final Selection): Updated email_subject to "{POSITION} Final Selection - {RESULT}"
- Step 8: Added form_fields for result and remarks

**includes/workflow.php:**
- Enhanced buildEmailVariables() to properly handle:
  - RESULT and REMARKS for interview result templates
  - DATE, TIME, MODE, INTERVIEWER for interview schedule templates
  - Stored interview data from candidate records

