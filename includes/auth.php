<?php
// includes/auth.php
// Authentication and Session Management

if (session_id() == '') {
    session_start();
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * Require user to be logged in, else redirect
 */
function require_login() {
    if (!is_logged_in()) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Get current user ID
 */
function current_user_id() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
}

/**
 * Middleware to redirect authenticated users away from login/register pages
 */
function require_guest() {
    if (is_logged_in()) {
        header("Location: dashboard.php");
        exit;
    }
}
