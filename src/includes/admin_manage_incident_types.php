<!-- Incident Types Tab Content -->
<div class="space-y-6">
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide">Total
                        Incident Types</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">
                        <?= $totalIncidentTypes ?>
                    </p>
                </div>
                <div class="w-12 h-12 bg-blue-50 dark:bg-blue-900/30 rounded-lg flex items-center justify-center">
                    <i class="fas fa-exclamation-triangle text-blue-600 dark:text-blue-400 text-xl"></i>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide">Active Types
                    </p>
                    <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white">
                        <?= $activeIncidentTypes ?>
                    </p>
                </div>
                <div class="w-12 h-12 bg-green-50 dark:bg-green-900/30 rounded-lg flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-600 dark:text-green-400 text-xl"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Button -->
    <div class="flex justify-end">
        <button onclick="showIncidentTypeModal()"
            class="inline-flex items-center px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
            <i class="fas fa-plus mr-2"></i> Add Incident Type
        </button>
    </div>

    <!-- Incident Types Table -->
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">All Incident Types</h3>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Manage incident type classifications</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 dark:text-gray-400 uppercase">
                            Incident Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 dark:text-gray-400 uppercase">
                            Service</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 dark:text-gray-400 uppercase">
                            Status</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 dark:text-gray-400 uppercase">
                            Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php if (empty($incidentTypes)): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                <i class="fas fa-exclamation-triangle text-4xl mb-3 text-gray-300 dark:text-gray-600"></i>
                                <p>No incident types found. Click "Add Incident Type" to create one.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($incidentTypes as $type): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        <?= htmlspecialchars($type['name']) ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900 dark:text-white">
                                        <?= htmlspecialchars($type['service_name']) ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <form method="POST" class="inline" onchange="this.submit()">
                                        <input type="hidden" name="action" value="toggle_incident_type_status">
                                        <input type="hidden" name="type_id" value="<?= $type['type_id'] ?>">
                                        <input type="hidden" name="new_status" value="<?= $type['is_active'] ? 0 : 1 ?>">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" <?= $type['is_active'] ? 'checked' : '' ?>
                                            onchange="this.form.submit()" class="sr-only peer">
                                            <div
                                                class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:start-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-green-600">
                                            </div>
                                            <span
                                                class="ms-3 text-sm font-medium <?= $type['is_active'] ? 'text-green-600 dark:text-green-400' : 'text-gray-500 dark:text-gray-400' ?>">
                                                <?= $type['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </label>
                                    </form>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button onclick='editIncidentType(<?= json_encode($type) ?>)'
                                        class="inline-flex items-center px-3 py-1.5 bg-blue-100 text-blue-700 hover:bg-blue-200 dark:bg-blue-900/30 dark:text-blue-400 dark:hover:bg-blue-900/50 text-xs font-semibold rounded-lg transition-colors mr-2">
                                        <i class="fas fa-edit mr-1"></i> Edit
                                    </button>
                                    <button
                                        onclick="deleteIncidentType(<?= $type['type_id'] ?>, '<?= addslashes(htmlspecialchars($type['name'])) ?>')"
                                        class="inline-flex items-center px-3 py-1.5 bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900/30 dark:text-red-400 dark:hover:bg-red-900/50 text-xs font-semibold rounded-lg transition-colors">
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

<!-- Incident Type Modal -->
<div id="incidentTypeModal"
    class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50 p-4">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full">
        <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white" id="incidentTypeModalTitle">Add Incident
                Type</h3>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" id="incidentTypeAction" value="create_incident_type">
            <input type="hidden" name="type_id" id="incidentTypeId" value="">

            <div>
                <label for="incidentTypeServiceId"
                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Service <span
                        class="text-red-500">*</span></label>
                <select name="service_id" id="incidentTypeServiceId" required
                    class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Select a service...</option>
                    <?php foreach ($services as $service): ?>
                        <option value="<?= $service['service_id'] ?>">
                            <?= htmlspecialchars($service['service_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="incidentTypeName"
                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Incident Type Name <span
                        class="text-red-500">*</span></label>
                <input type="text" name="type_name" id="incidentTypeName" required
                    placeholder="e.g., Hardware Failure, Network Outage, Software Bug"
                    class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>

            <div class="flex justify-end space-x-3 pt-2">
                <button type="button" onclick="hideIncidentTypeModal()"
                    class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                    Cancel
                </button>
                <button type="submit"
                    class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-sm font-medium">
                    <i class="fas fa-save mr-2"></i> Save
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function showIncidentTypeModal() {
        document.getElementById('incidentTypeModalTitle').textContent = 'Add Incident Type';
        document.getElementById('incidentTypeAction').value = 'create_incident_type';
        document.getElementById('incidentTypeId').value = '';
        document.getElementById('incidentTypeServiceId').value = '';
        document.getElementById('incidentTypeName').value = '';
        document.getElementById('incidentTypeModal').classList.remove('hidden');
    }

    function editIncidentType(type) {
        document.getElementById('incidentTypeModalTitle').textContent = 'Edit Incident Type';
        document.getElementById('incidentTypeAction').value = 'update_incident_type';
        document.getElementById('incidentTypeId').value = type.type_id;
        document.getElementById('incidentTypeServiceId').value = type.service_id;
        document.getElementById('incidentTypeName').value = type.name;
        document.getElementById('incidentTypeModal').classList.remove('hidden');
    }

    function hideIncidentTypeModal() {
        document.getElementById('incidentTypeModal').classList.add('hidden');
    }

    function deleteIncidentType(id, name) {
        if (confirm(`Are you sure you want to delete "${name}"?\n\nThis will affect any incidents using this incident type.`)) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
            <input type="hidden" name="action" value="delete_incident_type">
            <input type="hidden" name="type_id" value="${id}">
        `;
            document.body.appendChild(form);
            form.submit();
        }
    }
</script>