<!-- Services Tab Content -->
<div class="space-y-6">
    <!-- Statistics Card -->
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide">Total Services</p>
                <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white"><?= $totalServices ?></p>
            </div>
            <button onclick="showServiceModal()" class="inline-flex items-center px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-plus mr-2"></i> Add Service
            </button>
        </div>
    </div>

    <!-- Services Table -->
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">All Services</h3>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Manage system services</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 dark:text-gray-400 uppercase">Service Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 dark:text-gray-400 uppercase">Components</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 dark:text-gray-400 uppercase">Created</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 dark:text-gray-400 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php if (empty($services)): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                <i class="fas fa-server text-4xl mb-3 text-gray-300 dark:text-gray-600"></i>
                                <p>No services found. Click "Add Service" to create one.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($services as $service): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        <?= htmlspecialchars($service['service_name']) ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400">
                                        <?= $service['component_count'] ?> component<?= $service['component_count'] != 1 ? 's' : '' ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <?= date('M j, Y', strtotime($service['created_at'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button onclick='editService(<?= json_encode($service) ?>)' class="inline-flex items-center px-3 py-1.5 bg-blue-100 text-blue-700 hover:bg-blue-200 dark:bg-blue-900/30 dark:text-blue-400 dark:hover:bg-blue-900/50 text-xs font-semibold rounded-lg transition-colors mr-2">
                                        <i class="fas fa-edit mr-1"></i> Edit
                                    </button>
                                    <button onclick="deleteService(<?= $service['service_id'] ?>, '<?= addslashes(htmlspecialchars($service['service_name'])) ?>', <?= $service['component_count'] ?>)" class="inline-flex items-center px-3 py-1.5 bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900/30 dark:text-red-400 dark:hover:bg-red-900/50 text-xs font-semibold rounded-lg transition-colors">
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

<!-- Service Modal -->
<div id="serviceModal" class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50 p-4">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full">
        <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white" id="serviceModalTitle">Add Service</h3>
        </div>
        <form method="POST" class="p-6">
            <input type="hidden" name="action" id="serviceAction" value="create_service">
            <input type="hidden" name="service_id" id="serviceId" value="">
            
            <div class="mb-4">
                <label for="serviceName" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Service Name <span class="text-red-500">*</span></label>
                <input type="text" name="service_name" id="serviceName" required class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            
            <div class="flex justify-end space-x-3">
                <button type="button" onclick="hideServiceModal()" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium">
                    <i class="fas fa-save mr-2"></i> Save
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function showServiceModal() {
    document.getElementById('serviceModalTitle').textContent = 'Add Service';
    document.getElementById('serviceAction').value = 'create_service';
    document.getElementById('serviceId').value = '';
    document.getElementById('serviceName').value = '';
    document.getElementById('serviceModal').classList.remove('hidden');
}

function editService(service) {
    document.getElementById('serviceModalTitle').textContent = 'Edit Service';
    document.getElementById('serviceAction').value = 'update_service';
    document.getElementById('serviceId').value = service.service_id;
    document.getElementById('serviceName').value = service.service_name;
    document.getElementById('serviceModal').classList.remove('hidden');
}

function hideServiceModal() {
    document.getElementById('serviceModal').classList.add('hidden');
}

function deleteService(id, name, componentCount) {
    let message = `Are you sure you want to delete "${name}"?`;
    if (componentCount > 0) {
        message += `\n\nThis service has ${componentCount} component${componentCount > 1 ? 's' : ''} that will also be deleted.`;
    }
    
    if (confirm(message)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_service">
            <input type="hidden" name="service_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
