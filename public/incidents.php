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
        $userName = $_SESSION['full_name'];
        $errors = [];

        // Handle file uploads for root cause and lessons learned
        $root_cause_file = null;
        $lessons_learned_file = null;
        $uploadDir = __DIR__ . '/uploads/';
        $allowedExtensions = ['pdf', 'doc', 'docx', 'txt'];
        $maxFileSize = 10 * 1024 * 1024; // 10MB

        if ($status === 'resolved') {
            // Validate and process root cause file upload
            if (isset($_FILES['root_cause_file']) && $_FILES['root_cause_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['root_cause_file'];
                $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                if ($file['size'] > $maxFileSize) {
                    $errors[] = 'Root cause file exceeds 10MB limit.';
                } elseif (!in_array($fileExt, $allowedExtensions)) {
                    $errors[] = 'Invalid root cause file type. Allowed: PDF, DOC, DOCX, TXT.';
                } else {
                    $newFileName = md5(time() . $file['name']) . '.' . $fileExt;
                    $destPath = $uploadDir . 'root_cause/' . $newFileName;
                    if (move_uploaded_file($file['tmp_name'], $destPath)) {
                        $root_cause_file = 'uploads/root_cause/' . $newFileName;
                    } else {
                        $errors[] = 'Failed to upload root cause file.';
                    }
                }
            }

            // Validate and process lessons learned file upload
            if (isset($_FILES['lessons_learned_file']) && $_FILES['lessons_learned_file']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['lessons_learned_file'];
                $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                if ($file['size'] > $maxFileSize) {
                    $errors[] = 'Lessons learned file exceeds 10MB limit.';
                } elseif (!in_array($fileExt, $allowedExtensions)) {
                    $errors[] = 'Invalid lessons learned file type. Allowed: PDF, DOC, DOCX, TXT.';
                } else {
                    $newFileName = md5(time() . $file['name']) . '.' . $fileExt;
                    $destPath = $uploadDir . 'lessons_learned/' . $newFileName;
                    if (move_uploaded_file($file['tmp_name'], $destPath)) {
                        $lessons_learned_file = 'uploads/lessons_learned/' . $newFileName;
                    } else {
                        $errors[] = 'Failed to upload lessons learned file.';
                    }
                }
            }

            // Validation for resolved status
            $root_cause = $_POST['root_cause'] ?? '';
            $lessons_learned = $_POST['lessons_learned'] ?? '';
            $resolved_date = $_POST['resolved_date'] ?? null;

            // Check if root cause is required (either text or file)
            if (empty($root_cause) && empty($root_cause_file)) {
                $errors[] = 'Root cause is required when resolving an incident.';
            }

            // Check if lessons learned is provided (either text or file)
            if (empty($lessons_learned) && empty($lessons_learned_file)) {
                $errors[] = 'Lessons learned is required when resolving an incident.';
            }

            // Validate resolution date
            if (empty($resolved_date)) {
                $errors[] = 'Resolution date is required.';
            } elseif (strtotime($resolved_date) > time()) {
                $errors[] = 'Resolution date cannot be in the future.';
            }
        }

        if (!empty($errors)) {
            $_SESSION['error'] = implode(' ', $errors);
            header('Location: ' . $_SERVER['PHP_SELF']);
            exit;
        }

        // Get current incident status to detect if it's being reopened
        $currentStatusStmt = $pdo->prepare("SELECT status FROM incidents WHERE incident_id = :incident_id");
        $currentStatusStmt->execute([':incident_id' => $incidentId]);
        $currentStatus = $currentStatusStmt->fetchColumn();

        // Prepare the SQL based on status
        $sql = "UPDATE incidents SET status = :status";

        // Add fields for resolved status
        if ($status === 'resolved') {
            $sql .= ", resolved_by = :user_id, resolved_at = :resolved_at";
            if (!empty($root_cause)) {
                $sql .= ", root_cause = :root_cause";
            }
            if (!empty($root_cause_file)) {
                $sql .= ", root_cause_file = :root_cause_file";
            }
            if (!empty($lessons_learned)) {
                $sql .= ", lessons_learned = :lessons_learned";
            }
            if (!empty($lessons_learned_file)) {
                $sql .= ", lessons_learned_file = :lessons_learned_file";
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
            $params[':resolved_at'] = $resolved_date;
            if (!empty($root_cause)) {
                $params[':root_cause'] = $root_cause;
            }
            if (!empty($root_cause_file)) {
                $params[':root_cause_file'] = $root_cause_file;
            }
            if (!empty($lessons_learned)) {
                $params[':lessons_learned'] = $lessons_learned;
            }
            if (!empty($lessons_learned_file)) {
                $params[':lessons_learned_file'] = $lessons_learned_file;
            }
        }

        $stmt->execute($params);

        // Add system update with appropriate message
        // Detect if incident is being reopened (changing from resolved to pending)
        $isReopening = ($currentStatus === 'resolved' && $status === 'pending');

        if ($status === 'resolved') {
            $updateText = "Incident has been marked as resolved by " . $userName;
        } elseif ($isReopening) {
            $updateText = "Incident was reopened by " . $userName;
        } else {
            $updateText = "Incident status updated to pending by " . $userName;
        }

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
    } elseif ($_POST['action'] === 'edit_incident' && isset($_POST['incident_id'])) {
        // Handle incident editing
        $incidentId = intval($_POST['incident_id']);
        $serviceId = intval($_POST['service_id']);
        $componentId = !empty($_POST['component_id']) ? intval($_POST['component_id']) : null;
        $incidentTypeId = !empty($_POST['incident_type_id']) ? intval($_POST['incident_type_id']) : null;
        $impactLevel = $_POST['impact_level'];
        $priority = $_POST['priority'];
        $actualStartTime = $_POST['actual_start_time'];
        $description = !empty($_POST['description']) ? trim($_POST['description']) : null;
        $companies = isset($_POST['companies']) ? $_POST['companies'] : [];

        // Validate required fields
        if (empty($companies)) {
            $_SESSION['error'] = "Please select at least one affected company.";
        } else {
            try {
                $pdo->beginTransaction();

                // Update incident
                $stmt = $pdo->prepare("
                    UPDATE incidents 
                    SET service_id = :service_id,
                        component_id = :component_id,
                        incident_type_id = :incident_type_id,
                        impact_level = :impact_level,
                        priority = :priority,
                        actual_start_time = :actual_start_time,
                        description = :description,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE incident_id = :incident_id
                ");
                $stmt->execute([
                    ':service_id' => $serviceId,
                    ':component_id' => $componentId,
                    ':incident_type_id' => $incidentTypeId,
                    ':impact_level' => $impactLevel,
                    ':priority' => $priority,
                    ':actual_start_time' => $actualStartTime,
                    ':description' => $description,
                    ':incident_id' => $incidentId
                ]);

                // Update affected companies with audit trail
                // First, get existing companies to track changes
                $existingStmt = $pdo->prepare("SELECT company_id FROM incident_affected_companies WHERE incident_id = :incident_id");
                $existingStmt->execute([':incident_id' => $incidentId]);
                $existingCompanies = $existingStmt->fetchAll(PDO::FETCH_COLUMN);

                // Convert new companies to integers for comparison
                $newCompanies = array_map('intval', $companies);

                // Determine which companies were added and removed
                $addedCompanies = array_diff($newCompanies, $existingCompanies);
                $removedCompanies = array_diff($existingCompanies, $newCompanies);

                // Log removed companies to history
                if (!empty($removedCompanies)) {
                    $historyStmt = $pdo->prepare("
                        INSERT INTO incident_company_history (incident_id, company_id, action, changed_by) 
                        VALUES (:incident_id, :company_id, 'removed', :changed_by)
                    ");
                    foreach ($removedCompanies as $companyId) {
                        $historyStmt->execute([
                            ':incident_id' => $incidentId,
                            ':company_id' => $companyId,
                            ':changed_by' => $_SESSION['user_id']
                        ]);
                    }
                }

                // Log added companies to history
                if (!empty($addedCompanies)) {
                    $historyStmt = $pdo->prepare("
                        INSERT INTO incident_company_history (incident_id, company_id, action, changed_by) 
                        VALUES (:incident_id, :company_id, 'added', :changed_by)
                    ");
                    foreach ($addedCompanies as $companyId) {
                        $historyStmt->execute([
                            ':incident_id' => $incidentId,
                            ':company_id' => $companyId,
                            ':changed_by' => $_SESSION['user_id']
                        ]);
                    }
                }

                // Delete existing companies
                $deleteStmt = $pdo->prepare("DELETE FROM incident_affected_companies WHERE incident_id = :incident_id");
                $deleteStmt->execute([':incident_id' => $incidentId]);

                // Insert new companies
                $insertStmt = $pdo->prepare("
                    INSERT INTO incident_affected_companies (incident_id, company_id) 
                    VALUES (:incident_id, :company_id)
                ");
                foreach ($newCompanies as $companyId) {
                    $insertStmt->execute([
                        ':incident_id' => $incidentId,
                        ':company_id' => $companyId
                    ]);
                }

                // Handle attachment deletions
                if (!empty($_POST['delete_attachments']) && !empty($_POST['delete_attachment_paths'])) {
                    $deleteAttachmentIds = $_POST['delete_attachments'];
                    $deleteAttachmentPaths = $_POST['delete_attachment_paths'];

                    foreach ($deleteAttachmentIds as $index => $attachmentId) {
                        $attachmentId = intval($attachmentId);
                        $filePath = $deleteAttachmentPaths[$index];

                        // Delete from database
                        $deleteAttStmt = $pdo->prepare("DELETE FROM incident_attachments WHERE attachment_id = :attachment_id AND incident_id = :incident_id");
                        $deleteAttStmt->execute([
                            ':attachment_id' => $attachmentId,
                            ':incident_id' => $incidentId
                        ]);

                        // Delete file from server
                        $fullPath = __DIR__ . '/../' . $filePath;
                        if (file_exists($fullPath)) {
                            unlink($fullPath);
                        }
                    }
                }

                // Handle new file uploads
                if (!empty($_FILES['new_attachments']['name'][0])) {
                    $uploadDir = __DIR__ . '/../uploads/incidents/';
                    $allowedTypes = [
                        'image/jpeg',
                        'image/jpg',
                        'image/png',
                        'image/gif',
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'text/plain'
                    ];
                    $maxFileSize = 10 * 1024 * 1024; // 10MB

                    foreach ($_FILES['new_attachments']['name'] as $key => $fileName) {
                        if ($_FILES['new_attachments']['error'][$key] === UPLOAD_ERR_OK) {
                            $fileTmpPath = $_FILES['new_attachments']['tmp_name'][$key];
                            $fileType = $_FILES['new_attachments']['type'][$key];
                            $fileSize = $_FILES['new_attachments']['size'][$key];

                            // Get custom name if provided
                            $customName = isset($_POST['new_file_custom_names'][$key]) && !empty($_POST['new_file_custom_names'][$key])
                                ? $_POST['new_file_custom_names'][$key]
                                : $fileName;

                            // Validate file type
                            if (!in_array($fileType, $allowedTypes)) {
                                continue; // Skip invalid files
                            }

                            // Validate file size
                            if ($fileSize > $maxFileSize) {
                                continue; // Skip files that are too large
                            }

                            // Generate unique filename
                            $fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);
                            $newFileName = md5(uniqid() . $fileName . time()) . '.' . $fileExtension;
                            $destPath = $uploadDir . $newFileName;

                            // Move uploaded file
                            if (move_uploaded_file($fileTmpPath, $destPath)) {
                                // Insert into database
                                $insertAttStmt = $pdo->prepare("
                                    INSERT INTO incident_attachments (incident_id, file_path, file_name, file_type, file_size)
                                    VALUES (:incident_id, :file_path, :file_name, :file_type, :file_size)
                                ");
                                $insertAttStmt->execute([
                                    ':incident_id' => $incidentId,
                                    ':file_path' => 'uploads/incidents/' . $newFileName,
                                    ':file_name' => $customName,
                                    ':file_type' => $fileType,
                                    ':file_size' => $fileSize
                                ]);
                            }
                        }
                    }
                }

                // Add system update log
                $updateText = "Incident details updated by " . $_SESSION['full_name'];
                $logStmt = $pdo->prepare("
                    INSERT INTO incident_updates (incident_id, user_id, user_name, update_text) 
                    VALUES (:incident_id, :user_id, :user_name, :update_text)
                ");
                $logStmt->execute([
                    ':incident_id' => $incidentId,
                    ':user_id' => $_SESSION['user_id'],
                    ':user_name' => 'System',
                    ':update_text' => $updateText
                ]);

                $pdo->commit();
                $_SESSION['success'] = "Incident details updated successfully!";

            } catch (PDOException $e) {
                $pdo->rollBack();
                $_SESSION['error'] = "Failed to update incident: " . $e->getMessage();
            }
        }
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
            i.priority,
            i.attachment_path,
            u.full_name as user_name,
            i.created_at,
            res.full_name as resolved_by,
            i.resolved_at,
            i.updated_at,
            i.root_cause_file,
            i.lessons_learned,
            i.lessons_learned_file,
            s.service_name,
            sc.name as component_name,
            it.name as incident_type_name,
            CASE 
                WHEN GROUP_CONCAT(DISTINCT c.company_name ORDER BY c.company_name SEPARATOR ', ') LIKE '%All%' 
                THEN 'All'
                ELSE GROUP_CONCAT(DISTINCT c.company_name ORDER BY c.company_name SEPARATOR ', ')
            END as affected_companies,
            COUNT(DISTINCT c.company_id) as company_count,
            (SELECT COUNT(*) FROM incident_updates iu WHERE iu.incident_id = i.incident_id) as update_count,
            (SELECT COUNT(*) FROM incident_attachments ia WHERE ia.incident_id = i.incident_id) as attachment_count
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

    // Fetch data for edit modal dropdowns
    $services = $pdo->query("SELECT service_id, service_name FROM services ORDER BY service_name")->fetchAll();
    $components = $pdo->query("SELECT component_id, name, service_id FROM service_components ORDER BY name")->fetchAll();
    $incidentTypes = $pdo->query("SELECT type_id, name FROM incident_types ORDER BY name")->fetchAll();
    $companies = $pdo->query("SELECT company_id, company_name FROM companies ORDER BY company_name")->fetchAll();

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
                                <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor"
                                    viewBox="0 0 24 24">
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
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24"
                            stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No incidents found</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Try selecting a different filter.</p>
                    </div>

                    <?php if (empty($incidents)): ?>
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No incidents reported yet
                            </h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by reporting a new
                                incident.
                            </p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($incidents as $incident):
                            $statusClass = $incident['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' : 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400';

                            // Impact level colors
                            $impactColors = [
                                'critical' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
                                'high' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
                                'medium' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
                                'low' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400'
                            ];
                            $impactClass = $impactColors[strtolower($incident['impact_level'])] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300';

                            // Priority colors
                            $priorityColors = [
                                'urgent' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 border-red-300 dark:border-red-700',
                                'high' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400 border-orange-300 dark:border-orange-700',
                                'medium' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400 border-blue-300 dark:border-blue-700',
                                'low' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600'
                            ];
                            $priorityClass = $priorityColors[strtolower($incident['priority'])] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600';
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
                                                    Resolved by
                                                    <?php echo htmlspecialchars($incident['resolved_by'] ?? 'System'); ?> on
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
                                                <div class="flex items-center justify-between mb-1">
                                                    <h4
                                                        class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                                        Component Affected
                                                    </h4>
                                                    <?php if ($incident['status'] === 'pending'): ?>
                                                        <button type="button"
                                                            onclick="showEditModal(<?php echo $incident['incident_id']; ?>)"
                                                            class="inline-flex items-center px-2 py-1 text-xs font-medium text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 focus:outline-none">
                                                            <i class="fas fa-edit mr-1"></i> Edit Details
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
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
                                                    Description</h4>
                                                <?php if (!empty($incident['description'])): ?>
                                                    <p class="mt-1 text-sm text-gray-900 dark:text-white">
                                                        <?php echo nl2br(htmlspecialchars($incident['description'])); ?>
                                                    </p>
                                                <?php else: ?>
                                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 italic">No description
                                                        provided
                                                    </p>
                                                <?php endif; ?>
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
                                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400 italic">Not specified
                                                    </p>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Lessons Learned (only show for resolved incidents) -->
                                            <?php if ($incident['status'] === 'resolved' && !empty($incident['lessons_learned'])): ?>
                                                <div>
                                                    <h4
                                                        class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                                        Lessons Learned</h4>
                                                    <p class="mt-1 text-sm text-gray-900 dark:text-white">
                                                        <?php echo nl2br(htmlspecialchars($incident['lessons_learned'])); ?>
                                                    </p>
                                                </div>
                                            <?php endif; ?>

                                            <?php
                                            // Fetch all attachments for this incident
                                            $attachmentsQuery = $pdo->prepare("
                                                SELECT file_path, file_name, uploaded_at 
                                                FROM incident_attachments 
                                                WHERE incident_id = :incident_id 
                                                ORDER BY uploaded_at ASC
                                            ");
                                            $attachmentsQuery->execute([':incident_id' => $incident['incident_id']]);
                                            $attachments = $attachmentsQuery->fetchAll();

                                            // Build attachments array - prioritize incident_attachments table
                                            $allAttachments = [];

                                            // First, add all attachments from incident_attachments table (has proper file_name)
                                            foreach ($attachments as $attachment) {
                                                $allAttachments[] = [
                                                    'file_path' => $attachment['file_path'],
                                                    'file_name' => $attachment['file_name']
                                                ];
                                            }

                                            // Then, add legacy attachment_path only if it's not already in the list
                                            if (!empty($incident['attachment_path'])) {
                                                $exists = false;
                                                foreach ($allAttachments as $existing) {
                                                    if ($existing['file_path'] === $incident['attachment_path']) {
                                                        $exists = true;
                                                        break;
                                                    }
                                                }
                                                if (!$exists) {
                                                    // For legacy attachment_path, extract filename from path
                                                    $allAttachments[] = [
                                                        'file_path' => $incident['attachment_path'],
                                                        'file_name' => basename($incident['attachment_path'])
                                                    ];
                                                }
                                            }
                                            ?>

                                            <?php if (!empty($allAttachments)): ?>
                                                <div>
                                                    <h4
                                                        class="text-sm font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                                        <?php echo count($allAttachments) > 1 ? 'Attachments' : 'Attachment'; ?>
                                                    </h4>
                                                    <div class="mt-2 flex flex-wrap gap-2">
                                                        <?php foreach ($allAttachments as $attachment): ?>
                                                            <a href="<?= url($attachment['file_path']) ?>" target="_blank"
                                                                class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-blue-600 dark:text-blue-400 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                                                <i class="fas fa-file mr-2"></i>
                                                                <?php
                                                                $displayName = $attachment['file_name'];
                                                                echo htmlspecialchars(strlen($displayName) > 30 ? substr($displayName, 0, 27) . '...' : $displayName);
                                                                ?>
                                                            </a>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- RIGHT COLUMN: Action Taken -->
                                        <div class="lg:border-l lg:border-gray-200 dark:lg:border-gray-700 lg:pl-6">
                                            <!-- Reported By & Priority - Side by Side -->
                                            <div class="mb-4 pb-3 border-b border-gray-200 dark:border-gray-700">
                                                <div class="flex justify-between items-start gap-4">
                                                    <!-- Reported By -->
                                                    <div>
                                                        <p
                                                            class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                                            Reported By
                                                        </p>
                                                        <div class="flex items-center gap-2">
                                                            <i class="fas fa-user-circle text-gray-400 dark:text-gray-500"></i>
                                                            <span class="text-sm font-medium text-gray-900 dark:text-white">
                                                                <?php echo htmlspecialchars($incident['user_name']); ?>
                                                            </span>
                                                        </div>
                                                        <span class="text-xs text-gray-500 dark:text-gray-400 ml-6">
                                                            <?php echo date('M j, Y g:i A', strtotime($incident['created_at'])); ?>
                                                        </span>
                                                    </div>

                                                    <!-- Priority -->
                                                    <div class="text-right">
                                                        <p
                                                            class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">
                                                            Priority
                                                        </p>
                                                        <div class="flex items-center justify-end gap-2">
                                                            <span
                                                                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full border <?php echo $priorityClass; ?>">
                                                                <?php echo ucfirst($incident['priority']); ?>
                                                            </span>
                                                        </div>
                                                    </div>
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
            <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full transform transition-all duration-300 scale-95 opacity-0 max-h-[90vh] overflow-y-auto"
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
                            <label for="root_cause_textarea" class="block text-sm font-medium text-gray-700 mb-2">
                                Root Cause <span class="text-red-500">*</span>
                            </label>
                            <textarea name="root_cause" id="root_cause_textarea" rows="3" required
                                class="block w-full border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Describe the root cause of this incident..."></textarea>
                        </div>

                        <!-- Lessons Learned -->
                        <div>
                            <label for="lessons_learned" class="block text-sm font-medium text-gray-700 mb-2">
                                Lessons Learned <span class="text-red-500">*</span>
                            </label>
                            <textarea name="lessons_learned" id="lessons_learned" rows="4" required
                                class="block w-full border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-blue-500 focus:border-blue-500"
                                placeholder="What did we learn from this incident? How can we prevent it in the future?"></textarea>
                        </div>

                        <!-- Resolution Date -->
                        <div>
                            <label for="resolved_date" class="block text-sm font-medium text-gray-700">
                                Resolution Date & Time <span class="text-red-500">*</span>
                            </label>
                            <input type="datetime-local" name="resolved_date" id="resolved_date" required
                                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm py-2 px-3 text-sm focus:ring-blue-500 focus:border-blue-500">
                            <p class="text-xs text-gray-500 mt-1">When was this incident actually resolved?</p>
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
                            Are you sure you want to reopen this resolved incident? This will allow new updates to be
                            added.
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

        <!-- Edit Incident Modal -->
        <div id="editModal"
            class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50 p-4 transition-opacity duration-300">
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-3xl w-full transform transition-all duration-300 scale-95 opacity-0 max-h-[90vh] overflow-y-auto"
                id="editModalContent">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-1">Edit Incident Details</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4" id="editModalIncidentInfo"></p>

                    <form id="editForm" method="POST" enctype="multipart/form-data" class="space-y-4" x-data="{
                        existingAttachments: [],
                        attachmentsToDelete: [],
                        newFilePreviews: [],
                        markForDeletion(attachmentId, filePath) {
                            this.attachmentsToDelete.push({ id: attachmentId, path: filePath });
                            // Find and mark the attachment visually
                            const attachment = this.existingAttachments.find(a => a.attachment_id === attachmentId);
                            if (attachment) attachment.markedForDeletion = true;
                        },
                        unmarkForDeletion(attachmentId) {
                            this.attachmentsToDelete = this.attachmentsToDelete.filter(a => a.id !== attachmentId);
                            const attachment = this.existingAttachments.find(a => a.attachment_id === attachmentId);
                            if (attachment) attachment.markedForDeletion = false;
                        },
                        handleNewFiles(event) {
                            const files = Array.from(event.target.files);
                            const imageTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                            
                            files.forEach(file => {
                                const preview = {
                                    name: file.name,
                                    customName: file.name,
                                    type: imageTypes.includes(file.type) ? 'image' : 'document',
                                    url: null
                                };
                                
                                if (preview.type === 'image') {
                                    const reader = new FileReader();
                                    reader.onload = (e) => {
                                        preview.url = e.target.result;
                                        this.newFilePreviews.push(preview);
                                    };
                                    reader.readAsDataURL(file);
                                } else {
                                    this.newFilePreviews.push(preview);
                                }
                            });
                        },
                        removeNewFile(index) {
                            this.newFilePreviews.splice(index, 1);
                            const fileInput = this.$refs.newFileInput;
                            const dt = new DataTransfer();
                            const files = Array.from(fileInput.files);
                            files.forEach((file, i) => {
                                if (i !== index) dt.items.add(file);
                            });
                            fileInput.files = dt.files;
                        }
                    }">
                        <input type="hidden" name="action" value="edit_incident">
                        <input type="hidden" name="incident_id" id="edit_incident_id" value="">
                        <!-- Hidden inputs for attachments to delete -->
                        <template x-for="attachment in attachmentsToDelete" :key="attachment.id">
                            <input type="hidden" name="delete_attachments[]" :value="attachment.id">
                        </template>
                        <template x-for="attachment in attachmentsToDelete" :key="attachment.path">
                            <input type="hidden" name="delete_attachment_paths[]" :value="attachment.path">
                        </template>


                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Service -->
                            <div>
                                <label for="edit_service"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Service <span class="text-red-500">*</span>
                                </label>
                                <select id="edit_service" name="service_id" required
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm py-2 px-3 text-sm focus:ring-blue-500 focus:border-blue-500">
                                    <?php foreach ($services as $service): ?>
                                        <option value="<?= $service['service_id'] ?>">
                                            <?= htmlspecialchars($service['service_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Component -->
                            <div>
                                <label for="edit_component"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Component
                                </label>
                                <select id="edit_component" name="component_id"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm py-2 px-3 text-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">All</option>
                                    <?php foreach ($components as $component): ?>
                                        <option value="<?= $component['component_id'] ?>"
                                            data-service="<?= $component['service_id'] ?>">
                                            <?= htmlspecialchars($component['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Incident Type -->
                            <div>
                                <label for="edit_incident_type"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Incident Type
                                </label>
                                <select id="edit_incident_type" name="incident_type_id"
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm py-2 px-3 text-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="">All</option>
                                    <?php foreach ($incidentTypes as $type): ?>
                                        <option value="<?= $type['type_id'] ?>">
                                            <?= htmlspecialchars($type['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Impact Level -->
                            <div>
                                <label for="edit_impact"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Impact Level <span class="text-red-500">*</span>
                                </label>
                                <select id="edit_impact" name="impact_level" required
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm py-2 px-3 text-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="Low">Low</option>
                                    <option value="Medium">Medium</option>
                                    <option value="High">High</option>
                                    <option value="Critical">Critical</option>
                                </select>
                            </div>

                            <!-- Priority -->
                            <div>
                                <label for="edit_priority"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Priority <span class="text-red-500">*</span>
                                </label>
                                <select id="edit_priority" name="priority" required
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm py-2 px-3 text-sm focus:ring-blue-500 focus:border-blue-500">
                                    <option value="Low">Low</option>
                                    <option value="Medium">Medium</option>
                                    <option value="High">High</option>
                                    <option value="Urgent">Urgent</option>
                                </select>
                            </div>

                            <!-- Actual Start Time -->
                            <div>
                                <label for="edit_start_time"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Actual Start Time <span class="text-red-500">*</span>
                                </label>
                                <input type="datetime-local" id="edit_start_time" name="actual_start_time" required
                                    class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm py-2 px-3 text-sm focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>

                        <!-- Description -->
                        <div>
                            <label for="edit_description"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Description
                            </label>
                            <textarea id="edit_description" name="description" rows="3"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-md shadow-sm py-2 px-3 text-sm focus:ring-blue-500 focus:border-blue-500"
                                placeholder="Describe the incident..."></textarea>
                        </div>

                        <!-- Attachments Management -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Attachments
                            </label>

                            <!-- Existing Attachments -->
                            <div x-show="existingAttachments.length > 0" class="mb-3">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">Current Attachments:</p>
                                <div class="space-y-2">
                                    <template x-for="(attachment, index) in existingAttachments"
                                        :key="attachment.attachment_id">
                                        <div class="flex items-center justify-between p-2 border rounded-md"
                                            :class="attachment.markedForDeletion ? 'border-red-300 bg-red-50 dark:bg-red-900/20' : 'border-gray-300 dark:border-gray-600 bg-gray-50 dark:bg-gray-700'">
                                            <div class="flex items-center space-x-2 flex-1 min-w-0">
                                                <i class="fas fa-file text-gray-400"
                                                    :class="attachment.markedForDeletion ? 'text-red-400' : 'text-gray-400'"></i>
                                                <span class="text-sm truncate"
                                                    :class="attachment.markedForDeletion ? 'line-through text-red-500' : 'text-gray-700 dark:text-gray-300'"
                                                    x-text="attachment.file_name"></span>
                                            </div>
                                            <button type="button"
                                                @click="attachment.markedForDeletion ? unmarkForDeletion(attachment.attachment_id) : markForDeletion(attachment.attachment_id, attachment.file_path)"
                                                class="text-gray-400 hover:text-red-500 focus:outline-none transition-colors ml-2"
                                                :class="attachment.markedForDeletion ? 'text-green-500 hover:text-green-600' : ''">
                                                <i class="fas text-lg"
                                                    :class="attachment.markedForDeletion ? 'fa-undo' : 'fa-times'"></i>
                                            </button>
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <!-- New File Previews -->
                            <template x-if="newFilePreviews.length > 0">
                                <div class="mb-3 space-y-2">
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-2">New Attachments:</p>
                                    <template x-for="(preview, index) in newFilePreviews" :key="index">
                                        <div
                                            class="border border-gray-300 dark:border-gray-600 rounded-lg p-3 bg-white dark:bg-gray-700">
                                            <div class="flex items-center gap-3">
                                                <!-- Preview Icon/Image -->
                                                <div class="flex-shrink-0">
                                                    <template x-if="preview.type === 'image'">
                                                        <img :src="preview.url" class="w-12 h-12 object-cover rounded"
                                                            alt="Preview">
                                                    </template>
                                                    <template x-if="preview.type === 'document'">
                                                        <div
                                                            class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded flex items-center justify-center">
                                                            <i
                                                                class="fas fa-file-alt text-blue-600 dark:text-blue-400 text-xl"></i>
                                                        </div>
                                                    </template>
                                                </div>

                                                <!-- File Info -->
                                                <div class="flex-1 min-w-0">
                                                    <label
                                                        class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">
                                                        Display Name
                                                    </label>
                                                    <input type="text" x-model="preview.customName"
                                                        :name="'new_file_custom_names[' + index + ']'"
                                                        class="block w-full text-sm border-gray-300 dark:border-gray-600 rounded-md shadow-sm py-1.5 px-2 bg-white dark:bg-gray-800 dark:text-white focus:ring-blue-500 focus:border-blue-500"
                                                        placeholder="Enter display name...">
                                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                        Original: <span x-text="preview.name"></span>
                                                    </p>
                                                </div>

                                                <!-- Remove Button -->
                                                <button @click="removeNewFile(index)" type="button"
                                                    class="self-center text-gray-400 hover:text-red-500 focus:outline-none transition-colors">
                                                    <i class="fas fa-times text-lg"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <!-- File Upload Input -->
                            <div
                                class="border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg p-4 text-center hover:border-blue-400 dark:hover:border-blue-500 transition-colors">
                                <div class="flex text-sm text-gray-600 dark:text-gray-400 justify-center">
                                    <label for="new_attachments"
                                        class="relative cursor-pointer bg-white dark:bg-gray-800 rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500 px-2 py-0.5 border border-blue-600/20">
                                        <span>Upload files</span>
                                        <input id="new_attachments" name="new_attachments[]" type="file" class="sr-only"
                                            x-ref="newFileInput" @change="handleNewFiles" multiple>
                                    </label>
                                    <p class="pl-1">or drag and drop</p>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    PNG, JPG, GIF, PDF, DOC, TXT up to 10MB each
                                </p>
                            </div>
                        </div>

                        <!-- Affected Companies -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Affected Companies <span class="text-red-500">*</span>
                            </label>
                            <div
                                class="grid grid-cols-2 md:grid-cols-3 gap-2 max-h-48 overflow-y-auto border border-gray-300 dark:border-gray-600 rounded-md p-3">
                                <?php foreach ($companies as $company): ?>
                                    <label class="flex items-center space-x-2 text-sm">
                                        <input type="checkbox" name="companies[]" value="<?= $company['company_id'] ?>"
                                            class="edit-company-checkbox rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500">
                                        <span class="text-gray-700 dark:text-gray-300">
                                            <?= htmlspecialchars($company['company_name']) ?>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="flex justify-end space-x-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                            <button type="button" onclick="hideEditModal()"
                                class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Cancel
                            </button>
                            <button type="submit"
                                class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-save mr-1"></i> Save Changes
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

                // Set current date/time as default resolution date
                const now = new Date();
                const year = now.getFullYear();
                const month = String(now.getMonth() + 1).padStart(2, '0');
                const day = String(now.getDate()).padStart(2, '0');
                const hours = String(now.getHours()).padStart(2, '0');
                const minutes = String(now.getMinutes()).padStart(2, '0');
                const currentDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
                document.getElementById('resolved_date').value = currentDateTime;

                // Get the form element
                const form = document.getElementById('resolveForm');

                // Set Alpine.js data using x-data attributes
                form.setAttribute('x-data', JSON.stringify({
                    rootCauseMode: 'text',
                    lessonsMode: 'text',
                    rootCauseFileName: '',
                    lessonsFileName: ''
                }));

                // Show modal with animation
                modal.classList.remove('hidden');
                setTimeout(() => {
                    modalContent.classList.remove('opacity-0', 'scale-95');
                    modalContent.classList.add('opacity-100', 'scale-100');

                    // Pre-populate the root cause textarea if it exists
                    const rootCauseTextarea = document.getElementById('root_cause_textarea');
                    if (rootCauseTextarea && rootCause) {
                        rootCauseTextarea.value = rootCause;
                    }

                    document.getElementById('resolve_name').focus();
                }, 100);
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

            // Edit Modal Functions
            function showEditModal(incidentId) {
                // Fetch incident data with attachments
                fetch(`get_incident.php?id=${incidentId}&include_attachments=1`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.error) {
                            alert(data.error);
                            return;
                        }

                        // Populate form fields
                        document.getElementById('edit_incident_id').value = data.incident_id;
                        document.getElementById('editModalIncidentInfo').textContent = `Incident #${data.incident_id} - ${data.service_name}`;
                        document.getElementById('edit_service').value = data.service_id || '';
                        document.getElementById('edit_component').value = data.component_id || '';
                        document.getElementById('edit_incident_type').value = data.incident_type_id || '';
                        document.getElementById('edit_impact').value = data.impact_level;
                        document.getElementById('edit_priority').value = data.priority;
                        document.getElementById('edit_description').value = data.description || '';

                        // Format datetime for datetime-local input
                        if (data.actual_start_time) {
                            const date = new Date(data.actual_start_time);
                            const year = date.getFullYear();
                            const month = String(date.getMonth() + 1).padStart(2, '0');
                            const day = String(date.getDate()).padStart(2, '0');
                            const hours = String(date.getHours()).padStart(2, '0');
                            const minutes = String(date.getMinutes()).padStart(2, '0');
                            document.getElementById('edit_start_time').value = `${year}-${month}-${day}T${hours}:${minutes}`;
                        }

                        // Check affected companies
                        const allEditCheckboxes = document.querySelectorAll('.edit-company-checkbox');
                        const allEditCheckbox = Array.from(allEditCheckboxes).find(cb => cb.value === '3'); // Assuming "All" has company_id = 3
                        const otherEditCheckboxes = Array.from(allEditCheckboxes).filter(cb => cb.value !== '3');

                        // First, uncheck all
                        allEditCheckboxes.forEach(checkbox => {
                            checkbox.checked = false;
                        });

                        // Then check the appropriate ones
                        data.affected_companies.forEach(companyId => {
                            const checkbox = document.querySelector(`.edit-company-checkbox[value="${companyId}"]`);
                            if (checkbox) {
                                checkbox.checked = true;
                            }
                        });

                        // If "All" is checked, uncheck others
                        if (allEditCheckbox && allEditCheckbox.checked) {
                            otherEditCheckboxes.forEach(cb => cb.checked = false);
                        }

                        // Add event listeners for exclusive selection
                        if (allEditCheckbox) {
                            allEditCheckbox.addEventListener('change', function () {
                                if (this.checked) {
                                    otherEditCheckboxes.forEach(cb => cb.checked = false);
                                }
                            });

                            otherEditCheckboxes.forEach(checkbox => {
                                checkbox.addEventListener('change', function () {
                                    if (this.checked) {
                                        allEditCheckbox.checked = false;
                                    }
                                });
                            });
                        }

                        // Load existing attachments into Alpine.js
                        console.log('Attachment data received:', data.attachments);
                        if (data.attachments && data.attachments.length > 0) {
                            // Wait for modal to be visible before accessing Alpine data
                            setTimeout(() => {
                                const form = document.getElementById('editForm');
                                if (form && form._x_dataStack) {
                                    const alpineData = form._x_dataStack[0];
                                    console.log('Alpine data found:', alpineData);
                                    alpineData.existingAttachments = data.attachments.map(att => ({
                                        ...att,
                                        markedForDeletion: false
                                    }));
                                    alpineData.attachmentsToDelete = [];
                                    alpineData.newFilePreviews = [];
                                    console.log('Attachments loaded:', alpineData.existingAttachments);
                                } else {
                                    console.error('Alpine.js data not found on form element');
                                }
                            }, 100);
                        } else {
                            console.log('No attachments found for this incident');
                        }

                        // Show modal with animation
                        const modal = document.getElementById('editModal');
                        const modalContent = document.getElementById('editModalContent');
                        modal.classList.remove('hidden');
                        setTimeout(() => {
                            modalContent.classList.remove('opacity-0', 'scale-95');
                            modalContent.classList.add('opacity-100', 'scale-100');
                        }, 10);
                    })
                    .catch(error => {
                        console.error('Error fetching incident data:', error);
                        alert('Failed to load incident data. Please try again.');
                    });
            }

            function hideEditModal() {
                const modal = document.getElementById('editModal');
                const modalContent = document.getElementById('editModalContent');

                // Hide with animation
                modalContent.classList.remove('opacity-100', 'scale-100');
                modalContent.classList.add('opacity-0', 'scale-95');

                // Hide modal after animation
                setTimeout(() => {
                    modal.classList.add('hidden');
                    document.getElementById('editForm').reset();
                }, 200);
            }

            // Close edit modal when clicking outside
            document.getElementById('editModal').addEventListener('click', function (e) {
                if (e.target === this) {
                    hideEditModal();
                }
            });

            // Handle edit form submission
            document.getElementById('editForm').addEventListener('submit', function (e) {
                e.preventDefault();

                // Validate at least one company is selected
                const selectedCompanies = document.querySelectorAll('.edit-company-checkbox:checked');
                if (selectedCompanies.length === 0) {
                    alert('Please select at least one affected company.');
                    return;
                }

                // Submit form
                this.submit();
            });

            // Handle form submission with validation
            document.getElementById('resolveForm').addEventListener('submit', function (e) {
                e.preventDefault(); // Always prevent default first

                const form = this;
                const errors = [];

                // Validate resolution date
                const resolvedDate = document.getElementById('resolved_date').value;
                if (!resolvedDate) {
                    errors.push('Resolution date is required.');
                }

                // Validate root cause text
                const rootCauseText = form.querySelector('textarea[name="root_cause"]')?.value.trim() || '';
                if (!rootCauseText) {
                    errors.push('Root cause is required.');
                }

                // Validate lessons learned text
                const lessonsText = form.querySelector('textarea[name="lessons_learned"]')?.value.trim() || '';
                if (!lessonsText) {
                    errors.push('Lessons learned is required.');
                }

                // If there are errors, show them and don't submit
                if (errors.length > 0) {
                    alert('Please fix the following errors:\n\n' + errors.join('\n'));
                    return false;
                }

                // If validation passes, submit the form
                form.submit();
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