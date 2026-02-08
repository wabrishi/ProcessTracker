<?php
// Basic router - MUST start session at the very beginning
error_reporting(E_ALL);
ini_set('display_errors', 1);
error_log("Index.php started");
session_start();
error_log("Session started");

// Get page from GET or default to login
$page = $_GET['page'] ?? 'login';
error_log("Page: $page");

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
} elseif ($page === 'candidate_details' && isset($_SESSION['role']) && in_array($_SESSION['role'], ['HR', 'ADMIN'])) {
    include 'hr/candidate_details.php';
} elseif ($page === 'profile' && isset($_SESSION['role']) && in_array($_SESSION['role'], ['HR', 'ADMIN'])) {
    include 'hr/profile.php';
} elseif ($page === 'login_as' && isset($_SESSION['role']) && $_SESSION['role'] === 'ADMIN') {
    include 'auth/login_as.php';
} elseif ($page === 'switchback' && isset($_SESSION['role']) && $_SESSION['role'] === 'ADMIN') {
    include 'auth/login_as.php';
} else {
    header('Location: ?page=login');
    exit;
}
?>
