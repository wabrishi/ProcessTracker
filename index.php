<?php
session_start();
include 'includes/helpers.php';

// Basic router
$page = $_GET['page'] ?? 'login';

if ($page === 'logout') {
    session_destroy();
    header('Location: ?page=login');
    exit;
}

if ($page === 'login') {
    include 'auth/login.php';
} elseif ($page === 'admin' && isset($_SESSION['role']) && $_SESSION['role'] === 'ADMIN') {
    include 'admin/dashboard.php';
} elseif ($page === 'hr' && isset($_SESSION['role']) && $_SESSION['role'] === 'HR') {
    include 'hr/dashboard.php';
} else {
    header('Location: ?page=login');
    exit;
}
?>