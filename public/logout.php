<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/includes/auth.php';

// Log out the user
logout();

// Redirect to login page
header('Location: ' . url('login.php'));
exit;
