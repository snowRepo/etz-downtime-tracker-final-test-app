<?php
require_once '../../config/config.php';
require_once '../../src/includes/auth.php';
requireLogin();
requireRole('admin');

$currentUser = getCurrentUser();
$userId = $_GET['id'] ?? 0;

// Get user
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'User not found'];
        header('Location: users.php');
        exit;
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $role = $_POST['role'] ?? 'user';
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $new_password = $_POST['new_password'] ?? '';
    
    // Validation
    if (empty($email)) $errors[] = 'Email is required';
    if (empty($full_name)) $errors[] = 'Full name is required';
    
    // Prevent changing own role
    if ($userId == $currentUser['user_id'] && $role !== $user['role']) {
        $errors[] = 'Cannot change your own role';
    }
    
    // Validate password if provided
    if ($new_password) {
        $passwordValidation = validatePassword($new_password);
        if (!$passwordValidation['valid']) {
            $errors = array_merge($errors, $passwordValidation['errors']);
        }
    }
    
    // Update user
    if (!$errors) {
        try {
            // Track changes for logging
            $changes = [];
            if ($email !== $user['email']) $changes['email'] = ['from' => $user['email'], 'to' => $email];
            if ($full_name !== $user['full_name']) $changes['full_name'] = ['from' => $user['full_name'], 'to' => $full_name];
            if ($role !== $user['role']) $changes['role'] = ['from' => $user['role'], 'to' => $role];
            if ($is_active != $user['is_active']) $changes['is_active'] = ['from' => (bool)$user['is_active'], 'to' => (bool)$is_active];
            if ($new_password) $changes['password'] = 'changed';
            
            if ($new_password) {
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET email = ?, full_name = ?, role = ?, is_active = ?, password_hash = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$email, $full_name, $role, $is_active, $password_hash, $userId]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users 
                    SET email = ?, full_name = ?, role = ?, is_active = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$email, $full_name, $role, $is_active, $userId]);
            }
            
            // Log user update
            require_once '../../src/includes/activity_logger.php';
            logUserAction($_SESSION['user_id'], 'updated', $userId, $changes);
            
            $_SESSION['message'] = ['type' => 'success', 'text' => 'User updated successfully'];
            header('Location: users.php');
            exit;
        } catch (PDOException $e) {
            $errors[] = 'Error updating user: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - eTranzact</title>
    
    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <script>tailwind.config = { darkMode: 'class' }</script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>* { font-family: 'Inter', sans-serif; }</style>
</head>
<body class="bg-gray-50 dark:bg-gray-900" x-data="{ showPassword: false }">
    <?php include '../../src/includes/admin_navbar.php'; ?>
    <?php include '../../src/includes/loading.php'; ?>
    
    <main class="py-8">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="mb-6">
                <a href="users.php" class="text-sm text-blue-600 hover:text-blue-700 dark:text-blue-400">
                    <i class="fas fa-arrow-left mr-2"></i>Back to Users
                </a>
            </div>

            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Edit User</h2>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Update user information</p>
                </div>

                <?php if ($errors): ?>
                <div class="mx-6 mt-6 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <ul class="list-disc list-inside text-sm text-red-800">
                        <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <form method="POST" class="px-6 py-6 space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Username</label>
                        <input type="text" value="<?= htmlspecialchars($user['username']) ?>" disabled
                               class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-500 cursor-not-allowed">
                        <p class="mt-1 text-xs text-gray-500">Username cannot be changed</p>
                    </div>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email *</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($user['email']) ?>" required
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Full Name *</label>
                            <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name']) ?>" required
                                   class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">New Password (leave blank to keep current)</label>
                        <div class="relative">
                            <input :type="showPassword ? 'text' : 'password'" name="new_password"
                                   class="w-full px-4 py-2 pr-10 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                            <button type="button" @click="showPassword = !showPassword"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                                <i :class="showPassword ? 'fas fa-eye-slash' : 'fas fa-eye'"></i>
                            </button>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Role *</label>
                            <select name="role" <?= $userId == $currentUser['user_id'] ? 'disabled' : '' ?>
                                    class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white">
                                <option value="viewer" <?= $user['role'] === 'viewer' ? 'selected' : '' ?>>Viewer</option>
                                <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>User</option>
                                <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                            </select>
                            <?php if ($userId == $currentUser['user_id']): ?>
                            <p class="mt-1 text-xs text-gray-500">Cannot change your own role</p>
                            <input type="hidden" name="role" value="<?= $user['role'] ?>">
                            <?php endif; ?>
                        </div>

                        <div class="flex items-center pt-8">
                            <input type="checkbox" name="is_active" id="is_active" <?= $user['is_active'] ? 'checked' : '' ?>
                                   class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="is_active" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                Active Account
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center justify-end space-x-3 pt-6 border-t border-gray-200 dark:border-gray-700">
                        <a href="users.php" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                            Cancel
                        </a>
                        <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg">
                            <i class="fas fa-save mr-2"></i>Update User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</body>
</html>
