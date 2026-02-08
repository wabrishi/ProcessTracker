# TODO: Send Mail Feature Implementation

## Step 1: Create Mail Logs Database
- [x] Create `database/mail_logs.json` with initial empty structure

## Step 2: Create Mail Sender Helper Functions
- [x] Create `includes/mail_sender.php` with helper functions:
  - [x] `getAvailableTemplates()` - List all HTML templates
  - [x] `getTemplatePlaceholders($templateName)` - Extract placeholders from template
  - [x] `parseTemplate($templateName, $placeholders)` - Parse template with values
  - [x] `sendCustomMail($candidateId, $templateName, $placeholders, $subject)` - Send email with custom placeholders
  - [x] `logMail($data)` - Log email to database
  - [x] `getMailLogs($candidateId = null)` - Get mail logs

## Step 3: Update mailer.php
- [ ] Add `sendCustomTemplatedMail($to, $subject, $templateName, $placeholders, $attachments = [])` function

## Step 4: Update helpers.php
- [ ] Add `getMailLogs($candidateId = null)` function
- [ ] Add `appendToMailLogs($entry)` function

## Step 5: Create HR Send Mail Page
- [x] Create `hr/send_mail.php` with:
  - [x] Candidate dropdown (filtered by assigned HR)
  - [x] Template dropdown
  - [x] Dynamic placeholder form
  - [x] Email preview
  - [x] Send button

## Step 6: Create Admin Send Mail Page
- [x] Create `admin/send_mail.php` with:
  - [x] Candidate dropdown (all candidates)
  - [x] Template dropdown
  - [x] Dynamic placeholder form
  - [x] Email preview
  - [x] Send button

## Step 7: Update Admin Dashboard Menu
- [x] Add "📧 Send Mail" menu item in admin/dashboard.php sidebar

## Step 8: Update HR Dashboard Menu
- [x] Add "📧 Send Mail" menu item in hr/dashboard.php sidebar

## Step 9: Create Admin Mail Logs Page
- [x] Create `admin/mail_logs.php` to view sent email history

## Step 10: Testing
- [ ] Test HR can send email to assigned candidates
- [ ] Test Admin can send email to any candidate
- [ ] Test email logging works correctly
- [ ] Test placeholder replacement works
- [ ] Test error handling

---
## Progress: 8/10 Steps Completed (Core features done)
## Started: 2026-01-28

