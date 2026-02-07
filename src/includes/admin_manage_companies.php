<!-- Companies Tab Content -->
<div class="space-y-6">
    <!-- Statistics Card -->
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600 dark:text-gray-400 uppercase tracking-wide">Total Companies</p>
                <p class="mt-2 text-3xl font-semibold text-gray-900 dark:text-white"><?= $totalCompanies ?></p>
            </div>
            <button onclick="showCompanyModal()" class="inline-flex items-center px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
                <i class="fas fa-plus mr-2"></i> Add Company
            </button>
        </div>
    </div>

    <!-- Companies Table -->
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">
        <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">All Companies</h3>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Manage client companies</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-900/50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 dark:text-gray-400 uppercase">Company Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 dark:text-gray-400 uppercase">Category</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-600 dark:text-gray-400 uppercase">Created</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-600 dark:text-gray-400 uppercase">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php if (empty($companies)): ?>
                        <tr>
                            <td colspan="4" class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                                <i class="fas fa-building text-4xl mb-3 text-gray-300 dark:text-gray-600"></i>
                                <p>No companies found. Click "Add Company" to create one.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($companies as $company): ?>
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        <?= htmlspecialchars($company['company_name']) ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php if ($company['category']): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400">
                                            <?= htmlspecialchars($company['category']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs text-gray-400 dark:text-gray-500 italic">No category</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    <?= date('M j, Y', strtotime($company['created_at'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button onclick='editCompany(<?= json_encode($company) ?>)' class="inline-flex items-center px-3 py-1.5 bg-blue-100 text-blue-700 hover:bg-blue-200 dark:bg-blue-900/30 dark:text-blue-400 dark:hover:bg-blue-900/50 text-xs font-semibold rounded-lg transition-colors mr-2">
                                        <i class="fas fa-edit mr-1"></i> Edit
                                    </button>
                                    <button onclick="deleteCompany(<?= $company['company_id'] ?>, '<?= addslashes(htmlspecialchars($company['company_name'])) ?>')" class="inline-flex items-center px-3 py-1.5 bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-900/30 dark:text-red-400 dark:hover:bg-red-900/50 text-xs font-semibold rounded-lg transition-colors">
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

<!-- Company Modal -->
<div id="companyModal" class="hidden fixed inset-0 bg-gray-500 bg-opacity-75 flex items-center justify-center z-50 p-4">
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full">
        <div class="px-6 py-5 border-b border-gray-200 dark:border-gray-700">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white" id="companyModalTitle">Add Company</h3>
        </div>
        <form method="POST" class="p-6 space-y-4">
            <input type="hidden" name="action" id="companyAction" value="create_company">
            <input type="hidden" name="company_id" id="companyId" value="">
            
            <div>
                <label for="companyName" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Company Name <span class="text-red-500">*</span></label>
                <input type="text" name="company_name" id="companyName" required class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            
            <div>
                <label for="companyCategory" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Category <span class="text-gray-400 text-xs">(Optional)</span></label>
                <input type="text" name="category" id="companyCategory" placeholder="e.g., Banking, Telecom" class="w-full px-4 py-2.5 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            
            <div class="flex justify-end space-x-3 pt-2">
                <button type="button" onclick="hideCompanyModal()" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
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
function showCompanyModal() {
    document.getElementById('companyModalTitle').textContent = 'Add Company';
    document.getElementById('companyAction').value = 'create_company';
    document.getElementById('companyId').value = '';
    document.getElementById('companyName').value = '';
    document.getElementById('companyCategory').value = '';
    document.getElementById('companyModal').classList.remove('hidden');
}

function editCompany(company) {
    document.getElementById('companyModalTitle').textContent = 'Edit Company';
    document.getElementById('companyAction').value = 'update_company';
    document.getElementById('companyId').value = company.company_id;
    document.getElementById('companyName').value = company.company_name;
    document.getElementById('companyCategory').value = company.category || '';
    document.getElementById('companyModal').classList.remove('hidden');
}

function hideCompanyModal() {
    document.getElementById('companyModal').classList.add('hidden');
}

function deleteCompany(id, name) {
    if (confirm(`Are you sure you want to delete "${name}"?\n\nThis will remove the company from all incident records.`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete_company">
            <input type="hidden" name="company_id" value="${id}">
        `;
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
