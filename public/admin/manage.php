<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../src/includes/auth.php';
requireLogin();
requireRole('admin');

$currentUser = getCurrentUser();
$message = $_SESSION['message'] ?? null;
unset($_SESSION['message']);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        // ============ SERVICE ACTIONS ============
        if ($action === 'create_service') {
            $serviceName = trim($_POST['service_name'] ?? '');
            
            if (empty($serviceName)) {
                throw new Exception('Service name is required');
            }
            
            // Check for duplicates
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE service_name = ?");
            $stmt->execute([$serviceName]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('A service with this name already exists');
            }
            
            $stmt = $pdo->prepare("INSERT INTO services (service_name) VALUES (?)");
            $stmt->execute([$serviceName]);
            
            // Log activity
            logActivity($_SESSION['user_id'], 'created_service', "Created service: $serviceName");
            
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Service created successfully'];
            
        } elseif ($action === 'update_service') {
            $serviceId = $_POST['service_id'] ?? 0;
            $serviceName = trim($_POST['service_name'] ?? '');
            
            if (empty($serviceName)) {
                throw new Exception('Service name is required');
            }
            
            // Check for duplicates (excluding current service)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM services WHERE service_name = ? AND service_id != ?");
            $stmt->execute([$serviceName, $serviceId]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('A service with this name already exists');
            }
            
            $stmt = $pdo->prepare("UPDATE services SET service_name = ? WHERE service_id = ?");
            $stmt->execute([$serviceName, $serviceId]);
            
            logActivity($_SESSION['user_id'], 'updated_service', "Updated service ID $serviceId to: $serviceName");
            
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Service updated successfully'];
            
        } elseif ($action === 'delete_service') {
            $serviceId = $_POST['service_id'] ?? 0;
            
            // Get service name for logging
            $stmt = $pdo->prepare("SELECT service_name FROM services WHERE service_id = ?");
            $stmt->execute([$serviceId]);
            $serviceName = $stmt->fetchColumn();
            
            // Check for dependencies
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM service_components WHERE service_id = ?");
            $stmt->execute([$serviceId]);
            $componentCount = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM incidents WHERE service_id = ?");
            $stmt->execute([$serviceId]);
            $incidentCount = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("DELETE FROM services WHERE service_id = ?");
            $stmt->execute([$serviceId]);
            
            logActivity($_SESSION['user_id'], 'deleted_service', "Deleted service: $serviceName (had $componentCount components, $incidentCount incidents)");
            
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Service deleted successfully'];
            
        // ============ COMPANY ACTIONS ============
        } elseif ($action === 'create_company') {
            $companyName = trim($_POST['company_name'] ?? '');
            $category = trim($_POST['category'] ?? '');
            
            if (empty($companyName)) {
                throw new Exception('Company name is required');
            }
            
            // Check for duplicates
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM companies WHERE company_name = ?");
            $stmt->execute([$companyName]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('A company with this name already exists');
            }
            
            $stmt = $pdo->prepare("INSERT INTO companies (company_name, category) VALUES (?, ?)");
            $stmt->execute([$companyName, $category ?: null]);
            
            logActivity($_SESSION['user_id'], 'created_company', "Created company: $companyName" . ($category ? " (Category: $category)" : ''));
            
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Company created successfully'];
            
        } elseif ($action === 'update_company') {
            $companyId = $_POST['company_id'] ?? 0;
            $companyName = trim($_POST['company_name'] ?? '');
            $category = trim($_POST['category'] ?? '');
            
            if (empty($companyName)) {
                throw new Exception('Company name is required');
            }
            
            // Check for duplicates (excluding current company)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM companies WHERE company_name = ? AND company_id != ?");
            $stmt->execute([$companyName, $companyId]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('A company with this name already exists');
            }
            
            $stmt = $pdo->prepare("UPDATE companies SET company_name = ?, category = ? WHERE company_id = ?");
            $stmt->execute([$companyName, $category ?: null, $companyId]);
            
            logActivity($_SESSION['user_id'], 'updated_company', "Updated company ID $companyId to: $companyName");
            
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Company updated successfully'];
            
        } elseif ($action === 'delete_company') {
            $companyId = $_POST['company_id'] ?? 0;
            
            // Get company name for logging
            $stmt = $pdo->prepare("SELECT company_name FROM companies WHERE company_id = ?");
            $stmt->execute([$companyId]);
            $companyName = $stmt->fetchColumn();
            
            // Check for dependencies in incident_companies junction table
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM incident_companies WHERE company_id = ?");
            $stmt->execute([$companyId]);
            $incidentCount = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("DELETE FROM companies WHERE company_id = ?");
            $stmt->execute([$companyId]);
            
            logActivity($_SESSION['user_id'], 'deleted_company', "Deleted company: $companyName (was linked to $incidentCount incidents)");
            
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Company deleted successfully'];
            
        // ============ COMPONENT ACTIONS ============
        } elseif ($action === 'create_component') {
            $serviceId = $_POST['service_id'] ?? 0;
            $componentName = trim($_POST['component_name'] ?? '');
            
            if (empty($componentName)) {
                throw new Exception('Component name is required');
            }
            
            if (empty($serviceId)) {
                throw new Exception('Please select a service');
            }
            
            // Check for duplicates within the same service
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM service_components WHERE name = ? AND service_id = ?");
            $stmt->execute([$componentName, $serviceId]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('This component already exists for the selected service');
            }
            
            $stmt = $pdo->prepare("INSERT INTO service_components (service_id, name) VALUES (?, ?)");
            $stmt->execute([$serviceId, $componentName]);
            
            logActivity($_SESSION['user_id'], 'created_component', "Created component: $componentName for service ID $serviceId");
            
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Component created successfully'];
            
        } elseif ($action === 'update_component') {
            $componentId = $_POST['component_id'] ?? 0;
            $serviceId = $_POST['service_id'] ?? 0;
            $componentName = trim($_POST['component_name'] ?? '');
            
            if (empty($componentName)) {
                throw new Exception('Component name is required');
            }
            
            if (empty($serviceId)) {
                throw new Exception('Please select a service');
            }
            
            // Check for duplicates (excluding current component)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM service_components WHERE name = ? AND service_id = ? AND component_id != ?");
            $stmt->execute([$componentName, $serviceId, $componentId]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('This component already exists for the selected service');
            }
            
            $stmt = $pdo->prepare("UPDATE service_components SET service_id = ?, name = ? WHERE component_id = ?");
            $stmt->execute([$serviceId, $componentName, $componentId]);
            
            logActivity($_SESSION['user_id'], 'updated_component', "Updated component ID $componentId to: $componentName");
            
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Component updated successfully'];
            
        } elseif ($action === 'toggle_component_status') {
            $componentId = $_POST['component_id'] ?? 0;
            $newStatus = $_POST['new_status'] ?? 1;
            
            $stmt = $pdo->prepare("UPDATE service_components SET is_active = ? WHERE component_id = ?");
            $stmt->execute([$newStatus, $componentId]);
            
            $statusText = $newStatus ? 'activated' : 'deactivated';
            logActivity($_SESSION['user_id'], 'toggled_component_status', "Component ID $componentId $statusText");
            
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Component status updated successfully'];
            
        } elseif ($action === 'delete_component') {
            $componentId = $_POST['component_id'] ?? 0;
            
            // Get component name for logging
            $stmt = $pdo->prepare("SELECT name FROM service_components WHERE component_id = ?");
            $stmt->execute([$componentId]);
            $componentName = $stmt->fetchColumn();
            
            // Check for dependencies
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM incidents WHERE component_id = ?");
            $stmt->execute([$componentId]);
            $incidentCount = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("DELETE FROM service_components WHERE component_id = ?");
            $stmt->execute([$componentId]);
            
            logActivity($_SESSION['user_id'], 'deleted_component', "Deleted component: $componentName (had $incidentCount incidents)");
            
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Component deleted successfully'];
        }
        
    } catch (Exception $e) {
        $_SESSION['message'] = ['type' => 'error', 'text' => $e->getMessage()];
    }
    
    header('Location: manage.php');
    exit;
}

// Fetch all data
try {
    // Get all services with component count
    $services = $pdo->query("
        SELECT s.*, 
               COUNT(sc.component_id) as component_count
        FROM services s
        LEFT JOIN service_components sc ON s.service_id = sc.service_id
        GROUP BY s.service_id
        ORDER BY s.service_name ASC
    ")->fetchAll();
    
    // Get all companies
    $companies = $pdo->query("
        SELECT * FROM companies
        ORDER BY company_name ASC
    ")->fetchAll();
    
    // Get all components with service names
    $components = $pdo->query("
        SELECT sc.*, s.service_name
        FROM service_components sc
        INNER JOIN services s ON sc.service_id = s.service_id
        ORDER BY s.service_name ASC, sc.name ASC
    ")->fetchAll();
    
    // Get statistics
    $totalServices = count($services);
    $totalCompanies = count($companies);
    $totalComponents = count($components);
    $activeComponents = count(array_filter($components, fn($c) => $c['is_active']));
    
} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Management - eTranzact</title>

    <script src="https://cdn.tailwindcss.com?plugins=forms,typography,aspect-ratio"></script>
    <script>tailwind.config = { darkMode: 'class' }</script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Inter', sans-serif; }
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
    <?php include __DIR__ . '/../../src/includes/loading.php'; ?>

    <main class="py-8" x-data="{ activeTab: 'services' }">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white sm:text-3xl">System Management</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Manage services, companies, and components</p>
            </div>

            <!-- Message -->
            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-lg <?= $message['type'] === 'success' ? 'bg-green-50 border border-green-200 dark:bg-green-900/20 dark:border-green-800' : 'bg-red-50 border border-red-200 dark:bg-red-900/20 dark:border-red-800' ?>">
                    <p class="text-sm font-medium <?= $message['type'] === 'success' ? 'text-green-800 dark:text-green-300' : 'text-red-800 dark:text-red-300' ?>">
                        <?= htmlspecialchars($message['text']) ?>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Tab Navigation -->
            <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
                <nav class="flex space-x-8" aria-label="Tabs">
                    <button @click="activeTab = 'services'" :class="activeTab === 'services' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors">
                        <i class="fas fa-server mr-2"></i> Services (<?= $totalServices ?>)
                    </button>
                    <button @click="activeTab = 'companies'" :class="activeTab === 'companies' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors">
                        <i class="fas fa-building mr-2"></i> Companies (<?= $totalCompanies ?>)
                    </button>
                    <button @click="activeTab = 'components'" :class="activeTab === 'components' ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600'" class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors">
                        <i class="fas fa-cogs mr-2"></i> Components (<?= $totalComponents ?>)
                    </button>
                </nav>
            </div>

            <!-- SERVICES TAB -->
            <div x-show="activeTab === 'services'" x-cloak>
                <?php include __DIR__ . '/../../src/includes/admin_manage_services.php'; ?>
            </div>

            <!-- COMPANIES TAB -->
            <div x-show="activeTab === 'companies'" x-cloak>
                <?php include __DIR__ . '/../../src/includes/admin_manage_companies.php'; ?>
            </div>

            <!-- COMPONENTS TAB -->
            <div x-show="activeTab === 'components'" x-cloak>
                <?php include __DIR__ . '/../../src/includes/admin_manage_components.php'; ?>
            </div>
        </div>
    </main>
    </div> <!-- End Content Wrapper -->
</body>

</html>
