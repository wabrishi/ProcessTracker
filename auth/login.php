<?php
include_once __DIR__ . '/../includes/helpers.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $users = getUsers();
    foreach ($users as $user) {
        if ($user['email'] === $email && password_verify($password, $user['password']) && $user['active']) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            header('Location: ../index.php?page=' . strtolower($user['role']));
            exit;
        }
    }
    $error = 'Invalid credentials';
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="../styles.css">
</head>
<body>
    <div class="container">
        <h1>Login</h1>
        <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
        <form method="post" id="loginForm">
            <label>Email: <input type="email" name="email" required></label>
            <label>Password: <input type="password" name="password" required></label>
            <button type="submit">Login</button>
        </form>
    </div>

    <!-- Loading overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loader"></div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        });
    </script>
</body>
</html>