@extends('admin.layouts.app')
@section('title')
    {{ __('Admin Users Management') }}
@endsection

@push('style')
<style>
    .breadcrumb-item+.breadcrumb-item::before {
        content: " / " !important;
        color: #6c757d !important;
    }
    
    .table-controls {
        background: #f8f9fa;
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 15px;
    }
    
    .search-form {
        display: flex;
        gap: 10px;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .search-form .form-control,
    .search-form .form-select {
        height: 38px;
        border-radius: 8px;
    }
    
    .search-form .form-control {
        width: 250px;
    }
    
    .search-form .form-select {
        width: 150px;
    }
    
    .btn-add-user {
        background: linear-gradient(135deg, #ea6118, #d1520e);
        color: white;
        border: none;
        padding: 8px 20px;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
    }
    
    .btn-add-user:hover {
        background: linear-gradient(135deg, #d1520e, #b8450c);
        color: white;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(234, 97, 24, 0.3);
    }
    
    .role-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .role-super_admin {
        background: linear-gradient(135deg, #dc3545, #c82333);
        color: white;
    }
    
    .role-admin {
        background: linear-gradient(135deg, #6f42c1, #5a32a3);
        color: white;
    }
    
    .role-manager {
        background: linear-gradient(135deg, #17a2b8, #138496);
        color: white;
    }
    
    .role-staff {
        background: linear-gradient(135deg, #28a745, #218838);
        color: white;
    }
    
    .status-badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 500;
    }
    
    .status-active {
        background: #d4edda;
        color: #155724;
    }
    
    .status-inactive {
        background: #f8d7da;
        color: #721c24;
    }
    
    .table thead th {
        background: #293b50;
        color: white;
        font-weight: 500;
        padding: 12px 15px;
        white-space: nowrap;
    }
    
    .table tbody td {
        padding: 12px 15px;
        vertical-align: middle;
    }
    
    .table tbody tr:hover {
        background-color: rgba(234, 97, 24, 0.05);
    }
    
    .action-btns {
        display: flex;
        gap: 5px;
    }
    
    .action-btns .btn {
        padding: 5px 10px;
        font-size: 14px;
    }
    
    .pagination-info {
        background: #e9ecef;
        padding: 8px 15px;
        border-radius: 8px;
        font-size: 14px;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .pagination .page-link {
        color: #293b50;
        border-radius: 5px;
        margin: 0 2px;
    }
    
    .pagination .page-item.active .page-link {
        background: #ea6118;
        border-color: #ea6118;
    }
    
    .total-count {
        font-weight: 600;
        color: #ea6118;
    }
</style>
@endpush

@section('content')
<main class="main-wrapper" id="main-wrapper">
    <div class="main-content">
        <!-- Breadcrumb -->
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3 breadcolor" style="position: relative;">
            <div class="breadcrumb-title pe-3 title-name">Admin Users</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Admin Users</li>
                    </ol>
                </nav>
            </div>
            <div class="me-2 back-button-container" style="position: absolute; right: 0; top: 50%; transform: translateY(-50%);">
                <button id="backButton" class="btn btn-primary btn-sm">
                    <i class="bx bx-arrow-back"></i> Back
                </button>
            </div>
        </div>

        <!-- Flash Messages -->
        @if (session('success'))
            <div id="flash-message" class="alert alert-success alert-dismissible fade show">
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif
        @if (session('error'))
            <div id="flash-error-message" class="alert alert-danger alert-dismissible fade show">
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <!-- Main Card -->
        <div class="card">
            <div class="card-body">
                <!-- Table Controls -->
                <div class="table-controls">
                    <form action="{{ route('admin.admin-users.index') }}" method="GET" class="search-form">
                        <input type="text" name="search" class="form-control" placeholder="Search by name, email, username..." value="{{ $search }}">
                        <select name="role" class="form-select">
                            <option value="">All Roles</option>
                            <option value="super_admin" {{ $role == 'super_admin' ? 'selected' : '' }}>Super Admin</option>
                            <option value="admin" {{ $role == 'admin' ? 'selected' : '' }}>Admin</option>
                            <option value="manager" {{ $role == 'manager' ? 'selected' : '' }}>Manager</option>
                            <option value="staff" {{ $role == 'staff' ? 'selected' : '' }}>Staff</option>
                        </select>
                        <button type="submit" class="btn btn-secondary">
                            <i class="material-icons-outlined" style="font-size: 16px;">search</i> Search
                        </button>
                        @if($search || $role)
                            <a href="{{ route('admin.admin-users.index') }}" class="btn btn-outline-secondary">Clear</a>
                        @endif
                    </form>
                    
                    <div class="d-flex align-items-center gap-3">
                        <div class="pagination-info">
                            <i class="material-icons-outlined" style="font-size: 18px;">people</i>
                            Total: <span class="total-count">{{ $adminUsers->total() }}</span> users
                        </div>
                        <a href="{{ route('admin.admin-users.create') }}" class="btn btn-add-user">
                            <i class="material-icons-outlined" style="font-size: 18px;">person_add</i> Add New Admin
                        </a>
                    </div>
                </div>

                <!-- Table -->
                <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th style="width: 60px;">#</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Username</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last Login</th>
                                <th style="width: 150px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($adminUsers as $index => $user)
                                <tr>
                                    <td>{{ $adminUsers->firstItem() + $index }}</td>
                                    <td>{{ urldecode($user->contactname ?? '') }}</td>
                                    <td>{{ $user->contactemail }}</td>
                                    <td>{{ $user->uname }}</td>
                                    <td>
                                        <span class="role-badge role-{{ $user->role }}">
                                            {{ $user->getRoleDisplayName() }}
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge {{ !$user->bit_disabled ? 'status-active' : 'status-inactive' }}">
                                            {{ !$user->bit_disabled ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($user->last_login_at)
                                            {{ \Carbon\Carbon::parse($user->last_login_at)->format('d M Y H:i') }}
                                        @else
                                            <span class="text-muted">Never</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="action-btns">
                                            <a href="{{ route('admin.admin-users.edit', $user->id) }}" class="btn btn-sm btn-primary" title="Edit">
                                                <i class="material-icons-outlined" style="font-size: 16px;">edit</i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-{{ !$user->bit_disabled ? 'warning' : 'success' }}" 
                                                onclick="toggleStatus({{ $user->id }})" title="{{ !$user->bit_disabled ? 'Deactivate' : 'Activate' }}">
                                                <i class="material-icons-outlined" style="font-size: 16px;">{{ !$user->bit_disabled ? 'block' : 'check_circle' }}</i>
                                            </button>
                                            <form action="{{ route('admin.admin-users.destroy', $user->id) }}" method="POST" class="d-inline" 
                                                onsubmit="return confirm('Are you sure you want to delete this admin user?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger" title="Delete">
                                                    <i class="material-icons-outlined" style="font-size: 16px;">delete</i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <i class="material-icons-outlined" style="font-size: 48px; color: #ccc;">person_off</i>
                                        <p class="mt-2 text-muted">No admin users found.</p>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                @if($adminUsers->hasPages())
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <div class="text-muted">
                            Showing {{ $adminUsers->firstItem() }} to {{ $adminUsers->lastItem() }} of {{ $adminUsers->total() }} entries
                        </div>
                        {{ $adminUsers->appends(request()->query())->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</main>

@include('admin.layouts.footer')
@endsection

@push('js')
<script>
    // Auto-hide flash messages
    setTimeout(function() {
        let flashMessage = document.getElementById('flash-message');
        if (flashMessage) flashMessage.style.display = 'none';
    }, 3000);

    setTimeout(function() {
        let flashMessage = document.getElementById('flash-error-message');
        if (flashMessage) flashMessage.style.display = 'none';
    }, 3000);

    // Toggle status function
    function toggleStatus(userId) {
        if (!confirm('Are you sure you want to change the status of this user?')) {
            return;
        }

        fetch(`{{ url('admin/admin-users') }}/${userId}/toggle-status`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'An error occurred.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred while updating status.');
        });
    }
</script>
@endpush
