<?php
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

if (is_logged_in()) {
    redirect('dashboard.php');
} else {
    redirect('login.php');
}
