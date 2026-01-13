<?php
// logout.php
require_once 'includes/auth.php';
require_once 'includes/helpers.php';

session_destroy();
session_start();
set_flash('info', 'You have been logged out.');
redirect('login.php');
