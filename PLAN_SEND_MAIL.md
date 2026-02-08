# Plan: Send Mail Feature Implementation

## Overview
Add a new screen to send emails to candidates with:
- Select Candidate dropdown
- Select Template dropdown
- Dynamic form to fill placeholder values
- Send button
- Email logging

## Files to Create/Modify

### New Files to Create:
1. **database/mail_logs.json** - Store sent email logs
2. **hr/send_mail.php** - HR email sending interface
3. **admin/send_mail.php** - Admin email sending interface
4. **includes/mail_sender.php** - Helper functions for sending custom emails

### Files to Modify:
1. **includes/mailer.php** - Add function for custom templated emails
2. **includes/helpers.php** - Add mail-related helper functions
3. **admin/dashboard.php** - Add "Send Mail" menu item
4. **hr/dashboard.php** - Add "Send Mail" menu item

## Implementation Steps

### Step 1: Create Mail Logs Database
- Create `database/mail_logs.json` with structure:
```json
[
  {
    "id": "ML001",
    "candidate_id": "KRS-0001",
    "candidate_name": "John Doe",
    "candidate_email": "john@example.com",
    "template_name": "offer_letter",
    "subject": "Offer Letter for Airport Ticket Executive",
    "to_email": "john@example.com",
    "placeholders": {"POSITION": "Airport Ticket Executive", "DATE": "2026-01-24"},
    "sent_by": "U001",
    "sent_by_name": "Admin",
    "sent_at": "2026-01-24 10:22:02",
    "status": "SENT"
  }
]
```

### Step 2: Create Mail Sender Helper (includes/mail_sender.php)
Functions needed:
- `getAvailableTemplates()` - List all HTML templates
- `getTemplatePlaceholders($templateName)` - Extract placeholders from template
- `sendCustomMail($candidateId, $templateName, $placeholders)` - Send email with custom placeholders
- `logMail($candidateId, $templateName, $subject, $placeholders, $status)` - Log email to database

### Step 3: Update mailer.php
Add function:
- `sendCustomTemplatedMail($to, $subject, $templateName, $placeholders)` - For sending from UI

### Step 4: Create HR Send Mail Page (hr/send_mail.php)
Features:
- Select candidate from dropdown (filter by assigned HR)
- Select template from dropdown
- Display placeholder fields dynamically
- Preview email content
- Send button
- Success/Error messages

### Step 5: Create Admin Send Mail Page (admin/send_mail.php)
Features:
- Select candidate from dropdown (all candidates)
- Select template from dropdown
- Display placeholder fields dynamically
- Preview email content
- Send button
- Success/Error messages

### Step 6: Update Sidebar Menus
Add "📧 Send Mail" menu item in both:
- Admin sidebar (under Configuration or as main menu)
- HR sidebar (as main menu)

## Template Placeholder System

### Available Templates and Their Placeholders:

1. **profile_selected.html**
   - {NAME}, {POSITION}

2. **offer_letter.html**
   - {NAME}, {POSITION}

3. **interview_schedule.html**
   - {NAME}, {POSITION}, {DATE}, {TIME}, {MODE}, {INTERVIEWER}

4. **interview_result.html**
   - {NAME}, {POSITION}, {RESULT}, {REMARKS}

5. **confirmation.html**
   - {NAME}, {POSITION}

6. **cancellation.html**
   - {NAME}, {POSITION}

### Custom Placeholders:
Allow users to define custom placeholders like:
- {COMPANY_NAME}
- {HR_NAME}
- {SALARY}
- {JOINING_DATE}
- etc.

## User Interface Design

### Send Mail Form:
```
┌─────────────────────────────────────────────────┐
│  📧 Send Email                                   │
├─────────────────────────────────────────────────┤
│  Select Candidate *                              │
│  [Dropdown with ID - Name - Email]              │
├─────────────────────────────────────────────────┤
│  Select Template *                               │
│  [Dropdown with template names]                  │
├─────────────────────────────────────────────────┤
│  Fill Placeholder Values                         │
│  ┌─────────────────────────────────────────────┐ │
│  │ Name: [John Doe]     (auto-filled)          │ │
│  │ Position: [Developer] (auto-filled)          │ │
│  │ Date: [2026-01-24]                          │ │
│  │ Time: [10:00 AM]                            │ │
│  │ Mode: [In-Person ▼]                         │ │
│  │ Interviewer: [John]                         │ │
│  │ Remarks: [Textarea]                         │ │
│  └─────────────────────────────────────────────┘ │
├─────────────────────────────────────────────────┤
│  [ Preview ]  [ Send Email ]                     │
└─────────────────────────────────────────────────┘
```

## Database Schema

### mail_logs.json:
```json
{
  "id": "ML001",
  "candidate_id": "KRS-0001",
  "candidate_name": "John Doe",
  "candidate_email": "john@example.com",
  "template_name": "offer_letter",
  "subject": "Offer Letter for Airport Ticket Executive",
  "to_email": "john@example.com",
  "placeholders": {"NAME": "John", "POSITION": "Developer"},
  "sent_by": "U001",
  "sent_by_name": "Admin",
  "sent_at": "2026-01-24 10:22:02",
  "status": "SENT",
  "error_message": null
}
```

## Testing Checklist

- [ ] Can select candidate from dropdown
- [ ] Can select template
- [ ] Placeholders are auto-filled from candidate data
- [ ] Can edit placeholder values
- [ ] Email sends successfully
- [ ] Email logs are saved
- [ ] Admin can send to any candidate
- [ ] HR can send only to assigned candidates
- [ ] Error handling works (invalid template, send failure)

## Estimated Files to Create/Modify:
- New files: 4
- Modified files: 4

