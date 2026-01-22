<?php
/**
 * Login As User Handler
 * Allows admin to temporarily login as another user
 * Opens the target user's dashboard in a new window
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
error_log("login_as.php started");
session_start();
error_log("Session started");

include_once __DIR__ . '/../includes/helpers.php';

// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'ADMIN') {
    error_log("Unauthorized access attempt to login_as.php");
    header('Location: ../index.php?page=login');
    exit;
}

// Get target user ID from query parameter
$targetUserId = $_GET['user_id'] ?? '';
$action = $_GET['action'] ?? '';

if (empty($targetUserId)) {
    error_log("No target user ID provided");
    header('Location: ../index.php?page=admin');
    exit;
}

// Get target user details
$users = getUsers();
$targetUser = null;
foreach ($users as $user) {
    if ($user['id'] === $targetUserId) {
        $targetUser = $user;
        break;
    }
}

if (!$targetUser) {
    error_log("Target user not found: " . $targetUserId);
    header('Location: ../index.php?page=admin');
    exit;
}

if ($action === 'login') {
    // Store original admin session for switching back
    $_SESSION['original_admin_id'] = $_SESSION['user_id'];
    $_SESSION['original_admin_role'] = $_SESSION['role'];
    
    // Switch to target user session
    $_SESSION['user_id'] = $targetUser['id'];
    $_SESSION['role'] = $targetUser['role'];
    
    error_log("Admin switched to user: " . $targetUserId);
    
    // Determine target page based on role
    $targetPage = strtolower($targetUser['role']);
    
    // Open target dashboard in new window and close this page
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Logging in as <?php echo htmlspecialchars($targetUser['name']); ?>...</title>
        <link rel="stylesheet" href="../styles.css">
        <style>
            body {
                font-family: Arial, sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                margin: 0;
                background: #f4f4f4;
            }
            .login-as-container {
                background: #fff;
                padding: 40px;
                border-radius: 8px;
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
                text-align: center;
                max-width: 400px;
            }
            .spinner {
                border: 4px solid #f3f3f3;
                border-top: 4px solid #3498db;
                border-radius: 50%;
                width: 40px;
                height: 40px;
                animation: spin 1s linear infinite;
                margin: 20px auto;
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            h1 { color: #2c3e50; margin-bottom: 10px; }
            p { color: #666; }
            .user-info {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 4px;
                margin: 20px 0;
            }
            .user-info strong { color: #3498db; }
            .error { color: #e74c3c; margin-top: 10px; }
        </style>
    </head>
    <body>
        <div class="login-as-container">
            <h1>Switching User</h1>
            <div class="spinner"></div>
            <p>Logging in as <strong><?php echo htmlspecialchars($targetUser['name']); ?></strong></p>
            <div class="user-info">
                <p><strong>Email:</strong> <?php echo htmlspecialchars($targetUser['email']); ?></p>
                <p><strong>Role:</strong> <?php echo htmlspecialchars($targetUser['role']); ?></p>
            </div>
            <p id="status">Opening dashboard...</p>
            <script>
                // Open target dashboard in new window
                var targetUrl = '../index.php?page=<?php echo $targetPage; ?>';
                var newWindow = window.open(targetUrl, '_blank');
                
                if (newWindow) {
                    newWindow.focus();
                    document.getElementById('status').textContent = 'Dashboard opened in new window.';
                    document.getElementById('status').style.color = '#27ae60';
                    
                    // Close this helper window after a brief delay
                    setTimeout(function() {
                        window.close();
                    }, 2000);
                } else {
                    document.getElementById('status').textContent = 'Popup blocked! Please allow popups and try again.';
                    document.getElementById('status').className = 'error';
                    
                    // Fallback: redirect to target dashboard
                    setTimeout(function() {
                        document.getElementById('status').innerHTML += '<br><a href="' + targetUrl + '">Click here to open dashboard</a>';
                    }, 3000);
                }
            </script>
        </div>
    </body>
    </html>
    <?php
    exit;
} elseif ($action === 'switchback') {
    // Switch back to original admin
    if (isset($_SESSION['original_admin_id'])) {
        $_SESSION['user_id'] = $_SESSION['original_admin_id'];
        $_SESSION['role'] = $_SESSION['original_admin_role'];
        unset($_SESSION['original_admin_id'], $_SESSION['original_admin_role']);
        error_log("Switched back to admin");
        header('Location: ../index.php?page=admin');
        exit;
    } else {
        error_log("No original admin session to switch back to");
        header('Location: ../index.php?page=admin');
        exit;
    }
} else {
    // Invalid action
    error_log("Invalid action: " . $action);
    header('Location: ../index.php?page=admin');
    exit;
}

