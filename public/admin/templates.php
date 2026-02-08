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
        // ============ TEMPLATE ACTIONS ============
        if ($action === 'create_template') {
            $templateName = trim($_POST['template_name'] ?? '');
            $serviceId = $_POST['service_id'] ?? null;
            $componentId = $_POST['component_id'] ?? null;
            $impactLevel = $_POST['impact_level'] ?? 'medium';
            $description = trim($_POST['description'] ?? '');
            $rootCause = trim($_POST['root_cause'] ?? '');
            
            if (empty($templateName)) {
                throw new Exception('Template name is required');
            }
            
            if (empty($description)) {
                throw new Exception('Description is required');
            }
            
            // Check for duplicates
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM incident_templates WHERE template_name = ?");
            $stmt->execute([$templateName]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('A template with this name already exists');
            }
            
            // Convert empty strings to null
            $serviceId = $serviceId ?: null;
            $componentId = $componentId ?: null;
            $rootCause = $rootCause ?: null;
            
            $stmt = $pdo->prepare("
                INSERT INTO incident_templates 
                (template_name, service_id, component_id, impact_level, description, root_cause, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$templateName, $serviceId, $componentId, $impactLevel, $description, $rootCause, $_SESSION['user_id']]);
            
            logActivity($_SESSION['user_id'], 'created_template', "Created incident template: $templateName");
            
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Template created successfully'];
            
        } elseif ($action === 'update_template') {
            $templateId = $_POST['template_id'] ?? 0;
            $templateName = trim($_POST['template_name'] ?? '');
            $serviceId = $_POST['service_id'] ?? null;
            $componentId = $_POST['component_id'] ?? null;
            $impactLevel = $_POST['impact_level'] ?? 'medium';
            $description = trim($_POST['description'] ?? '');
            $rootCause = trim($_POST['root_cause'] ?? '');
            
            if (empty($templateName)) {
                throw new Exception('Template name is required');
            }
            
            if (empty($description)) {
                throw new Exception('Description is required');
            }
            
            // Check for duplicates (excluding current template)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM incident_templates WHERE template_name = ? AND template_id != ?");
            $stmt->execute([$templateName, $templateId]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('A template with this name already exists');
            }
            
            // Convert empty strings to null
            $serviceId = $serviceId ?: null;
            $componentId = $componentId ?: null;
            $rootCause = $rootCause ?: null;
            
            $stmt = $pdo->prepare("
                UPDATE incident_templates 
                SET template_name = ?, service_id = ?, component_id = ?, 
                    impact_level = ?, description = ?, root_cause = ?
                WHERE template_id = ?
            ");
            $stmt->execute([$templateName, $serviceId, $componentId, $impactLevel, $description, $rootCause, $templateId]);
            
            logActivity($_SESSION['user_id'], 'updated_template', "Updated incident template: $templateName");
            
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Template updated successfully'];
            
        } elseif ($action === 'toggle_template_status') {
            $templateId = $_POST['template_id'] ?? 0;
            $newStatus = $_POST['new_status'] ?? 1;
            
            $stmt = $pdo->prepare("UPDATE incident_templates SET is_active = ? WHERE template_id = ?");
            $stmt->execute([$newStatus, $templateId]);
            
            $statusText = $newStatus ? 'activated' : 'deactivated';
            logActivity($_SESSION['user_id'], 'toggled_template_status', "Template ID $templateId $statusText");
            
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Template status updated successfully'];
            
        } elseif ($action === 'delete_template') {
            $templateId = $_POST['template_id'] ?? 0;
            
            // Get template name for logging
            $stmt = $pdo->prepare("SELECT template_name FROM incident_templates WHERE template_id = ?");
            $stmt->execute([$templateId]);
            $templateName = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("DELETE FROM incident_templates WHERE template_id = ?");
            $stmt->execute([$templateId]);
            
            logActivity($_SESSION['user_id'], 'deleted_template', "Deleted incident template: $templateName");
            
            $_SESSION['message'] = ['type' => 'success', 'text' => 'Template deleted successfully'];
        }
        
    } catch (Exception $e) {
        $_SESSION['message'] = ['type' => 'error', 'text' => $e->getMessage()];
    }
    
    header('Location: templates.php');
    exit;
}

// Fetch all data
try {
    // Get all templates with service and component names
    $templates = $pdo->query("
        SELECT t.*, 
               s.service_name,
               sc.name as component_name,
               u.username as created_by_name
        FROM incident_templates t
        LEFT JOIN services s ON t.service_id = s.service_id
        LEFT JOIN service_components sc ON t.component_id = sc.component_id
        LEFT JOIN users u ON t.created_by = u.user_id
        ORDER BY t.usage_count DESC, t.template_name ASC
    ")->fetchAll();
    
    // Get services for dropdown
    $services = $pdo->query("SELECT * FROM services ORDER BY service_name ASC")->fetchAll();
    
    // Get all components for dropdown (will be filtered by JS)
    $allComponents = $pdo->query("
        SELECT sc.*, s.service_name
        FROM service_components sc
        INNER JOIN services s ON sc.service_id = s.service_id
        WHERE sc.is_active = 1
        ORDER BY s.service_name ASC, sc.name ASC
    ")->fetchAll();
    
    // Get statistics
    $totalTemplates = count($templates);
    $activeTemplates = count(array_filter($templates, fn($t) => $t['is_active']));
    
    // Top 3 most used templates
    $topTemplates = array_slice($templates, 0, 3);
    
} catch (PDOException $e) {
    die("Error fetching data: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incident Templates - eTranzact</title>

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

    <main class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header -->
            <div class="mb-8">
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white sm:text-3xl">Incident Templates</h1>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Create reusable templates for common incidents</p>
            </div>

            <!-- Message -->
            <?php if ($message): ?>
                <div class="mb-6 p-4 rounded-lg <?= $message['type'] === 'success' ? 'bg-green-50 border border-green-200 dark:bg-green-900/20 dark:border-green-800' : 'bg-red-50 border border-red-200 dark:bg-red-900/20 dark:border-red-800' ?>">
                    <p class="text-sm font-medium <?= $message['type'] === 'success' ? 'text-green-800 dark:text-green-300' : 'text-red-800 dark:text-red-300' ?>">
                        <?= htmlspecialchars($message['text']) ?>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide">Total Templates</p>
                            <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white"><?= $totalTemplates ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-50 dark:bg-blue-900/30 rounded-lg flex items-center justify-center">
                            <i class="fas fa-file-alt text-blue-600 dark:text-blue-400 text-xl"></i>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide">Active Templates</p>
                            <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white"><?= $activeTemplates ?></p>
                        </div>
                        <div class="w-12 h-12 bg-green-50 dark:bg-green-900/30 rounded-lg flex items-center justify-center">
                            <i class="fas fa-check-circle text-green-600 dark:text-green-400 text-xl"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-6">
                    <div>
                        <p class="text-sm font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide mb-3">Most Used</p>
                        <?php if (!empty($topTemplates)): ?>
                            <?php foreach ($topTemplates as $idx => $top): ?>
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs text-gray-700 dark:text-gray-300 truncate" title="<?= htmlspecialchars($top['template_name']) ?>">
                                        <?= ($idx + 1) ?>. <?= htmlspecialchars(substr($top['template_name'], 0, 20)) ?><?= strlen($top['template_name']) > 20 ? '...' : '' ?>
                                    </span>
                                    <span class="text-xs font-semibold text-blue-600 dark:text-blue-400"><?= $top['usage_count'] ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-xs text-gray-500 dark:text-gray-400">No templates used yet</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Add Button -->
            <div class="flex justify-end mb-6">
                <button onclick="showTemplateModal()" class="inline-flex items-center px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                    <i class="fas fa-plus mr-2"></i> Create Template
                </button>
            </div>

            <!-- Templates Table -->
            <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">All Templates</h3>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Manage incident templates</p>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-900/50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 dark:text-gray-400 uppercase">Template Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 dark:text-gray-400 uppercase">Service</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 dark:text-gray-400 uppercase">Impact</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 dark:text-gray-400 uppercase">Used</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 dark:text-gray-400 uppercase">Status</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 dark:text-gray-400 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <?php if (empty($templates)): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                        <i class="fas fa-file-alt text-4xl mb-3 text-gray-300 dark:text-gray-600"></i>
                                        <p>No templates found. Click "Create Template" to add one.</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($templates as $template): ?>
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-6 py-4">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                <?= htmlspecialchars($template['template_name']) ?>
                                            </div>
                                            <?php if ($template['component_name']): ?>
                                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                                    <i class="fas fa-cog mr-1"></i><?= htmlspecialchars($template['component_name']) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($template['service_name']): ?>
                                                <span class="text-sm text-gray-900 dark:text-white"><?= htmlspecialchars($template['service_name']) ?></span>
                                            <?php else: ?>
                                                <span class="text-xs text-gray-400 dark:text-gray-500 italic">Any Service</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $impactColors = [
                                                'low' => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
                                                'medium' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
                                                'high' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400',
                                                'critical' => 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400'
                                            ];
                                            $colorClass = $impactColors[$template['impact_level']] ?? $impactColors['medium'];
                                            ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?= $colorClass ?>">
                                                <?= ucfirst($template['impact_level']) ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            <?= $template['usage_count'] ?> times
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <form method="POST" class="inline" onchange="this.submit()">
                                                <input type="hidden" name="action" value="toggle_template_status">
                                                <input type="hidden" name="template_id" value="<?= $template['template_id'] ?>">
                                                <input type="hidden" name="new_status" value="<?= $template['is_active'] ? 0 : 1 ?>">
                                                <label class="relative inline-flex items-center cursor-pointer">
                                                    <input type="checkbox" <?= $template['is_active'] ? 'checked' : '' ?> onchange="this.form.submit()" class="sr-only peer">
                                                    <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-green-600"></div>
                                                    <span class="ms-3 text-sm font-medium <?= $template['is_active'] ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400' ?>">
                                                        <?= $template['is_active'] ? 'Active' : 'Inactive' ?>
                                                    </span>
                                                </label>
                                            </form>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <button onclick='editTemplate(<?= json_encode($template) ?>)' class="inline-flex items-center px-3 py-1.5 bg-blue-100 text-blue-700 hover:bg-blue-200 dark:bg-blue-900/30 dark:text-blue-400 dark:hover:bg-blue-900/50 text-xs font-semibold rounded-lg transition-colors mr-2">
                                                <i class="fas fa-edit mr-1"></i> Edit
                                            </button>
                                            <button onclick="deleteTemplate(<?= $template['template_id'] ?>, '<?= addslashes(htmlspecialchars($template['template_name'])) ?>')" class="inline-flex items-center px-3 py-1.5 bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900/30 dark:text-red-400 dark:hover:bg-red-900/50 text-xs font-semibold rounded-lg transition-colors">
                                                <i class="fas fa-trash mr-1"></i> Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    </div> <!-- End Content Wrapper -->

    <!-- Template Modal -->
    <div id="templateModal" class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50 p-4">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white" id="templateModalTitle">Create Template</h3>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" id="templateAction" value="create_template">
                <input type="hidden" name="template_id" id="templateId" value="">
                
                <div>
                    <label for="templateName" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Template Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="template_name" id="templateName" required 
                        placeholder="e.g., Database Connection Timeout"
                        class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="templateServiceId" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Service <span class="text-gray-400 text-xs">(Optional)</span>
                        </label>
                        <select name="service_id" id="templateServiceId" onchange="filterComponents(this.value)"
                            class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">Any Service</option>
                            <?php foreach ($services as $service): ?>
                                <option value="<?= $service['service_id'] ?>"><?= htmlspecialchars($service['service_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label for="templateComponentId" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Component <span class="text-gray-400 text-xs">(Optional)</span>
                        </label>
                        <select name="component_id" id="templateComponentId"
                            class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">None</option>
                            <?php foreach ($allComponents as $comp): ?>
                                <option value="<?= $comp['component_id'] ?>" data-service-id="<?= $comp['service_id'] ?>">
                                    <?= htmlspecialchars($comp['name']) ?> (<?= htmlspecialchars($comp['service_name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label for="templateImpactLevel" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Impact Level <span class="text-red-500">*</span>
                    </label>
                    <select name="impact_level" id="templateImpactLevel" required
                        class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>
                
                <div>
                    <label for="templateDescription" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Description <span class="text-red-500">*</span>
                    </label>
                    <textarea name="description" id="templateDescription" required rows="4"
                        placeholder="Describe the typical issue that this template addresses..."
                        class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>

                <div>
                    <label for="templateRootCause" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Typical Root Cause <span class="text-gray-400 text-xs">(Optional)</span>
                    </label>
                    <textarea name="root_cause" id="templateRootCause" rows="3"
                        placeholder="Common root cause for this issue..."
                        class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>
                
                <div class="flex justify-end space-x-3 pt-2">
                    <button type="button" onclick="hideTemplateModal()" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium">
                        <i class="fas fa-save mr-2"></i> Save Template
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    // Component filtering
    function filterComponents(serviceId) {
        const componentSelect = document.getElementById('templateComponentId');
        const options = componentSelect.querySelectorAll('option');
        
        options.forEach(option => {
            if (option.value === '') {
                option.style.display = 'block';
                return;
            }
            
            const optionServiceId = option.getAttribute('data-service-id');
            if (!serviceId || optionServiceId === serviceId) {
                option.style.display = 'block';
            } else {
                option.style.display = 'none';
            }
        });
        
        // Reset component selection if it doesn't match the new service
        const selectedOption = componentSelect.options[componentSelect.selectedIndex];
        if (selectedOption && selectedOption.getAttribute('data-service-id') !== serviceId && serviceId !== '') {
            componentSelect.value = '';
        }
    }

    function showTemplateModal() {
        document.getElementById('templateModalTitle').textContent = 'Create Template';
        document.getElementById('templateAction').value = 'create_template';
        document.getElementById('templateId').value = '';
        document.getElementById('templateName').value = '';
        document.getElementById('templateServiceId').value = '';
        document.getElementById('templateComponentId').value = '';
        document.getElementById('templateImpactLevel').value = 'medium';
        document.getElementById('templateDescription').value = '';
        document.getElementById('templateRootCause').value = '';
        filterComponents('');
        document.getElementById('templateModal').classList.remove('hidden');
    }

    function editTemplate(template) {
        document.getElementById('templateModalTitle').textContent = 'Edit Template';
        document.getElementById('templateAction').value = 'update_template';
        document.getElementById('templateId').value = template.template_id;
        document.getElementById('templateName').value = template.template_name;
        document.getElementById('templateServiceId').value = template.service_id || '';
        document.getElementById('templateComponentId').value = template.component_id || '';
        document.getElementById('templateImpactLevel').value = template.impact_level;
        document.getElementById('templateDescription').value = template.description;
        document.getElementById('templateRootCause').value = template.root_cause || '';
        filterComponents(template.service_id || '');
        document.getElementById('templateModal').classList.remove('hidden');
    }

    function hideTemplateModal() {
        document.getElementById('templateModal').classList.add('hidden');
    }

    function deleteTemplate(id, name) {
        if (confirm(`Are you sure you want to delete "${name}"?\n\nThis action cannot be undone.`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete_template">
                <input type="hidden" name="template_id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    }
    </script>
</body>

</html>
