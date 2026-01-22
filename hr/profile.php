<?php
// Determine dashboard URL based on user role
$currentUserRole = $_SESSION['role'] ?? '';
$dashboardUrl = ($currentUserRole === 'ADMIN') ? '../index.php?page=admin' : 'dashboard.php';

include_once __DIR__ . '/../includes/helpers.php';

$message = '';
$currentUserId = $_SESSION['user_id'] ?? null;
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
                    if (isset($user['phone'])) {
                        $user['phone'] = $phone;
                    } else {
                        $user['phone'] = $phone;
                    }
                    break;
                }
            }
            if (saveUsers($users)) {
                $currentUser['name'] = $name;
                $currentUser['email'] = $email;
                $currentUser['phone'] = $phone;
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
    <style>
        .profile-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .profile-section {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .profile-section h2 {
            margin-top: 0;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
            color: #2c3e50;
        }
        .profile-info {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 15px;
        }
        .profile-info-item {
            flex: 1;
            min-width: 200px;
        }
        .profile-info-item label {
            display: block;
            font-weight: bold;
            color: #666;
            margin-bottom: 5px;
        }
        .profile-info-item span {
            font-size: 1.1em;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #3498db;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
        }
        .form-row .form-group {
            flex: 1;
            min-width: 200px;
        }
        .form-row label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .form-row input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .password-strength {
            font-size: 0.85em;
            margin-top: 5px;
        }
        .password-strength.weak { color: #e74c3c; }
        .password-strength.medium { color: #f39c12; }
        .password-strength.strong { color: #27ae60; }
    </style>
</head>
<body>
    <div class="container">
        <a href="<?php echo $dashboardUrl; ?>" class="back-link">← Back to Dashboard</a>
        
        <div class="profile-container">
            <h1>My Profile</h1>
            
            <?php if ($message): ?>
                <p class="<?php echo strpos($message, 'successfully') !== false ? 'message' : 'error'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </p>
            <?php endif; ?>
            
            <!-- Profile Information -->
            <div class="profile-section">
                <h2>Profile Information</h2>
                <form method="post">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="name">Full Name *</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($currentUser['name'] ?? ''); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email Address *</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="role">Role</label>
                            <input type="text" id="role" value="<?php echo htmlspecialchars($currentUser['role'] ?? ''); ?>" disabled style="background: #f5f5f5;">
                        </div>
                    </div>
                    <button type="submit" name="update_profile">Update Profile</button>
                </form>
            </div>
            
            <!-- Change Password -->
            <div class="profile-section">
                <h2>Change Password</h2>
                <form method="post">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="current_password">Current Password *</label>
                            <input type="password" id="current_password" name="current_password" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password">New Password *</label>
                            <input type="password" id="new_password" name="new_password" required minlength="6">
                            <div class="password-strength" id="passwordStrength"></div>
                        </div>
                        <div class="form-group">
                            <label for="confirm_password">Confirm New Password *</label>
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                        </div>
                    </div>
                    <button type="submit" name="change_password" id="changePasswordBtn">Change Password</button>
                </form>
            </div>
            
            <!-- Account Information -->
            <div class="profile-section">
                <h2>Account Information</h2>
                <div class="profile-info">
                    <div class="profile-info-item">
                        <label>User ID</label>
                        <span><?php echo htmlspecialchars($currentUser['id'] ?? ''); ?></span>
                    </div>
                    <div class="profile-info-item">
                        <label>Account Status</label>
                        <span><?php echo ($currentUser['active'] ?? false) ? '<span style="color: #27ae60;">Active</span>' : '<span style="color: #e74c3c;">Disabled</span>'; ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loader"></div>
    </div>

    <script>
        // Show loader on form submission
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                document.getElementById('loadingOverlay').style.display = 'flex';
            });
        });

        // Password strength indicator
        const newPasswordInput = document.getElementById('new_password');
        const passwordStrengthDiv = document.getElementById('passwordStrength');
        
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

        // Confirm password validation
        const confirmPasswordInput = document.getElementById('confirm_password');
        const changePasswordBtn = document.getElementById('changePasswordBtn');
        
        confirmPasswordInput.addEventListener('input', function() {
            if (this.value !== newPasswordInput.value) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
</body>
</html>

