@extends('admin.layouts.app')
@section('title')
    {{ __('Create Admin User') }}
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
    
    .section-title {
        background: linear-gradient(135deg, #293b50, #1f2c3d);
        color: white;
        padding: 12px 20px;
        border-radius: 8px;
        margin-bottom: 20px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .section-title i {
        font-size: 20px;
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

    .btn-next {
        background: linear-gradient(135deg, #28a745, #218838);
        color: white;
        border: none;
        padding: 12px 30px;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .btn-next:hover {
        background: linear-gradient(135deg, #218838, #1e7e34);
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
    }

    .btn-next:disabled {
        background: #ccc;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    .btn-back {
        background: #6c757d;
        color: white;
        border: none;
        padding: 12px 30px;
        border-radius: 8px;
        font-weight: 500;
    }

    .btn-back:hover {
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

    /* Step Indicator Styles */
    .step-indicator {
        display: flex;
        justify-content: center;
        align-items: flex-start;
        margin-bottom: 30px;
        padding: 20px 0 50px 0;
        border-bottom: 1px solid #e9ecef;
    }

    .step {
        display: flex;
        flex-direction: column;
        align-items: center;
        position: relative;
    }

    .step:not(:last-child) {
        margin-right: 100px;
    }

    .step:not(:last-child)::after {
        content: '';
        position: absolute;
        top: 22px;
        left: calc(50% + 30px);
        width: 80px;
        height: 3px;
        background: #e9ecef;
    }

    .step:not(:last-child).completed::after {
        background: #28a745;
    }

    .step-number {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        background: #e9ecef;
        color: #6c757d;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 16px;
        transition: all 0.3s ease;
        margin-bottom: 10px;
    }

    .step.active .step-number {
        background: linear-gradient(135deg, #ea6118, #d1520e);
        color: white;
        box-shadow: 0 4px 12px rgba(234, 97, 24, 0.3);
    }

    .step.completed .step-number {
        background: #28a745;
        color: white;
    }

    .step-label {
        white-space: nowrap;
        font-size: 13px;
        color: #6c757d;
        font-weight: 500;
        text-align: center;
    }

    .step.active .step-label {
        color: #ea6118;
        font-weight: 600;
    }

    .step.completed .step-label {
        color: #28a745;
    }

    /* Tab styles - Hidden tabs, content only */
    .nav-tabs {
        display: none !important;
    }
    
    .tab-content {
        padding-top: 20px;
    }

    .tab-pane {
        display: none;
    }

    .tab-pane.active {
        display: block;
    }

    /* Role Management Styles */
    .role-select-wrapper {
        display: flex;
        gap: 10px;
        align-items: flex-start;
    }
    
    .role-select-wrapper .form-select {
        flex: 1;
    }
    
    .btn-add-role {
        background: #28a745;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 6px;
        white-space: nowrap;
    }
    
    .btn-add-role:hover {
        background: #218838;
        color: white;
    }

    .role-description {
        background: #e9ecef;
        border-radius: 6px;
        padding: 10px 15px;
        margin-top: 10px;
        font-size: 13px;
        color: #666;
    }

    .email-notification-box {
        background: linear-gradient(135deg, #e3f2fd, #bbdefb);
        border: 1px solid #2196f3;
        border-radius: 8px;
        padding: 15px;
        margin-top: 20px;
    }

    .email-notification-box .form-check-label {
        font-weight: 500;
        color: #1565c0;
    }

    /* Modal Styles */
    .modal-header {
        background: linear-gradient(135deg, #293b50, #1f2c3d);
        color: white;
    }

    .modal-header .btn-close {
        filter: invert(1);
    }

    .modal-header .modal-title {
        color: white !important;
    }

    .modal-footer .btn-submit {
        background: linear-gradient(135deg, #ea6118, #d1520e);
        color: white;
        border: none;
    }

    .modal-footer .btn-submit:hover {
        background: linear-gradient(135deg, #d1520e, #b8450c);
        color: white;
    }

    /* Button container */
    .step-buttons {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 1px solid #e9ecef;
    }

    .step-buttons .left-buttons {
        display: flex;
        gap: 10px;
    }

    .step-buttons .right-buttons {
        display: flex;
        gap: 10px;
    }

    /* Validation error styling */
    .validation-error {
        color: #dc3545;
        font-size: 13px;
        margin-top: 5px;
    }

    .is-invalid {
        border-color: #dc3545 !important;
    }
</style>
@endpush

@section('content')
<main class="main-wrapper" id="main-wrapper">
    <div class="main-content">
        <!-- Breadcrumb -->
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3 breadcolor" style="position: relative;">
            <div class="breadcrumb-title pe-3 title-name">Create Admin User</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.admin-users.index') }}">Admin Users</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Create</li>
                    </ol>
                </nav>
            </div>
            <div class="me-2 back-button-container" style="position: absolute; right: 0; top: 50%; transform: translateY(-50%);">
                <button id="backButton" class="btn btn-primary btn-sm">
                    <i class="bx bx-arrow-back"></i> Back
                </button>
            </div>
        </div>

        <!-- Form Card -->
        <div class="card form-card">
            <div class="card-body p-4">
                <!-- Step Indicator -->
                <div class="step-indicator">
                    <div class="step active" data-step="1">
                        <div class="step-number">1</div>
                        <div class="step-label">Basic Information</div>
                    </div>
                    <div class="step" data-step="2">
                        <div class="step-number">2</div>
                        <div class="step-label">Role & Access</div>
                    </div>
                    <div class="step" data-step="3">
                        <div class="step-number">3</div>
                        <div class="step-label">Permissions</div>
                    </div>
                </div>

                <form action="{{ route('admin.admin-users.store') }}" method="POST" id="adminUserForm">
                    @csrf
                    
                    <!-- Hidden field for role permissions usage -->
                    <input type="hidden" name="use_role_permissions" id="useRolePermissionsHidden" value="1">

                    <!-- Tab content -->
                    <div class="tab-content">
                        <!-- Step 1: Basic Information -->
                        <div class="tab-pane active" id="step-1">
                            <div class="section-title">
                                <i class="material-icons-outlined">person</i>
                                Basic Information
                            </div>
                            
                            <div class="row mb-4">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" 
                                        value="{{ old('name') }}" placeholder="Enter full name">
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Email <span class="text-danger">*</span></label>
                                    <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" 
                                        value="{{ old('email') }}" placeholder="Enter email address">
                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" name="username" id="username" class="form-control @error('username') is-invalid @enderror" 
                                        value="{{ old('username') }}" placeholder="Enter username">
                                    @error('username')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Phone</label>
                                    <input type="text" name="phone" id="phone" class="form-control @error('phone') is-invalid @enderror" 
                                        value="{{ old('phone') }}" placeholder="Enter phone number">
                                    @error('phone')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Password <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="password" name="password" id="password" class="form-control @error('password') is-invalid @enderror" placeholder="Enter password">
                                        <button class="btn btn-outline-secondary" type="button" onclick="generatePassword()">
                                            <i class="material-icons-outlined" style="font-size: 18px;">autorenew</i>
                                        </button>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassword('password')">
                                            <i class="material-icons-outlined" style="font-size: 18px;" id="password-icon">visibility</i>
                                        </button>
                                    </div>
                                    @error('password')
                                        <div class="invalid-feedback d-block">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Confirm Password <span class="text-danger">*</span></label>
                                    <input type="password" name="password_confirmation" id="password_confirmation" class="form-control" placeholder="Confirm password">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Status</label>
                                    <div class="form-check form-switch mt-2">
                                        <input class="form-check-input" type="checkbox" name="is_active" id="isActive" checked style="width: 50px; height: 25px;">
                                        <label class="form-check-label ms-2" for="isActive">Active</label>
                                    </div>
                                </div>
                            </div>

                            <!-- Email Notification -->
                            <div class="email-notification-box">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="send_email" id="sendEmail" checked>
                                    <label class="form-check-label" for="sendEmail">
                                        <i class="material-icons-outlined" style="font-size: 18px; vertical-align: middle;">email</i>
                                        Send login credentials to user's email
                                    </label>
                                </div>
                                <small class="text-muted d-block mt-2">
                                    The user will receive an email with their username, password, and login URL.
                                </small>
                            </div>

                            <!-- Step 1 Buttons -->
                            <div class="step-buttons">
                                <div class="left-buttons">
                                    <a href="{{ route('admin.admin-users.index') }}" class="btn btn-cancel">
                                        <i class="material-icons-outlined" style="font-size: 18px;">close</i> Cancel
                                    </a>
                                </div>
                                <div class="right-buttons">
                                    <button type="button" class="btn btn-next" onclick="goToStep(2)">
                                        Next <i class="material-icons-outlined" style="font-size: 18px;">arrow_forward</i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Role & Access -->
                        <div class="tab-pane" id="step-2">
                            <div class="section-title">
                                <i class="material-icons-outlined">badge</i>
                                Role & Access
                            </div>

                            <div class="row">
                                <div class="col-md-8">
                                    <label class="form-label">Select Role <span class="text-danger">*</span></label>
                                    <div class="role-select-wrapper">
                                        <select name="role_id" id="roleSelect" class="form-select @error('role_id') is-invalid @enderror">
                                            <option value="">Select Role</option>
                                            @foreach($roles as $role)
                                                <option value="{{ $role->id }}" 
                                                    data-slug="{{ $role->slug }}"
                                                    data-description="{{ $role->description }}"
                                                    data-permissions="{{ json_encode($role->permissions) }}"
                                                    data-is-system="{{ $role->is_system ? '1' : '0' }}"
                                                    {{ old('role_id') == $role->id ? 'selected' : '' }}>
                                                    {{ $role->name }}
                                                    @if($role->is_system) (System) @endif
                                                </option>
                                            @endforeach
                                        </select>
                                        <button type="button" class="btn btn-add-role" data-bs-toggle="modal" data-bs-target="#createRoleModal">
                                            <i class="material-icons-outlined" style="font-size: 18px;">add</i> New Role
                                        </button>
                                    </div>
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
                                    <input class="form-check-input" type="checkbox" id="useRolePermissions" checked>
                                    <label class="form-check-label" for="useRolePermissions">
                                        <strong>Use role's default permissions</strong>
                                    </label>
                                </div>
                                <small class="text-muted">
                                    Uncheck to customize permissions in the next step.
                                </small>
                            </div>

                            <!-- Role Permissions Preview -->
                            <div class="mt-4" id="rolePermissionsPreview" style="display: none;">
                                <h6 class="mb-3">
                                    <i class="material-icons-outlined" style="font-size: 18px;">preview</i>
                                    Role Permissions Preview
                                </h6>
                                <div id="permissionsPreviewList" class="row">
                                    <!-- Permissions will be loaded here -->
                                </div>
                            </div>

                            <!-- Step 2 Buttons -->
                            <div class="step-buttons">
                                <div class="left-buttons">
                                    <button type="button" class="btn btn-back" onclick="goToStep(1)">
                                        <i class="material-icons-outlined" style="font-size: 18px;">arrow_back</i> Back
                                    </button>
                                </div>
                                <div class="right-buttons">
                                    <button type="button" class="btn btn-next" id="step2NextBtn" onclick="goToStep(3)" disabled>
                                        Next <i class="material-icons-outlined" style="font-size: 18px;">arrow_forward</i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Custom Permissions -->
                        <div class="tab-pane" id="step-3">
                            <div class="section-title">
                                <i class="material-icons-outlined">security</i>
                                Custom Permissions
                            </div>

                            <div class="alert alert-info" id="customPermissionsAlert">
                                <i class="material-icons-outlined" style="font-size: 18px;">info</i>
                                <strong>Note:</strong> Custom permissions will only be applied if "Use role's default permissions" is unchecked.
                            </div>

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
                                                        id="{{ $key }}" value="1" {{ old($key) ? 'checked' : '' }}>
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
                                                        id="{{ $key }}" value="1" {{ old($key) ? 'checked' : '' }}>
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
                                                        id="{{ $key }}" value="1" {{ old($key) ? 'checked' : '' }}>
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
                                                        id="{{ $key }}" value="1" {{ old($key) ? 'checked' : '' }}>
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
                                                        id="{{ $key }}" value="1" {{ old($key) ? 'checked' : '' }}>
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
                                                    {{-- Auto-on: Livebeat is granted to every admin by default (still un-tickable). --}}
                                                    <input class="form-check-input permission-checkbox" type="checkbox" name="{{ $key }}"
                                                        id="{{ $key }}" value="1" {{ old($key, '1') ? 'checked' : '' }}>
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
                                                        id="{{ $key }}" value="1" {{ old($key) ? 'checked' : '' }}>
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
                                                        id="{{ $key }}" value="1" {{ old($key) ? 'checked' : '' }}>
                                                    <label for="{{ $key }}">{{ $label }}</label>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                @endif
                            </div>

                            <!-- Step 3 Buttons -->
                            <div class="step-buttons">
                                <div class="left-buttons">
                                    <button type="button" class="btn btn-back" onclick="goToStep(2)">
                                        <i class="material-icons-outlined" style="font-size: 18px;">arrow_back</i> Back
                                    </button>
                                </div>
                                <div class="right-buttons">
                                    <button type="submit" class="btn btn-submit">
                                        <i class="material-icons-outlined" style="font-size: 18px;">person_add</i> Create Admin User
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<!-- Create Role Modal -->
<div class="modal fade" id="createRoleModal" tabindex="-1" aria-labelledby="createRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-white" id="createRoleModalLabel">
                    <i class="material-icons-outlined" style="font-size: 24px;">add_circle</i>
                    Create New Role
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="createRoleForm">
                    <div class="mb-3">
                        <label class="form-label">Role Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="newRoleName" required placeholder="e.g., Support Agent">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" id="newRoleDescription" rows="3" placeholder="Brief description of this role"></textarea>
                    </div>
                    <div class="alert alert-info mb-0">
                        <i class="material-icons-outlined" style="font-size: 18px; vertical-align: middle;">info</i>
                        <small>After creating the role, you can assign permissions in the <strong>Permissions</strong> step.</small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-submit" onclick="createRole()">
                    <i class="material-icons-outlined" style="font-size: 18px;">save</i> Create Role
                </button>
            </div>
        </div>
    </div>
</div>

@include('admin.layouts.footer')
@endsection

@push('js')
<script>
    // Current step tracker
    let currentStep = 1;
    
    // All permissions for reference
    const allPermissions = @json(array_merge(...array_values($permissions)));

    // Go to specific step
    function goToStep(step) {
        // Validate current step before moving forward
        if (step > currentStep) {
            if (!validateStep(currentStep)) {
                return;
            }
        }

        // Hide current step
        document.getElementById('step-' + currentStep).classList.remove('active');
        
        // Show target step
        document.getElementById('step-' + step).classList.add('active');
        
        // Update step indicators
        updateStepIndicators(step);
        
        // Update current step
        currentStep = step;

        // Scroll to top of form
        document.querySelector('.form-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    // Update step indicators
    function updateStepIndicators(activeStep) {
        document.querySelectorAll('.step').forEach(step => {
            const stepNum = parseInt(step.dataset.step);
            step.classList.remove('active', 'completed');
            
            if (stepNum === activeStep) {
                step.classList.add('active');
            } else if (stepNum < activeStep) {
                step.classList.add('completed');
            }
        });
    }

    // Validate step
    function validateStep(step) {
        let isValid = true;
        
        // Clear previous errors
        document.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
        document.querySelectorAll('.validation-error').forEach(el => el.remove());

        if (step === 1) {
            // Validate Basic Information
            const name = document.getElementById('name');
            const email = document.getElementById('email');
            const username = document.getElementById('username');
            const password = document.getElementById('password');
            const passwordConfirmation = document.getElementById('password_confirmation');

            if (!name.value.trim()) {
                showError(name, 'Full name is required');
                isValid = false;
            }

            if (!email.value.trim()) {
                showError(email, 'Email is required');
                isValid = false;
            } else if (!isValidEmail(email.value)) {
                showError(email, 'Please enter a valid email address');
                isValid = false;
            }

            if (!username.value.trim()) {
                showError(username, 'Username is required');
                isValid = false;
            }

            if (!password.value) {
                showError(password, 'Password is required');
                isValid = false;
            } else if (password.value.length < 8) {
                showError(password, 'Password must be at least 8 characters');
                isValid = false;
            }

            if (password.value !== passwordConfirmation.value) {
                showError(passwordConfirmation, 'Passwords do not match');
                isValid = false;
            }
        }

        if (step === 2) {
            // Validate Role Selection
            const roleSelect = document.getElementById('roleSelect');
            if (!roleSelect.value) {
                showError(roleSelect, 'Please select a role');
                isValid = false;
            }
        }

        return isValid;
    }

    // Show error message
    function showError(element, message) {
        element.classList.add('is-invalid');
        const errorDiv = document.createElement('div');
        errorDiv.className = 'validation-error';
        errorDiv.textContent = message;
        element.parentNode.appendChild(errorDiv);
    }

    // Validate email format
    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }

    // Role change handler
    document.getElementById('roleSelect').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        const roleSlug = selectedOption.dataset.slug;
        const description = selectedOption.dataset.description;
        const permissions = selectedOption.dataset.permissions ? JSON.parse(selectedOption.dataset.permissions) : [];
        const isSystem = selectedOption.dataset.isSystem === '1';
        
        const roleInfo = document.getElementById('roleInfo');
        const roleInfoText = document.getElementById('roleInfoText');
        const roleDescription = document.getElementById('roleDescription');
        const roleDescText = document.getElementById('roleDescText');
        const permissionsSection = document.getElementById('permissionsSection');
        const permissionsPreview = document.getElementById('rolePermissionsPreview');
        const useRolePermissions = document.getElementById('useRolePermissions');
        const step2NextBtn = document.getElementById('step2NextBtn');
        
        // Enable/disable next button based on selection
        step2NextBtn.disabled = !this.value;
        
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
            useRolePermissions.checked = true;
            useRolePermissions.disabled = true;
            document.getElementById('useRolePermissionsHidden').value = '1';
            selectAllPermissions();
        } else if (roleSlug) {
            roleInfo.style.display = 'none';
            permissionsSection.style.opacity = '1';
            permissionsSection.style.pointerEvents = 'auto';
            useRolePermissions.disabled = false;
            
            // Show permissions preview
            showPermissionsPreview(permissions);
            
            // If using role permissions, update checkboxes
            if (useRolePermissions.checked) {
                setPermissionsFromRole(permissions);
            }
        } else {
            roleInfo.style.display = 'none';
            roleDescription.style.display = 'none';
            permissionsPreview.style.display = 'none';
        }
    });

    // Show permissions preview
    function showPermissionsPreview(permissions) {
        const preview = document.getElementById('rolePermissionsPreview');
        const list = document.getElementById('permissionsPreviewList');
        
        if (permissions.length === 0) {
            preview.style.display = 'none';
            return;
        }
        
        preview.style.display = 'block';
        let html = '';
        
        permissions.forEach(perm => {
            const label = allPermissions[perm] || perm.replace(/_/g, ' ').replace(/can /i, '');
            html += `<div class="col-md-4 mb-2">
                <span class="badge bg-success">
                    <i class="material-icons-outlined" style="font-size: 14px;">check</i>
                    ${label}
                </span>
            </div>`;
        });
        
        list.innerHTML = html;
    }

    // Set permissions from role
    function setPermissionsFromRole(permissions) {
        // First deselect all
        deselectAllPermissions();
        
        // Then select the role's permissions
        permissions.forEach(perm => {
            const checkbox = document.getElementById(perm);
            if (checkbox) {
                checkbox.checked = true;
            }
        });
    }

    // Use role permissions checkbox handler
    document.getElementById('useRolePermissions').addEventListener('change', function() {
        const permissionsSection = document.getElementById('permissionsSection');
        const customAlert = document.getElementById('customPermissionsAlert');
        
        // Update hidden field
        document.getElementById('useRolePermissionsHidden').value = this.checked ? '1' : '0';
        
        if (this.checked) {
            permissionsSection.style.opacity = '0.7';
            customAlert.className = 'alert alert-info';
            customAlert.innerHTML = '<i class="material-icons-outlined" style="font-size: 18px;">info</i> <strong>Note:</strong> Custom permissions will only be applied if "Use role\'s default permissions" is unchecked.';
            
            // Apply role permissions
            const roleSelect = document.getElementById('roleSelect');
            const selectedOption = roleSelect.options[roleSelect.selectedIndex];
            const permissions = selectedOption.dataset.permissions ? JSON.parse(selectedOption.dataset.permissions) : [];
            setPermissionsFromRole(permissions);
        } else {
            permissionsSection.style.opacity = '1';
            customAlert.className = 'alert alert-warning';
            customAlert.innerHTML = '<i class="material-icons-outlined" style="font-size: 18px;">warning</i> <strong>Custom Mode:</strong> The permissions you select below will be used instead of the role\'s default permissions.';
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

    // Generate random password
    function generatePassword() {
        const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
        let password = '';
        for (let i = 0; i < 12; i++) {
            password += chars.charAt(Math.floor(Math.random() * chars.length));
        }
        document.getElementById('password').value = password;
        document.getElementById('password_confirmation').value = password;
        
        // Show password
        document.getElementById('password').type = 'text';
        document.getElementById('password-icon').textContent = 'visibility_off';
    }

    // Toggle password visibility
    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        const icon = document.getElementById(fieldId + '-icon');
        
        if (field.type === 'password') {
            field.type = 'text';
            icon.textContent = 'visibility_off';
        } else {
            field.type = 'password';
            icon.textContent = 'visibility';
        }
    }

    // Create new role
    function createRole() {
        const name = document.getElementById('newRoleName').value;
        const description = document.getElementById('newRoleDescription').value;
        
        if (!name) {
            alert('Please enter a role name');
            return;
        }
        
        // Send AJAX request to create role
        fetch('{{ route("admin.admin-users.store-role") }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                name: name,
                description: description,
                permissions: []
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Add new role to select
                const roleSelect = document.getElementById('roleSelect');
                const option = new Option(data.role.name, data.role.id);
                option.dataset.slug = data.role.slug;
                option.dataset.description = data.role.description;
                option.dataset.permissions = JSON.stringify(data.role.permissions || []);
                option.dataset.isSystem = '0';
                roleSelect.add(option);
                roleSelect.value = data.role.id;
                roleSelect.dispatchEvent(new Event('change'));
                
                // Close modal
                bootstrap.Modal.getInstance(document.getElementById('createRoleModal')).hide();
                
                // Reset form
                document.getElementById('createRoleForm').reset();
                
                // Uncheck 'Use role permissions' so user can set custom permissions
                document.getElementById('useRolePermissions').checked = false;
                document.getElementById('useRolePermissions').dispatchEvent(new Event('change'));
                
                // Show success message
                alert('Role created successfully! Please set permissions in Step 3.');
            } else {
                alert(data.message || 'Failed to create role');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while creating the role');
        });
    }

    // Form submission validation
    document.getElementById('adminUserForm').addEventListener('submit', function(e) {
        // Validate all steps
        let allValid = true;
        
        for (let i = 1; i <= 3; i++) {
            if (!validateStep(i)) {
                allValid = false;
                goToStep(i);
                break;
            }
        }
        
        if (!allValid) {
            e.preventDefault();
        }
    });

    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        const roleSelect = document.getElementById('roleSelect');
        if (roleSelect.value) {
            roleSelect.dispatchEvent(new Event('change'));
        }
    });
</script>
@endpush
