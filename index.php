<?php
session_start();
require_once 'config/database.php';

// Redirect to dashboard if already logged in
if (isLoggedIn()) {
    header('Location: ' . getBaseUrl() . '/dashboard.php');
    exit();
}

// Redirect to login page
header('Location: ' . getBaseUrl() . '/login.php');
exit();
?>
