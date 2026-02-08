
<?php
include_once __DIR__ . '/../includes/helpers.php';
include_once __DIR__ . '/../includes/candidate.php';

$message = '';
$currentUserId = $_SESSION['user_id'] ?? null;
$currentUserName = $_SESSION['user_name'] ?? 'Admin';

// Get active menu item from URL
$activeMenu = $_GET['menu'] ?? 'dashboard';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle bulk assignment to HR
    if (isset($_POST['assign_to_hr'])) {
        $hrId = $_POST['hr_id'] ?? '';
        $assignDate = $_POST['assign_date'] ?? date('Y-m-d');
        $defaultPosition = $_POST['default_position'] ?? 'Airport Ticket Executive';
        $lines = [];

        // Parse uploaded CSV or Excel
        if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['csv_file']['tmp_name'];
            $ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
            $content = file_get_contents($tmp);
            
            if ($ext === 'csv') {
                $rows = preg_split('/\r\n|\r|\n/', $content);
                foreach ($rows as $r) {
                    $r = trim($r);
                    if ($r !== '') $lines[] = $r;
                }
            }
        }

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

            if (substr_count($entry, '/') >= 3) {
                $parts = array_map('trim', explode('/', $entry));
                $name = $parts[0] ?? '';
                $phone = $parts[1] ?? '';
                $email = $parts[2] ?? '';
                $location = $parts[3] ?? '';
                $position = $parts[4] ?? $defaultPosition;

                if (!empty($name) && !empty($email)) {
                    $foundKey = null;
                    foreach ($candidates as $cid => $cand) {
                        if (strtolower($cand['email']) === strtolower($email)) {
                            $foundKey = $cid;
                            break;
                        }
                    }

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
                    } else {
                        $candidates[$foundKey]['assigned_to'] = $hrId;
                        $candidates[$foundKey]['assigned_date'] = $assignDate;
                        $updated++;
                    }
                }
            }
        }

        saveCandidates($candidates);
        $msgParts = [];
        if ($created > 0) $msgParts[] = "$created candidate(s) created";
        if ($updated > 0) $msgParts[] = "$updated candidate(s) assigned";
        $message = "Success: " . (implode(' and ', $msgParts) ?: "No changes made");
    }
    
    // Add User
    if (isset($_POST['add_user'])) {
        $name = $_POST['name'];
        $email = $_POST['email'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $_POST['role'];
        $users = getUsers();
        $id = 'U' . str_pad(count($users) + 1, 3, '0', STR_PAD_LEFT);
        $users[] = ['id' => $id, 'name' => $name, 'email' => $email, 'role' => $role, 'password' => $password, 'active' => true];
        saveUsers($users);
        $message = 'User added successfully';
    }
    
    // Toggle User Status
    if (isset($_POST['toggle_user'])) {
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
    }
    
    // Change Password
    if (isset($_POST['change_user_password'])) {
        $userId = $_POST['user_id'];
        $newPassword = $_POST['new_password'];
        $confirmPassword = $_POST['confirm_password'];
        
        if ($newPassword === $confirmPassword && strlen($newPassword) >= 6) {
            $users = getUsers();
            foreach ($users as &$user) {
                if ($user['id'] === $userId) {
                    $user['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
                    break;
                }
            }
            saveUsers($users);
            $message = 'Password changed successfully.';
        } else {
            $message = 'Password validation failed.';
        }
    }
    
    // Update SMTP
    if (isset($_POST['update_smtp'])) {
        $smtp = [
            'host' => $_POST['host'],
            'port' => (int)$_POST['port'],
            'username' => $_POST['username'],
            'password' => $_POST['password'],
            'from_email' => $_POST['from_email'],
            'from_name' => $_POST['from_name']
        ];
        file_put_contents(__DIR__ . '/../config/smtp.json', json_encode($smtp, JSON_PRETTY_PRINT));
        $message = 'SMTP configuration updated successfully';
    }
    
    // Update Template
    if (isset($_POST['update_template'])) {
        $template = $_POST['template'];
        $content = $_POST['content'];
        file_put_contents(__DIR__ . '/../mail_templates/' . $template . '.html', $content);
        $message = 'Template updated successfully';
    }
    
    // Update Profile
    if (isset($_POST['update_profile'])) {
        $name = $_POST['name'] ?? '';
        $email = $_POST['email'] ?? '';
        if (!empty($name) && !empty($email)) {
            $users = getUsers();
            foreach ($users as &$user) {
                if ($user['id'] === $currentUserId) {
                    $user['name'] = $name;
                    $user['email'] = $email;
                    $_SESSION['user_name'] = $name;
                    break;
                }
            }
            saveUsers($users);
            $message = 'Profile updated successfully';
        }
    }
}

$users = getUsers();
$candidates = getCandidates();
$smtp = json_decode(file_get_contents(__DIR__ . '/../config/smtp.json'), true);
$templates = ['confirmation', 'cancellation', 'interview_schedule', 'profile_selected'];

// Stats
$totalHr = count(array_filter($users, fn($u) => $u['role'] === 'HR'));
$totalCandidates = count($candidates);
$activeCandidates = count(array_filter($candidates, fn($c) => $c['status'] === 'IN_PROGRESS'));
$completedCandidates = count(array_filter($candidates, fn($c) => $c['status'] === 'COMPLETED'));
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - ProcessTracker</title>
    <link rel="stylesheet" href="../styles.css">
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>ProcessTracker</h2>
                <div class="user-info">Welcome, <?php echo htmlspecialchars($currentUserName); ?></div>
            </div>
            
            <ul class="menu">
                <li class="menu-item">
                    <a href="index.php?page=admin&menu=dashboard" class="menu-link <?php echo $activeMenu === 'dashboard' ? 'active' : ''; ?>">
                        <span class="icon">📊</span>
                        Dashboard
                    </a>
                </li>
                
                <!-- User Management Submenu -->
                <li class="menu-item">
                    <a href="javascript:void(0)" class="menu-link <?php echo in_array($activeMenu, ['users', 'add_user']) ? 'active expanded' : ''; ?>" onclick="toggleSubmenu('userManagement')">
                        <span class="icon">👥</span>
                        User Management
                        <span class="arrow">▶</span>
                    </a>
                    <ul class="submenu <?php echo in_array($activeMenu, ['users', 'add_user']) ? 'open' : ''; ?>" id="userManagement">
                        <li><a href="index.php?page=admin&menu=users" class="submenu-link <?php echo $activeMenu === 'users' ? 'active' : ''; ?>">All Users</a></li>
                        <li><a href="index.php?page=admin&menu=add_user" class="submenu-link <?php echo $activeMenu === 'add_user' ? 'active' : ''; ?>">Add New User</a></li>
                    </ul>
                </li>
                
                <li class="menu-item">
                    <a href="index.php?page=admin&menu=assign" class="menu-link <?php echo $activeMenu === 'assign' ? 'active' : ''; ?>">
                        <span class="icon">📋</span>
                        Assign Candidates
                    </a>
                </li>
                
                <li class="menu-item">
                    <a href="index.php?page=admin&menu=candidates" class="menu-link <?php echo $activeMenu === 'candidates' ? 'active' : ''; ?>">
                        <span class="icon">📝</span>
                        All Candidates
                    </a>
                </li>
                
                <li class="menu-item">
                    <a href="index.php?page=admin&menu=send_mail" class="menu-link <?php echo $activeMenu === 'send_mail' ? 'active' : ''; ?>">
                        <span class="icon">📧</span>
                        Send Mail
                    </a>
                </li>
                
                <li class="menu-item">
                    <a href="index.php?page=admin&menu=mail_logs" class="menu-link <?php echo $activeMenu === 'mail_logs' ? 'active' : ''; ?>">
                        <span class="icon">📋</span>
                        Mail Logs
                    </a>
                </li>
                
                <!-- Configuration Submenu -->
                <li class="menu-item">
                    <a href="javascript:void(0)" class="menu-link <?php echo in_array($activeMenu, ['smtp', 'templates', 'workflow', 'sequences']) ? 'active expanded' : ''; ?>" onclick="toggleSubmenu('config')">
                        <span class="icon">⚙️</span>
                        Configuration
                        <span class="arrow">▶</span>
                    </a>
                    <ul class="submenu <?php echo in_array($activeMenu, ['smtp', 'templates', 'workflow', 'sequences']) ? 'open' : ''; ?>" id="config">
                        <li><a href="index.php?page=admin&menu=smtp" class="submenu-link <?php echo $activeMenu === 'smtp' ? 'active' : ''; ?>">SMTP Config</a></li>
                        <li><a href="index.php?page=admin&menu=templates" class="submenu-link <?php echo $activeMenu === 'templates' ? 'active' : ''; ?>">Mail Templates</a></li>
                        <li><a href="index.php?page=admin&menu=workflow" class="submenu-link <?php echo $activeMenu === 'workflow' ? 'active' : ''; ?>">Workflow Manager</a></li>
                        <li><a href="index.php?page=admin&menu=sequences" class="submenu-link <?php echo $activeMenu === 'sequences' ? 'active' : ''; ?>">🔢 Sequence Manager</a></li>
                    </ul>
                </li>
                
                <li class="menu-item">
                    <a href="index.php?page=admin&menu=profile" class="menu-link <?php echo $activeMenu === 'profile' ? 'active' : ''; ?>">
                        <span class="icon">👤</span>
                        My Profile
                    </a>
                </li>
                
                <li class="menu-item">
                    <a href="../index.php?page=logout" class="menu-link">
                        <span class="icon">🚪</span>
                        Logout
                    </a>
                </li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Mobile Toggle -->
            <button class="sidebar-toggle" onclick="toggleSidebar()">☰</button>
            <div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleSidebar()"></div>

            <!-- Dashboard View -->
            <?php if ($activeMenu === 'dashboard'): ?>
                <div class="page-header">
                    <h1>📊 Dashboard Overview</h1>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-value"><?php echo $totalHr; ?></div>
                        <div class="stat-label">Total HR Users</div>
                    </div>
                    <div class="stat-card blue">
                        <div class="stat-value"><?php echo $totalCandidates; ?></div>
                        <div class="stat-label">Total Candidates</div>
                    </div>
                    <div class="stat-card orange">
                        <div class="stat-value"><?php echo $activeCandidates; ?></div>
                        <div class="stat-label">Active Candidates</div>
                    </div>
                    <div class="stat-card green">
                        <div class="stat-value"><?php echo $completedCandidates; ?></div>
                        <div class="stat-label">Completed</div>
                    </div>
                </div>

                <?php if ($message): ?>
                    <div class="message"><?php echo $message; ?></div>
                <?php endif; ?>

                <div class="content-card">
                    <h2>Quick Actions</h2>
                    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                        <a href="index.php?page=admin&menu=add_user" class="btn btn-primary">➕ Add New User</a>
                        <a href="index.php?page=admin&menu=assign" class="btn btn-success">📋 Assign Candidates</a>
                        <a href="index.php?page=admin&menu=candidates" class="btn btn-secondary">📝 View All Candidates</a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- All Users View -->
            <?php if ($activeMenu === 'users'): ?>
                <div class="page-header">
                    <h1>👥 All Users</h1>
                    <div class="header-actions">
                        <a href="index.php?page=admin&menu=add_user" class="btn btn-primary">➕ Add User</a>
                    </div>
                </div>
                
                <?php if ($message): ?>
                    <div class="message"><?php echo $message; ?></div>
                <?php endif; ?>

                <div class="content-card">
                    <div class="table-container">
                        <table class="data-table">
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
                                    <tr>
                                        <td><?php echo $user['id']; ?></td>
                                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo $user['role']; ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $user['active'] ? 'active' : 'disabled'; ?>">
                                                <?php echo $user['active'] ? 'Active' : 'Disabled'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-secondary" onclick="openChangePasswordModal('<?php echo $user['id']; ?>', '<?php echo htmlspecialchars($user['name']); ?>')">🔑 Password</button>
                                            <?php if ($user['id'] !== $currentUserId): ?>
                                                <a href="../index.php?page=login_as&user_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-secondary" target="_blank">👤 Login As</a>
                                            <?php endif; ?>
                                            <form method="post" style="display:inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="toggle_user" class="btn btn-sm <?php echo $user['active'] ? 'btn-danger' : 'btn-success'; ?>">
                                                    <?php echo $user['active'] ? 'Disable' : 'Enable'; ?>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Add User View -->
            <?php if ($activeMenu === 'add_user'): ?>
                <div class="page-header">
                    <h1>➕ Add New User</h1>
                </div>
                
                <?php if ($message): ?>
                    <div class="message"><?php echo $message; ?></div>
                <?php endif; ?>

                <div class="content-card">
                    <form method="post">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Full Name *</label>
                                <input type="text" name="name" required>
                            </div>
                            <div class="form-group">
                                <label>Email Address *</label>
                                <input type="email" name="email" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Password *</label>
                                <input type="password" name="password" required minlength="6">
                            </div>
                            <div class="form-group">
                                <label>Role *</label>
                                <select name="role" required>
                                    <option value="HR">HR</option>
                                    <option value="ADMIN">ADMIN</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" name="add_user" class="btn btn-primary">➕ Add User</button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Assign Candidates View -->
            <?php if ($activeMenu === 'assign'): ?>
                <div class="page-header">
                    <h1>📋 Assign Candidates to HR</h1>
                </div>
                
                <?php if ($message): ?>
                    <div class="message"><?php echo $message; ?></div>
                <?php endif; ?>

                <div class="content-card">
                    <form method="post" enctype="multipart/form-data">
                        <div class="form-row">
                            <div class="form-group">
                                <label>HR Assignee *</label>
                                <select name="hr_id" required>
                                    <option value="">Select HR</option>
                                    <?php foreach ($users as $u): if ($u['role'] === 'HR'): ?>
                                        <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option>
                                    <?php endif; endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Assigned Date *</label>
                                <input type="date" name="assign_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Default Position</label>
                            <input type="text" name="default_position" value="Airport Ticket Executive">
                        </div>
                        
                        <div class="form-group">
                            <label>Upload CSV File</label>
                            <input type="file" name="csv_file" accept=".csv">
                        </div>
                        
                        <div class="form-group">
                            <label>Or Paste List (name/phone/email/location/position per line)</label>
                            <textarea name="paste_list" rows="6" placeholder="John Doe/9876543210/john@example.com/Delhi/Developer"></textarea>
                        </div>
                        
                        <button type="submit" name="assign_to_hr" class="btn btn-success">📋 Assign Candidates</button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- All Candidates View -->
            <?php if ($activeMenu === 'candidates'): ?>
                <div class="page-header">
                    <h1>📝 All Candidates</h1>
                    <div class="header-actions">
                        <a href="index.php?page=admin&menu=dashboard" class="btn btn-secondary">← Back to Dashboard</a>
                    </div>
                </div>
                
                <!-- Search and Filter -->
                <div class="search-filter-bar">
                    <div class="search-group">
                        <input type="text" id="searchInput" placeholder="Search by ID, Name, Email, Phone..." class="search-input">
                    </div>
                    <select id="statusFilter" class="filter-select">
                        <option value="">All Status</option>
                        <option value="IN_PROGRESS">In Progress</option>
                        <option value="COMPLETED">Completed</option>
                        <option value="CANCELLED">Cancelled</option>
                    </select>
                    <select id="hrFilter" class="filter-select">
                        <option value="">All HR</option>
                        <?php foreach ($users as $u): if ($u['role'] === 'HR'): ?>
                            <option value="<?php echo $u['id']; ?>"><?php echo htmlspecialchars($u['name']); ?></option>
                        <?php endif; endforeach; ?>
                    </select>
                    <input type="date" id="dateFrom" class="filter-select" placeholder="From Date">
                    <input type="date" id="dateTo" class="filter-select" placeholder="To Date">
                    <select id="sortBy" class="filter-select">
                        <option value="name">Sort by Name</option>
                        <option value="assigned_date">Sort by Date</option>
                        <option value="status">Sort by Status</option>
                    </select>
                    <select id="sortOrder" class="filter-select">
                        <option value="asc">Ascending</option>
                        <option value="desc">Descending</option>
                    </select>
                    <button type="button" id="clearFilters" class="btn btn-secondary btn-sm">Clear</button>
                </div>
                
                <div class="content-card">
                    <div class="table-container">
                        <table class="data-table" id="candidatesTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Position</th>
                                    <th>Step</th>
                                    <th>Status</th>
                                    <th>Assigned Date</th>
                                    <th>Assigned To</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="candidatesTableBody">
                                <?php foreach ($candidates as $id => $cand): ?>
                                    <?php 
                                        $assignedTo = $cand['assigned_to'] ?? '-';
                                        $assignedDate = $cand['assigned_date'] ?? '';
                                        $hrName = '-';
                                        if (!empty($assignedTo) && $assignedTo !== '-') {
                                            foreach ($users as $u) {
                                                if ($u['id'] === $assignedTo) {
                                                    $hrName = $u['name'];
                                                    break;
                                                }
                                            }
                                        }
                                    ?>
                                    <tr data-id="<?php echo $id; ?>"
                                        data-name="<?php echo strtolower($cand['name']); ?>"
                                        data-email="<?php echo strtolower($cand['email']); ?>"
                                        data-phone="<?php echo strtolower($cand['phone']); ?>"
                                        data-position="<?php echo strtolower($cand['position']); ?>"
                                        data-status="<?php echo $cand['status']; ?>"
                                        data-step="<?php echo $cand['current_step']; ?>"
                                        data-hr="<?php echo $assignedTo; ?>"
                                        data-assigned-date="<?php echo $assignedDate; ?>">
                                        <td data-label="ID"><?php echo $id; ?></td>
                                        <td data-label="Name"><?php echo htmlspecialchars($cand['name']); ?></td>
                                        <td data-label="Email"><?php echo htmlspecialchars($cand['email']); ?></td>
                                        <td data-label="Phone"><?php echo $cand['phone']; ?></td>
                                        <td data-label="Position"><?php echo htmlspecialchars($cand['position']); ?></td>
                                        <td data-label="Step"><?php echo $cand['current_step'] . ' - ' . STEPS[$cand['current_step']]; ?></td>
                                        <td data-label="Status">
                                            <span class="status-badge status-<?php echo strtolower($cand['status']); ?>">
                                                <?php echo $cand['status']; ?>
                                            </span>
                                        </td>
                                        <td data-label="Assigned Date">
                                            <?php if (!empty($assignedDate)): ?>
                                                <?php echo date('d M Y', strtotime($assignedDate)); ?>
                                            <?php else: ?>
                                                <span class="no-data">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Assigned To"><?php echo $assignedTo !== '-' ? $assignedTo . ' (' . $hrName . ')' : '-'; ?></td>
                                        <td data-label="Actions" class="actions-cell">
                                            <a href="../index.php?page=candidate_details&id=<?php echo $id; ?>" class="btn btn-sm btn-secondary">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- SMTP Config View -->
            <?php if ($activeMenu === 'smtp'): ?>
                <div class="page-header">
                    <h1>⚙️ SMTP Configuration</h1>
                </div>
                
                <?php if ($message): ?>
                    <div class="message"><?php echo $message; ?></div>
                <?php endif; ?>

                <div class="content-card">
                    <form method="post">
                        <div class="form-row">
                            <div class="form-group">
                                <label>SMTP Host *</label>
                                <input type="text" name="host" value="<?php echo htmlspecialchars($smtp['host']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>SMTP Port *</label>
                                <input type="number" name="port" value="<?php echo $smtp['port']; ?>" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Username</label>
                                <input type="text" name="username" value="<?php echo htmlspecialchars($smtp['username']); ?>">
                            </div>
                            <div class="form-group">
                                <label>Password</label>
                                <input type="password" name="password" value="<?php echo htmlspecialchars($smtp['password']); ?>">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>From Email *</label>
                                <input type="email" name="from_email" value="<?php echo htmlspecialchars($smtp['from_email']); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>From Name *</label>
                                <input type="text" name="from_name" value="<?php echo htmlspecialchars($smtp['from_name']); ?>" required>
                            </div>
                        </div>
                        <button type="submit" name="update_smtp" class="btn btn-primary">💾 Update SMTP</button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Mail Templates View -->
            <?php if ($activeMenu === 'templates'): ?>
                <div class="page-header">
                    <h1>📧 Mail Templates</h1>
                </div>
                
                <?php if ($message): ?>
                    <div class="message"><?php echo $message; ?></div>
                <?php endif; ?>

                <?php foreach ($templates as $tpl): ?>
                    <div class="content-card">
                        <h3><?php echo ucfirst(str_replace('_', ' ', $tpl)); ?></h3>
                        <form method="post">
                            <input type="hidden" name="template" value="<?php echo $tpl; ?>">
                            <div class="form-group">
                                <textarea name="content" rows="8" style="font-family: monospace;"><?php echo htmlspecialchars(file_get_contents(__DIR__ . '/../mail_templates/' . $tpl . '.html')); ?></textarea>
                            </div>
                            <button type="submit" name="update_template" class="btn btn-primary">💾 Update Template</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Profile View -->
            <?php if ($activeMenu === 'profile'): ?>
                <div class="page-header">
                    <h1>👤 My Profile</h1>
                </div>
                
                <?php 
                $currentUserData = null;
                foreach ($users as $user) {
                    if ($user['id'] === $currentUserId) {
                        $currentUserData = $user;
                        break;
                    }
                }
                ?>
                
                <?php if ($message): ?>
                    <div class="message"><?php echo $message; ?></div>
                <?php endif; ?>
                
                <div class="content-card">
                    <h3>Profile Information</h3>
                    <form method="post">
                        <div class="form-row">
                            <div class="form-group">
                                <label>User ID</label>
                                <input type="text" value="<?php echo $currentUserData['id'] ?? ''; ?>" disabled style="background: #f5f5f5;">
                            </div>
                            <div class="form-group">
                                <label>Role</label>
                                <input type="text" value="<?php echo $currentUserData['role'] ?? ''; ?>" disabled style="background: #f5f5f5;">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>Name</label>
                                <input type="text" name="name" value="<?php echo htmlspecialchars($currentUserData['name'] ?? ''); ?>" required>
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($currentUserData['email'] ?? ''); ?>" required>
                            </div>
                        </div>
                        <button type="submit" name="update_profile" class="btn btn-primary">💾 Update Profile</button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Workflow Manager View -->
            <?php if ($activeMenu === 'workflow'): ?>
                <?php 
                // Include the workflow manager page
                ob_start();
                include __DIR__ . '/workflow_manager.php';
                $workflowContent = ob_get_clean();
                echo $workflowContent;
                ?>
            <?php endif; ?>

            <!-- Sequence Manager View -->
            <?php if ($activeMenu === 'sequences'): ?>
                <?php 
                // Include the sequence manager page
                ob_start();
                include __DIR__ . '/sequence_manager.php';
                $sequenceContent = ob_get_clean();
                echo $sequenceContent;
                ?>
            <?php endif; ?>

            <!-- Send Mail View -->
            <?php if ($activeMenu === 'send_mail'): ?>
                <?php 
                // Include the send mail page
                ob_start();
                include __DIR__ . '/send_mail.php';
                $sendMailContent = ob_get_clean();
                echo $sendMailContent;
                ?>
            <?php endif; ?>

            <!-- Mail Logs View -->
            <?php if ($activeMenu === 'mail_logs'): ?>
                <?php 
                // Include the mail logs page
                ob_start();
                include __DIR__ . '/mail_logs.php';
                $mailLogsContent = ob_get_clean();
                echo $mailLogsContent;
                ?>
            <?php endif; ?>
        </main>
    </div>

    <!-- Change Password Modal -->
    <div class="modal-overlay" id="changePasswordModal">
        <div class="modal">
            <h3>Change Password</h3>
            <form method="post" id="changePasswordForm">
                <input type="hidden" name="user_id" id="passwordUserId">
                <div class="form-group">
                    <label>New Password *</label>
                    <input type="password" name="new_password" id="newPassword" required minlength="6">
                </div>
                <div class="form-group">
                    <label>Confirm New Password *</label>
                    <input type="password" name="confirm_password" id="confirmPassword" required minlength="6">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeChangePasswordModal()">Cancel</button>
                    <button type="submit" name="change_user_password" class="btn btn-primary">Change Password</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loader"></div>
    </div>

    <script>
        function toggleSubmenu(id) {
            const submenu = document.getElementById(id);
            const link = submenu.previousElementSibling;
            submenu.classList.toggle('open');
            link.classList.toggle('expanded');
        }

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebarOverlay').classList.toggle('open');
        }

        function openChangePasswordModal(userId, userName) {
            document.getElementById('passwordUserId').value = userId;
            document.getElementById('changePasswordModal').classList.add('open');
        }

        function closeChangePasswordModal() {
            document.getElementById('changePasswordModal').classList.remove('open');
        }

        document.getElementById('changePasswordModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeChangePasswordModal();
            }
        });

        document.getElementById('confirmPassword').addEventListener('input', function() {
            if (this.value !== document.getElementById('newPassword').value) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                if (form.id !== 'changePasswordForm') {
                    document.getElementById('loadingOverlay').style.display = 'flex';
                }
            });
        });
        
        // Candidate table filtering
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const hrFilter = document.getElementById('hrFilter');
        const dateFrom = document.getElementById('dateFrom');
        const dateTo = document.getElementById('dateTo');
        const sortBy = document.getElementById('sortBy');
        const sortOrder = document.getElementById('sortOrder');
        const clearFilters = document.getElementById('clearFilters');
        const candidatesTableBody = document.getElementById('candidatesTableBody');
        
        if (searchInput && candidatesTableBody) {
            const rows = Array.from(document.querySelectorAll('#candidatesTableBody tr'));
            
            function filterCandidates() {
                const search = searchInput.value.toLowerCase().trim();
                const status = statusFilter.value;
                const hr = hrFilter.value;
                const fromDate = dateFrom.value;
                const toDate = dateTo.value;
                const sortByValue = sortBy ? sortBy.value : 'name';
                const sortOrderValue = sortOrder ? sortOrder.value : 'asc';
                
                // Filter rows
                rows.forEach(row => {
                    const id = row.dataset.id || '';
                    const name = row.dataset.name || '';
                    const email = row.dataset.email || '';
                    const phone = row.dataset.phone || '';
                    const position = row.dataset.position || '';
                    const rowStatus = row.dataset.status || '';
                    const rowHr = row.dataset.hr || '';
                    const assignedDate = row.dataset.assignedDate || '';
                    
                    // Search by ID, Name, Email, or Phone
                    const matchesSearch = !search || 
                        id.toLowerCase().includes(search) ||
                        name.includes(search) || 
                        email.includes(search) || 
                        phone.includes(search) ||
                        position.includes(search);
                    
                    const matchesStatus = !status || rowStatus === status;
                    const matchesHr = !hr || rowHr === hr;
                    
                    // Date range filtering
                    let matchesDate = true;
                    if (fromDate && assignedDate < fromDate) {
                        matchesDate = false;
                    }
                    if (toDate && assignedDate > toDate) {
                        matchesDate = false;
                    }
                    if (!fromDate && !toDate) {
                        matchesDate = true;
                    }
                    
                    row.style.display = (matchesSearch && matchesStatus && matchesHr && matchesDate) ? '' : 'none';
                });
                
                // Sort rows
                const visibleRows = rows.filter(row => row.style.display !== 'none');
                visibleRows.sort((a, b) => {
                    let aValue, bValue;
                    
                    switch (sortByValue) {
                        case 'name':
                            aValue = a.dataset.name || '';
                            bValue = b.dataset.name || '';
                            break;
                        case 'assigned_date':
                            aValue = a.dataset.assignedDate || '';
                            bValue = b.dataset.assignedDate || '';
                            break;
                        case 'status':
                            aValue = a.dataset.status || '';
                            bValue = b.dataset.status || '';
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
                
                // Reorder in DOM
                visibleRows.forEach(row => {
                    candidatesTableBody.appendChild(row);
                });
            }
            
            if (searchInput) searchInput.addEventListener('input', filterCandidates);
            if (statusFilter) statusFilter.addEventListener('change', filterCandidates);
            if (hrFilter) hrFilter.addEventListener('change', filterCandidates);
            if (dateFrom) dateFrom.addEventListener('change', filterCandidates);
            if (dateTo) dateTo.addEventListener('change', filterCandidates);
            if (sortBy) sortBy.addEventListener('change', filterCandidates);
            if (sortOrder) sortOrder.addEventListener('change', filterCandidates);
            
            if (clearFilters) {
                clearFilters.addEventListener('click', function() {
                    searchInput.value = '';
                    statusFilter.value = '';
                    hrFilter.value = '';
                    if (dateFrom) dateFrom.value = '';
                    if (dateTo) dateTo.value = '';
                    if (sortBy) sortBy.value = 'name';
                    if (sortOrder) sortOrder.value = 'asc';
                    filterCandidates();
                });
            }
        }
    </script>
    
    <style>
        /* Mobile-friendly table styles */
        @media (max-width: 992px) {
            .data-table thead {
                display: none;
            }
            
            .data-table, .data-table tbody, .data-table tr, .data-table td {
                display: block;
                width: 100%;
            }
            
            .data-table tr {
                margin-bottom: 15px;
                border: 1px solid #e1e5e9;
                border-radius: 8px;
                overflow: hidden;
                background: #fff;
            }
            
            .data-table td {
                padding: 10px 15px;
                text-align: left;
                border-bottom: 1px solid #eee;
                position: relative;
                padding-left: 45%;
            }
            
            .data-table td:last-child {
                border-bottom: none;
            }
            
            .data-table td::before {
                content: attr(data-label);
                position: absolute;
                left: 15px;
                font-weight: 600;
                color: #7f8c8d;
                font-size: 0.85em;
            }
            
            .data-table td[data-label="Actions"] {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
                background: #f8f9fa;
                padding: 12px 15px;
            }
            
            .data-table td[data-label="Actions"]::before {
                display: none;
            }
            
            .actions-cell {
                justify-content: flex-start;
            }
        }
        
        @media (max-width: 768px) {
            .search-filter-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-input, .filter-select {
                width: 100%;
            }
        }
    </style>
</body>
</html>

