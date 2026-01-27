<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/includes/auth.php';

// Require login, but don't redirect back here since auth.php handles that
if (!isLoggedIn()) {
    header('Location: ' . getLoginUrl());
    exit;
}

$currentUser = getCurrentUser();
$errors = [];
$success = false;

// If user already changed password and is not admin, redirect to index
if (isset($_SESSION['changed_password']) && $_SESSION['changed_password'] == 1 && $currentUser['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($current_password))
        $errors[] = 'Current password is required';
    if (empty($new_password))
        $errors[] = 'New password is required';
    if ($new_password !== $confirm_password)
        $errors[] = 'Passwords do not match';

    // Verify current password
    if (!$errors) {
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
        $stmt->execute([$currentUser['user_id']]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($current_password, $user['password_hash'])) {
            $errors[] = 'Invalid current password';
        }
    }

    // Validate new password strength
    if (!$errors) {
        $passwordValidation = validatePassword($new_password);
        if (!$passwordValidation['valid']) {
            $errors = array_merge($errors, $passwordValidation['errors']);
        }
    }

    // Update password
    if (!$errors) {
        try {
            $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, changed_password = 1 WHERE user_id = ?");
            $stmt->execute([$new_hash, $currentUser['user_id']]);

            // Update session
            $_SESSION['changed_password'] = 1;

            // Log activity
            logActivity($currentUser['user_id'], 'password_changed', 'User changed their password on first login');

            $success = true;
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Password updated successfully! You now have full access.'];

            // Redirect after a short delay or immediately
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Error updating password: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - eTranzact</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <script>tailwind.config = { darkMode: 'class' }</script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
    </style>
</head>

<body class="relative min-h-screen">
    <!-- Background Image with Overlay -->
    <div class="fixed inset-0 z-0">
        <img src="<?= url('../src/assets/mainbg.jpg') ?>" alt="Background" class="w-full h-full object-cover">
        <div class="absolute inset-0 bg-white/90 dark:bg-gray-900/95"></div>
    </div>

    <!-- Content Wrapper -->
    <div class="relative z-10 min-h-full flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div class="text-center">
                <img src="<?= url('../src/assets/logo1.png') ?>" alt="eTranzact Logo" class="mx-auto h-20 w-auto">
                <h2 class="mt-6 text-3xl font-extrabold text-gray-900 dark:text-white">Change Your Password</h2>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    For security reasons, you must change your default password before proceeding.
                </p>
            </div>

            <div
                class="bg-white dark:bg-gray-800 py-8 px-10 shadow-xl rounded-2xl border border-gray-200 dark:border-gray-700">
                <?php if ($errors): ?>
                    <div class="mb-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <ul class="list-disc list-inside text-sm text-red-800">
                            <?php foreach ($errors as $error): ?>
                                <li>
                                    <?= htmlspecialchars($error) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" class="space-y-6" x-data="{ showPasswords: false }">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Current
                            Password</label>
                        <div class="relative">
                            <input :type="showPasswords ? 'text' : 'password'" name="current_password" required
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">New
                            Password</label>
                        <div class="relative">
                            <input :type="showPasswords ? 'text' : 'password'" name="new_password" required
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none">
                        </div>
                        <p class="mt-1 text-xs text-gray-500">Must be at least 8 characters with uppercase, lowercase,
                            number, and special character.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Confirm New
                            Password</label>
                        <div class="relative">
                            <input :type="showPasswords ? 'text' : 'password'" name="confirm_password" required
                                class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 outline-none">
                        </div>
                    </div>

                    <div class="flex items-center">
                        <input type="checkbox" x-model="showPasswords" id="show-passwords"
                            class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <label for="show-passwords" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">Show
                            passwords</label>
                    </div>

                    <button type="submit"
                        class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">
                        Update Password & Continue
                    </button>
                </form>
            </div>

            <div class="text-center">
                <a href="logout.php" class="text-sm font-medium text-blue-600 hover:text-blue-500">
                    <i class="fas fa-sign-out-alt mr-1"></i> Sign out
                </a>
            </div>
        </div>
    </div>
    </div> <!-- End Content Wrapper -->
</body>

</html>