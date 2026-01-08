# ProcessTracker - Recruitment Selection Workflow System

A Core PHP application for managing multi-step recruitment processes with JSON storage, SMTP email sending, and role-based access.

## Features

- **Admin Role**: Manage HR users, configure SMTP, edit email templates, view all candidates and logs.
- **HR Role**: Create candidates, upload documents, schedule interviews, send emails at each step.
- **Workflow Enforcement**: Mandatory 7-step sequence with no skipping.
- **JSON Storage**: All data stored in JSON files with file locking.
- **SMTP Emails**: HTML emails with attachments using PHPMailer.
- **Session Auth**: Password-hashed authentication.

## Setup

1. **Prerequisites**: PHP 8.0+, web server (e.g., Apache/Nginx), PHPMailer (already included in `vendor/`).

2. **Directory Permissions**: Ensure `database/`, `uploads/`, and subdirs are writable by the web server.

3. **Default Admin Login**:
   - Email: `admin@company.com`
   - Password: `password` (change after first login via hashing).

4. **SMTP Configuration**: Edit `config/smtp.json` or use Admin dashboard to set SMTP settings for email sending.

5. **Run**: Point your web server document root to this directory. Access `index.php`.

## Workflow Steps

1. Profile Selection (create candidate, upload resume, send notification)
2. Confirmation Letter OR Cancellation Letter (choose, send email with attachment)
3. Document Verification (upload docs, set status)
4. 1st Round Interview – Schedule (set date/time/mode, send email)
5. 1st Round Interview – Result (selected/rejected, remarks)
6. 2nd Round Interview – Schedule (similar to 4)
7. 2nd Round Interview – Result (final decision)

## File Structure

- `database/`: JSON files for users, candidates, logs
- `uploads/`: Subdirs for resumes, documents, letters
- `mail_templates/`: HTML email templates
- `config/`: SMTP config
- `includes/`: Helper functions
- `auth/`, `admin/`, `hr/`: Role-specific pages

## Security

- Password hashing with `password_hash`
- Session-based auth
- File type/size validation
- JSON file locking to prevent corruption

## Notes

- No database/framework used as per requirements.
- Emails logged in `recruitment_logs.json`.
- For production, secure SMTP credentials and file permissions.