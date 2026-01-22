# ProcessTracker - Recruitment Selection Workflow System

A comprehensive Core PHP application for managing multi-step recruitment processes with JSON-based storage, SMTP email automation, and role-based access control.

---

## 📋 Table of Contents

1. [Overview](#overview)
2. [Features](#features)
3. [User Roles](#user-roles)
4. [Workflow Steps](#workflow-steps)
5. [Call Log System](#call-log-system)
6. [Setup Instructions](#setup-instructions)
7. [File Structure](#file-structure)
8. [API Endpoints](#api-endpoints)
9. [Email Templates](#email-templates)
10. [Security](#security)
11. [Troubleshooting](#troubleshooting)

---

## Overview

ProcessTracker is designed to streamline the recruitment process by providing a structured workflow for tracking candidates through multiple stages. The system supports multiple HR users with role-based permissions, automated email notifications, and comprehensive logging.

### Key Highlights

- **No Database Required**: Uses JSON files for data storage
- **Role-Based Access**: Separate interfaces for Admin and HR roles
- **7-Step Workflow**: Structured progression from profile selection to final decision
- **Automated Emails**: SMTP-based notifications at each workflow stage
- **Call Logging**: Track candidate communication with multiple status options

---

## Features

### Admin Features
- **Dashboard**: Overview of all candidates and HR users
- **User Management**: Create, edit, disable/enable HR users
- **Candidate Assignment**: Assign candidates to specific HR staff
- **Bulk Import**: Import candidates via CSV or paste list
- **SMTP Configuration**: Configure email server settings
- **Email Templates**: Edit HTML email templates
- **Recruitment Logs**: View all actions taken on candidates
- **Profile Management**: Update own profile and password

### HR Features
- **Dashboard**: View assigned candidates and statistics
- **Candidate Management**: Create new candidates, upload resumes
- **Document Management**: Upload and manage candidate documents
- **Interview Scheduling**: Schedule 1st and 2nd round interviews
- **Call Logging**: Log call attempts with multiple result options
- **Email Automation**: Automatic emails at each workflow stage
- **Profile Management**: Update own profile details

### General Features
- **Responsive Design**: Mobile-friendly interface
- **Search & Filter**: Advanced search across all candidates
- **Sorting**: Multiple sort options for candidate lists
- **Activity Logging**: All actions logged for audit trail
- **Session Management**: Secure session-based authentication

---

## User Roles

### Administrator (ADMIN)
Full system access including:
- Managing HR user accounts
- Configuring SMTP settings
- Editing email templates
- Viewing all candidates across all HR users
- Bulk candidate assignment
- Access to recruitment logs

### HR User (HR)
Restricted access including:
- Managing only assigned candidates
- Progressing candidates through workflow
- Scheduling interviews
- Logging calls
- Uploading documents
- Sending automated emails

---

## Workflow Steps

The recruitment process follows a mandatory 7-step sequence:

| Step | Name | Description | Actions |
|------|------|-------------|---------|
| 1 | Profile Selection | Initial candidate entry | Create candidate, upload resume, send notification |
| 2 | Confirmation/Cancellation | Send offer or rejection | Generate PDF letter, send email, optionally cancel |
| 3 | Document Verification | Verify submitted documents | Upload documents, set verification status |
| 4 | 1st Round Interview | Schedule first interview | Set date/time/mode, send interview email |
| 5 | 1st Round Result | Record first interview outcome | Pass/Fail with remarks, send result email |
| 6 | 2nd Round Interview | Schedule final interview | Set date/time/mode, send interview email |
| 7 | 2nd Round Result | Record final outcome | Pass/Fail with remarks, complete or cancel |

### Status States

- **IN_PROGRESS**: Active candidate in workflow
- **COMPLETED**: Successfully completed all stages
- **CANCELLED**: Process terminated (rejection or withdrawal)

---

## Call Log System

### Call Result Options

The system supports three call result options:

| Option | Value | Icon | Color | Description |
|--------|-------|------|-------|-------------|
| Interested | `interested` | ✓ | Green | Candidate is interested in the position |
| Not Interested | `not_interested` | ✗ | Red | Candidate explicitly declined |
| Not Pick | `not_pick` | 📵 | Yellow/Orange | Candidate didn't answer/return calls |

### Data Structure

Call logs are stored in the candidate record as:

```json
{
  "call_logs": [
    {
      "timestamp": "2025-01-15 14:30:00",
      "result": "not_pick",
      "remarks": "Called twice, no response",
      "called_by": "HR001"
    }
  ],
  "last_call": "2025-01-15 14:30:00",
  "call_result": "not_pick"
}
```

### Features

- **Call History**: View all previous call attempts
- **Real-time Logging**: Log calls immediately via modal or candidate details page
- **Visual Indicators**: Color-coded badges showing last call result in candidate lists
- **Remarks**: Optional notes for each call log entry
- **Timestamps**: Automatic recording of when calls were made
- **Caller Tracking**: Records which HR user made each call

---

## Setup Instructions

### Prerequisites

- PHP 8.0 or higher
- Web server (Apache/Nginx)
- PHPMailer library (included in vendor/)
- Write permissions for `database/`, `uploads/`, and subdirectories

### Installation

1. **Clone/Download**: Place the application in your web root

2. **Set Permissions**:
   ```bash
   chmod 755 database/ uploads/ -R
   ```

3. **Configure SMTP**: Edit `config/smtp.json` or use Admin dashboard:
   ```json
   {
     "host": "smtp.gmail.com",
     "port": 587,
     "username": "your-email@gmail.com",
     "password": "your-app-password",
     "from_email": "noreply@yourcompany.com",
     "from_name": "ProcessTracker"
   }
   ```

4. **Default Admin Login**:
   - Email: `admin@company.com`
   - Password: `password`
   - **Change immediately after first login**

5. **Access**: Open `index.php` in your browser

---

## File Structure

```
ProcessTracker/
├── index.php              # Main entry point, routing
├── php.ini                # PHP configuration
├── README.md              # This file
├── composer.json          # Composer dependencies
├── composer.lock          # Composer lock file
│
├── admin/                 # Admin role pages
│   ├── dashboard.php      # Admin dashboard and all admin views
│
├── auth/                  # Authentication
│   ├── login.php          # Login page
│   └── login_as.php       # Impersonate other users (admin only)
│
├── config/                # Configuration files
│   └── smtp.json          # SMTP settings
│
├── database/              # JSON data storage
│   ├── users.json         # User accounts
│   ├── candidates.json    # Candidate records
│   └── recruitment_logs.json  # Audit log
│
├── hr/                    # HR role pages
│   ├── dashboard.php      # HR dashboard, candidate list, call modal
│   ├── candidate_details.php  # Candidate view, call log form
│   └── profile.php        # HR profile management
│
├── includes/              # Core functionality
│   ├── candidate.php      # Candidate workflow logic
│   ├── helpers.php        # JSON storage helpers
│   ├── mailer.php         # PHPMailer integration
│   └── upload.php         # File upload handling
│
├── mail_templates/        # HTML email templates
│   ├── confirmation.html
│   ├── cancellation.html
│   ├── interview_schedule.html
│   ├── interview_result.html
│   └── profile_selected.html
│
├── pdf_templates/         # PDF generation
│   ├── confirmation_template.pdf
│   └── README.md
│
├── styles.css             # Main stylesheet
│
├── test_email.php         # Email testing utility
├── test.html              # Test page
├── test.php               # Test page
│
└── uploads/               # File uploads (auto-created)
    ├── resumes/           # Candidate resumes
    ├── documents/         # Additional documents
    └── letters/           # Generated PDF letters
```

---

## API Endpoints

### `hr/update_call.php`

Save a new call log entry for a candidate.

**Method**: POST

**Parameters**:
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | string | Yes | Candidate ID (e.g., CAND001) |
| result | string | Yes | Call result: `interested`, `not_interested`, or `not_pick` |
| remarks | string | No | Call notes/remarks |

**Response** (JSON):
```json
{
  "success": true,
  "message": "Call logged successfully",
  "data": {
    "id": "CAND001",
    "result": "not_pick",
    "remarks": "Called twice, no response",
    "last_call": "2025-01-15 14:30:00",
    "call_logs": [...]
  }
}
```

**Example Usage**:
```javascript
// JavaScript (Fetch API)
fetch('hr/update_call.php', {
  method: 'POST',
  body: new FormData(form)
})
.then(response => response.json())
.then(data => console.log(data));
```

---

## Email Templates

All email templates are stored in `mail_templates/` as HTML files with placeholders:

### Available Placeholders

| Placeholder | Description |
|-------------|-------------|
| `{NAME}` | Candidate's full name |
| `{POSITION}` | Applied position |
| `{EMAIL}` | Candidate's email |
| `{PHONE}` | Candidate's phone |
| `{DATE}` | Interview date |
| `{TIME}` | Interview time |
| `{MODE}` | Interview mode (Online/Offline) |
| `{INTERVIEWER}` | Interviewer name |
| `{RESULT}` | Pass/Fail result |
| `{REMARKS}` | Interview remarks |

### Template Files

| File | Trigger | Purpose |
|------|---------|---------|
| `profile_selected.html` | Step 1 | Welcome candidate, profile created |
| `confirmation.html` | Step 2 (confirmation) | Offer confirmation with letter |
| `cancellation.html` | Step 2 (cancellation) | Rejection notification |
| `interview_schedule.html` | Steps 4, 6 | Interview scheduling |
| `interview_result.html` | Steps 5, 7 | Interview outcome |

---

## Security

### Authentication
- **Password Hashing**: Uses `password_hash()` with PHP's default algorithm
- **Session Management**: Server-side sessions with secure session handling
- **Role Verification**: Server-side role checks on all protected pages

### File Security
- **File Type Validation**: Restricted to PDF, JPG, PNG, DOC, DOCX
- **Size Limits**: Maximum file size enforced
- **File Locking**: JSON files locked during writes to prevent corruption

### Recommended Production Security

1. **HTTPS**: Always use HTTPS in production
2. **File Permissions**: Restrict `database/` and `uploads/` to web server user only
3. **SMTP Credentials**: Store SMTP password securely
4. **Session Config**: Configure proper session timeouts
5. **Input Validation**: Server-side validation on all inputs

---

## Troubleshooting

### Common Issues

#### Cannot Login
- Check default credentials: `admin@company.com` / `password`
- Ensure PHP session is working
- Check `users.json` exists in `database/`

#### Emails Not Sending
- Verify SMTP settings in `config/smtp.json`
- Check spam folder
- For Gmail, use App Password, not regular password
- Check server has network access to SMTP host

#### File Upload Errors
- Check `uploads/` directory exists and is writable
- Verify file size is under limit
- Check file type is allowed (PDF, JPG, PNG, DOC, DOCX)

#### Call Log Not Saving
- Ensure `database/candidates.json` is writable
- Check POST parameters include `id`, `result`, and `remarks`
- Verify `result` is one of: `interested`, `not_interested`, `not_pick`

#### JSON Errors
- Ensure `database/` directory is writable
- Check file permissions: `chmod 755 database/ -R`
- Verify JSON syntax in `smtp.json` (no trailing commas)

### Error Logs

Check PHP error log and browser console for debugging:
```bash
# PHP errors (check php.ini for location)
tail -f /var/log/apache2/error.log

# Application errors
# Check browser Network tab for fetch() responses
```

---

## Recent Updates

### Call Log Enhancement
Added "Not Pick" call result option (📵) to support tracking of unanswered calls:

- **New Value**: `not_pick` added as valid call result
- **Visual Feedback**: Yellow/orange color coding for easy identification
- **UI Options**: Three radio buttons in call forms
- **Display**: Dedicated icon (📵) in candidate lists and call history
- **CSS Classes**: `.call-log-entry.not-pick` and `.call-indicator.not-pick`

---

## License

This project is open source and available for use.

---

## Support

For issues and feature requests, please check the GitHub repository or contact the development team.

