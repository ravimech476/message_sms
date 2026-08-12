<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h5 class="mb-0"><i class="material-icons-outlined font-18 me-1">monitor_heart</i> Process Monitor</h5>
            <button type="button" class="btn btn-primary btn-sm" id="refreshMonitorBtn">
                <i class="material-icons-outlined font-18">refresh</i> Refresh
            </button>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card bg-success text-white">
                    <div class="card-body text-center">
                        <i class="material-icons-outlined" style="font-size: 48px;">done_all</i>
                        <h6 class="mt-2">Enabled Crons</h6>
                        <h3 id="enabled-cron-count">-</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card bg-warning text-white">
                    <div class="card-body text-center">
                        <i class="material-icons-outlined" style="font-size: 48px;">pause_circle</i>
                        <h6 class="mt-2">Disabled Crons</h6>
                        <h3 id="disabled-cron-count">-</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cron Jobs Management -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center" style="background-color: #343a40;">
                <h6 class="mb-0" style="color: #ffffff !important;">
                    <i class="material-icons-outlined font-18 me-1" style="color: #ffffff !important;">settings</i>
                    <span style="color: #ffffff !important;">Cron Jobs Management</span>
                </h6>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-light me-2" id="enableAllCronsBtn">
                        <i class="material-icons-outlined font-14">check_circle</i> Enable All
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-light" id="disableAllCronsBtn">
                        <i class="material-icons-outlined font-14">cancel</i> Disable All
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="cron-management-table">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">Status</th>
                                <th>Cron Name</th>
                                <th>Command</th>
                                <th>Schedule</th>
                                <th>Description</th>
                                <th class="text-center" style="width: 120px;">Enable/Disable</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td colspan="6" class="text-center py-4">
                                    <div class="spinner-border spinner-border-sm" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    Loading cron jobs...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .badge {
        font-size: 0.75rem;
        padding: 0.35em 0.65em;
    }
    .table td, .table th {
        vertical-align: middle;
    }
    .status-badge {
        padding: 5px 10px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 4px;
    }
    .status-enabled {
        background-color: #28a745;
        color: white;
    }
    .status-disabled {
        background-color: #dc3545;
        color: white;
    }
    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    .spinning {
        animation: spin 1s linear infinite;
        display: inline-block;
    }
    /* Toggle Switch Styles */
    .form-switch .form-check-input {
        width: 50px;
        height: 26px;
        cursor: pointer;
    }
    .form-switch .form-check-input:checked {
        background-color: #28a745;
        border-color: #28a745;
    }
    .form-switch .form-check-input:not(:checked) {
        background-color: #dc3545;
        border-color: #dc3545;
    }
    .form-switch .form-check-input:focus {
        box-shadow: none;
    }
    .cron-row-disabled {
        background-color: #f8f9fa;
        opacity: 0.7;
    }
    .cron-row-disabled td {
        text-decoration: line-through;
        color: #6c757d;
    }
    .cron-row-disabled .form-check-input,
    .cron-row-disabled .status-badge {
        text-decoration: none;
    }
</style>

<script>
(function() {
    'use strict';

    function initializeMonitor() {
        document.getElementById('refreshMonitorBtn')?.addEventListener('click', loadCronManagement);
        document.getElementById('enableAllCronsBtn')?.addEventListener('click', () => toggleAllCrons(true));
        document.getElementById('disableAllCronsBtn')?.addEventListener('click', () => toggleAllCrons(false));

        loadCronManagement();
    }

    // Load cron management list
    function loadCronManagement() {
        fetch('{{ route("admin.monitor.cron-list") }}')
            .then(response => response.json())
            .then(data => {
                let tbody = document.querySelector('#cron-management-table tbody');
                if (!tbody) return;

                tbody.innerHTML = '';

                let enabled = 0, disabled = 0;

                if (data.success && data.crons && data.crons.length > 0) {
                    data.crons.forEach(function(cron) {
                        let isEnabled = cron.is_enabled == 1 || cron.is_enabled === true;
                        let statusClass = isEnabled ? 'status-enabled' : 'status-disabled';
                        let rowClass = isEnabled ? '' : 'cron-row-disabled';

                        if (isEnabled) enabled++;
                        else disabled++;

                        let row = document.createElement('tr');
                        row.className = rowClass;
                        row.id = `cron-row-${cron.id}`;
                        row.innerHTML = `
                            <td class="text-center">
                                <span class="status-badge ${statusClass}" id="status-badge-${cron.id}">
                                    ${isEnabled ? 'ON' : 'OFF'}
                                </span>
                            </td>
                            <td>
                                <i class="material-icons-outlined font-18 me-1 align-middle">schedule</i>
                                <strong>${cron.name}</strong>
                            </td>
                            <td><code class="text-primary">${cron.command}</code></td>
                            <td><span class="badge bg-info">${cron.schedule || '* * * * *'}</span></td>
                            <td><small class="text-muted">${cron.description || '-'}</small></td>
                            <td class="text-center">
                                <div class="form-check form-switch d-flex justify-content-center mb-0">
                                    <input class="form-check-input" type="checkbox" role="switch"
                                           id="cron-toggle-${cron.id}"
                                           ${isEnabled ? 'checked' : ''}
                                           onchange="toggleCron(${cron.id}, this.checked)">
                                </div>
                            </td>
                        `;
                        tbody.appendChild(row);
                    });
                } else {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                <i class="material-icons-outlined" style="font-size: 48px; color: #6c757d;">event_busy</i>
                                <p class="text-muted mt-2 mb-0">No cron jobs configured</p>
                            </td>
                        </tr>
                    `;
                }

                const enabledCount = document.getElementById('enabled-cron-count');
                const disabledCount = document.getElementById('disabled-cron-count');
                if (enabledCount) enabledCount.textContent = enabled;
                if (disabledCount) disabledCount.textContent = disabled;
            })
            .catch(error => {
                console.error('Error loading cron management:', error);
                const tbody = document.querySelector('#cron-management-table tbody');
                if (tbody) {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="6" class="text-center text-danger py-4">
                                Failed to load cron list
                            </td>
                        </tr>
                    `;
                }
            });
    }

    // Toggle single cron - Make it global
    window.toggleCron = function(cronId, isEnabled) {
        const toggle = document.getElementById(`cron-toggle-${cronId}`);
        const row = document.getElementById(`cron-row-${cronId}`);
        const statusBadge = document.getElementById(`status-badge-${cronId}`);

        if (toggle) toggle.disabled = true;

        fetch('{{ route("admin.monitor.cron-toggle") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                cron_id: cronId,
                is_enabled: isEnabled ? 1 : 0
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (row) {
                    row.className = isEnabled ? '' : 'cron-row-disabled';
                }
                if (statusBadge) {
                    statusBadge.className = `status-badge ${isEnabled ? 'status-enabled' : 'status-disabled'}`;
                    statusBadge.textContent = isEnabled ? 'ON' : 'OFF';
                }

                updateCronCounts();
                showToast(`Cron job ${isEnabled ? 'enabled' : 'disabled'} successfully!`, 'success');
            } else {
                if (toggle) toggle.checked = !isEnabled;
                showToast('Error: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (toggle) toggle.checked = !isEnabled;
            showToast('Failed to toggle cron job. Please try again.', 'error');
        })
        .finally(() => {
            if (toggle) toggle.disabled = false;
        });
    };

    // Toggle all crons
    function toggleAllCrons(enable) {
        const action = enable ? 'enable' : 'disable';
        if (!confirm(`Are you sure you want to ${action} ALL cron jobs?`)) {
            return;
        }

        const enableBtn = document.getElementById('enableAllCronsBtn');
        const disableBtn = document.getElementById('disableAllCronsBtn');
        if (enableBtn) enableBtn.disabled = true;
        if (disableBtn) disableBtn.disabled = true;

        fetch('{{ route("admin.monitor.cron-toggle-all") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                is_enabled: enable ? 1 : 0
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast(`All cron jobs ${enable ? 'enabled' : 'disabled'} successfully!`, 'success');
                loadCronManagement();
            } else {
                showToast('Error: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('Failed to toggle all cron jobs. Please try again.', 'error');
        })
        .finally(() => {
            if (enableBtn) enableBtn.disabled = false;
            if (disableBtn) disableBtn.disabled = false;
        });
    }

    function updateCronCounts() {
        const enabledToggles = document.querySelectorAll('#cron-management-table .form-check-input:checked');
        const allToggles = document.querySelectorAll('#cron-management-table .form-check-input');

        const enabledCount = document.getElementById('enabled-cron-count');
        const disabledCount = document.getElementById('disabled-cron-count');

        if (enabledCount) enabledCount.textContent = enabledToggles.length;
        if (disabledCount) disabledCount.textContent = allToggles.length - enabledToggles.length;
    }

    function showToast(message, type) {
        if (typeof toastr !== 'undefined') {
            toastr[type === 'error' ? 'error' : 'success'](message);
            return;
        }

        if (type === 'error') {
            alert('Error: ' + message);
        } else {
            const toast = document.createElement('div');
            toast.className = `alert alert-${type === 'error' ? 'danger' : 'success'} position-fixed`;
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 250px;';
            toast.innerHTML = `
                <i class="material-icons-outlined font-18 me-1">${type === 'error' ? 'error' : 'check_circle'}</i>
                ${message}
            `;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.remove();
            }, 3000);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeMonitor);
    } else {
        initializeMonitor();
    }
})();
</script>
