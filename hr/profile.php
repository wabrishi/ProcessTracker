<?php
include_once __DIR__ . '/../includes/helpers.php';

$message = '';
$currentUserId = $_SESSION['user_id'] ?? null;
$currentUserName = $_SESSION['user_name'] ?? 'HR User';
$currentUser = null;

// Get current user data
if ($currentUserId) {
    $users = getUsers();
    foreach ($users as $user) {
        if ($user['id'] === $currentUserId) {
            $currentUser = $user;
            break;
        }
    }
}

if (!$currentUser) {
    header('Location: ../index.php?page=login');
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        if (empty($name) || empty($email)) {
            $message = 'Name and email are required.';
        } else {
            $users = getUsers();
            foreach ($users as &$user) {
                if ($user['id'] === $currentUserId) {
                    $user['name'] = $name;
                    $user['email'] = $email;
                    $user['phone'] = $phone;
                    break;
                }
            }
            if (saveUsers($users)) {
                $currentUser['name'] = $name;
                $currentUser['email'] = $email;
                $currentUser['phone'] = $phone;
                $_SESSION['user_name'] = $name;
                $message = 'Profile updated successfully.';
            } else {
                $message = 'Failed to update profile.';
            }
        }
    } elseif (isset($_POST['change_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $message = 'All password fields are required.';
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'New password and confirm password do not match.';
        } elseif (strlen($newPassword) < 6) {
            $message = 'New password must be at least 6 characters long.';
        } elseif (!password_verify($currentPassword, $currentUser['password'])) {
            $message = 'Current password is incorrect.';
        } else {
            $users = getUsers();
            foreach ($users as &$user) {
                if ($user['id'] === $currentUserId) {
                    $user['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
                    break;
                }
            }
            if (saveUsers($users)) {
                $message = 'Password changed successfully.';
            } else {
                $message = 'Failed to change password.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - ProcessTracker</title>
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
                    <a href="index.php?page=hr&menu=dashboard" class="menu-link">
                        <span class="icon">📊</span>
                        Dashboard
                    </a>
                </li>
                
                <li class="menu-item">
                    <a href="index.php?page=hr&menu=create" class="menu-link">
                        <span class="icon">➕</span>
                        Create Candidate
                    </a>
                </li>
                
                <li class="menu-item">
                    <a href="index.php?page=hr&menu=candidates" class="menu-link">
                        <span class="icon">👥</span>
                        My Candidates
                    </a>
                </li>
                
                <li class="menu-item">
                    <a href="index.php?page=profile" class="menu-link active">
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

            <div class="page-header">
                <h1>👤 My Profile</h1>
            </div>
            
            <?php if ($message): ?>
                <div class="<?php echo strpos($message, 'successfully') !== false ? 'message' : 'error'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>

            <!-- Profile Information -->
            <div class="content-card">
                <h2>Profile Information</h2>
                <form method="post">
                    <div class="form-row">
                        <div class="form-group">
                            <label>User ID</label>
                            <input type="text" value="<?php echo htmlspecialchars($currentUser['id'] ?? ''); ?>" disabled style="background: #f5f5f5;">
                        </div>
                        <div class="form-group">
                            <label>Role</label>
                            <input type="text" value="<?php echo htmlspecialchars($currentUser['role'] ?? ''); ?>" disabled style="background: #f5f5f5;">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Full Name *</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($currentUser['name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email Address *</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Account Status</label>
                            <input type="text" value="<?php echo ($currentUser['active'] ?? false) ? 'Active' : 'Disabled'; ?>" disabled style="background: #f5f5f5; color: <?php echo ($currentUser['active'] ?? false) ? '#27ae60' : '#e74c3c'; ?>;">
                        </div>
                    </div>
                    <button type="submit" name="update_profile" class="btn btn-primary">💾 Update Profile</button>
                </form>
            </div>

            <!-- Change Password -->
            <div class="content-card">
                <h2>Change Password</h2>
                <form method="post">
                    <div class="form-group">
                        <label>Current Password *</label>
                        <input type="password" name="current_password" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>New Password *</label>
                            <input type="password" name="new_password" id="newPassword" required minlength="6">
                            <div class="password-strength" id="passwordStrength"></div>
                        </div>
                        <div class="form-group">
                            <label>Confirm New Password *</label>
                            <input type="password" name="confirm_password" id="confirmPassword" required minlength="6">
                        </div>
                    </div>
                    <button type="submit" name="change_password" class="btn btn-primary">🔑 Change Password</button>
                </form>
            </div>
        </main>
    </div>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loader"></div>
    </div>

    <script>
        // Mobile sidebar toggle
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebarOverlay').classList.toggle('open');
        }

        // Show loader on form submission
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                document.getElementById('loadingOverlay').style.display = 'flex';
            });
        });

        // Password strength indicator
        const newPasswordInput = document.getElementById('newPassword');
        const passwordStrengthDiv = document.getElementById('passwordStrength');
        
        if (newPasswordInput) {
            newPasswordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = '';
                let strengthClass = '';
                
                if (password.length === 0) {
                    passwordStrengthDiv.textContent = '';
                    passwordStrengthDiv.className = 'password-strength';
                } else if (password.length < 6) {
                    strength = 'Password too short (min 6 characters)';
                    strengthClass = 'weak';
                } else {
                    let score = 0;
                    if (password.length >= 8) score++;
                    if (/[A-Z]/.test(password)) score++;
                    if (/[a-z]/.test(password)) score++;
                    if (/[0-9]/.test(password)) score++;
                    if (/[^A-Za-z0-9]/.test(password)) score++;
                    
                    if (score <= 2) {
                        strength = 'Weak password';
                        strengthClass = 'weak';
                    } else if (score <= 3) {
                        strength = 'Medium strength';
                        strengthClass = 'medium';
                    } else {
                        strength = 'Strong password';
                        strengthClass = 'strong';
                    }
                }
                
                passwordStrengthDiv.textContent = strength;
                passwordStrengthDiv.className = 'password-strength ' + strengthClass;
            });
        }

        // Confirm password validation
        const confirmPasswordInput = document.getElementById('confirmPassword');
        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener('input', function() {
                if (this.value !== newPasswordInput.value) {
                    this.setCustomValidity('Passwords do not match');
                } else {
                    this.setCustomValidity('');
                }
            });
        }
    </script>
</body>
</html>

