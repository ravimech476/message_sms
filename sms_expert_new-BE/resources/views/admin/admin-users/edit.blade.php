@extends('admin.layouts.app')
@section('title')
    {{ __('Edit Admin User') }}
@endsection

@push('style')
<style>
    .breadcrumb-item+.breadcrumb-item::before {
        content: " / " !important;
        color: #6c757d !important;
    }
    
    .form-card {
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }
    
    .permission-group {
        background: #f8f9fa;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .permission-group-title {
        font-weight: 600;
        color: #293b50;
        margin-bottom: 15px;
        padding-bottom: 10px;
        border-bottom: 2px solid #ea6118;
    }
    
    .permission-item {
        display: flex;
        align-items: center;
        padding: 8px 12px;
        background: white;
        border-radius: 6px;
        margin-bottom: 8px;
        transition: all 0.2s ease;
    }
    
    .permission-item:hover {
        background: #fff8f5;
        box-shadow: 0 2px 4px rgba(234, 97, 24, 0.1);
    }
    
    .permission-item .form-check-input {
        width: 18px;
        height: 18px;
        margin-right: 12px;
        cursor: pointer;
    }
    
    .permission-item .form-check-input:checked {
        background-color: #ea6118;
        border-color: #ea6118;
    }
    
    .permission-item label {
        cursor: pointer;
        margin-bottom: 0;
        flex: 1;
    }
    
    .btn-submit {
        background: linear-gradient(135deg, #ea6118, #d1520e);
        color: white;
        border: none;
        padding: 12px 30px;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    
    .btn-submit:hover {
        background: linear-gradient(135deg, #d1520e, #b8450c);
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(234, 97, 24, 0.3);
    }
    
    .btn-cancel {
        background: #6c757d;
        color: white;
        border: none;
        padding: 12px 30px;
        border-radius: 8px;
        font-weight: 500;
    }
    
    .btn-cancel:hover {
        background: #5a6268;
        color: white;
    }
    
    .select-all-btn {
        background: #17a2b8;
        color: white;
        border: none;
        padding: 5px 15px;
        border-radius: 5px;
        font-size: 12px;
        cursor: pointer;
    }
    
    .deselect-all-btn {
        background: #6c757d;
        color: white;
        border: none;
        padding: 5px 15px;
        border-radius: 5px;
        font-size: 12px;
        cursor: pointer;
    }
    
    .role-info {
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 8px;
        padding: 12px 15px;
        margin-top: 10px;
        font-size: 14px;
    }
    
    .role-info i {
        color: #856404;
    }
    
    .user-info-card {
        background: linear-gradient(135deg, #293b50, #1f2c3d);
        color: white;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
    }
    
    .user-info-card .user-name {
        font-size: 20px;
        font-weight: 600;
        margin-bottom: 5px;
    }
    
    .user-info-card .user-email {
        opacity: 0.8;
        font-size: 14px;
    }
    
    .user-info-card .last-login {
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid rgba(255,255,255,0.2);
        font-size: 13px;
        opacity: 0.7;
    }

    .nav-tabs .nav-link {
        color: #293b50;
        font-weight: 500;
        border: none;
        border-bottom: 2px solid transparent;
        padding: 12px 20px;
    }
    
    .nav-tabs .nav-link:hover {
        border-color: transparent;
        border-bottom: 2px solid #ea6118;
        color: #ea6118;
    }
    
    .nav-tabs .nav-link.active {
        color: #ea6118;
        background-color: transparent;
        border-color: transparent;
        border-bottom: 2px solid #ea6118;
    }
    
    .tab-content {
        padding-top: 20px;
    }

    .role-description {
        background: #e9ecef;
        border-radius: 6px;
        padding: 10px 15px;
        margin-top: 10px;
        font-size: 13px;
        color: #666;
    }

    .btn-resend {
        background: #28a745;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
    }

    .btn-resend:hover {
        background: #218838;
        color: white;
    }
</style>
@endpush

@section('content')
<main class="main-wrapper" id="main-wrapper">
    <div class="main-content">
        <!-- Breadcrumb -->
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3 breadcolor" style="position: relative;">
            <div class="breadcrumb-title pe-3 title-name">Edit Admin User</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.admin-users.index') }}">Admin Users</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Edit</li>
                    </ol>
                </nav>
            </div>
            <div class="me-2 back-button-container" style="position: absolute; right: 0; top: 50%; transform: translateY(-50%);">
                <button id="backButton" class="btn btn-primary btn-sm">
                    <i class="bx bx-arrow-back"></i> Back
                </button>
            </div>
        </div>

        <!-- User Info Card -->
        <div class="user-info-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="user-name">{{ urldecode($adminUser->contactname ?? '') }}</div>
                    <div class="user-email">{{ $adminUser->contactemail }}</div>
                    @if($adminUser->adminRole)
                        <span class="badge bg-warning mt-2">{{ $adminUser->adminRole->name }}</span>
                    @elseif($adminUser->role)
                        <span class="badge bg-info mt-2">{{ ucfirst(str_replace('_', ' ', $adminUser->role)) }}</span>
                    @endif
                </div>
                <div class="text-end">
                    <span class="badge bg-{{ !$adminUser->bit_disabled ? 'success' : 'danger' }}" style="font-size: 14px;">
                        {{ !$adminUser->bit_disabled ? 'Active' : 'Inactive' }}
                    </span>
                    <button type="button" class="btn btn-resend btn-sm ms-2" onclick="resendCredentials()">
                        <i class="material-icons-outlined" style="font-size: 16px;">email</i> Resend Credentials
                    </button>
                </div>
            </div>
            <div class="last-login">
                <i class="material-icons-outlined" style="font-size: 14px;">schedule</i>
                Last Login: {{ $adminUser->last_login_at ? \Carbon\Carbon::parse($adminUser->last_login_at)->format('d M Y H:i:s') : 'Never' }}
                @if($adminUser->last_login_ip)
                    | IP: {{ $adminUser->last_login_ip }}
                @endif
            </div>
        </div>

        <!-- Form Card -->
        <div class="card form-card">
            <div class="card-body p-4">
                <form action="{{ route('admin.admin-users.update', $adminUser->id) }}" method="POST" id="adminUserForm">
                    @csrf
                    @method('PUT')
                    
                    <!-- Nav tabs -->
                    <ul class="nav nav-tabs nav-success" role="tablist">
                        <li class="nav-item" role="presentation">
                            <a class="nav-link active" data-bs-toggle="tab" href="#basic-info" role="tab" aria-selected="true">
                                <div class="d-flex align-items-center">
                                    <div class="tab-icon"><i class="material-icons-outlined font-18 me-1">person</i></div>
                                    <div class="tab-title">Basic Information</div>
                                </div>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-bs-toggle="tab" href="#roles-tab" role="tab" aria-selected="false">
                                <div class="d-flex align-items-center">
                                    <div class="tab-icon"><i class="material-icons-outlined font-18 me-1">badge</i></div>
                                    <div class="tab-title">Role & Access</div>
                                </div>
                            </a>
                        </li>
                        <li class="nav-item" role="presentation">
                            <a class="nav-link" data-bs-toggle="tab" href="#permissions-tab" role="tab" aria-selected="false">
                                <div class="d-flex align-items-center">
                                    <div class="tab-icon"><i class="material-icons-outlined font-18 me-1">security</i></div>
                                    <div class="tab-title">Permissions</div>
                                </div>
                            </a>
                        </li>
                    </ul>

                    <!-- Tab content -->
                    <div class="tab-content py-3">
                        <!-- Basic Information Tab -->
                        <div class="tab-pane fade show active" id="basic-info" role="tabpanel">
                            <div class="row mb-4">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" 
                                        value="{{ old('name', urldecode($adminUser->contactname ?? '')) }}" required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" 
                                        value="{{ old('email', $adminUser->contactemail) }}" required>
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" name="username" class="form-control @error('username') is-invalid @enderror" 
                                        value="{{ old('username', $adminUser->uname) }}" required>
                                    @error('username')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" 
                                        value="{{ old('phone', $adminUser->phone) }}">
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">New Password <small class="text-muted">(Leave blank to keep current)</small></label>
                                    <input type="password" name="password" class="form-control @error('password') is-invalid @enderror">
                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" name="password_confirmation" class="form-control">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Status</label>
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" name="is_active" id="isActive" 
                                            {{ old('is_active', !$adminUser->bit_disabled) ? 'checked' : '' }} style="width: 50px; height: 25px;">
                                        <label class="form-check-label ms-2" for="isActive">Active</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Role & Access Tab -->
                        <div class="tab-pane fade" id="roles-tab" role="tabpanel">
                            <div class="row">
                                <div class="col-md-8">
                                    <label class="form-label">Select Role <span class="text-danger">*</span></label>
                                    <select name="role_id" id="roleSelect" class="form-select @error('role_id') is-invalid @enderror">
                                        <option value="">Select Role</option>
                                        @foreach($roles as $role)
                                            <option value="{{ $role->id }}" 
                                                data-slug="{{ $role->slug }}"
                                                data-description="{{ $role->description }}"
                                                data-permissions="{{ json_encode($role->permissions) }}"
                                                data-is-system="{{ $role->is_system ? '1' : '0' }}"
                                                {{ old('role_id', $adminUser->admin_role_id) == $role->id ? 'selected' : '' }}>
                                                {{ $role->name }}
                                                @if($role->is_system) (System) @endif
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('role_id')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                    
                                    <div id="roleDescription" class="role-description" style="display: none;">
                                        <strong>Description:</strong> <span id="roleDescText"></span>
                                    </div>
                                    
                                    <div class="role-info mt-3" id="roleInfo" style="display: none;">
                                        <i class="material-icons-outlined" style="font-size: 16px;">info</i>
                                        <span id="roleInfoText"></span>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="card bg-light">
                                        <div class="card-body">
                                            <h6 class="card-title">
                                                <i class="material-icons-outlined" style="font-size: 18px;">help_outline</i>
                                                Role Guide
                                            </h6>
                                            <ul class="list-unstyled mb-0" style="font-size: 13px;">
                                                <li class="mb-2"><strong>Super Admin:</strong> Full access</li>
                                                <li class="mb-2"><strong>Admin:</strong> Most features</li>
                                                <li class="mb-2"><strong>Manager:</strong> Customer & reports</li>
                                                <li><strong>Staff:</strong> View only access</li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="use_role_permissions" id="useRolePermissions">
                                    <label class="form-check-label" for="useRolePermissions">
                                        <strong>Reset to role's default permissions</strong>
                                    </label>
                                </div>
                                <small class="text-muted">
                                    Check this to override current permissions with the selected role's default permissions.
                                </small>
                            </div>
                        </div>

                        <!-- Permissions Tab -->
                        <div class="tab-pane fade" id="permissions-tab" role="tabpanel">
                            <div class="d-flex justify-content-end mb-3">
                                <button type="button" class="select-all-btn me-2" onclick="selectAllPermissions()">Select All</button>
                                <button type="button" class="deselect-all-btn" onclick="deselectAllPermissions()">Deselect All</button>
                            </div>

                            <div id="permissionsSection">
                                <!-- Menu Permissions -->
                                <div class="permission-group">
                                    <div class="permission-group-title">
                                        <i class="material-icons-outlined" style="font-size: 18px; vertical-align: middle;">menu</i>
                                        Menu Access Permissions
                                    </div>
                                    <div class="row">
                                        @foreach($permissions['menu_permissions'] as $key => $label)
                                            <div class="col-md-4">
                                                <div class="permission-item">
                                                    <input class="form-check-input permission-checkbox" type="checkbox" name="{{ $key }}" 
                                                        id="{{ $key }}" value="1" 
                                                        {{ old($key, $adminUser->adminPermissions?->$key ?? false) ? 'checked' : '' }}>
                                                    <label for="{{ $key }}">{{ $label }}</label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <!-- Customer Tab Permissions -->
                                <div class="permission-group">
                                    <div class="permission-group-title">
                                        <i class="material-icons-outlined" style="font-size: 18px; vertical-align: middle;">tab</i>
                                        Customer View Tab Permissions
                                    </div>
                                    <div class="row">
                                        @foreach($permissions['customer_tab_permissions'] as $key => $label)
                                            <div class="col-md-4">
                                                <div class="permission-item">
                                                    <input class="form-check-input permission-checkbox" type="checkbox" name="{{ $key }}" 
                                                        id="{{ $key }}" value="1" 
                                                        {{ old($key, $adminUser->adminPermissions?->$key ?? false) ? 'checked' : '' }}>
                                                    <label for="{{ $key }}">{{ $label }}</label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <!-- Settings Tab Permissions -->
                                <div class="permission-group">
                                    <div class="permission-group-title">
                                        <i class="material-icons-outlined" style="font-size: 18px; vertical-align: middle;">settings</i>
                                        Settings Tab Permissions
                                    </div>
                                    <div class="row">
                                        @foreach($permissions['settings_tab_permissions'] as $key => $label)
                                            <div class="col-md-4">
                                                <div class="permission-item">
                                                    <input class="form-check-input permission-checkbox" type="checkbox" name="{{ $key }}" 
                                                        id="{{ $key }}" value="1" 
                                                        {{ old($key, $adminUser->adminPermissions?->$key ?? false) ? 'checked' : '' }}>
                                                    <label for="{{ $key }}">{{ $label }}</label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <!-- Customer Settings Sub-Permissions -->
                                @if(isset($permissions['customer_settings_sub_permissions']))
                                <div class="permission-group">
                                    <div class="permission-group-title">
                                        <i class="material-icons-outlined" style="font-size: 18px; vertical-align: middle;">manage_accounts</i>
                                        Customer Settings Sub-Permissions
                                        <small class="text-muted ms-2" style="font-weight: normal;">(Requires "Customer Settings" permission above)</small>
                                    </div>
                                    <div class="row">
                                        @foreach($permissions['customer_settings_sub_permissions'] as $key => $label)
                                            <div class="col-md-4">
                                                <div class="permission-item">
                                                    <input class="form-check-input permission-checkbox" type="checkbox" name="{{ $key }}" 
                                                        id="{{ $key }}" value="1" 
                                                        {{ old($key, $adminUser->adminPermissions?->$key ?? false) ? 'checked' : '' }}>
                                                    <label for="{{ $key }}">{{ $label }}</label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                @endif

                                <!-- Reports Tab Permissions -->
                                <div class="permission-group">
                                    <div class="permission-group-title">
                                        <i class="material-icons-outlined" style="font-size: 18px; vertical-align: middle;">assessment</i>
                                        Reports Tab Permissions
                                    </div>
                                    <div class="row">
                                        @foreach($permissions['reports_tab_permissions'] as $key => $label)
                                            <div class="col-md-4">
                                                <div class="permission-item">
                                                    <input class="form-check-input permission-checkbox" type="checkbox" name="{{ $key }}" 
                                                        id="{{ $key }}" value="1" 
                                                        {{ old($key, $adminUser->adminPermissions?->$key ?? false) ? 'checked' : '' }}>
                                                    <label for="{{ $key }}">{{ $label }}</label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <!-- Other Links Permissions -->
                                @if(isset($permissions['other_links_permissions']) && count($permissions['other_links_permissions']) > 0)
                                <div class="permission-group">
                                    <div class="permission-group-title">
                                        <i class="material-icons-outlined" style="font-size: 18px; vertical-align: middle;">dns</i>
                                        Other Links Permissions
                                    </div>
                                    <div class="row">
                                        @foreach($permissions['other_links_permissions'] as $key => $label)
                                            <div class="col-md-4">
                                                <div class="permission-item">
                                                    <input class="form-check-input permission-checkbox" type="checkbox" name="{{ $key }}"
                                                        id="{{ $key }}" value="1"
                                                        {{ old($key, $adminUser->adminPermissions?->$key ?? false) ? 'checked' : '' }}>
                                                    <label for="{{ $key }}">{{ $label }}</label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                @endif

                                <!-- Action Permissions -->
                                <div class="permission-group">
                                    <div class="permission-group-title">
                                        <i class="material-icons-outlined" style="font-size: 18px; vertical-align: middle;">touch_app</i>
                                        Action Permissions
                                    </div>
                                    <div class="row">
                                        @foreach($permissions['action_permissions'] as $key => $label)
                                            <div class="col-md-4">
                                                <div class="permission-item">
                                                    <input class="form-check-input permission-checkbox" type="checkbox" name="{{ $key }}" 
                                                        id="{{ $key }}" value="1" 
                                                        {{ old($key, $adminUser->adminPermissions?->$key ?? false) ? 'checked' : '' }}>
                                                    <label for="{{ $key }}">{{ $label }}</label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <!-- Notification Permissions -->
                                @if(isset($permissions['notification_permissions']) && count($permissions['notification_permissions']) > 0)
                                <div class="permission-group">
                                    <div class="permission-group-title">
                                        <i class="material-icons-outlined" style="font-size: 18px; vertical-align: middle;">notifications</i>
                                        Notification Permissions
                                    </div>
                                    <div class="row">
                                        @foreach($permissions['notification_permissions'] as $key => $label)
                                            <div class="col-md-4">
                                                <div class="permission-item">
                                                    <input class="form-check-input permission-checkbox" type="checkbox" name="{{ $key }}" 
                                                        id="{{ $key }}" value="1" 
                                                        {{ old($key, $adminUser->adminPermissions?->$key ?? false) ? 'checked' : '' }}>
                                                    <label for="{{ $key }}">{{ $label }}</label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="d-flex gap-3 mt-4">
                        <button type="submit" class="btn btn-submit">
                            <i class="material-icons-outlined" style="font-size: 18px;">save</i> Update Admin User
                        </button>
                        <a href="{{ route('admin.admin-users.index') }}" class="btn btn-cancel">
                            <i class="material-icons-outlined" style="font-size: 18px;">close</i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

@include('admin.layouts.footer')
@endsection

@push('js')
<script>
    // Role change handler
    document.getElementById('roleSelect').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const roleSlug = selectedOption.dataset.slug;
        const description = selectedOption.dataset.description;
        
        const roleInfo = document.getElementById('roleInfo');
        const roleInfoText = document.getElementById('roleInfoText');
        const roleDescription = document.getElementById('roleDescription');
        const roleDescText = document.getElementById('roleDescText');
        const permissionsSection = document.getElementById('permissionsSection');
        
        // Remove error styling when role is selected
        if (this.value) {
            this.classList.remove('is-invalid');
            const errorDiv = this.parentNode.querySelector('.invalid-feedback');
            if (errorDiv) {
                errorDiv.remove();
            }
        }
        
        // Show description
        if (description) {
            roleDescription.style.display = 'block';
            roleDescText.textContent = description;
        } else {
            roleDescription.style.display = 'none';
        }
        
        if (roleSlug === 'super_admin') {
            roleInfo.style.display = 'block';
            roleInfoText.textContent = 'Super Admin has full access to all features. All permissions will be automatically granted.';
            permissionsSection.style.opacity = '0.5';
            permissionsSection.style.pointerEvents = 'none';
        } else {
            roleInfo.style.display = 'none';
            permissionsSection.style.opacity = '1';
            permissionsSection.style.pointerEvents = 'auto';
        }
    });

    // Select all permissions
    function selectAllPermissions() {
        document.querySelectorAll('.permission-checkbox').forEach(checkbox => {
            checkbox.checked = true;
        });
    }

    // Deselect all permissions
    function deselectAllPermissions() {
        document.querySelectorAll('.permission-checkbox').forEach(checkbox => {
            checkbox.checked = false;
        });
    }

    // Resend credentials
    function resendCredentials() {
        if (!confirm('This will generate a new password and send it to the user. Continue?')) {
            return;
        }

        fetch('{{ route("admin.admin-users.resend-credentials", $adminUser->id) }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
            } else {
                alert(data.message || 'Failed to resend credentials');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while resending credentials');
        });
    }

    // Trigger on page load
    document.addEventListener('DOMContentLoaded', function() {
        const roleSelect = document.getElementById('roleSelect');
        if (roleSelect.value) {
            roleSelect.dispatchEvent(new Event('change'));
        }
    });

    // Form validation before submit
    document.getElementById('adminUserForm').addEventListener('submit', function(e) {
        const roleSelect = document.getElementById('roleSelect');
        
        // Check if role is selected
        if (!roleSelect.value) {
            e.preventDefault();
            
            // Switch to Role & Access tab
            const roleTab = document.querySelector('a[href="#roles-tab"]');
            if (roleTab) {
                const tab = new bootstrap.Tab(roleTab);
                tab.show();
            }
            
            // Highlight the select field
            roleSelect.classList.add('is-invalid');
            roleSelect.focus();
            
            // Show error message
            let errorDiv = roleSelect.parentNode.querySelector('.invalid-feedback');
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.className = 'invalid-feedback d-block';
                roleSelect.parentNode.appendChild(errorDiv);
            }
            errorDiv.textContent = 'Please select a role';
            
            alert('Please select a role for this admin user.');
            return false;
        }
        
        return true;
    });
</script>
@endpush
