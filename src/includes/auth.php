<?php
/**
 * Authentication Functions
 * Core authentication and authorization functions for the application
 */

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include activity logger
require_once __DIR__ . '/activity_logger.php';

/**
 * Authenticate user with username/email and password
 * @param string $usernameOrEmail Username or email
 * @param string $password Plain text password
 * @return array|false User data array on success, false on failure
 */
function login($usernameOrEmail, $password)
{
    global $pdo;

    try {
        // Prepare query to find user by username or email
        $stmt = $pdo->prepare("
            SELECT user_id, username, email, password_hash, full_name, role, is_active, changed_password 
            FROM users 
            WHERE (username = ? OR email = ?) AND is_active = TRUE
        ");
        $stmt->execute([$usernameOrEmail, $usernameOrEmail]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Verify user exists and password is correct
        if ($user && password_verify($password, $user['password_hash'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['changed_password'] = $user['changed_password'];
            $_SESSION['login_time'] = time();

            // Regenerate session ID for security
            session_regenerate_id(true);

            // Update last login time
            updateLastLogin($user['user_id']);

            // Log successful login
            logLogin($user['user_id'], true);

            return $user;
        }

        // Log failed login attempt
        if ($user) {
            logLogin($user['user_id'], false, 'Invalid password');
        } else {
            logActivity(null, 'login_failed', "Failed login attempt for: {$usernameOrEmail}");
        }

        return false;
    } catch (PDOException $e) {
        error_log("Login error: " . $e->getMessage());
        return false;
    }
}

/**
 * Log out current user
 */
function logout()
{
    // Log logout before destroying session
    if (isset($_SESSION['user_id'])) {
        logLogout($_SESSION['user_id']);
    }

    // Unset all session variables
    $_SESSION = array();

    // Destroy the session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }

    // Destroy the session
    session_destroy();
}

/**
 * Check if user is logged in
 * @return bool True if logged in, false otherwise
 */
function isLoggedIn()
{
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['login_time'])) {
        return false;
    }

    // Check session timeout (default 1 hour)
    $timeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 3600;
    if (time() - $_SESSION['login_time'] > $timeout) {
        logout();
        return false;
    }

    // Update last activity time
    $_SESSION['login_time'] = time();

    return true;
}

/**
 * Require user to be logged in, redirect to login if not
 * @param string $redirectTo URL to redirect to after login
 */
function requireLogin($redirectTo = null)
{
    if (!isLoggedIn()) {
        // Store intended destination
        if ($redirectTo) {
            $_SESSION['redirect_after_login'] = $redirectTo;
        } else {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        }

        // Redirect to login page
        header('Location: ' . getLoginUrl());
        exit;
    }

    // Check if password change is required (not for admins)
    if (
        isset($_SESSION['changed_password']) &&
        $_SESSION['changed_password'] == 0 &&
        isset($_SESSION['role']) &&
        $_SESSION['role'] !== 'admin'
    ) {
        $currentPage = basename($_SERVER['PHP_SELF']);
        // Avoid redirect loop and allow logout
        if ($currentPage !== 'change_password.php' && $currentPage !== 'logout.php') {
            header('Location: ' . url('change_password.php'));
            exit;
        }
    }
}

/**
 * Check if current user has specific role
 * @param string $role Role to check
 * @return bool True if user has role, false otherwise
 */
function hasRole($role)
{
    return isLoggedIn() && isset($_SESSION['role']) && $_SESSION['role'] === $role;
}

/**
 * Require user to have specific role
 * @param string $role Required role
 */
function requireRole($role)
{
    if (!isLoggedIn()) {
        requireLogin();
    }

    if (!hasRole($role)) {
        http_response_code(403);
        die('Access denied. You do not have permission to access this page.');
    }
}

/**
 * Get current logged-in user data
 * @return array|null User data or null if not logged in
 */
function getCurrentUser()
{
    if (!isLoggedIn()) {
        return null;
    }

    return [
        'user_id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'full_name' => $_SESSION['full_name'] ?? null,
        'role' => $_SESSION['role'] ?? null
    ];
}

/**
 * Generate a consistent URL based on the environment
 * Handles both router mode and subdirectory (XAMPP) mode
 * @param string $path The relative path from the public directory
 * @return string The absolute URL path
 */
function url($path)
{
    $path = ltrim($path, '/');

    // Get the script name (e.g., /index.php or /project/public/index.php)
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';

    // Check if we are in a 'public' subdirectory context
    $publicPos = strpos($scriptName, '/public/');

    if ($publicPos !== false) {
        // We are in a subdirectory like /project/public/
        $base = substr($scriptName, 0, $publicPos + 8); // includes the trailing slash
        return $base . $path;
    }

    // Check if we are in 'public' directly (XAMPP root of public)
    if (strpos($scriptName, '/public') === 0 && (strlen($scriptName) === 7 || $scriptName[7] === '/')) {
        return '/public/' . $path;
    }

    // Default to root-relative path (router mode)
    return '/' . $path;
}

/**
 * Update user's last login timestamp
 * @param int $userId User ID
 */
function updateLastLogin($userId)
{
    global $pdo;

    try {
        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
        $stmt->execute([$userId]);
    } catch (PDOException $e) {
        error_log("Update last login error: " . $e->getMessage());
    }
}

/**
 * Get login page URL
 * @return string Login page URL
 */
function getLoginUrl()
{
    return url('login.php');
}

/**
 * Validate password strength
 * @param string $password Password to validate
 * @return array Array with 'valid' boolean and 'errors' array
 */
function validatePassword($password)
{
    $errors = [];
    $minLength = defined('PASSWORD_MIN_LENGTH') ? PASSWORD_MIN_LENGTH : 8;

    if (strlen($password) < $minLength) {
        $errors[] = "Password must be at least {$minLength} characters long";
    }

    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }

    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }

    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }

    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = "Password must contain at least one special character";
    }

    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}
