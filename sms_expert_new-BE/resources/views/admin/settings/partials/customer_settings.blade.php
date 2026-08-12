@php
    // Get admin user from session
    $adminUser = Session::get('admin_user');
    $isSuperAdmin = isset($adminUser['role']) && $adminUser['role'] === 'super_admin';
    
    // Get permissions from session
    $permissions = $adminUser['permissions'] ?? [];
    
    // Check sub-permissions - Super admin has all permissions
    // Note: General Customer Settings is hidden as per requirement
    $canManageGeneralSettings = false; // Hidden - was: $isSuperAdmin || ($permissions['can_manage_general_customer_settings'] ?? false);
    $canManageGlobalMaintenance = $isSuperAdmin || ($permissions['can_manage_global_maintenance'] ?? false);
    $canManageCustomerMaintenance = $isSuperAdmin || ($permissions['can_manage_customer_maintenance'] ?? false);
    
    // Determine default sub-tab based on permissions
    $requestedSubTab = request()->get('subtab', '');
    $defaultSubTab = '';
    
    if ($canManageGeneralSettings) {
        $defaultSubTab = 'general-settings';
    } elseif ($canManageGlobalMaintenance) {
        $defaultSubTab = 'global-maintenance';
    } elseif ($canManageCustomerMaintenance) {
        $defaultSubTab = 'customer-maintenance';
    }
    
    // If no sub-tab requested, use default
    if (empty($requestedSubTab)) {
        $requestedSubTab = $defaultSubTab;
    }
    
    // Validate requested sub-tab against permissions
    $subTabPermissions = [
        'general-settings' => $canManageGeneralSettings,
        'global-maintenance' => $canManageGlobalMaintenance,
        'customer-maintenance' => $canManageCustomerMaintenance,
    ];
    
    // If user doesn't have permission for requested sub-tab, use default
    if (!($subTabPermissions[$requestedSubTab] ?? false)) {
        $requestedSubTab = $defaultSubTab;
    }
    
    // Check if user has any sub-permission
    $hasAnySubPermission = $canManageGeneralSettings || $canManageGlobalMaintenance || $canManageCustomerMaintenance;
@endphp

<!-- Customer Settings Tab -->
<div class="row">
    @if(!$hasAnySubPermission)
        <div class="col-12">
            <div class="alert alert-warning">
                <i class="material-icons-outlined me-2" style="vertical-align: middle;">warning</i>
                You don't have permission to access any customer settings. Please contact your administrator.
            </div>
        </div>
    @else
        <!-- Vertical Sidebar Menu -->
        <div class="col-md-3 col-lg-2">
            <div class="customer-settings-sidebar">
                <div class="sidebar-header">
                    <i class="material-icons-outlined">tune</i>
                    <span>Settings Menu</span>
                </div>
                <nav class="nav flex-column customer-settings-nav" id="customerSettingsSubTabs" role="tablist">
                    @if($canManageGeneralSettings)
                    <a class="nav-link {{ $requestedSubTab === 'general-settings' ? 'active' : '' }}" 
                       id="general-settings-tab" 
                       data-bs-toggle="pill" 
                       href="#general-settings-content" 
                       role="tab">
                        <i class="material-icons-outlined">settings</i>
                        <span>General Settings</span>
                    </a>
                    @endif
                    
                    @if($canManageGlobalMaintenance)
                    <a class="nav-link {{ $requestedSubTab === 'global-maintenance' ? 'active' : '' }}" 
                       id="global-maintenance-tab" 
                       data-bs-toggle="pill" 
                       href="#global-maintenance-content" 
                       role="tab">
                        <i class="material-icons-outlined">public</i>
                        <span>Global Maintenance</span>
                    </a>
                    @endif
                    
                    @if($canManageCustomerMaintenance)
                    <a class="nav-link {{ $requestedSubTab === 'customer-maintenance' ? 'active' : '' }}" 
                       id="customer-maintenance-tab" 
                       data-bs-toggle="pill" 
                       href="#customer-maintenance-content" 
                       role="tab">
                        <i class="material-icons-outlined">person_off</i>
                        <span>Customer Maintenance</span>
                        @if(count($customersInMaintenance ?? []) > 0)
                            <span class="badge bg-danger">{{ count($customersInMaintenance) }}</span>
                        @endif
                    </a>
                    @endif
                </nav>
            </div>
        </div>

        <!-- Tab Content -->
        <div class="col-md-9 col-lg-10">
            <div class="tab-content" id="customerSettingsSubTabContent">
                
                {{-- General Customer Settings Tab --}}
                @if($canManageGeneralSettings)
                <div class="tab-pane fade {{ $requestedSubTab === 'general-settings' ? 'show active' : '' }}" 
                     id="general-settings-content" 
                     role="tabpanel">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-gradient text-white" style="background: linear-gradient(135deg, #293b50, #1f2c3d);">
                            <h5 class="mb-0">
                                <i class="material-icons-outlined me-2" style="vertical-align: middle;">settings</i>
                                General Customer Settings
                            </h5>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('admin.settings.customer.save') }}" method="POST">
                                @csrf
                                
                                <!-- Price Margin Section -->
                                <div class="row mb-4">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label fw-bold">
                                                <i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">percent</i>
                                                Default Price Margin Percentage
                                            </label>
                                            <div class="input-group">
                                                <input type="number" 
                                                       name="default_margin_percentage" 
                                                       class="form-control" 
                                                       value="{{ $defaultMarginPercentage ?? 10 }}" 
                                                       min="0" 
                                                       max="100" 
                                                       step="0.01"
                                                       required>
                                                <span class="input-group-text">%</span>
                                            </div>
                                            <small class="text-muted">This margin will be applied to new customer pricing during onboarding.</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="alert alert-info mb-0">
                                            <h6 class="alert-heading">
                                                <i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">info</i>
                                                How Margin Works
                                            </h6>
                                            <small>
                                                The margin percentage is added to the base cost when calculating customer pricing.
                                                For example, a 10% margin on a £0.05 base cost would result in a £0.055 customer price.
                                            </small>
                                        </div>
                                    </div>
                                </div>

                                <div class="text-end">
                                    <button type="submit" class="btn text-white" style="background: linear-gradient(135deg, #ea6118, #d1520e);">
                                        <i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">save</i>
                                        Save Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Global Maintenance Mode Tab --}}
                @if($canManageGlobalMaintenance)
                <div class="tab-pane fade {{ $requestedSubTab === 'global-maintenance' ? 'show active' : '' }}" 
                     id="global-maintenance-content" 
                     role="tabpanel">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-gradient text-white" style="background: linear-gradient(135deg, #293b50, #1f2c3d);">
                            <h5 class="mb-0">
                                <i class="material-icons-outlined me-2" style="vertical-align: middle;">public</i>
                                Global Maintenance Mode
                            </h5>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('admin.settings.customer.save') }}" method="POST">
                                @csrf
                                <input type="hidden" name="save_global_maintenance" value="1">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" 
                                                   type="checkbox" 
                                                   name="global_maintenance_mode" 
                                                   id="globalMaintenanceMode" 
                                                   {{ ($globalMaintenanceMode ?? false) ? 'checked' : '' }}
                                                   style="width: 50px; height: 25px;">
                                            <label class="form-check-label ms-2" for="globalMaintenanceMode">
                                                <strong>Enable maintenance mode for ALL customers</strong>
                                            </label>
                                        </div>
                                        
                                        <div class="alert alert-danger py-2 {{ ($globalMaintenanceMode ?? false) ? '' : 'd-none' }}" id="globalMaintenanceWarning">
                                            <i class="material-icons-outlined me-1" style="font-size: 16px; vertical-align: middle;">warning</i>
                                            <strong>Warning:</strong> All customers will see the maintenance message when they log in!
                                        </div>
                                        
                                        <div class="alert alert-info py-2">
                                            <i class="material-icons-outlined me-1" style="font-size: 16px; vertical-align: middle;">info</i>
                                            <small>Global maintenance mode affects all customers immediately. Individual customer maintenance settings are overridden when this is enabled.</small>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <label class="form-label fw-bold">
                                                <i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">message</i>
                                                Maintenance Message
                                            </label>
                                            <textarea name="maintenance_message" 
                                                      class="form-control" 
                                                      rows="4" 
                                                      placeholder="Enter maintenance message">{{ $maintenanceMessage ?? 'The site is currently under maintenance. Please try again later.' }}</textarea>
                                            <small class="text-muted">This message will be displayed to all customers during maintenance.</small>
                                        </div>
                                    </div>
                                </div>

                                <hr class="my-4">

                                <div class="text-end">
                                    <button type="submit" class="btn text-white" style="background: linear-gradient(135deg, #ea6118, #d1520e);">
                                        <i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">save</i>
                                        Save Global Maintenance Settings
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                @endif

                {{-- Customer-Specific Maintenance Tab --}}
                @if($canManageCustomerMaintenance)
                <div class="tab-pane fade {{ $requestedSubTab === 'customer-maintenance' ? 'show active' : '' }}" 
                     id="customer-maintenance-content" 
                     role="tabpanel">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-gradient text-white" style="background: linear-gradient(135deg, #293b50, #1f2c3d);">
                            <h5 class="mb-0">
                                <i class="material-icons-outlined me-2" style="vertical-align: middle;">person_off</i>
                                Customer-Specific Maintenance Mode
                            </h5>
                        </div>
                        <div class="card-body">
                            <!-- Add Customers to Maintenance -->
                            <div class="card bg-light border-0 mb-4">
                                <div class="card-body">
                                    <h6 class="fw-bold mb-3">
                                        <i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">add_circle</i>
                                        Add Customers to Maintenance Mode
                                    </h6>
                                    <form action="{{ route('admin.settings.customer.maintenance.add') }}" method="POST" id="addMaintenanceForm">
                                        @csrf
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Select Customers</label>
                                                <select name="customer_ids[]" 
                                                        id="customerSelect" 
                                                        class="form-select select2-customers" 
                                                        multiple 
                                                        required>
                                                    @foreach($allCustomers ?? [] as $customer)
                                                        <option value="{{ $customer->id }}" 
                                                                data-busname="{{ urldecode($customer->busname) }}"
                                                                data-contactname="{{ urldecode($customer->contactname) }}"
                                                                data-email="{{ $customer->contactemail }}">
                                                            {{ urldecode($customer->busname ?: $customer->contactname) }} ({{ $customer->uname }})
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <small class="text-muted">Search and select multiple customers</small>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Custom Message (Optional)</label>
                                                <textarea name="maintenance_message" 
                                                          class="form-control" 
                                                          rows="3"
                                                          placeholder="Leave empty to use default message"></textarea>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label class="form-label">Start Time (Optional)</label>
                                                <input type="datetime-local" name="start_time" class="form-control">
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <label class="form-label">End Time (Optional)</label>
                                                <input type="datetime-local" name="end_time" class="form-control">
                                            </div>
                                            <div class="col-md-6 mb-3 d-flex align-items-end">
                                                <button type="submit" class="btn btn-success">
                                                    <i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">add</i>
                                                    Add to Maintenance
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <!-- Customers Currently in Maintenance -->
                            <h6 class="fw-bold mb-3">
                                <i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">list</i>
                                Customers Currently in Maintenance Mode
                                <span class="badge bg-danger ms-2">{{ count($customersInMaintenance ?? []) }}</span>
                            </h6>
                            
                            @if(count($customersInMaintenance ?? []) > 0)
                                <div class="table-responsive">
                                    <table class="table table-hover table-striped">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Customer</th>
                                                <th>Username</th>
                                                <th>Message</th>
                                                <th>Start Time</th>
                                                <th>End Time</th>
                                                <th>Added On</th>
                                                <th width="120">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($customersInMaintenance as $maintenance)
                                                <tr id="maintenance-row-{{ $maintenance->id }}">
                                                    <td>
                                                        <strong>{{ urldecode($maintenance->user->busname ?? $maintenance->user->contactname ?? 'N/A') }}</strong>
                                                        <br>
                                                        <small class="text-muted">{{ $maintenance->user->contactemail ?? '' }}</small>
                                                    </td>
                                                    <td>{{ $maintenance->user->uname ?? 'N/A' }}</td>
                                                    <td>
                                                        <small>{{ Str::limit($maintenance->maintenance_message ?? 'Default message', 50) }}</small>
                                                    </td>
                                                    <td>
                                                        @if($maintenance->start_time)
                                                            {{ $maintenance->start_time->format('Y-m-d H:i') }}
                                                        @else
                                                            <span class="text-muted">Immediate</span>
                                                        @endif
                                                    </td>
                                                    <td>
                                                        @if($maintenance->end_time)
                                                            {{ $maintenance->end_time->format('Y-m-d H:i') }}
                                                        @else
                                                            <span class="text-muted">Indefinite</span>
                                                        @endif
                                                    </td>
                                                    <td>{{ $maintenance->created_at->format('Y-m-d H:i') }}</td>
                                                    <td>
                                                        <button type="button" 
                                                                class="btn btn-sm btn-danger" 
                                                                onclick="removeFromMaintenance({{ $maintenance->id }})"
                                                                title="Remove from maintenance">
                                                            <i class="material-icons-outlined" style="font-size: 16px;">remove_circle</i>
                                                        </button>
                                                        <button type="button" 
                                                                class="btn btn-sm btn-outline-danger" 
                                                                onclick="deleteMaintenance({{ $maintenance->id }})"
                                                                title="Delete record">
                                                            <i class="material-icons-outlined" style="font-size: 16px;">delete</i>
                                                        </button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="alert alert-info">
                                    <i class="material-icons-outlined me-2" style="vertical-align: middle;">info</i>
                                    No customers are currently in maintenance mode.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
    @endif
</div>

<style>
    /* Vertical Sidebar Menu Styling */
    .customer-settings-sidebar {
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        overflow: hidden;
        position: sticky;
        top: 20px;
    }
    
    .customer-settings-sidebar .sidebar-header {
        background: linear-gradient(135deg, #293b50, #1f2c3d);
        color: white;
        padding: 12px 15px;
        font-size: 13px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .customer-settings-sidebar .sidebar-header i {
        font-size: 18px;
    }
    
    .customer-settings-nav {
        padding: 8px;
    }
    
    .customer-settings-nav .nav-link {
        color: #495057;
        padding: 10px 12px;
        margin-bottom: 4px;
        border-radius: 6px;
        font-size: 13px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 10px;
        transition: all 0.2s ease;
        text-decoration: none;
    }
    
    .customer-settings-nav .nav-link i {
        font-size: 18px;
        color: #6c757d;
        transition: color 0.2s ease;
    }
    
    .customer-settings-nav .nav-link span {
        flex: 1;
    }
    
    .customer-settings-nav .nav-link .badge {
        font-size: 10px;
        padding: 3px 6px;
    }
    
    .customer-settings-nav .nav-link:hover {
        background: #f8f9fa;
        color: #ea6118;
    }
    
    .customer-settings-nav .nav-link:hover i {
        color: #ea6118;
    }
    
    .customer-settings-nav .nav-link.active {
        background: linear-gradient(135deg, #ea6118, #d1520e);
        color: white;
    }
    
    .customer-settings-nav .nav-link.active i {
        color: white;
    }
    
    .customer-settings-nav .nav-link.active .badge {
        background: white !important;
        color: #ea6118;
    }

    /* Select2 Custom Styling */
    .select2-container--bootstrap-5 .select2-selection--multiple {
        min-height: 45px;
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
    }
    
    .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
        background: linear-gradient(135deg, #ea6118, #d1520e);
        border: none;
        color: white;
        padding: 4px 10px;
        border-radius: 4px;
        margin: 4px;
    }
    
    .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice__remove {
        color: white;
        margin-right: 5px;
    }
    
    .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice__remove:hover {
        color: #ffcccb;
        background: transparent;
    }
    
    .select2-container--bootstrap-5 .select2-dropdown {
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }
    
    .select2-container--bootstrap-5 .select2-results__option--highlighted[aria-selected] {
        background: linear-gradient(135deg, #ea6118, #d1520e);
    }
    
    .select2-container--bootstrap-5 .select2-search--dropdown .select2-search__field {
        border: 1px solid #dee2e6;
        border-radius: 0.375rem;
        padding: 8px 12px;
    }
    
    .customer-option-content {
        display: flex;
        flex-direction: column;
    }
    
    .customer-option-name {
        font-weight: 600;
        color: #293b50;
    }
    
    .customer-option-email {
        font-size: 12px;
        color: #6c757d;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .customer-settings-sidebar {
            position: relative;
            top: 0;
            margin-bottom: 20px;
        }
        
        .customer-settings-nav {
            display: flex;
            flex-wrap: nowrap;
            overflow-x: auto;
            padding: 10px;
        }
        
        .customer-settings-nav .nav-link {
            white-space: nowrap;
            margin-right: 8px;
            margin-bottom: 0;
        }
    }
</style>

<script>
    // Initialize Select2 for customer selection
    $(document).ready(function() {
        // Check if Select2 is available
        if (typeof $.fn.select2 !== 'undefined') {
            initCustomerSelect2();
        } else {
            // Load Select2 dynamically if not available
            loadSelect2();
        }
    });

    function loadSelect2() {
        // Load Select2 CSS
        if (!document.querySelector('link[href*="select2"]')) {
            var cssLink = document.createElement('link');
            cssLink.rel = 'stylesheet';
            cssLink.href = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css';
            document.head.appendChild(cssLink);
            
            var cssTheme = document.createElement('link');
            cssTheme.rel = 'stylesheet';
            cssTheme.href = 'https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css';
            document.head.appendChild(cssTheme);
        }
        
        // Load Select2 JS
        if (!document.querySelector('script[src*="select2"]')) {
            var script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js';
            script.onload = function() {
                initCustomerSelect2();
            };
            document.body.appendChild(script);
        }
    }

    function initCustomerSelect2() {
        $('.select2-customers').select2({
            theme: 'bootstrap-5',
            placeholder: 'Search customers by name, username or email...',
            allowClear: true,
            width: '100%',
            closeOnSelect: false,
            templateResult: formatCustomerOption,
            templateSelection: formatCustomerSelection,
            matcher: customMatcher
        });
    }

    // Custom template for dropdown options
    function formatCustomerOption(customer) {
        if (!customer.id) {
            return customer.text;
        }
        
        var $option = $(customer.element);
        var busname = $option.data('busname') || '';
        var contactname = $option.data('contactname') || '';
        var email = $option.data('email') || '';
        var displayName = busname || contactname || customer.text;
        
        var $result = $(
            '<div class="customer-option-content">' +
                '<span class="customer-option-name">' + displayName + '</span>' +
                '<span class="customer-option-email">' + email + '</span>' +
            '</div>'
        );
        
        return $result;
    }

    // Custom template for selected items
    function formatCustomerSelection(customer) {
        if (!customer.id) {
            return customer.text;
        }
        
        var $option = $(customer.element);
        var busname = $option.data('busname') || '';
        var contactname = $option.data('contactname') || '';
        var displayName = busname || contactname;
        
        // Truncate long names
        if (displayName.length > 25) {
            displayName = displayName.substring(0, 25) + '...';
        }
        
        return displayName || customer.text;
    }

    // Custom matcher for searching
    function customMatcher(params, data) {
        // If there are no search terms, return all data
        if ($.trim(params.term) === '') {
            return data;
        }
        
        // Do not display the item if there is no 'text' property
        if (typeof data.text === 'undefined') {
            return null;
        }
        
        var searchTerm = params.term.toLowerCase();
        var $option = $(data.element);
        var busname = ($option.data('busname') || '').toLowerCase();
        var contactname = ($option.data('contactname') || '').toLowerCase();
        var email = ($option.data('email') || '').toLowerCase();
        var text = data.text.toLowerCase();
        
        // Check if search term matches any field
        if (text.indexOf(searchTerm) > -1 ||
            busname.indexOf(searchTerm) > -1 ||
            contactname.indexOf(searchTerm) > -1 ||
            email.indexOf(searchTerm) > -1) {
            return data;
        }
        
        return null;
    }

    // Toggle global maintenance warning
    document.getElementById('globalMaintenanceMode')?.addEventListener('change', function() {
        const warning = document.getElementById('globalMaintenanceWarning');
        if (this.checked) {
            warning.classList.remove('d-none');
        } else {
            warning.classList.add('d-none');
        }
    });

    // Remove from maintenance
    function removeFromMaintenance(maintenanceId) {
        if (!confirm('Are you sure you want to remove this customer from maintenance mode?')) {
            return;
        }

        fetch('{{ route("admin.settings.customer.maintenance.remove") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ maintenance_id: maintenanceId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('maintenance-row-' + maintenanceId).remove();
                alert(data.message);
                // Reload with correct subtab
                window.location.href = '{{ route("admin.settings") }}?tab=customer-settings&subtab=customer-maintenance';
            } else {
                alert(data.message || 'Failed to remove from maintenance');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred');
        });
    }

    // Delete maintenance record
    function deleteMaintenance(maintenanceId) {
        if (!confirm('Are you sure you want to permanently delete this maintenance record?')) {
            return;
        }

        fetch('{{ route("admin.settings.customer.maintenance.delete") }}', {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ maintenance_id: maintenanceId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('maintenance-row-' + maintenanceId).remove();
                alert(data.message);
                // Reload with correct subtab
                window.location.href = '{{ route("admin.settings") }}?tab=customer-settings&subtab=customer-maintenance';
            } else {
                alert(data.message || 'Failed to delete record');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred');
        });
    }
</script>
