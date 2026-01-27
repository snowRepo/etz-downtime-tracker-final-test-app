<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../src/includes/auth.php';
requireLogin();

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Simple rate limiting (5 requests per minute)
$rateLimitKey = 'rate_limit_' . md5($_SERVER['REMOTE_ADDR']);
$currentTime = time();
$rateWindow = 60; // 1 minute
$maxRequests = 5;

if (isset($_SESSION[$rateLimitKey])) {
    list($count, $timestamp) = explode('|', $_SESSION[$rateLimitKey]);
    
    if (($currentTime - $timestamp) < $rateWindow) {
        if ($count >= $maxRequests) {
            die("Too many requests. Please try again later.");
        }
        $count++;
    } else {
        $count = 1;
    }
} else {
    $count = 1;
}

$_SESSION[$rateLimitKey] = "$count|$currentTime";

// Helper function to sanitize output
function sanitize($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// Fetch services, companies, components, and types
try {
    $services = $pdo->query("SELECT * FROM services ORDER BY service_name")->fetchAll(PDO::FETCH_ASSOC);
    $components = $pdo->query("SELECT * FROM service_components WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $incidentTypes = $pdo->query("SELECT * FROM incident_types WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch all companies and sort them with 'All' first, then alphabetically
    $allCompanies = $pdo->query("SELECT * FROM companies")->fetchAll(PDO::FETCH_ASSOC);
    
    // Separate 'All' from other companies
    $allOption = [];
    $otherCompanies = [];
    
    foreach ($allCompanies as $company) {
        if (strtolower($company['company_name']) === 'all') {
            $allOption[] = $company;
        } else {
            $otherCompanies[] = $company;
        }
    }
    
    // Sort other companies alphabetically
    usort($otherCompanies, function($a, $b) {
        return strcasecmp($a['company_name'], $b['company_name']);
    });
    
    // Combine 'All' with the rest of the companies
    $companies = array_merge($allOption, $otherCompanies);
} catch(PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    die("An error occurred. Please try again later.");
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid request");
    }

    // Sanitize and validate inputs
    $service_id = $_POST['service_id'] === 'all' ? 'all' : filter_var($_POST['service_id'] ?? null, FILTER_VALIDATE_INT);
    $component_id = $_POST['component_id'] === 'all' ? 'all' : (filter_var($_POST['component_id'] ?? null, FILTER_VALIDATE_INT) ?: null);
    $incident_type_id = $_POST['incident_type_id'] === 'all' ? 'all' : (filter_var($_POST['incident_type_id'] ?? null, FILTER_VALIDATE_INT) ?: null);
    $impact_level = in_array($_POST['impact_level'] ?? '', ['Low', 'Medium', 'High', 'Critical']) ? $_POST['impact_level'] : 'Low';
    $root_cause = trim(filter_var($_POST['root_cause'] ?? '', FILTER_SANITIZE_STRING));
    
    // Handle incident date and time
    $incident_date = $_POST['incident_date'] ?? date('Y-m-d');
    $incident_time = $_POST['incident_time'] ?? date('H:i');
    $actual_start_time = $incident_date . ' ' . $incident_time . ':00';
    
    $is_planned = isset($_POST['is_planned']) ? 1 : 0;
    $downtime_category = in_array($_POST['downtime_category'] ?? '', ['Network', 'Server', 'Maintenance', 'Third-party', 'Other']) ? $_POST['downtime_category'] : 'Other';
    $status = 'pending';
    
    // Validate and deduplicate company IDs
    $company_ids = [];
    if (!empty($_POST['company_ids']) && is_array($_POST['company_ids'])) {
        foreach ($_POST['company_ids'] as $company_id) {
            if ($company_id === 'all') {
                // If 'all' is selected, get all company IDs except 'all' itself
                $company_ids = array_map(function($c) { return $c['company_id']; }, $otherCompanies);
                break;
            }
            $company_id = filter_var($company_id, FILTER_VALIDATE_INT);
            if ($company_id) {
                $company_ids[] = $company_id;
            }
        }
        $company_ids = array_values(array_unique($company_ids));
    }
    
    // Handle file upload
    $attachment_path = null;
    if (isset($_FILES['evidence']) && $_FILES['evidence']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['evidence']['tmp_name'];
        $fileName = $_FILES['evidence']['name'];
        $fileSize = $_FILES['evidence']['size'];
        $fileType = $_FILES['evidence']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));

        // Sanitize file name
        $newFileName = md5(time() . $fileName) . '.' . $fileExtension;

        // Allowed file extensions
        $allowedfileExtensions = array('jpg', 'gif', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx');

        if (in_array($fileExtension, $allowedfileExtensions)) {
            // Directory in which the uploaded file will be moved
            $uploadFileDir = __DIR__ . '/uploads/incidents/';
            $dest_path = $uploadFileDir . $newFileName;

            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $attachment_path = 'uploads/incidents/' . $newFileName;
            } else {
                $errors[] = 'There was some error moving the file to upload directory. Please make sure the upload directory is writable by web server.';
            }
        } else {
            $errors[] = 'Upload failed. Allowed file types: ' . implode(',', $allowedfileExtensions);
        }
    }

    // Validation
    $errors = [];
    if (empty($service_id)) {
        $errors[] = "Please select a service.";
    }
    if (empty($company_ids)) {
        $errors[] = "Please select at least one company.";
    }
    if (strlen($root_cause) > 1000) {
        $errors[] = "Root cause is too long (max 1000 characters).";
    }
    
    // Validate incident date/time
    $datetime = DateTime::createFromFormat('Y-m-d H:i:s', $actual_start_time);
    if (!$datetime) {
        $errors[] = "Invalid incident date or time format.";
    } elseif ($datetime > new DateTime()) {
        $errors[] = "Incident date/time cannot be in the future.";
    }
    
    if (!empty($errors)) {
        $error = implode(" ", $errors);
    } else {
        // Start transaction
        $pdo->beginTransaction();
        $success = false;
        
        try {
            // Handle expansion
            $service_ids = ($service_id === 'all') ? array_map(function($s) { return $s['service_id']; }, $services) : [$service_id];
            
            foreach ($service_ids as $s_id) {
                // For components and types, we don't expand into multiple incidents.
                // Instead, we use NULL to represent "All" or "Any" as per the schema.
                $c_id = ($component_id === 'all') ? null : $component_id;
                $t_id = ($incident_type_id === 'all') ? null : $incident_type_id;

                // 1. Insert into incidents table
                $sql = "INSERT INTO incidents 
                        (service_id, component_id, incident_type_id, impact_level, root_cause, attachment_path, actual_start_time, status, reported_by) 
                        VALUES (:service_id, :component_id, :incident_type_id, :impact_level, :root_cause, :attachment_path, :actual_start_time, :status, :reported_by)";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([
                    ':service_id' => $s_id,
                    ':component_id' => $c_id,
                    ':incident_type_id' => $t_id,
                    ':impact_level' => $impact_level,
                    ':root_cause' => $root_cause,
                    ':attachment_path' => $attachment_path,
                    ':actual_start_time' => $actual_start_time,
                    ':status' => $status,
                    ':reported_by' => $_SESSION['user_id']
                ]);
                $incident_id = $pdo->lastInsertId();
                
                // 2. Insert affected companies
                $iac_sql = "INSERT INTO incident_affected_companies (incident_id, company_id) VALUES (:incident_id, :company_id)";
                $iac_stmt = $pdo->prepare($iac_sql);
                foreach ($company_ids as $co_id) {
                    $iac_stmt->execute([
                        ':incident_id' => $incident_id,
                        ':company_id' => $co_id
                    ]);
                }
                
                // 3. Insert into downtime_incidents table
                $downtime_sql = "INSERT INTO downtime_incidents 
                                (incident_id, actual_start_time, is_planned, downtime_category)
                                VALUES (:incident_id, :actual_start_time, :is_planned, :downtime_category)";
                $downtime_stmt = $pdo->prepare($downtime_sql);
                $downtime_stmt->execute([
                    ':incident_id' => $incident_id,
                    ':actual_start_time' => $actual_start_time,
                    ':is_planned' => $is_planned,
                    ':downtime_category' => $downtime_category
                ]);
            }
            
            $pdo->commit();
            $success = true;
            
            // Log incident creation
            require_once __DIR__ . '/../src/includes/activity_logger.php';
            logIncidentAction($_SESSION['user_id'], 'created_multiple', null, [
                'services_count' => count($service_ids),
                'impact_level' => $impact_level,
                'companies_count' => count($company_ids)
            ]);
            
        } catch (Exception $e) {
            // Rollback on error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Error: " . $e->getMessage();
        }
        
        if ($success) {
            $_SESSION['success'] = "Incident(s) reported successfully!";
            header("Location: report.php");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Incident - eTranzact</title>
    
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .form-input:focus {
            transition: all 0.15s ease;
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
    <main class="py-8">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 overflow-hidden rounded-xl" style="box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px 0 rgba(0, 0, 0, 0.03);">
                <div class="px-6 py-5 sm:px-8 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                        Report New Incident
                    </h3>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Fill in the details below to report a new downtime incident
                    </p>
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="bg-red-50 border-l-4 border-red-500 p-4 mx-6 mt-6 rounded-r-lg animate-slide-in">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle text-red-500 text-xl"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-red-800"><?php echo htmlspecialchars($error); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="bg-green-50 border-l-4 border-green-500 p-4 mx-6 mt-6 rounded-r-lg animate-slide-in">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-check-circle text-green-500 text-xl"></i>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-green-800"><?php echo htmlspecialchars($_SESSION['success']); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <form action="report.php" method="POST" enctype="multipart/form-data" class="px-6 pb-8 pt-6 sm:px-8 space-y-6"
                      x-data="{ 
                        fileName: '', 
                        filePreview: null,
                        handleFileChange(event) {
                            const file = event.target.files[0];
                            this.fileName = file ? file.name : '';
                            if (file && file.type.startsWith('image/')) {
                                const reader = new FileReader();
                                reader.onload = (e) => { this.filePreview = e.target.result; };
                                reader.readAsDataURL(file);
                            } else {
                                this.filePreview = null;
                            }
                        }
                      }">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    
                    <!-- Reporter Info (Read Only) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Reporter
                        </label>
                        <input type="text" 
                               value="<?= htmlspecialchars($_SESSION['full_name']) ?>" 
                               readonly
                               class="bg-gray-100 dark:bg-gray-600 rounded-lg py-2.5 px-3.5 text-sm text-gray-500 dark:text-gray-400 border border-gray-200 dark:border-gray-500 w-full cursor-not-allowed">
                    </div>

                    <!-- Service, Component, Type Row -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Service Selection -->
                        <div>
                            <label for="service_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Service Affected <span class="text-red-500">*</span>
                            </label>
                            <select name="service_id" id="service_id" required onchange="filterDetails()"
                                class="block w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm py-2.5 px-3.5 text-sm bg-white dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select service...</option>
                                <option value="all" <?= (isset($_POST['service_id']) && $_POST['service_id'] === 'all') ? 'selected' : '' ?>>All Services</option>
                                <?php foreach ($services as $service): ?>
                                    <option value="<?= $service['service_id'] ?>" <?= (isset($_POST['service_id']) && $_POST['service_id'] == $service['service_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($service['service_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Component Selection -->
                        <div>
                            <label for="component_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Component Affected
                            </label>
                            <select name="component_id" id="component_id"
                                class="block w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm py-2.5 px-3.5 text-sm bg-white dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select component...</option>
                                <option value="all" class="component-option">All Components</option>
                                <?php foreach ($components as $component): ?>
                                    <option value="<?= $component['component_id'] ?>" data-service="<?= $component['service_id'] ?>" class="component-option hidden">
                                        <?= htmlspecialchars($component['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Incident Type Selection -->
                        <div>
                            <label for="incident_type_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Incident Type
                            </label>
                            <select name="incident_type_id" id="incident_type_id"
                                class="block w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm py-2.5 px-3.5 text-sm bg-white dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                                <option value="">Select type...</option>
                                <option value="all" class="type-option">All Incident Types</option>
                                <?php foreach ($incidentTypes as $type): ?>
                                    <option value="<?= $type['type_id'] ?>" data-service="<?= $type['service_id'] ?>" class="type-option hidden">
                                        <?= htmlspecialchars($type['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Companies Affected -->
                    <div>
                        <label for="company_ids" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Companies Affected <span class="text-red-500">*</span>
                        </label>
                        <div class="mt-1 relative">
                            <button type="button" id="company-dropdown-button" class="relative w-full bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm pl-3 pr-10 py-2 text-left cursor-default focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 sm:text-sm text-gray-900 dark:text-white">
                                <span id="company-selected-text" class="block truncate">Select companies...</span>
                                <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                        <path fill-rule="evenodd" d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </span>
                            </button>
                            <div id="company-dropdown" class="hidden absolute z-10 mt-1 w-full bg-white dark:bg-gray-700 shadow-lg max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 dark:ring-gray-600 overflow-auto focus:outline-none sm:text-sm">
                                <?php foreach ($companies as $company): ?>
                                    <div class="flex items-center px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-600">
                                    <input type="checkbox" id="company-<?php echo strtolower($company['company_name']) === 'all' ? 'all' : $company['company_id']; ?>" 
                                           name="company_ids[]" 
                                           value="<?php echo strtolower($company['company_name']) === 'all' ? 'all' : $company['company_id']; ?>"
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded"
                                           <?php 
                                           $isChecked = false;
                                           if (isset($_POST['company_ids'])) {
                                               if (strtolower($company['company_name']) === 'all') {
                                                   $isChecked = in_array('all', $_POST['company_ids']);
                                               } else {
                                                   $isChecked = in_array($company['company_id'], $_POST['company_ids']);
                                               }
                                           }
                                           echo $isChecked ? 'checked' : ''; 
                                           ?>>
                                    <label for="company-<?php echo strtolower($company['company_name']) === 'all' ? 'all' : $company['company_id']; ?>" class="ml-3 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        <?php echo htmlspecialchars($company['company_name']); ?>
                                    </label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <div id="selected-companies" class="mt-2 flex flex-wrap gap-2">
                                <?php if (isset($_POST['company_ids'])): ?>
                                    <?php foreach ($_POST['company_ids'] as $selected_id): 
                                        $selected_company = array_filter($companies, function($company) use ($selected_id) {
                                            return $company['company_id'] == $selected_id;
                                        });
                                        $selected_company = reset($selected_company);
                                        if ($selected_company): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                <?php echo htmlspecialchars($selected_company['company_name']); ?>
                                                <input type="hidden" name="company_ids[]" value="<?php echo $selected_company['company_id']; ?>">
                                            </span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Click to select multiple companies</p>
                    </div>

                    <!-- Incident Date & Time -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            When Did the Incident Occur? <span class="text-red-500">*</span>
                        </label>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Incident Date -->
                            <div>
                                <label for="incident_date" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                    Date
                                </label>
                                <input type="date" 
                                       name="incident_date" 
                                       id="incident_date" 
                                       value="<?= isset($_POST['incident_date']) ? htmlspecialchars($_POST['incident_date']) : date('Y-m-d') ?>" 
                                       max="<?= date('Y-m-d') ?>"
                                       required
                                       class="block w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm py-2.5 px-3.5 text-sm bg-white dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                            </div>
                            
                            <!-- Incident Time -->
                            <div>
                                <label for="incident_time" class="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">
                                    Time
                                </label>
                                <input type="time" 
                                       name="incident_time" 
                                       id="incident_time" 
                                       value="<?= isset($_POST['incident_time']) ? htmlspecialchars($_POST['incident_time']) : date('H:i') ?>" 
                                       required
                                       class="block w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm py-2.5 px-3.5 text-sm bg-white dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            <i class="fas fa-info-circle mr-1"></i>
                            Specify the actual time when the incident started. This can be in the past but not in the future.
                        </p>
                    </div>

                    <!-- File Upload -->
                    <div class="space-y-2">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Evidence / Attachment
                        </label>
                        <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 dark:border-gray-600 border-dashed rounded-md hover:border-blue-400 dark:hover:border-blue-500 transition-colors bg-gray-50 dark:bg-gray-700/50">
                            <div class="space-y-1 text-center">
                                <template x-if="!filePreview">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48" aria-hidden="true">
                                        <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                </template>
                                <template x-if="filePreview">
                                    <div class="relative inline-block">
                                        <img :src="filePreview" class="mx-auto h-48 w-auto rounded-lg shadow-sm object-cover">
                                        <button @click="filePreview = null; fileName = ''; $refs.fileInput.value = ''" type="button" class="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 hover:bg-red-600 focus:outline-none">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </template>
                                <div class="flex text-sm text-gray-600 dark:text-gray-400">
                                    <label for="evidence" class="relative cursor-pointer bg-white dark:bg-gray-800 rounded-md font-medium text-blue-600 hover:text-blue-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-blue-500 px-2 py-0.5 border border-blue-600/20">
                                        <span>Upload a file</span>
                                        <input id="evidence" name="evidence" type="file" class="sr-only" x-ref="fileInput" @change="handleFileChange">
                                    </label>
                                    <p class="pl-1">or drag and drop</p>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    PNG, JPG, GIF, PDF, DOC up to 10MB
                                </p>
                                <p x-show="fileName" x-text="fileName" class="text-sm font-medium text-blue-600 dark:text-blue-400 transition-all"></p>
                            </div>
                        </div>
                    </div>

                    <!-- Impact, Category, Planned Row -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Impact Level -->
                        <div>
                            <label for="impact_level" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Impact Level <span class="text-red-500">*</span>
                            </label>
                            <select name="impact_level" id="impact_level" required
                                class="block w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm py-2.5 px-3.5 text-sm bg-white dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                                <option value="Low">Low</option>
                                <option value="Medium">Medium</option>
                                <option value="High">High</option>
                                <option value="Critical">Critical</option>
                            </select>
                        </div>

                        <!-- Category -->
                        <div>
                            <label for="downtime_category" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Downtime Category
                            </label>
                            <select name="downtime_category" id="downtime_category"
                                class="block w-full border-gray-300 dark:border-gray-600 rounded-lg shadow-sm py-2.5 px-3.5 text-sm bg-white dark:bg-gray-700 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                                <option value="Network">Network</option>
                                <option value="Server">Server</option>
                                <option value="Maintenance">Maintenance</option>
                                <option value="Third-party">Third-party</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>

                        <!-- Planned -->
                        <div class="flex items-center mt-8">
                            <input id="is_planned" name="is_planned" type="checkbox"
                                class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                            <label for="is_planned" class="ml-3 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Is this a planned maintenance?
                            </label>
                        </div>
                    </div>

                    <!-- Root Cause -->
                    <div>
                        <label for="root_cause" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Root Cause (Optional)
                        </label>
                        <div class="mt-1">
                            <textarea id="root_cause" name="root_cause" rows="3" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border border-gray-300 dark:border-gray-600 rounded-md p-3 bg-white dark:bg-gray-700 dark:text-white"><?php echo isset($_POST['root_cause']) ? sanitize($_POST['root_cause']) : ''; ?></textarea>
                        </div>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Briefly describe what caused the issue (if known)</p>
                    </div>

                    <div class="pt-5">
                        <div class="flex justify-end">
                            <a href="index.php" class="bg-white dark:bg-gray-700 py-2 px-4 border border-gray-300 dark:border-gray-600 rounded-md shadow-sm text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Cancel
                            </a>
                            <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                <i class="fas fa-paper-plane mr-2"></i> Report Incident
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dropdownButton = document.getElementById('company-dropdown-button');
            const dropdown = document.getElementById('company-dropdown');
            const selectedCompanies = document.getElementById('selected-companies');
            const companyCheckboxes = document.querySelectorAll('input[name="company_ids[]"]');
            
            // Toggle dropdown
            dropdownButton.addEventListener('click', function() {
                dropdown.classList.toggle('hidden');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(event) {
                if (!dropdownButton.contains(event.target) && !dropdown.contains(event.target)) {
                    dropdown.classList.add('hidden');
                }
            });
            
            // Handle checkbox changes
            companyCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    if (this.value === 'all') {
                        // When "All" is checked, check all other checkboxes
                        // When unchecked, uncheck all other checkboxes
                        const isChecked = this.checked;
                        companyCheckboxes.forEach(cb => {
                            if (cb !== this) {
                                cb.checked = isChecked;
                            }
                        });
                    } else {
                        // For other checkboxes, uncheck "All" if any is unchecked
                        // Or check "All" if all others are checked
                        const allCheckbox = document.querySelector('input[value="all"]');
                        if (allCheckbox) {
                            if (!this.checked) {
                                allCheckbox.checked = false;
                            } else {
                                // Check if all other checkboxes are checked
                                const allChecked = Array.from(companyCheckboxes)
                                    .filter(cb => cb.value !== 'all')
                                    .every(cb => cb.checked);
                                allCheckbox.checked = allChecked;
                            }
                        }
                    }
                    updateSelectedCompanies();
                });
            });
            
            function updateSelectedCompanies() {
                const selected = [];
                const selectedNames = [];
                
                document.querySelectorAll('input[name="company_ids[]"]:checked').forEach(checkbox => {
                    selected.push({
                        id: checkbox.value,
                        name: checkbox.nextElementSibling.textContent.trim()
                    });
                    selectedNames.push(checkbox.nextElementSibling.textContent.trim());
                });
                
                // Update the selected text
                const selectedText = selectedNames.length > 0 
                    ? selectedNames.join(', ') 
                    : 'Select companies...';
                document.getElementById('company-selected-text').textContent = selectedText;
                
                // Update the hidden inputs in the selected-companies container
                selectedCompanies.innerHTML = selected.map(company => `
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                        ${company.name}
                        <input type="hidden" name="company_ids[]" value="${company.id}">
                    </span>
                `).join('');
            }
            
            // Initialize with any pre-selected companies
            updateSelectedCompanies();
            
            // Handle 'All' checkbox on page load
            const allCheckbox = document.querySelector('input[value="all"]');
            if (allCheckbox && allCheckbox.checked) {
                // Check all checkboxes when 'All' is checked on page load
                companyCheckboxes.forEach(checkbox => {
                    checkbox.checked = true;
                });
                updateSelectedCompanies();
            }
            
            // Service details filtering
            window.filterDetails = function() {
                const serviceId = document.getElementById('service_id').value;
                const componentId = document.getElementById('component_id');
                const incidentTypeId = document.getElementById('incident_type_id');
                
                // Reset and hide all
                componentId.value = '';
                incidentTypeId.value = '';
                
                document.querySelectorAll('.component-option').forEach(opt => {
                    if (serviceId === 'all' || opt.dataset.service == serviceId || !opt.dataset.service) {
                        opt.classList.remove('hidden');
                    } else {
                        opt.classList.add('hidden');
                    }
                });
                
                document.querySelectorAll('.type-option').forEach(opt => {
                    if (serviceId === 'all' || opt.dataset.service == serviceId || !opt.dataset.service) {
                        opt.classList.remove('hidden');
                    } else {
                        opt.classList.add('hidden');
                    }
                });
            };
            
            // Trigger on load if service is pre-selected
            if (document.getElementById('service_id').value) {
                filterDetails();
            }
            
            // Incident date/time validation
            const incidentDate = document.getElementById('incident_date');
            const incidentTime = document.getElementById('incident_time');
            
            // Validate date is not in the future
            incidentDate.addEventListener('change', function() {
                const selectedDate = new Date(this.value);
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                
                if (selectedDate > today) {
                    alert('Incident date cannot be in the future. Please select today or an earlier date.');
                    this.value = '<?= date('Y-m-d') ?>';
                }
            });
            
            // Validate datetime combination is not in the future
            function validateDateTime() {
                if (!incidentDate.value || !incidentTime.value) return;
                
                const selectedDateTime = new Date(incidentDate.value + 'T' + incidentTime.value);
                const now = new Date();
                
                if (selectedDateTime > now) {
                    alert('Incident date/time cannot be in the future. The time has been adjusted to the current time.');
                    incidentTime.value = '<?= date('H:i') ?>';
                }
            }
            
            incidentTime.addEventListener('change', validateDateTime);
        });
    </script>
    </div> <!-- End Content Wrapper -->
</body>
</html>
