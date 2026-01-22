<?php
include_once __DIR__ . '/../includes/helpers.php';
include_once __DIR__ . '/../includes/candidate.php';

$message = '';
$currentUserId = $_SESSION['user_id'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle bulk assignment to HR (CSV upload or pasted list)
    if (isset($_POST['assign_to_hr'])) {
        $hrId = $_POST['hr_id'] ?? '';
        $assignDate = $_POST['assign_date'] ?? date('Y-m-d');
        $defaultPosition = $_POST['default_position'] ?? 'Airport Ticket Executive';
        $lines = [];

        // Parse uploaded CSV or SpreadsheetML (.xls) if present
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['csv_file']['tmp_name'];
            $origName = $_FILES['csv_file']['name'];
            $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

            $content = file_get_contents($tmp);
            if (in_array($ext, ['xls','xml']) && stripos($content, '<Workbook') !== false) {
                // SpreadsheetML (Excel 2003 XML) parsing
                try {
                    $xml = simplexml_load_string($content);
                    if ($xml) {
                        // Try to find Data nodes under Workbook->Worksheet->Table->Row
                        $rowsXml = [];
                        if (isset($xml->Worksheet)) {
                            foreach ($xml->Worksheet as $ws) {
                                if (isset($ws->Table->Row)) {
                                    foreach ($ws->Table->Row as $r) {
                                        $rowsXml[] = $r;
                                    }
                                }
                            }
                        }

                        foreach ($rowsXml as $r) {
                            // take first Cell->Data or join cells
                            $vals = [];
                            if (isset($r->Cell)) {
                                foreach ($r->Cell as $c) {
                                    if (isset($c->Data)) $vals[] = trim((string)$c->Data);
                                }
                            }
                            $line = implode(' ', $vals);
                            if ($line !== '') $lines[] = $line;
                        }
                    }
                } catch (Exception $e) {
                    // fallback to CSV parsing below
                }
            } elseif (in_array($ext, ['xlsx'])) {
                // For .xlsx, try simple zip + sheet1 parse (basic implementation)
                try {
                    $zip = new ZipArchive();
                    if ($zip->open($tmp) === true) {
                        // attempt to read sharedStrings and sheet1
                        $shared = [];
                        if (($idx = $zip->locateName('xl/sharedStrings.xml')) !== false) {
                            $s = $zip->getFromIndex($idx);
                            $sx = simplexml_load_string($s);
                            if ($sx && isset($sx->si)) {
                                foreach ($sx->si as $si) {
                                    $shared[] = trim((string)$si->t);
                                }
                            }
                        }
                        if (($idx = $zip->locateName('xl/worksheets/sheet1.xml')) !== false) {
                            $s = $zip->getFromIndex($idx);
                            $sx = simplexml_load_string($s);
                            if ($sx && isset($sx->sheetData->row)) {
                                foreach ($sx->sheetData->row as $r) {
                                    $vals = [];
                                    foreach ($r->c as $c) {
                                        $t = (string)$c['t'];
                                        $v = isset($c->v) ? (string)$c->v : '';
                                        if ($t === 's' && $v !== '') {
                                            $i = (int)$v;
                                            $vals[] = $shared[$i] ?? $v;
                                        } else {
                                            $vals[] = $v;
                                        }
                                    }
                                    $line = implode(' ', array_map('trim', $vals));
                                    if ($line !== '') $lines[] = $line;
                                }
                            }
                        }
                        $zip->close();
                    }
                } catch (Exception $e) {
                    // fallback
                }
            } else {
                // default CSV/text parsing
                $rows = preg_split('/\r\n|\r|\n/', $content);
                foreach ($rows as $r) {
                    $r = trim($r);
                    if ($r !== '') $lines[] = $r;
                }
            }
        }

        // Parse pasted textarea (one id or email per line)
        if (!empty($_POST['paste_list'])) {
            $rows = preg_split('/\r\n|\r|\n/', $_POST['paste_list']);
            foreach ($rows as $r) {
                $r = trim($r);
                if ($r !== '') $lines[] = $r;
            }
        }

        $candidates = getCandidates();
        $updated = 0;
        $created = 0;
        
        foreach ($lines as $entry) {
            $entry = trim($entry);
            if (empty($entry)) continue;

            // Check if it's the format: name/phone/email/location/position or name/phone/email/location or just a simple identifier
            if (substr_count($entry, '/') >= 3) {
                // Parse as name/phone/email/location/position
                $parts = array_map('trim', explode('/', $entry));
                $name = $parts[0] ?? '';
                $phone = $parts[1] ?? '';
                $email = $parts[2] ?? '';
                $location = $parts[3] ?? '';
                $position = $parts[4] ?? $defaultPosition; // Position is 5th field, fallback to default

                if (!empty($name) && !empty($email)) {
                    // Check if candidate with this email already exists
                    $foundKey = null;
                    foreach ($candidates as $cid => $cand) {
                        if (strtolower($cand['email']) === strtolower($email)) {
                            $foundKey = $cid;
                            break;
                        }
                    }

                    // If not found, create new candidate
                    if (!$foundKey) {
                        include_once __DIR__ . '/../includes/candidate.php';
                        
                        $candId = generateCandidateId();
                        while (isset($candidates[$candId])) {
                            $candId = generateCandidateId();
                        }

                        $candidates[$candId] = [
                            'name' => $name,
                            'email' => $email,
                            'phone' => $phone,
                            'position' => $position ?: 'Not Specified',
                            'location' => $location ?: 'Not Specified',
                            'current_step' => 1,
                            'status' => 'IN_PROGRESS',
                            'documents' => [],
                            'interviews' => [],
                            'resume' => null,
                            'assigned_to' => $hrId,
                            'assigned_date' => $assignDate
                        ];
                        $created++;
                        $foundKey = $candId;
                    } else {
                        // Candidate exists, just assign
                        $candidates[$foundKey]['assigned_to'] = $hrId;
                        $candidates[$foundKey]['assigned_date'] = $assignDate;
                        // Update position and location if provided in new entry
                        $candidates[$foundKey]['position'] = $position ?: $candidates[$foundKey]['position'];
                        $candidates[$foundKey]['location'] = $location ?: $candidates[$foundKey]['location'];
                        $updated++;
                    }
                }
            } else {
                // Old format: just ID or email
                // Try by ID first
                if (isset($candidates[$entry])) {
                    $candidates[$entry]['assigned_to'] = $hrId;
                    $candidates[$entry]['assigned_date'] = $assignDate;
                    $updated++;
                    continue;
                }

                // Try by email (case-insensitive)
                $foundKey = null;
                foreach ($candidates as $cid => $cand) {
                    if (strtolower($cand['email']) === strtolower($entry)) {
                        $foundKey = $cid;
                        break;
                    }
                }
                if ($foundKey) {
                    $candidates[$foundKey]['assigned_to'] = $hrId;
                    $candidates[$foundKey]['assigned_date'] = $assignDate;
                    $updated++;
                }
            }
        }

        saveCandidates($candidates);
        $msgParts = [];
        if ($created > 0) $msgParts[] = "$created candidate(s) created";
        if ($updated > 0) $msgParts[] = "$updated candidate(s) assigned";
        $message = "Success: " . (implode(' and ', $msgParts) ?: "No changes made") . " to HR user $hrId on $assignDate";
    }
    if (isset($_POST['add_user'])) {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $_POST['role'];
        $users = getUsers();
        $id = 'U' . str_pad(count($users) + 1, 3, '0', STR_PAD_LEFT);
        $users[] = ['id' => $id, 'name' => $name, 'email' => $email, 'role' => $role, 'password' => $password, 'active' => true];
        saveUsers($users);
        $message = 'User added';
    } elseif (isset($_POST['toggle_user'])) {
        $id = $_POST['user_id'];
        $users = getUsers();
        foreach ($users as &$user) {
            if ($user['id'] === $id) {
                $user['active'] = !$user['active'];
                break;
            }
        }
        saveUsers($users);
        $message = 'User status updated';
    } elseif (isset($_POST['update_smtp'])) {
        $smtp = [
            'host' => $_POST['host'],
            'port' => (int)$_POST['port'],
            'username' => $_POST['username'],
            'password' => $_POST['password'],
            'from_email' => $_POST['from_email'],
            'from_name' => $_POST['from_name']
        ];
        file_put_contents(__DIR__ . '/../config/smtp.json', json_encode($smtp, JSON_PRETTY_PRINT));
        $message = 'SMTP updated';
    } elseif (isset($_POST['update_template'])) {
        $template = $_POST['template'];
        $content = $_POST['content'];
        file_put_contents(__DIR__ . '/../mail_templates/' . $template . '.html', $content);
        $message = 'Template updated';
    } elseif (isset($_POST['change_user_password'])) {
        $userId = $_POST['user_id'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        if (empty($newPassword) || empty($confirmPassword)) {
            $message = 'Password fields cannot be empty.';
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'Passwords do not match.';
        } elseif (strlen($newPassword) < 6) {
            $message = 'Password must be at least 6 characters.';
        } else {
            $users = getUsers();
            foreach ($users as &$user) {
                if ($user['id'] === $userId) {
                    $user['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
                    break;
                }
            }
            if (saveUsers($users)) {
                $message = 'Password changed successfully for user.';
            } else {
                $message = 'Failed to change password.';
            }
        }
    }
}

$users = getUsers();
$candidates = getCandidates();
$smtp = json_decode(file_get_contents(__DIR__ . '/../config/smtp.json'), true);
$templates = ['confirmation', 'cancellation', 'interview_schedule', 'profile_selected'];
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="../styles.css">
    <style>
        .tabs {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
        }
        .tab {
            flex: 1;
            text-align: center;
            padding: 15px;
            cursor: pointer;
            background: #e9ecef;
            border: 1px solid #ddd;
            border-bottom: none;
            transition: background 0.3s;
        }
        .tab:hover {
            background: #d1ecf1;
        }
        .tab.active {
            background: #fff;
            border-bottom: 1px solid #fff;
            color: #007bff;
            font-weight: bold;
        }
        .tab-content {
            display: none;
            padding: 20px;
            background: #fff;
            border: 1px solid #ddd;
            border-top: none;
        }
        .tab-input {
            display: none;
        }
        .tab-input:checked + .tab {
            background: #fff;
            border-bottom: 1px solid #fff;
            color: #007bff;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Admin Dashboard</h1>
        <a href="../index.php?page=logout">Logout</a>
        <?php if ($message) echo "<p class='message'>$message</p>"; ?>

        <div class="tabs">
            <input type="radio" name="tab" id="tab1" class="tab-input" checked>
            <label for="tab1" class="tab">Manage HR Users</label>
            <input type="radio" name="tab" id="tabUserMgmt" class="tab-input">
            <label for="tabUserMgmt" class="tab">User Management</label>
            <input type="radio" name="tab" id="tab2" class="tab-input">
            <label for="tab2" class="tab">All Candidates</label>
            <input type="radio" name="tab" id="tabAssign" class="tab-input">
            <label for="tabAssign" class="tab">Assign to HR</label>
            <input type="radio" name="tab" id="tab3" class="tab-input">
            <label for="tab3" class="tab">SMTP Config</label>
            <input type="radio" name="tab" id="tab4" class="tab-input">
            <label for="tab4" class="tab">Mail Templates</label>
        </div>

        <div class="tab-content" style="display: block;">
            <h2>Manage HR Users</h2>
            <form method="post">
                <label>Name: <input name="name" required></label>
                <label>Email: <input type="email" name="email" required></label>
                <label>Password: <input type="password" name="password" required></label>
                <label>Role: <select name="role"><option value="HR">HR</option></select></label>
                <button name="add_user">Add User</button>
            </form>
            <table class="responsive-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): if ($user['role'] === 'HR') { ?>
                        <tr>
                            <td data-label="Name"><?php echo $user['name']; ?></td>
                            <td data-label="Email"><?php echo $user['email']; ?></td>
                            <td data-label="Status"><?php echo $user['active'] ? 'Active' : 'Disabled'; ?></td>
                            <td data-label="Action">
                                <form method="post" style="display:inline">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button name="toggle_user"><?php echo $user['active'] ? 'Disable' : 'Enable'; ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php } endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- User Management Tab -->
        <div class="tab-content" id="tabUserMgmtContent">
            <h2>User Management</h2>
            <p style="margin-bottom: 20px; color: #666;">Manage all users (Admin and HR). Change passwords or login as other users.</p>
            
            <?php if ($message): ?>
                <p class="<?php echo strpos($message, 'successfully') !== false ? 'message' : 'error'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </p>
            <?php endif; ?>
            
            <table class="responsive-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                        <tr data-user-id="<?php echo $user['id']; ?>">
                            <td data-label="ID"><?php echo $user['id']; ?></td>
                            <td data-label="Name"><?php echo htmlspecialchars($user['name']); ?></td>
                            <td data-label="Email"><?php echo htmlspecialchars($user['email']); ?></td>
                            <td data-label="Role"><?php echo $user['role']; ?></td>
                            <td data-label="Status">
                                <span style="color: <?php echo $user['active'] ? '#27ae60' : '#e74c3c'; ?>;">
                                    <?php echo $user['active'] ? 'Active' : 'Disabled'; ?>
                                </span>
                            </td>
                            <td data-label="Actions">
                                <button type="button" class="action-btn" 
                                        style="background:#17a2b8; padding: 6px 12px; font-size: 0.85em;"
                                        onclick="openChangePasswordModal('<?php echo $user['id']; ?>', '<?php echo htmlspecialchars($user['name']); ?>')">
                                    Change Password
                                </button>
                                <?php if ($user['id'] !== $currentUserId): ?>
                                    <button type="button" class="action-btn" 
                                            style="background:#9b59b6; padding: 6px 12px; font-size: 0.85em;"
                                            onclick="loginAsUser('<?php echo $user['id']; ?>', '<?php echo htmlspecialchars($user['name']); ?>')">
                                        Login as User
                                    </button>
                                <?php else: ?>
                                    <span style="color: #999; font-size: 0.85em;">(Current User)</span>
                                <?php endif; ?>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" name="toggle_user" class="action-btn" 
                                            style="background:<?php echo $user['active'] ? '#e74c3c' : '#27ae60'; ?>; padding: 6px 12px; font-size: 0.85em;">
                                        <?php echo $user['active'] ? 'Disable' : 'Enable'; ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Change Password Modal -->
        <div id="changePasswordModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:99999; justify-content:center; align-items:center;">
            <div style="background:#fff; padding:20px; border-radius:8px; width:90%; max-width:480px; box-shadow:0 6px 30px rgba(0,0,0,0.2);">
                <h3 style="margin-top:0;">Change Password for <span id="passwordUserName"></span></h3>
                <form method="post" id="changePasswordForm">
                    <input type="hidden" name="user_id" id="passwordUserId">
                    <div style="margin-bottom:10px;">
                        <label style="font-weight:bold; display:block; margin-bottom:6px;">New Password *</label>
                        <input type="password" name="new_password" id="newPassword" required minlength="6" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                    </div>
                    <div style="margin-bottom:10px;">
                        <label style="font-weight:bold; display:block; margin-bottom:6px;">Confirm New Password *</label>
                        <input type="password" name="confirm_password" id="confirmPassword" required minlength="6" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;">
                    </div>
                    <div style="display:flex; gap:10px; justify-content:flex-end;">
                        <button type="button" onclick="closeChangePasswordModal()" style="background:#6c757d; color:#fff; padding:8px 12px; border-radius:4px; border:none;">Cancel</button>
                        <button type="submit" name="change_user_password" style="background:#28a745; color:#fff; padding:8px 12px; border-radius:4px; border:none;">Change Password</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Login As User Confirmation Modal -->
        <div id="loginAsModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:99999; justify-content:center; align-items:center;">
            <div style="background:#fff; padding:20px; border-radius:8px; width:90%; max-width:480px; box-shadow:0 6px 30px rgba(0,0,0,0.2);">
                <h3 style="margin-top:0;">Login as User</h3>
                <p>You are about to login as <strong id="loginAsUserName"></strong>.</p>
                <p style="color: #666; font-size: 0.9em;">This will open the user's dashboard in a new window. Your admin session will be preserved in this window.</p>
                <div style="display:flex; gap:10px; justify-content:flex-end;">
                    <button onclick="closeLoginAsModal()" style="background:#6c757d; color:#fff; padding:8px 12px; border-radius:4px; border:none;">Cancel</button>
                    <button onclick="confirmLoginAs()" style="background:#9b59b6; color:#fff; padding:8px 12px; border-radius:4px; border:none;">Login as User</button>
                </div>
            </div>
        </div>

        <div class="tab-content">
            <h2>All Candidates</h2>
            
            <!-- Search and Filter Controls -->
            <div class="search-filter-container" style="background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); margin-bottom: 20px;">
                <div style="display: flex; flex-wrap: wrap; gap: 15px; align-items: center;">
                    <div style="flex: 1; min-width: 200px;">
                        <label>Search: <input type="text" id="searchInput" placeholder="Search by name, email, position..." style="width: 100%;"></label>
                    </div>
                    <div>
                        <label>Status: 
                            <select id="statusFilter">
                                <option value="">All Status</option>
                                <option value="IN_PROGRESS">In Progress</option>
                                <option value="COMPLETED">Completed</option>
                                <option value="CANCELLED">Cancelled</option>
                            </select>
                        </label>
                    </div>
                    <div>
                        <label>Step: 
                            <select id="stepFilter">
                                <option value="">All Steps</option>
                                <?php for ($i = 1; $i <= 7; $i++): ?>
                                    <option value="<?php echo $i; ?>"><?php echo $i . ' - ' . STEPS[$i]; ?></option>
                                <?php endfor; ?>
                            </select>
                        </label>
                    </div>
                    <div>
                        <label>Sort by: 
                            <select id="sortBy">
                                <option value="name">Name</option>
                                <option value="current_step">Step</option>
                                <option value="status">Status</option>
                                <option value="assigned_date">Assigned Date</option>
                                <option value="created">ID</option>
                            </select>
                        </label>
                    </div>
                    <div>
                        <label>Order: 
                            <select id="sortOrder">
                                <option value="asc">Ascending</option>
                                <option value="desc">Descending</option>
                            </select>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Candidates Table -->
            <div class="candidates-table-container">
                <table class="candidates-table" id="candidatesTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Position</th>
                            <th>Location</th>
                            <th>Current Step</th>
                            <th>Status</th>
                            <th>Last Call</th>
                            <th>Call Remarks</th>
                            <th>Assigned To</th>
                            <th>Assigned Date</th>
                            <th>Resume</th>
                            <th>Documents</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="candidatesTableBody">
                        <?php foreach ($candidates as $id => $cand): ?>
                            <?php 
                                $assignedDate = $cand['assigned_date'] ?? '';
                                $assignedTo = $cand['assigned_to'] ?? '-';
                                $location = $cand['location'] ?? '-';
                            ?>
                            <tr data-id="<?php echo $id; ?>" data-name="<?php echo strtolower($cand['name']); ?>" data-email="<?php echo strtolower($cand['email']); ?>" data-position="<?php echo strtolower($cand['position']); ?>" data-status="<?php echo $cand['status']; ?>" data-step="<?php echo $cand['current_step']; ?>" data-assigned-date="<?php echo $assignedDate; ?>">
                                <td data-label="ID"><?php echo $id; ?></td>
                                <td data-label="Name"><?php echo $cand['name']; ?></td>
                                <td data-label="Email"><?php echo $cand['email']; ?></td>
                                <td data-label="Phone"><?php echo $cand['phone']; ?></td>
                                <td data-label="Position"><?php echo $cand['position']; ?></td>
                                <td data-label="Location"><?php echo $location; ?></td>
                                <td data-label="Current Step"><?php echo $cand['current_step'] . ' - ' . STEPS[$cand['current_step']]; ?></td>
                                <td data-label="Status">
                                    <span class="status-badge status-<?php echo strtolower($cand['status']); ?>"><?php echo $cand['status']; ?></span>
                                    <?php if (!empty($cand['last_call'])): ?>
                                        <?php 
                                        $lastResult = $cand['call_result'] ?? '';
                                        $isInterested = $lastResult === 'interested';
                                        $statusClass = $isInterested ? 'call-interested' : 'call-not-interested';
                                        $statusIcon = $isInterested ? '✓' : '✗';
                                        $statusText = $isInterested ? 'Interested' : 'Not Interested';
                                        ?>
                                        <div class="call-status-badge <?php echo $statusClass; ?>" style="font-size: 0.7em; margin-top: 4px; padding: 2px 6px; border-radius: 3px; display: inline-block;">
                                            <?php echo $statusIcon . ' ' . $statusText; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Last Call">
                                    <?php if (!empty($cand['last_call'])): ?>
                                        <?php echo date('d M Y, h:i A', strtotime($cand['last_call'])); ?>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Call Remarks">
                                    <?php 
                                    $remarks = '';
                                    if (!empty($cand['call_logs'][0]['remarks'])) {
                                        $remarks = $cand['call_logs'][0]['remarks'];
                                    } elseif (!empty($cand['call_remarks'])) {
                                        $remarks = $cand['call_remarks'];
                                    }
                                    ?>
                                    <?php if (!empty($remarks)): ?>
                                        <span title="<?php echo htmlspecialchars($remarks); ?>">
                                            <?php echo strlen($remarks) > 30 ? htmlspecialchars(substr($remarks, 0, 30)) . '...' : htmlspecialchars($remarks); ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Assigned To">
                                    <?php 
                                    // Show HR name if assigned
                                    $hrName = '-';
                                    if (!empty($assignedTo) && $assignedTo !== '-') {
                                        foreach ($users as $u) {
                                            if ($u['id'] === $assignedTo) {
                                                $hrName = $u['name'];
                                                break;
                                            }
                                        }
                                    }
                                    echo $assignedTo !== '-' ? $assignedTo . ' (' . $hrName . ')' : '-';
                                    ?>
                                </td>
                                <td data-label="Assigned Date"><?php echo !empty($assignedDate) ? $assignedDate : '-'; ?></td>
                                <td data-label="Resume">
                                    <?php if (!empty($cand['resume'])): ?>
                                        <a href="../uploads/resumes/<?php echo $cand['resume']; ?>" target="_blank" class="resume-link">View</a>
                                    <?php else: ?>
                                        <span class="no-resume">-</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Documents">
                                    <?php 
                                    $docCount = !empty($cand['documents']) ? count($cand['documents']) : 0;
                                    echo $docCount > 0 ? $docCount . ' file' . ($docCount > 1 ? 's' : '') : '-';
                                    ?>
                                </td>
                                <td data-label="Actions">
                                    <a href="../index.php?page=candidate_details&id=<?php echo $id; ?>" class="action-btn">View Details</a>
                                    <button type="button" class="action-btn call-now-btn" data-id="<?php echo $id; ?>" data-phone="<?php echo htmlspecialchars($cand['phone']); ?>" style="margin-left:8px; background:#ffc109;">Call Now</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Loading overlay -->
        <div class="loading-overlay" id="loadingOverlay">
            <div class="loader"></div>
        </div>

        <!-- Call Modal -->
        <div id="callModal" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(0,0,0,0.5); z-index:99999; justify-content:center; align-items:center;">
            <div style="background:#fff; padding:20px; border-radius:8px; width:90%; max-width:480px; box-shadow:0 6px 30px rgba(0,0,0,0.2);">
                <h3 style="margin-top:0;">Log Call</h3>
                <form id="callForm">
                    <input type="hidden" name="id" id="callCandidateId">
                    <div style="margin-bottom:10px;">
                        <label style="font-weight:bold; display:block; margin-bottom:6px;">Remarks (optional)</label>
                        <textarea name="remarks" id="callRemarks" rows="4" style="width:100%; padding:8px; border:1px solid #ddd; border-radius:4px;"></textarea>
                    </div>
                    <div style="display:flex; gap:10px; align-items:center; margin-bottom:10px;">
                        <label style="display:flex; align-items:center; gap:6px;"><input type="radio" name="result" value="interested" checked> Interested</label>
                        <label style="display:flex; align-items:center; gap:6px;"><input type="radio" name="result" value="not_interested"> Not Interested</label>
                    </div>
                    <div style="display:flex; gap:10px; justify-content:flex-end;">
                        <button type="button" id="callCancel" style="background:#6c757d; color:#fff; padding:8px 12px; border-radius:4px; border:none;">Cancel</button>
                        <button type="submit" style="background:#28a745; color:#fff; padding:8px 12px; border-radius:4px; border:none;">Save</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            // Tab switching logic
            document.querySelectorAll('.tab-input').forEach((input, index) => {
                input.addEventListener('change', () => {
                    document.querySelectorAll('.tab-content').forEach(content => content.style.display = 'none');
                    document.querySelectorAll('.tab-content')[index].style.display = 'block';
                });
            });

            // Show loader on form submission
            document.addEventListener('DOMContentLoaded', function() {
                const forms = document.querySelectorAll('form');
                forms.forEach(form => {
                    form.addEventListener('submit', function(e) {
                        // Don't show loading overlay for call form (it has its own handling)
                        if (form.id === 'callForm') {
                            return;
                        }
                        document.getElementById('loadingOverlay').style.display = 'flex';
                    });
                });

                // Search and Filter functionality for All Candidates
                const searchInput = document.getElementById('searchInput');
                const statusFilter = document.getElementById('statusFilter');
                const stepFilter = document.getElementById('stepFilter');
                const sortBy = document.getElementById('sortBy');
                const sortOrder = document.getElementById('sortOrder');
                const candidatesTableBody = document.getElementById('candidatesTableBody');
                const candidateRows = Array.from(document.querySelectorAll('#candidatesTableBody tr'));

                function filterAndSortCandidates() {
                    const searchTerm = searchInput.value.toLowerCase();
                    const statusValue = statusFilter.value;
                    const stepValue = stepFilter.value;
                    const sortByValue = sortBy.value;
                    const sortOrderValue = sortOrder.value;

                    // Filter candidates
                    const filteredCandidates = candidateRows.filter(row => {
                        const name = row.dataset.name;
                        const email = row.dataset.email;
                        const position = row.dataset.position;
                        const status = row.dataset.status;
                        const step = row.dataset.step;

                        const matchesSearch = searchTerm === '' || 
                            name.includes(searchTerm) || 
                            email.includes(searchTerm) || 
                            position.includes(searchTerm);

                        const matchesStatus = statusValue === '' || status === statusValue;
                        const matchesStep = stepValue === '' || step === stepValue;

                        return matchesSearch && matchesStatus && matchesStep;
                    });

                    // Sort candidates
                    filteredCandidates.sort((a, b) => {
                        let aValue, bValue;

                        switch (sortByValue) {
                            case 'name':
                                aValue = a.dataset.name;
                                bValue = b.dataset.name;
                                break;
                            case 'current_step':
                                aValue = parseInt(a.dataset.step);
                                bValue = parseInt(b.dataset.step);
                                break;
                            case 'status':
                                aValue = a.dataset.status;
                                bValue = b.dataset.status;
                                break;
                            case 'assigned_date':
                                aValue = a.dataset.assignedDate || '';
                                bValue = b.dataset.assignedDate || '';
                                break;
                            case 'created':
                                aValue = a.dataset.id;
                                bValue = b.dataset.id;
                                break;
                            default:
                                return 0;
                        }

                        if (sortOrderValue === 'asc') {
                            return aValue > bValue ? 1 : aValue < bValue ? -1 : 0;
                        } else {
                            return aValue < bValue ? 1 : aValue > bValue ? -1 : 0;
                        }
                    });

                    // Hide all rows first
                    candidateRows.forEach(row => row.style.display = 'none');

                    // Show filtered and sorted rows
                    filteredCandidates.forEach(row => row.style.display = '');

                    // Reorder in DOM
                    filteredCandidates.forEach(row => {
                        candidatesTableBody.appendChild(row);
                    });
                }

                // Add event listeners
                if (searchInput) searchInput.addEventListener('input', filterAndSortCandidates);
                if (statusFilter) statusFilter.addEventListener('change', filterAndSortCandidates);
                if (stepFilter) stepFilter.addEventListener('change', filterAndSortCandidates);
                if (sortBy) sortBy.addEventListener('change', filterAndSortCandidates);
                if (sortOrder) sortOrder.addEventListener('change', filterAndSortCandidates);

                // Initial sort
                if (candidatesTableBody && candidateRows.length > 0) {
                    filterAndSortCandidates();
                }

                // User Management Modal Functions
                var changePasswordModal = document.getElementById('changePasswordModal');
                var loginAsModal = document.getElementById('loginAsModal');
                var passwordUserIdInput = document.getElementById('passwordUserId');
                var passwordUserNameSpan = document.getElementById('passwordUserName');
                var loginAsUserNameSpan = document.getElementById('loginAsUserName');
                var pendingLoginAsUserId = null;

                window.openChangePasswordModal = function(userId, userName) {
                    passwordUserIdInput.value = userId;
                    passwordUserNameSpan.textContent = userName;
                    document.getElementById('newPassword').value = '';
                    document.getElementById('confirmPassword').value = '';
                    changePasswordModal.style.display = 'flex';
                };

                window.closeChangePasswordModal = function() {
                    changePasswordModal.style.display = 'none';
                };

                window.loginAsUser = function(userId, userName) {
                    pendingLoginAsUserId = userId;
                    loginAsUserNameSpan.textContent = userName;
                    loginAsModal.style.display = 'flex';
                };

                window.closeLoginAsModal = function() {
                    loginAsModal.style.display = 'none';
                    pendingLoginAsUserId = null;
                };

                window.confirmLoginAs = function() {
                    if (pendingLoginAsUserId) {
                        window.open('../index.php?page=login_as&user_id=' + pendingLoginAsUserId + '&action=login', '_blank');
                        closeLoginAsModal();
                    }
                };

                // Close modals when clicking outside
                if (changePasswordModal) {
                    changePasswordModal.addEventListener('click', function(e) {
                        if (e.target === changePasswordModal) {
                            closeChangePasswordModal();
                        }
                    });
                }

                if (loginAsModal) {
                    loginAsModal.addEventListener('click', function(e) {
                        if (e.target === loginAsModal) {
                            closeLoginAsModal();
                        }
                    });
                }

                // Form submission handlers for modals
                var changePasswordForm = document.getElementById('changePasswordForm');
                if (changePasswordForm) {
                    changePasswordForm.addEventListener('submit', function() {
                        document.getElementById('loadingOverlay').style.display = 'flex';
                    });
                }

                // Confirm password validation
                var newPasswordInput = document.getElementById('newPassword');
                var confirmPasswordInput = document.getElementById('confirmPassword');
                if (confirmPasswordInput && newPasswordInput) {
                    confirmPasswordInput.addEventListener('input', function() {
                        if (this.value !== newPasswordInput.value) {
                            this.setCustomValidity('Passwords do not match');
                        } else {
                            this.setCustomValidity('');
                        }
                    });
                }

                // Call Now modal handler
                const modal = document.getElementById('callModal');
                const callForm = document.getElementById('callForm');
                const callIdInput = document.getElementById('callCandidateId');
                const callRemarks = document.getElementById('callRemarks');
                const callCancel = document.getElementById('callCancel');

                function hideCallModal() {
                    modal.style.display = 'none';
                }

                function showCallModal() {
                    modal.style.display = 'flex';
                }

                const callButtons = document.querySelectorAll('.call-now-btn');
                callButtons.forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        const id = this.dataset.id;
                        const phone = this.dataset.phone;
                        
                        callIdInput.value = id;
                        callRemarks.value = '';
                        
                        // Open phone dialer
                        if (phone) {
                            window.location.href = 'tel:' + phone;
                        }
                        
                        showCallModal();
                        e.preventDefault();
                        return false;
                    });
                });

                callCancel.addEventListener('click', function(e) {
                    hideCallModal();
                    e.preventDefault();
                });

                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        hideCallModal();
                    }
                });

                const modalContent = modal.querySelector('div[style*="background:#fff"]');
                if (modalContent) {
                    modalContent.addEventListener('click', function(e) {
                        e.stopPropagation();
                    });
                }

                callForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(callForm);
                    const candidateId = formData.get('id');
                    const result = formData.get('result');
                    const remarks = formData.get('remarks');
                    
                    if (!candidateId || !result) {
                        alert('Error: Missing required fields');
                        return false;
                    }
                    
                    document.getElementById('loadingOverlay').style.display = 'flex';
                    
                    const submitBtn = callForm.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.textContent = 'Saving...';
                    }
                    
                    fetch('../hr/update_call.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Server error: ' + response.status);
                        }
                        return response.json();
                    })
                    .then(data => {
                        document.getElementById('loadingOverlay').style.display = 'none';
                        
                        if (data.success) {
                            hideCallModal();
                            
                            const row = document.querySelector('#candidatesTableBody tr[data-id="' + candidateId + '"]');
                            if (row) {
                                const statusCell = row.querySelector('td[data-label="Status"]');
                                const lastCallCell = row.querySelector('td[data-label="Last Call"]');
                                const remarksCell = row.querySelector('td[data-label="Call Remarks"]');
                                
                                // Update last call cell
                                if (lastCallCell) {
                                    lastCallCell.innerHTML = data.data.last_call ? new Date(data.data.last_call).toLocaleString() : '-';
                                }
                                
                                // Update remarks cell
                                if (remarksCell && data.data.remarks) {
                                    const shortRemarks = data.data.remarks.length > 30 ? 
                                        data.data.remarks.substring(0, 30) + '...' : data.data.remarks;
                                    remarksCell.innerHTML = '<span title="' + data.data.remarks + '">' + shortRemarks + '</span>';
                                }
                                
                                // Update status badge
                                if (statusCell) {
                                    const existingBadge = statusCell.querySelector('.call-status-badge');
                                    if (existingBadge) existingBadge.remove();
                                    
                                    const callInfo = document.createElement('div');
                                    callInfo.className = 'call-status-badge';
                                    callInfo.style.fontSize = '0.7em';
                                    callInfo.style.marginTop = '4px';
                                    callInfo.style.padding = '2px 6px';
                                    callInfo.style.borderRadius = '3px';
                                    callInfo.style.display = 'inline-block';
                                    
                                    if (result === 'interested') {
                                        callInfo.style.backgroundColor = '#d4edda';
                                        callInfo.style.color = '#155724';
                                        callInfo.style.border = '1px solid #c3e6cb';
                                        callInfo.innerHTML = '✓ Interested';
                                    } else {
                                        callInfo.style.backgroundColor = '#f8d7da';
                                        callInfo.style.color = '#721c24';
                                        callInfo.style.border = '1px solid #f5c6cb';
                                        callInfo.innerHTML = '✗ Not Interested';
                                    }
                                    statusCell.appendChild(callInfo);
                                }
                            }
                            
                            if (submitBtn) {
                                submitBtn.textContent = 'Saved!';
                                setTimeout(() => {
                                    submitBtn.disabled = false;
                                    submitBtn.textContent = 'Save';
                                }, 1500);
                            }
                        } else {
                            alert('Error: ' + (data.message || 'Failed to save call log'));
                            if (submitBtn) {
                                submitBtn.disabled = false;
                                submitBtn.textContent = 'Save';
                            }
                        }
                    })
                    .catch(err => {
                        document.getElementById('loadingOverlay').style.display = 'none';
                        console.error('Error saving call:', err);
                        alert('Request failed: ' + err.message);
                        if (submitBtn) {
                            submitBtn.disabled = false;
                            submitBtn.textContent = 'Save';
                        }
                    });
                    
                    return false;
                });
            });
        </script>

        <div class="tab-content">
            <h2>Assign Candidates to HR</h2>
            <p>Upload CSV/Excel or paste a list. Supports two formats:</p>
            <ul style="margin: 10px 0; padding-left: 20px;">
                <li><strong>Format 1:</strong> name/phone/email/location/position (creates new candidates, position is optional - will use default if not provided)</li>
                <li><strong>Format 2:</strong> candidate_id or email (assigns existing candidates)</li>
            </ul>
            <form method="post" enctype="multipart/form-data">
                <a href="../sample_assign.csv" download class="action-btn" style="background:#17a2b8; display:inline-block; margin-bottom:10px;">Download Sample (CSV)</a>
                <a href="../sample_assign.xls" download class="action-btn" style="background:#17a2b8; display:inline-block; margin-bottom:10px; margin-left:8px;">Download Sample (Excel)</a>
                <label>HR Assignee:
                    <select name="hr_id" required>
                        <option value="">Select HR</option>
                        <?php foreach ($users as $u): if ($u['role'] === 'HR'): ?>
                            <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name'] . ' (' . $u['email'] . ')'); ?></option>
                        <?php endif; endforeach; ?>
                    </select>
                </label>
                <label>Assigned Date: <input type="date" name="assign_date" value="<?php echo date('Y-m-d'); ?>" required></label>
                <label>Default Position (used when position not provided): <input type="text" name="default_position" placeholder="e.g., Software Developer, HR Manager" value="Airport Ticket Executive"></label>
                <label>Upload CSV/Excel: <input type="file" name="csv_file" accept=".csv,.xls,.xlsx"></label>
                <label>Or paste list (one id or email per line):</label>
                <textarea name="paste_list" rows="6" placeholder="Format 1: name/phone/email/location/position&#10;Vikas Sharma/9129206589/vikassharmagkp156@gmail.com/Gorakhpur/Software Developer&#10;&#10;Format 1 (without position - uses default):&#10;Vikas Sharma/9129206589/vikassharmagkp156@gmail.com/Gorakhpur&#10;&#10;Format 2: candidate_id or email (one per line)&#10;CAND001&#10;email@example.com"></textarea>
                <button name="assign_to_hr">Assign</button>
            </form>
        </div>

        <div class="tab-content">
            <h2>SMTP Configuration</h2>
            <form method="post">
                <label>Host: <input name="host" value="<?php echo $smtp['host']; ?>" required></label>
                <label>Port: <input type="number" name="port" value="<?php echo $smtp['port']; ?>" required></label>
                <label>Username: <input name="username" value="<?php echo $smtp['username']; ?>"></label>
                <label>Password: <input type="password" name="password" value="<?php echo $smtp['password']; ?>"></label>
                <label>From Email: <input type="email" name="from_email" value="<?php echo $smtp['from_email']; ?>" required></label>
                <label>From Name: <input name="from_name" value="<?php echo $smtp['from_name']; ?>" required></label>
                <button name="update_smtp">Update SMTP</button>
            </form>
        </div>

        <div class="tab-content">
            <h2>Mail Templates</h2>
            <?php foreach ($templates as $tpl): ?>
                <h3><?php echo ucfirst($tpl); ?></h3>
                <form method="post">
                    <input type="hidden" name="template" value="<?php echo $tpl; ?>">
                    <textarea name="content" rows="10"><?php echo htmlspecialchars(file_get_contents(__DIR__ . '/../mail_templates/' . $tpl . '.html')); ?></textarea>
                    <button name="update_template">Update</button>
                </form>
            <?php endforeach; ?>
        </div>
    </div>
    <script>
        document.querySelectorAll('.tab-input').forEach((input, index) => {
            input.addEventListener('change', () => {
                document.querySelectorAll('.tab-content').forEach(content => content.style.display = 'none');
                document.querySelectorAll('.tab-content')[index].style.display = 'block';
            });
        });

        // Show loader on form submission
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function() {
                    document.getElementById('loadingOverlay').style.display = 'flex';
                });
            });
        });
    </script>

    <!-- Loading overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loader"></div>
    </div>
</body>
</html>