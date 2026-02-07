<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/includes/auth.php';
requireLogin();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_update' && !empty($_POST['update_text']) && !empty($_POST['user_name'])) {
        // Add new update
        $stmt = $pdo->prepare("
            INSERT INTO incident_updates (incident_id, user_id, user_name, update_text) 
            VALUES (:incident_id, :user_id, :user_name, :update_text)
        ");
        $stmt->execute([
            ':incident_id' => $_POST['incident_id'],
            ':user_id' => $_SESSION['user_id'],
            ':user_name' => trim($_POST['user_name']),
            ':update_text' => trim($_POST['update_text'])
        ]);
    } elseif ($_POST['action'] === 'update_status' && isset($_POST['incident_id'], $_POST['status'])) {
        $status = $_POST['status'];
        $incidentId = $_POST['incident_id'];
        $userName = $_SESSION['full_name']; // Use full name from session

        // Prepare the SQL based on status
        $sql = "UPDATE incidents 
                SET status = :status";

        // Add resolved_by and resolved_at for resolved status
        if ($status === 'resolved') {
            $sql .= ", resolved_by = :user_id, resolved_at = NOW()";
            
            // Add root_cause and lessons_learned if provided
            if (isset($_POST['root_cause']) && !empty(trim($_POST['root_cause']))) {
                $sql .= ", root_cause = :root_cause";
            }
            if (isset($_POST['lessons_learned']) && !empty(trim($_POST['lessons_learned']))) {
                $sql .= ", lessons_learned = :lessons_learned";
            }
        } else {
            $sql .= ", resolved_by = NULL, resolved_at = NULL";
        }

        $sql .= " WHERE incident_id = :incident_id";

        $stmt = $pdo->prepare($sql);
        $params = [
            ':status' => $status,
            ':incident_id' => $incidentId
        ];
        if ($status === 'resolved') {
            $params[':user_id'] = $_SESSION['user_id'];
            
            // Add root_cause and lessons_learned to params if provided
            if (isset($_POST['root_cause']) && !empty(trim($_POST['root_cause']))) {
                $params[':root_cause'] = trim($_POST['root_cause']);
            }
            if (isset($_POST['lessons_learned']) && !empty(trim($_POST['lessons_learned']))) {
                $params[':lessons_learned'] = trim($_POST['lessons_learned']);
            }
        }

        $stmt->execute($params);

        // Add system update
        $statusText = $status === 'resolved' ? 'resolved' : 'reopened';
        $updateText = "Incident has been marked as {$statusText} by " . $userName;

        $stmt = $pdo->prepare("
            INSERT INTO incident_updates (incident_id, user_id, user_name, update_text) 
            VALUES (:incident_id, :user_id, :user_name, :update_text)
        ");
        $stmt->execute([
            ':incident_id' => $incidentId,
            ':user_id' => $_SESSION['user_id'],
            ':user_name' => 'System',
            ':update_text' => $updateText
        ]);

        $_SESSION['success'] = "Incident updated successfully!";
    }

    // Redirect to prevent form resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Pagination settings
$itemsPerPage = 10;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($currentPage - 1) * $itemsPerPage;

// Get all incidents with their updates
try {
    // First, count total incidents for pagination
    $countQuery = "SELECT COUNT(*) FROM incidents";
    $totalIncidents = $pdo->query($countQuery)->fetchColumn();
    $totalPages = ceil($totalIncidents / $itemsPerPage);

    // Get incidents with service and affected companies (with pagination)
    $incidents = $pdo->prepare("
        SELECT 
            i.incident_id,
            i.service_id,
            i.root_cause,
            i.status,
            i.impact_level,
            i.attachment_path,
            u.full_name as user_name,
            i.created_at,
            res.full_name as resolved_by,
            i.resolved_at,
            i.updated_at,
            s.service_name,
            sc.name as component_name,
            it.name as incident_type_name,
            GROUP_CONCAT(DISTINCT c.company_name ORDER BY c.company_name SEPARATOR ', ') as affected_companies,
            COUNT(DISTINCT c.company_id) as company_count,
            (SELECT COUNT(*) FROM incident_updates iu WHERE iu.incident_id = i.incident_id) as update_count
        FROM incidents i
        JOIN services s ON i.service_id = s.service_id
        JOIN users u ON i.reported_by = u.user_id
        LEFT JOIN users res ON i.resolved_by = res.user_id
        LEFT JOIN incident_affected_companies iac ON i.incident_id = iac.incident_id
        LEFT JOIN companies c ON iac.company_id = c.company_id
        LEFT JOIN service_components sc ON i.component_id = sc.component_id
        LEFT JOIN incident_types it ON i.incident_type_id = it.type_id
        GROUP BY i.incident_id
        ORDER BY 
            FIELD(i.status, 'pending', 'resolved'),
            i.updated_at DESC
        LIMIT ? OFFSET ?
    ");
    $incidents->execute([$itemsPerPage, $offset]);
    $incidents = $incidents->fetchAll(PDO::FETCH_ASSOC);

    // Get updates for each incident
    foreach ($incidents as &$incident) {
        $stmt = $pdo->prepare("
            SELECT * FROM incident_updates 
            WHERE incident_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$incident['incident_id']]);
        $incident['updates'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    unset($incident); // Break the reference

} catch (PDOException $e) {
    die("ERROR: Could not fetch incidents. " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incidents - ETZ Downtime</title>

    <!-- Tailwind CSS v3.4.17 -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio"></script>
    <script>
        tailwind.config = {
            darkMode: 'class'
        }
    </script>

    <!-- Alpine.js v3.x -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Font Awesome 6.5.1 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">

    <style>
        * {
            font-family: 'Inter', sans-serif;
        }

        .line-clamp-3 {
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .incident-card {
            transition: box-shadow 0.15s ease;
        }

        .incident-card:hover {
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.08), 0 2px 4px -1px rgba(0, 0, 0, 0.04);
        }

        .status-badge {
            @apply inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium border;
        }

        /* Impact level badges */
        .impact-high,
        .impact-critical {
            @apply bg-red-50 text-red-700 border-red-200;
        }

        .impact-medium {
            @apply bg-yellow-50 text-yellow-700 border-yellow-200;
        }

        .impact-low {
            @apply bg-green-50 text-green-700 border-green-200;
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
    <div class="relative z-10">
    <!-- Navbar -->
    <?php include __DIR__ . '/../src/includes/navbar.php'; ?>

    <!-- Loading Overlay -->
    <?php include __DIR__ . '/../src/includes/loading.php'; ?>

    <!-- Main Content -->
    <main class="py-6">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 mb-6">
                <div class="bg-green-50 border-l-4 border-green-400 p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-check-circle text-green-400"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm text-green-700"><?php echo htmlspecialchars($_SESSION['success']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Page Header -->
            <div class="mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-2xl font-bold leading-7 text-gray-900 dark:text-white sm:text-3xl sm:truncate">
                        Incident Management
                    </h2>
                </div>
                <div class="mt-4 sm:mt-0 flex items-center space-x-3">
                    <span class="text-xs text-gray-500 dark:text-gray-400" id="last-updated">
                        Last updated: <?php echo date('g:i A'); ?>
                    </span>
                    <button onclick="refreshIncidents()"
                        class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-150">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15">
                            </path>
                        </svg>
                        Refresh
                    </button>
                </div>
            </div>

            <!-- Search and Filter Bar -->
            <div class="mb-6 flex flex-col sm:flex-row gap-4">
                <div class="flex-1">
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                        <input type="text" id="incident-search"
                            placeholder="Search incidents by service, company, or root cause..."
                            class="block w-full pl-10 pr-3 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent sm:text-sm"
                            onkeyup="filterIncidents()">
                    </div>
                </div>
                <div class="mt-4 flex md:mt-0 md:ml-6">
                    <div class="inline-flex rounded-lg shadow-sm" role="group">
                        <button type="button" data-status="all"
                            class="status-toggle px-4 py-2 text-sm font-medium rounded-l-lg border border-gray-200 bg-white text-gray-700 hover:bg-gray-50 transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-blue-500">
                            <span class="flex items-center">
                                <i class="fas fa-list-ul mr-2 text-gray-500"></i>
                                <span>All</span>
                            </span>
                        </button>
                        <button type="button" data-status="pending"
                            class="status-toggle px-4 py-2 text-sm font-medium border-t border-b border-gray-200 bg-white text-gray-700 hover:bg-yellow-50 transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-yellow-500">
                            <span class="flex items-center">
                                <i class="fas fa-clock mr-2 text-yellow-500"></i>
                                <span>Pending</span>
                            </span>
                        </button>
                        <button type="button" data-status="resolved"
                            class="status-toggle px-4 py-2 text-sm font-medium rounded-r-lg border border-gray-200 bg-white text-gray-700 hover:bg-green-50 transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-green-500">
                            <span class="flex items-center">
                                <i class="fas fa-check-circle mr-2 text-green-500"></i>
                                <span>Resolved</span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Incidents List -->
            <div class="space-y-4">
                <!-- Empty State for No Results -->
                <div id="no-results" class="hidden text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No incidents found</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Try selecting a different filter.</p>
                </div>

                <?php if (empty($incidents)): ?>
                    <div class="text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No incidents reported yet</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by reporting a new incident.
                        </p>
                    </div>
                <?php else: ?>
                    <?php foreach ($incidents as $incident):
                        $statusClass = $incident['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800';

                        // Impact level colors
                        $impactColors = [
                            'critical' => 'bg-red-100 text-red-800',
                            'high' => 'bg-orange-100 text-orange-800',
                            'medium' => 'bg-yellow-100 text-yellow-800',
                            'low' => 'bg-blue-100 text-blue-800'
                        ];
                        $impactClass = $impactColors[strtolower($incident['impact_level'])] ?? 'bg-gray-100 text-gray-800';
                        ?>
                        <div class="incident-card bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-lg mb-6 border dark:border-gray-700"
                            data-status="<?php echo $incident['status']; ?>">
                            <div class="px-4 py-5 sm:px-6">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($incident['service_name']); ?>
                                            </h3>
                                            <span
                                                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $impactClass; ?>">
                                                <?php echo ucfirst($incident['impact_level']); ?>
                                            </span>
                                            <span
                                                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusClass; ?>">
                                                <?php echo ucfirst($incident['status']); ?>
                                            </span>
                                        </div>
                                        <p class="mt-1 max-w-2xl text-sm text-gray-500 dark:text-gray-400">
                                            Reported by <?php echo htmlspecialchars($incident['user_name']); ?> on
                                            <?php echo date('M j, Y g:i A', strtotime($incident['created_at'])); ?>
                                        </p>
                                    </div>
                                    <?php if ($incident['status'] === 'pending'): ?>
                                        <button type="button"
                                            onclick="showResolveModal(<?php echo $incident['incident_id']; ?>, '<?php echo addslashes(htmlspecialchars($incident['service_name'])); ?>', '<?php echo addslashes(htmlspecialchars($incident['root_cause'] ?? '')); ?>')"
                                            class="mt-2 sm:mt-0 inline-flex items-center px-3 py-1.5 border border-transparent text-xs font-medium rounded shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                            <i class="fas fa-check mr-1"></i> Mark as Resolved
                                        </button>
                                    <?php else: ?>
                                        <div class="mt-2 sm:mt-0 flex flex-col sm:flex-row items-start sm:items-center gap-2">
                                            <span class="text-sm text-green-600 dark:text-green-400 font-medium">
                                                Resolved by <?php echo htmlspecialchars($incident['resolved_by'] ?? 'System'); ?> on
                                                <?php echo $incident['resolved_at'] ? date('M j, Y g:i A', strtotime($incident['resolved_at'])) : 'Unknown'; ?>
                                            </span>
                                            <button type="button"
                                                onclick="showReopenModal(<?php echo $incident['incident_id']; ?>, '<?php echo addslashes(htmlspecialchars($incident['service_name'])); ?>')"
                                                class="inline-flex items-center px-3 py-1.5 border border-orange-600 dark:border-orange-500 text-xs font-medium rounded shadow-sm text-orange-600 dark:text-orange-400 bg-white dark:bg-gray-800 hover:bg-orange-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500">
                                                <i class="fas fa-redo mr-1"></i> Reopen Incident
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="border-t border-gray-200 dark:border-gray-700 px-4 py-5 sm:p-6">
                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                                    <!-- LEFT COLUMN: Details -->
                                    <div class="space-y-4">
                                        <div>
                                            <h4
                                                class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                                Component Affected</h4>
                                            <p class="mt-1 text-sm text-gray-900 dark:text-white font-medium">
                                                <?php echo htmlspecialchars($incident['component_name'] ?? 'All'); ?>
                                            </p>
                                        </div>

                                        <div>
                                            <h4
                                                class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                                Incident Type</h4>
                                            <p class="mt-1 text-sm text-gray-900 dark:text-white font-medium">
                                                <?php echo htmlspecialchars($incident['incident_type_name'] ?? 'All'); ?>
                                            </p>
                                        </div>

                                        <div>
                                            <h4
                                                class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                                Affected Companies</h4>
                                            <p class="mt-1 text-sm text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($incident['affected_companies']); ?>
                                            </p>
                                        </div>

                                        <div>
                                            <h4
                                                class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                                Root Cause</h4>
                                            <?php if (!empty($incident['root_cause'])): ?>
                                                <p class="mt-1 text-sm text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($incident['root_cause']); ?>
                                                </p>
                                            <?php else: ?>
                                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 italic">Not specified</p>
                                            <?php endif; ?>
                                        </div>

                                        <?php if (!empty($incident['attachment_path'])): ?>
                                            <div>
                                                <h4
                                                    class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                                    Attachment</h4>
                                                <div class="mt-2 text-sm">
                                                    <a href="<?= url($incident['attachment_path']) ?>" target="_blank"
                                                        class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-blue-600 dark:text-blue-400 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                        <i class="fas fa-paperclip mr-2"></i> View Evidence
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- RIGHT COLUMN: Action Taken -->
                                    <div class="lg:border-l lg:border-gray-200 dark:lg:border-gray-700 lg:pl-6">
                                        <!-- Reported By -->
                                        <div class="mb-4 pb-3 border-b border-gray-200 dark:border-gray-700">
                                            <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                                Reported By</p>
                                            <div class="flex items-center gap-2">
                                                <i class="fas fa-user-circle text-gray-400 dark:text-gray-500"></i>
                                                <span class="text-sm font-medium text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($incident['user_name']); ?>
                                                </span>
                                                <span class="text-xs text-gray-500 dark:text-gray-400">
                                                    •
                                                    <?php echo date('M j, Y g:i A', strtotime($incident['created_at'])); ?>
                                                </span>
                                            </div>
                                        </div>

                                        <h4
                                            class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3">
                                            Action Taken (<?php echo $incident['update_count']; ?>)</h4>

                                        <?php if (empty($incident['updates'])): ?>
                                            <p class="text-sm text-gray-500 dark:text-gray-400 italic">No action taken yet.</p>
                                        <?php else: ?>
                                            <div class="space-y-3 mb-4">
                                                <?php foreach ($incident['updates'] as $update): ?>
                                                    <div class="text-sm">
                                                        <div class="flex items-baseline gap-2">
                                                            <span
                                                                class="font-medium text-gray-900 dark:text-white"><?php echo htmlspecialchars($update['user_name']); ?></span>
                                                            <span class="text-xs text-gray-500 dark:text-gray-400">•
                                                                <?php echo date('M j, g:i A', strtotime($update['created_at'])); ?></span>
                                                        </div>
                                                        <p class="mt-0.5 text-gray-700 dark:text-gray-300">
                                                            <?php echo nl2br(htmlspecialchars($update['update_text'])); ?>
                                                        </p>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Add Update Form - Inline -->
                                        <?php if ($incident['status'] === 'pending'): ?>
                                            <form method="POST" class="mt-3">
                                                <input type="hidden" name="action" value="add_update">
                                                <input type="hidden" name="incident_id"
                                                    value="<?php echo $incident['incident_id']; ?>">
                                                <div class="flex flex-col sm:flex-row gap-2">
                                                    <input type="text" name="user_name"
                                                        value="<?= htmlspecialchars($_SESSION['full_name']) ?>"
                                                        placeholder="Your Name" readonly
                                                        class="w-full sm:w-32 text-sm border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-600 text-gray-500 dark:text-gray-400 rounded-md shadow-sm cursor-not-allowed py-1.5 px-3">
                                                    <input type="text" name="update_text" placeholder="Describe action taken..."
                                                        required
                                                        class="flex-1 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm focus:border-blue-500 focus:ring-blue-500 py-1.5 px-3">
                                                    <button type="submit"
                                                        class="w-full sm:w-auto inline-flex items-center justify-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md text-white bg-gray-900 hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500">
                                                        Post
                                                    </button>
                                                </div>
                                            </form>
                                        <?php else: ?>
                                            <div
                                                class="mt-3 p-3 bg-gray-50 dark:bg-gray-700/50 border border-gray-200 dark:border-gray-600 rounded-md">
                                                <p class="text-xs text-gray-500 dark:text-gray-400 flex items-center">
                                                    <i class="fas fa-lock mr-2"></i>
                                                    This incident is resolved. Reopen the incident to add action taken.
                                                </p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div
                    class="mt-8 flex items-center justify-between border-t border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 px-4 py-3 sm:px-6 rounded-lg shadow">
                    <div class="flex flex-1 justify-between sm:hidden">
                        <!-- Mobile Pagination -->
                        <?php if ($currentPage > 1): ?>
                            <a href="?page=<?= $currentPage - 1 ?>"
                                class="relative inline-flex items-center rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                                Previous
                            </a>
                        <?php else: ?>
                            <span
                                class="relative inline-flex items-center rounded-md border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-800 px-4 py-2 text-sm font-medium text-gray-400 dark:text-gray-500 cursor-not-allowed">
                                Previous
                            </span>
                        <?php endif; ?>

                        <span class="text-sm text-gray-700 dark:text-gray-300">
                            Page <?= $currentPage ?> of <?= $totalPages ?>
                        </span>

                        <?php if ($currentPage < $totalPages): ?>
                            <a href="?page=<?= $currentPage + 1 ?>"
                                class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600">
                                Next
                            </a>
                        <?php else: ?>
                            <span
                                class="relative ml-3 inline-flex items-center rounded-md border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-800 px-4 py-2 text-sm font-medium text-gray-400 dark:text-gray-500 cursor-not-allowed">
                                Next
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                        <div>
                            <p class="text-sm text-gray-700 dark:text-gray-300">
                                Showing
                                <span class="font-medium"><?= min($offset + 1, $totalIncidents) ?></span>
                                to
                                <span class="font-medium"><?= min($offset + $itemsPerPage, $totalIncidents) ?></span>
                                of
                                <span class="font-medium"><?= $totalIncidents ?></span>
                                results
                            </p>
                        </div>
                        <div>
                            <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                                <!-- Previous Button -->
                                <?php if ($currentPage > 1): ?>
                                    <a href="?page=<?= $currentPage - 1 ?>"
                                        class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 focus:z-20 focus:outline-offset-0">
                                        <span class="sr-only">Previous</span>
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd"
                                                d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                <?php else: ?>
                                    <span
                                        class="relative inline-flex items-center rounded-l-md px-2 py-2 text-gray-300 dark:text-gray-600 ring-1 ring-inset ring-gray-300 dark:ring-gray-600 cursor-not-allowed">
                                        <span class="sr-only">Previous</span>
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd"
                                                d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                <?php endif; ?>

                                <!-- Page Numbers -->
                                <?php
                                $startPage = max(1, $currentPage - 2);
                                $endPage = min($totalPages, $currentPage + 2);

                                if ($startPage > 1): ?>
                                    <a href="?page=1"
                                        class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 dark:text-gray-300 ring-1 ring-inset ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 focus:z-20 focus:outline-offset-0">1</a>
                                    <?php if ($startPage > 2): ?>
                                        <span
                                            class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-400 ring-1 ring-inset ring-gray-300 dark:ring-gray-600">...</span>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <?php if ($i == $currentPage): ?>
                                        <span
                                            class="relative z-10 inline-flex items-center bg-blue-600 px-4 py-2 text-sm font-semibold text-white focus:z-20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600"><?= $i ?></span>
                                    <?php else: ?>
                                        <a href="?page=<?= $i ?>"
                                            class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 dark:text-gray-300 ring-1 ring-inset ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 focus:z-20 focus:outline-offset-0"><?= $i ?></a>
                                    <?php endif; ?>
                                <?php endfor; ?>

                                <?php if ($endPage < $totalPages): ?>
                                    <?php if ($endPage < $totalPages - 1): ?>
                                        <span
                                            class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-700 dark:text-gray-400 ring-1 ring-inset ring-gray-300 dark:ring-gray-600">...</span>
                                    <?php endif; ?>
                                    <a href="?page=<?= $totalPages ?>"
                                        class="relative inline-flex items-center px-4 py-2 text-sm font-semibold text-gray-900 dark:text-gray-300 ring-1 ring-inset ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 focus:z-20 focus:outline-offset-0"><?= $totalPages ?></a>
                                <?php endif; ?>

                                <!-- Next Button -->
                                <?php if ($currentPage < $totalPages): ?>
                                    <a href="?page=<?= $currentPage + 1 ?>"
                                        class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-400 ring-1 ring-inset ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 focus:z-20 focus:outline-offset-0">
                                        <span class="sr-only">Next</span>
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd"
                                                d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </a>
                                <?php else: ?>
                                    <span
                                        class="relative inline-flex items-center rounded-r-md px-2 py-2 text-gray-300 dark:text-gray-600 ring-1 ring-inset ring-gray-300 dark:ring-gray-600 cursor-not-allowed">
                                        <span class="sr-only">Next</span>
                                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd"
                                                d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </span>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Resolve Issue Modal -->
    <div id="resolveModal"
        class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50 p-4 transition-opacity duration-300">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full transform transition-all duration-300 scale-95 opacity-0"
            id="modalContent">
            <div class="p-6">
                <h3 class="text-lg font-medium text-gray-900 mb-1">Resolve Issue</h3>
                <p class="text-sm text-gray-500 mb-4" id="modalServiceName"></p>

                <form id="resolveForm" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="incident_id" id="modal_incident_id" value="">
                    <input type="hidden" name="status" value="resolved">

                    <div>
                        <label for="resolve_name" class="block text-sm font-medium text-gray-700">Your Name</label>
                        <input type="text" id="resolve_name" name="user_name"
                            value="<?= htmlspecialchars($_SESSION['full_name']) ?>"
                            class="mt-1 block w-full border border-gray-300 bg-gray-100 text-gray-500 rounded-md shadow-sm py-2 px-3 cursor-not-allowed sm:text-sm"
                            readonly autocomplete="off">
                    </div>

                    <!-- Root Cause -->
                    <div>
                        <label for="resolve_root_cause" class="block text-sm font-medium text-gray-700">
                            Root Cause <span class="text-gray-400">(Optional)</span>
                        </label>
                        <textarea id="resolve_root_cause" name="root_cause" rows="3"
                            placeholder="Describe what caused this incident..."
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
                        <p class="mt-1 text-xs text-gray-500">Update or add the root cause if it wasn't specified earlier</p>
                    </div>

                    <!-- Lessons Learned -->
                    <div>
                        <label for="resolve_lessons_learned" class="block text-sm font-medium text-gray-700">
                            Lessons Learned <span class="text-red-500">*</span>
                        </label>
                        <textarea id="resolve_lessons_learned" name="lessons_learned" rows="4" required
                            placeholder="What did we learn from this incident? How can we prevent it in the future?"
                            class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
                        <p class="mt-1 text-xs text-gray-500">Document key insights and preventive measures</p>
                    </div>

                    <div class="flex justify-end space-x-3 pt-2">
                        <button type="button" onclick="hideResolveModal()"
                            class="inline-flex justify-center py-2 px-4 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Cancel
                        </button>
                        <button type="submit"
                            class="inline-flex justify-center items-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            <i class="fas fa-check mr-2"></i> Mark as Resolved
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Reopen Issue Modal -->
    <div id="reopenModal"
        class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50 p-4 transition-opacity duration-300">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full transform transition-all duration-300 scale-95 opacity-0"
            id="reopenModalContent">
            <div class="p-6">
                <div class="flex items-center mb-4">
                    <div
                        class="flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-orange-100 dark:bg-orange-900/30">
                        <i class="fas fa-exclamation-triangle text-orange-600 dark:text-orange-400 text-xl"></i>
                    </div>
                    <div class="ml-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Reopen Incident</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400" id="reopenModalServiceName"></p>
                    </div>
                </div>

                <div
                    class="mb-4 p-3 bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-md">
                    <p class="text-sm text-orange-800 dark:text-orange-300">
                        <i class="fas fa-info-circle mr-1"></i>
                        Are you sure you want to reopen this resolved incident? This will allow new updates to be added.
                    </p>
                </div>

                <form id="reopenForm" method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="incident_id" id="reopen_incident_id" value="">
                    <input type="hidden" name="status" value="pending">

                    <div class="flex justify-end space-x-3 pt-2">
                        <button type="button" onclick="hideReopenModal()"
                            class="inline-flex justify-center py-2 px-4 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Cancel
                        </button>
                        <button type="submit"
                            class="inline-flex justify-center items-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500">
                            <i class="fas fa-redo mr-2"></i> Reopen Incident
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Status toggle functionality
        document.addEventListener('DOMContentLoaded', function () {
            const statusToggles = document.querySelectorAll('.status-toggle');

            statusToggles.forEach(button => {
                button.addEventListener('click', function () {
                    const status = this.getAttribute('data-status');

                    // Update active state
                    statusToggles.forEach(btn => {
                        btn.classList.remove(
                            'bg-blue-50', 'text-blue-700', 'border-blue-200',
                            'bg-yellow-50', 'text-yellow-700', 'border-yellow-200',
                            'bg-green-50', 'text-green-700', 'border-green-200'
                        );
                        btn.classList.add('bg-white', 'text-gray-700', 'border-gray-200');

                        // Reset icon colors
                        const icon = btn.querySelector('i');
                        if (icon) {
                            icon.classList.remove('text-blue-500', 'text-yellow-500', 'text-green-500');
                            if (btn.getAttribute('data-status') === 'pending') {
                                icon.classList.add('text-yellow-500');
                            } else if (btn.getAttribute('data-status') === 'resolved') {
                                icon.classList.add('text-green-500');
                            } else {
                                icon.classList.add('text-gray-500');
                            }
                        }
                    });

                    // Set active button styles
                    if (status === 'pending') {
                        this.classList.add('bg-yellow-50', 'text-yellow-700', 'border-yellow-200');
                        this.querySelector('i').classList.add('text-yellow-600');
                    } else if (status === 'resolved') {
                        this.classList.add('bg-green-50', 'text-green-700', 'border-green-200');
                        this.querySelector('i').classList.add('text-green-600');
                    } else {
                        this.classList.add('bg-blue-50', 'text-blue-700', 'border-blue-200');
                        this.querySelector('i').classList.add('text-blue-600');
                    }

                    // Filter incidents
                    const incidents = document.querySelectorAll('.incident-card');
                    const noResults = document.getElementById('no-results');
                    let visibleCount = 0;

                    incidents.forEach(incident => {
                        const incidentStatus = incident.getAttribute('data-status');
                        if (status === 'all' || incidentStatus === status) {
                            incident.classList.remove('hidden');
                            visibleCount++;
                        } else {
                            incident.classList.add('hidden');
                        }
                    });

                    // Show/hide empty state
                    // Only show "no results" if there are incidents but they're all filtered
                    const hasIncidents = incidents.length > 0;
                    if (visibleCount === 0 && hasIncidents) {
                        noResults.classList.remove('hidden');
                    } else {
                        noResults.classList.add('hidden');
                    }

                    // Update URL without page reload
                    const url = new URL(window.location);
                    if (status === 'all') {
                        url.searchParams.delete('status');
                    } else {
                        url.searchParams.set('status', status);
                    }
                    window.history.pushState({}, '', url);
                });
            });

            // Set initial active state from URL
            const urlParams = new URLSearchParams(window.location.search);
            const statusParam = urlParams.get('status');
            if (statusParam) {
                const activeButton = document.querySelector(`.status-toggle[data-status="${statusParam}"]`);
                if (activeButton) activeButton.click();
            } else {
                // Default to 'all' if no status in URL
                const allButton = document.querySelector('.status-toggle[data-status="all"]');
                if (allButton) allButton.click();
            }
        });

        // Resolve Modal Functions
        function showResolveModal(incidentId, serviceName, rootCause = '') {
            const modal = document.getElementById('resolveModal');
            const modalContent = document.getElementById('modalContent');

            // Set the incident ID and service name
            document.getElementById('modal_incident_id').value = incidentId;
            document.getElementById('modalServiceName').textContent = `Service: ${serviceName}`;
            
            // Pre-populate root cause if it exists
            document.getElementById('resolve_root_cause').value = rootCause;

            // Show modal with animation
            modal.classList.remove('hidden');
            setTimeout(() => {
                modalContent.classList.remove('opacity-0', 'scale-95');
                modalContent.classList.add('opacity-100', 'scale-100');
                // Focus on lessons learned field since that's required
                document.getElementById('resolve_lessons_learned').focus();
            }, 10);
        }

        function hideResolveModal() {
            const modal = document.getElementById('resolveModal');
            const modalContent = document.getElementById('modalContent');

            // Hide with animation
            modalContent.classList.remove('opacity-100', 'scale-100');
            modalContent.classList.add('opacity-0', 'scale-95');

            // Hide modal after animation
            setTimeout(() => {
                modal.classList.add('hidden');
                // Reset form
                document.getElementById('resolveForm').reset();
            }, 200);
        }

        // Close modal when clicking outside
        document.getElementById('resolveModal').addEventListener('click', function (e) {
            if (e.target === this) {
                hideResolveModal();
            }
        });

        // Handle form submission
        document.getElementById('resolveForm').addEventListener('submit', function (e) {
            const nameInput = document.getElementById('resolve_name');
            if (!nameInput.value.trim()) {
                e.preventDefault();
                nameInput.focus();
                // Add error class
                nameInput.classList.add('border-red-500');
                // Remove error class after animation
                setTimeout(() => nameInput.classList.remove('border-red-500'), 1000);
            }
        });

        // Close modal with ESC key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                if (!document.getElementById('resolveModal').classList.contains('hidden')) {
                    hideResolveModal();
                }
                if (!document.getElementById('reopenModal').classList.contains('hidden')) {
                    hideReopenModal();
                }
            }
        });

        // Reopen Modal Functions
        function showReopenModal(incidentId, serviceName) {
            const modal = document.getElementById('reopenModal');
            const modalContent = document.getElementById('reopenModalContent');

            // Set the incident ID and service name
            document.getElementById('reopen_incident_id').value = incidentId;
            document.getElementById('reopenModalServiceName').textContent = `Service: ${serviceName}`;

            // Show modal with animation
            modal.classList.remove('hidden');
            setTimeout(() => {
                modalContent.classList.remove('opacity-0', 'scale-95');
                modalContent.classList.add('opacity-100', 'scale-100');
            }, 10);
        }

        function hideReopenModal() {
            const modal = document.getElementById('reopenModal');
            const modalContent = document.getElementById('reopenModalContent');

            // Hide with animation
            modalContent.classList.remove('opacity-100', 'scale-100');
            modalContent.classList.add('opacity-0', 'scale-95');

            // Hide modal after animation
            setTimeout(() => {
                modal.classList.add('hidden');
                // Reset form
                document.getElementById('reopenForm').reset();
            }, 200);
        }

        // Close reopen modal when clicking outside
        document.getElementById('reopenModal').addEventListener('click', function (e) {
            if (e.target === this) {
                hideReopenModal();
            }
        });
    </script>

    <script>
        function toggleRootCause(issueId) {
            const rootCause = document.getElementById(`root-cause-${issueId}`);
            const readMoreBtn = rootCause.nextElementSibling;
            const readMoreText = readMoreBtn.querySelector('.read-more');
            const readLessText = readMoreBtn.querySelector('.read-less');

            if (rootCause.classList.contains('line-clamp-3')) {
                rootCause.classList.remove('line-clamp-3');
                readMoreText.classList.add('hidden');
                readLessText.classList.remove('hidden');
            } else {
                rootCause.classList.add('line-clamp-3');
                readMoreText.classList.remove('hidden');
                readLessText.classList.add('hidden');

                // Scroll the element into view if needed
                rootCause.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        }

        // Auto-resize textareas
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('textarea').forEach(textarea => {
                textarea.addEventListener('input', function () {
                    this.style.height = 'auto';
                    this.style.height = (this.scrollHeight) + 'px';
                });
            });
        });
    </script>

    <script>
        // Search/Filter incidents
        function filterIncidents() {
            const searchTerm = document.getElementById('incident-search').value.toLowerCase();
            const cards = document.querySelectorAll('.incident-card');
            let visibleCount = 0;

            cards.forEach(card => {
                const text = card.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    card.style.display = '';
                    visibleCount++;
                } else {
                    card.style.display = 'none';
                }
            });
        }

        // Refresh incidents function
        function refreshIncidents() {
            const btn = event.target.closest('button');

            // Show loading state
            btn.disabled = true;
            btn.innerHTML = '<div class="btn-spinner"></div> Refreshing...';

            // Reload the page
            setTimeout(() => {
                location.reload();
            }, 300);
        }
    </script>
    </div> <!-- End Content Wrapper -->
</body>

</html>