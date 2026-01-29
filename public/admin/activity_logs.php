<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/includes/auth.php';
require_once __DIR__ . '/../../src/includes/activity_logger.php';
requireLogin();
requireRole('admin');

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filters = [
        'user_id' => $_GET['user_id'] ?? null,
        'action' => $_GET['action_type'] ?? null,
        'start_date' => $_GET['start_date'] ?? null,
        'end_date' => $_GET['end_date'] ?? null,
        'search' => $_GET['search'] ?? null
    ];

    $csv = exportActivityLogsCSV($filters);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="activity_logs_' . date('Y-m-d_His') . '.csv"');
    echo $csv;
    exit;
}

// Get filter parameters
$filters = [
    'user_id' => $_GET['user_id'] ?? null,
    'action' => !empty($_GET['action_types']) ? $_GET['action_types'] : null,
    'start_date' => $_GET['start_date'] ?? null,
    'end_date' => $_GET['end_date'] ?? null,
    'search' => $_GET['search'] ?? null
];

// Pagination
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = intval($_GET['per_page'] ?? 25);
$offset = ($page - 1) * $perPage;

// Get logs and total count
$logs = getActivityLogs($filters, $perPage, $offset);
$totalLogs = getActivityLogsCount($filters);
$totalPages = ceil($totalLogs / $perPage);

// Get statistics
$stats = getActivityStats($filters['start_date'], $filters['end_date']);

// Get all users for filter dropdown
$usersStmt = $pdo->query("SELECT user_id, username, full_name FROM users ORDER BY username");
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// Available action types
$actionTypes = [
    'login',
    'logout',
    'login_failed',
    'user_created',
    'user_updated',
    'user_deleted',
    'user_role_changed',
    'incident_created',
    'incident_updated',
    'incident_deleted',
    'analytics_exported',
    'sla_report_exported',
    'incident_exported',
    'password_changed',
    'profile_updated',
    'settings_changed',
    'other'
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - eTranzact</title>

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
        <img src="<?= url('assets/mainbg.jpg') ?>" alt="Background" class="w-full h-full object-cover">
        <div class="absolute inset-0 bg-white/90 dark:bg-gray-900/95"></div>
    </div>

    <!-- Content Wrapper -->
    <div class="relative z-10" x-data="{ showFilters: true, detailModal: null }">
    <?php include __DIR__ . '/../../src/includes/admin_navbar.php'; ?>
    <?php include __DIR__ . '/../../src/includes/loading.php'; ?>

    <main class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Activity Logs</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Monitor all user actions and system events</p>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4 mb-6">
                <div
                    class="bg-white dark:bg-gray-800 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-list-ul text-2xl text-blue-600"></i>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Total Logs
                                    </dt>
                                    <dd class="text-lg font-semibold text-gray-900 dark:text-white">
                                        <?= number_format($stats['total_logs']) ?>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="bg-white dark:bg-gray-800 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-users text-2xl text-green-600"></i>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Unique
                                        Users</dt>
                                    <dd class="text-lg font-semibold text-gray-900 dark:text-white">
                                        <?= number_format($stats['unique_users']) ?>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="bg-white dark:bg-gray-800 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-chart-line text-2xl text-purple-600"></i>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Top Action
                                    </dt>
                                    <dd class="text-lg font-semibold text-gray-900 dark:text-white">
                                        <?= !empty($stats['top_actions']) ? ucfirst(str_replace('_', ' ', $stats['top_actions'][0]['action_type'])) : 'N/A' ?>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="bg-white dark:bg-gray-800 overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700">
                    <div class="p-5">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-user-check text-2xl text-orange-600"></i>
                            </div>
                            <div class="ml-5 w-0 flex-1">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">Most
                                        Active</dt>
                                    <dd class="text-lg font-semibold text-gray-900 dark:text-white">
                                        <?= !empty($stats['top_users']) ? htmlspecialchars($stats['top_users'][0]['username']) : 'N/A' ?>
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg mb-6">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        <i class="fas fa-filter mr-2"></i>Filters
                    </h2>
                    <button @click="showFilters = !showFilters"
                        class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200">
                        <i class="fas" :class="showFilters ? 'fa-chevron-up' : 'fa-chevron-down'"></i>
                    </button>
                </div>

                <form method="GET" x-show="showFilters" x-transition class="px-6 py-4">
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <!-- Date Range -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start
                                Date</label>
                            <input type="date" name="start_date"
                                value="<?= htmlspecialchars($filters['start_date'] ?? '') ?>"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">End
                                Date</label>
                            <input type="date" name="end_date"
                                value="<?= htmlspecialchars($filters['end_date'] ?? '') ?>"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm">
                        </div>

                        <!-- User Filter -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">User</label>
                            <select name="user_id"
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm">
                                <option value="">All Users</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= $user['user_id'] ?>" <?= (isset($filters['user_id']) && $filters['user_id'] == $user['user_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($user['username']) ?>
                                        (<?= htmlspecialchars($user['full_name']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Search -->
                        <div>
                            <label
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
                            <input type="text" name="search" value="<?= htmlspecialchars($filters['search'] ?? '') ?>"
                                placeholder="Search descriptions..."
                                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm">
                        </div>
                    </div>

                    <!-- Action Types -->
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Action
                            Types</label>
                        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-2">
                            <?php foreach ($actionTypes as $type): ?>
                                <label class="flex items-center">
                                    <input type="checkbox" name="action_types[]" value="<?= $type ?>"
                                        <?php echo (isset($filters['action']) && is_array($filters['action']) && in_array($type, $filters['action'])) ? 'checked' : ''; ?>
                                        class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                    <span
                                        class="ml-2 text-sm text-gray-700 dark:text-gray-300"><?= ucfirst(str_replace('_', ' ', $type)) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Filter Actions -->
                    <div class="mt-4 flex flex-col sm:flex-row gap-3">
                        <button type="submit"
                            class="inline-flex items-center justify-center px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                            <i class="fas fa-search mr-2"></i>Apply Filters
                        </button>
                        <a href="activity_logs.php"
                            class="inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            <i class="fas fa-times mr-2"></i>Clear
                        </a>
                        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>"
                            class="inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            <i class="fas fa-download mr-2"></i>Export CSV
                        </a>
                    </div>
                </form>
            </div>

            <!-- Activity Logs Table -->
            <div
                class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                    <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Activity Logs (<?= number_format($totalLogs) ?> total)
                    </h2>
                </div>

                <?php if (empty($logs)): ?>
                    <div class="px-6 py-12 text-center">
                        <i class="fas fa-inbox text-4xl text-gray-400 mb-3"></i>
                        <p class="text-gray-500 dark:text-gray-400">No activity logs found</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-900">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        User</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Action</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Description</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        IP Address</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Timestamp</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <?php foreach ($logs as $log): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div
                                                    class="h-8 w-8 rounded-full bg-blue-600 flex items-center justify-center text-white text-xs font-semibold">
                                                    <?= $log['username'] ? strtoupper(substr($log['username'], 0, 2)) : 'SY' ?>
                                                </div>
                                                <div class="ml-3">
                                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                        <?= $log['username'] ? htmlspecialchars($log['username']) : 'System' ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $actionColors = [
                                                'login' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                                'logout' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200',
                                                'login_failed' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                                'user_created' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                                'user_updated' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                                'user_deleted' => 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
                                                'incident_created' => 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
                                            ];
                                            $actionKey = $log['action'] ?? '';
                                            $colorClass = $actionColors[$actionKey] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200';
                                            ?>
                                            <span
                                                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $colorClass ?>">
                                                <?= ucfirst(str_replace('_', ' ', $actionKey)) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-white max-w-md truncate">
                                            <?= htmlspecialchars($log['description']) ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            <?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            <div title="<?= $log['created_at'] ?>">
                                                <?php
                                                $timestamp = strtotime($log['created_at'] ?? 'now');
                                                $diff = time() - $timestamp;
                                                if ($diff < 60)
                                                    echo 'Just now';
                                                elseif ($diff < 3600)
                                                    echo floor($diff / 60) . 'm ago';
                                                elseif ($diff < 86400)
                                                    echo floor($diff / 3600) . 'h ago';
                                                elseif ($diff < 604800)
                                                    echo floor($diff / 86400) . 'd ago';
                                                else
                                                    echo date('M j, Y', $timestamp);
                                                ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <button @click="detailModal = <?= htmlspecialchars(json_encode($log)) ?>"
                                                class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                                <i class="fas fa-eye"></i> View
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                            <div class="flex-1 flex justify-between sm:hidden">
                                <?php if ($page > 1): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>"
                                        class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                        Previous
                                    </a>
                                <?php endif; ?>
                                <?php if ($page < $totalPages): ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>"
                                        class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                        Next
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">
                                        Showing <span class="font-medium"><?= $offset + 1 ?></span> to
                                        <span class="font-medium"><?= min($offset + $perPage, $totalLogs) ?></span> of
                                        <span class="font-medium"><?= number_format($totalLogs) ?></span> results
                                    </p>
                                </div>
                                <div>
                                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">
                                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"
                                                class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?= $i === $page ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50' ?> dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300">
                                                <?= $i ?>
                                            </a>
                                        <?php endfor; ?>
                                    </nav>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Detail Modal -->
    <div x-show="detailModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
        <div class="flex items-center justify-center min-h-screen px-4">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="detailModal = null"></div>

            <div class="relative bg-white dark:bg-gray-800 rounded-lg max-w-2xl w-full p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Log Details</h3>
                    <button @click="detailModal = null" class="text-gray-400 hover:text-gray-500">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <template x-if="detailModal">
                    <div class="space-y-3">
                        <div class="grid grid-cols-3 gap-4">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Log ID:</div>
                            <div class="col-span-2 text-sm text-gray-900 dark:text-white"
                                x-text="'#' + detailModal.log_id"></div>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">User:</div>
                            <div class="col-span-2 text-sm text-gray-900 dark:text-white"
                                x-text="detailModal.username || 'System'"></div>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Action Type:</div>
                            <div class="col-span-2 text-sm text-gray-900 dark:text-white"
                                x-text="detailModal.action"></div>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Description:</div>
                            <div class="col-span-2 text-sm text-gray-900 dark:text-white"
                                x-text="detailModal.description"></div>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">IP Address:</div>
                            <div class="col-span-2 text-sm text-gray-900 dark:text-white"
                                x-text="detailModal.ip_address || 'N/A'"></div>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">User Agent:</div>
                            <div class="col-span-2 text-sm text-gray-900 dark:text-white break-all"
                                x-text="detailModal.user_agent || 'N/A'"></div>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Timestamp:</div>
                            <div class="col-span-2 text-sm text-gray-900 dark:text-white"
                                x-text="detailModal.created_at"></div>
                        </div>
                        <template x-if="detailModal.metadata">
                            <div class="grid grid-cols-3 gap-4">
                                <div class="text-sm font-medium text-gray-500 dark:text-gray-400">Metadata:</div>
                                <div class="col-span-2">
                                    <pre class="text-xs bg-gray-100 dark:bg-gray-900 p-3 rounded overflow-x-auto"
                                        x-text="JSON.stringify(JSON.parse(detailModal.metadata), null, 2)"></pre>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </div>
    </div> <!-- End Content Wrapper -->
</body>

</html>