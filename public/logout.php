<?php
require_once '../config/config.php';
require_once '../src/includes/auth.php';

// Log out the user
logout();

// Redirect to login page
header('Location: login.php');
exit;
