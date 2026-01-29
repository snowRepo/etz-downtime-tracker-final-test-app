<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/includes/auth.php';
requireLogin();
requireRole('admin');

$currentUser = getCurrentUser();
$userId = $_GET['id'] ?? 0;

// Prevent deleting own account
if ($userId == $currentUser['user_id']) {
    $_SESSION['message'] = ['type' => 'error', 'text' => 'Cannot delete your own account'];
    header('Location: users.php');
    exit;
}

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

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        // Log user deletion before deleting
        require_once __DIR__ . '/../../src/includes/activity_logger.php';
        logUserAction($_SESSION['user_id'], 'deleted', $userId, [
            'username' => $user['username'],
            'email' => $user['email'],
            'role' => $user['role']
        ]);

        $stmt = $pdo->prepare("DELETE FROM users WHERE user_id = ?");
        $stmt->execute([$userId]);

        $_SESSION['message'] = ['type' => 'success', 'text' => 'User deleted successfully'];
        header('Location: users.php');
        exit;
    } catch (PDOException $e) {
        $_SESSION['message'] = ['type' => 'error', 'text' => 'Error deleting user'];
        header('Location: users.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delete User - eTranzact</title>

    <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
    <script>tailwind.config = { darkMode: 'class' }</script>
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
        <img src="<?= url('assets/mainbg.jpg') ?>" alt="Background" class="w-full h-full object-cover">
        <div class="absolute inset-0 bg-white/90 dark:bg-gray-900/95"></div>
    </div>

    <!-- Content Wrapper -->
    <div class="relative z-10">
    <?php include __DIR__ . '/../../src/includes/admin_navbar.php'; ?>

    <main class="py-8">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 border border-red-200 dark:border-red-800 rounded-xl overflow-hidden">
                <div class="px-6 py-5 bg-red-50 dark:bg-red-900/30 border-b border-red-200 dark:border-red-800">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-triangle text-red-600 dark:text-red-400 text-2xl mr-3"></i>
                        <div>
                            <h2 class="text-xl font-semibold text-red-900 dark:text-red-100">Delete User</h2>
                            <p class="mt-1 text-sm text-red-700 dark:text-red-300">This action cannot be undone</p>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-6">
                    <p class="text-gray-700 dark:text-gray-300 mb-4">
                        Are you sure you want to delete the following user?
                    </p>

                    <div
                        class="bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 rounded-lg p-4 mb-6">
                        <div class="flex items-center">
                            <div
                                class="w-12 h-12 rounded-full bg-blue-600 flex items-center justify-center text-white font-semibold text-lg">
                                <?= strtoupper(substr($user['full_name'], 0, 1)) ?>
                            </div>
                            <div class="ml-4">
                                <div class="text-base font-medium text-gray-900 dark:text-white">
                                    <?= htmlspecialchars($user['full_name']) ?></div>
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    @<?= htmlspecialchars($user['username']) ?> •
                                    <?= htmlspecialchars($user['email']) ?></div>
                            </div>
                        </div>
                    </div>

                    <div
                        class="bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-6">
                        <p class="text-sm text-yellow-800 dark:text-yellow-200">
                            <i class="fas fa-info-circle mr-2"></i>
                            All data associated with this user will be permanently deleted.
                        </p>
                    </div>

                    <form method="POST" class="flex items-center justify-end space-x-3">
                        <a href="users.php"
                            class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                            Cancel
                        </a>
                        <button type="submit" name="confirm_delete" value="1"
                            class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg">
                            <i class="fas fa-trash mr-2"></i>Delete User
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>
    </div> <!-- End Content Wrapper -->
</body>

</html>