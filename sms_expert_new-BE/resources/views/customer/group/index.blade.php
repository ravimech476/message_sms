@extends('layouts.app')
@section('title', 'Groups - SMS Expert')

  @push('style')
<style>
.back-btn {
    display: flex;
    align-items: center;
    font-size: 0.85rem;
    padding: 6px 12px;
    border-radius: 6px;
    transition: all 0.2s ease;
}
    .groups-container {
        background: #f8fafc;
        min-height: 100vh;
        margin: -2rem;
        padding: 2rem;
    }

    .groups-card {
        background: white;
        border-radius: 15px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        overflow: hidden;
        position: relative;
    }

    .groups-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, #ea6118, #293b50);
    }

    .groups-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }

    .section-card {
        background: white;
        border-radius: 15px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        margin-bottom: 2rem;
        overflow: hidden;
    }

    .section-header {
        background: linear-gradient(135deg, #f8fafc, #e2e8f0);
        padding: 1.5rem;
        border-bottom: 2px solid #e2e8f0;
    }

    .section-title {
        color: #293b50;
        font-weight: 700;
        font-size: 1.2rem;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .section-content {
        padding: 2rem;
    }

    .breadcrumb-container {
        background: white;
        border-radius: 15px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        padding: 1rem 1.5rem;
        margin-bottom: 2rem;
    }

    .breadcrumb {
        margin: 0;
        background: transparent;
    }

    .breadcrumb-item a {
        color: #ea6118;
        text-decoration: none;
    }

    .breadcrumb-item.active {
        color: #64748b;
    }

    .breadcrumb-title {
        color: #293b50;
        font-weight: 700;
        font-size: 1.2rem;
    }

    .back-button {
        background: linear-gradient(135deg, #64748b, #475569);
        border: none;
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .back-button:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(100, 116, 139, 0.3);
        color: white;
    }

    .alert {
        border-radius: 10px;
        border: none;
        padding: 1rem 1.5rem;
        margin-bottom: 2rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .alert-success {
        background: linear-gradient(135deg, #16a34a, #15803d);
        color: white;
    }

    .alert-danger {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
        color: white;
    }

    .data-table {
        background: white;
        border-radius: 15px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }

    .table {
        margin: 0;
        border-collapse: separate;
        border-spacing: 0;
    }

    .table thead th {
        background: linear-gradient(135deg, #f8fafc, #e2e8f0);
        color: #293b50;
        font-weight: 700;
        padding: 1.5rem 1rem;
        border: none;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .table tbody td {
        padding: 1.25rem 1rem;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
        color: #475569;
    }

    .table tbody tr:hover {
        background: #f8fafc;
        transform: translateX(2px);
        transition: all 0.2s ease;
    }

    .table tbody tr:last-child td {
        border-bottom: none;
    }

    .group-name {
        color: #293b50;
        font-weight: 600;
        font-size: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .member-count {
        background: linear-gradient(135deg, #0891b2, #0e7490);
        color: white;
        padding: 0.35rem 0.75rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        min-width: 60px;
        justify-content: center;
    }

    .zero-members {
        background: #f1f5f9;
        color: #64748b;
        border: 1px solid #e2e8f0;
    }

    .action-button {
        border: none;
        border-radius: 8px;
        padding: 0.5rem 1rem;
        font-weight: 600;
        font-size: 0.85rem;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        margin: 0.25rem;
    }

    .btn-add {
        background: linear-gradient(135deg, #16a34a, #15803d);
        color: white;
    }

    .btn-add:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(22, 163, 74, 0.4);
        color: white;
    }

    .btn-edit {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
    }

    .btn-edit:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(245, 158, 11, 0.4);
        color: white;
    }

    .btn-delete {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
        color: white;
    }

    .btn-delete:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4);
        color: white;
    }

    .stats-summary {
        background: linear-gradient(135deg, rgba(234, 97, 24, 0.1), rgba(41, 59, 80, 0.1));
        border: 2px solid #ea6118;
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 2rem;
        text-align: center;
    }

    .stat-item {
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .stats-number {
        font-size: 2rem;
        font-weight: 700;
        color: #ea6118;
        margin-bottom: 0.5rem;
    }

    .stats-label {
        color: #64748b;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-size: 0.9rem;
    }

    .create-button {
        background: linear-gradient(135deg, #ea6118, #293b50);
        border: none;
        color: white;
        padding: 1rem 2rem;
        border-radius: 12px;
        font-weight: 600;
        font-size: 1rem;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 2rem;
    }

    .create-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(234, 97, 24, 0.4);
        color: white;
    }

    .no-data {
        text-align: center;
        padding: 4rem 2rem;
        color: #64748b;
    }

    .no-data i {
        font-size: 4rem;
        color: #cbd5e1;
        margin-bottom: 1rem;
    }

    .icon-primary {
        color: #ea6118;
        font-size: 1.2rem;
    }

    .info-card {
        background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
        border: 2px solid #0891b2;
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }

    .info-card h5 {
        color: #0891b2;
        font-weight: 700;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .info-card p {
        color: #64748b;
        margin-bottom: 0;
        line-height: 1.6;
    }

    .dt-buttons {
        margin-bottom: 1rem;
    }

    .dt-button {
        background: linear-gradient(135deg, #ea6118, #293b50) !important;
        border: none !important;
        color: white !important;
        border-radius: 8px !important;
        padding: 0.5rem 1rem !important;
        font-weight: 600 !important;
        margin-right: 0.5rem !important;
    }

    .dt-button:hover {
        transform: translateY(-1px) !important;
        box-shadow: 0 4px 12px rgba(234, 97, 24, 0.4) !important;
    }

    .groups-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .group-card {
        background: white;
        border-radius: 15px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        padding: 1.5rem;
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .group-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 3px;
        background: linear-gradient(90deg, #ea6118, #293b50);
    }

    .group-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    }

    .group-card-header {
        display: flex;
        justify-content: between;
        align-items: center;
        margin-bottom: 1rem;
    }

    .group-card-title {
        color: #293b50;
        font-weight: 700;
        font-size: 1.1rem;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .group-card-members {
        margin-bottom: 1.5rem;
    }

    .group-card-actions {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .compact-action-button {
        padding: 0.5rem 0.75rem;
        font-size: 0.8rem;
        border-radius: 6px;
        flex: 1;
        min-width: 80px;
        justify-content: center;
    }
</style>
@endpush

@section('content')
<div class="groups-container">
    <!-- Breadcrumb -->
    <div class="breadcrumb-container d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <div class="breadcrumb-title pe-3">
                <i class="material-icons-outlined icon-primary">groups</i>
                Groups
            </div>&nbsp;
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    {{-- <li class="breadcrumb-item">
                        <i class="material-icons-outlined">home</i>
                    </li> --}}
                    <li class="breadcrumb-item">
                        <a href="{{ route('dashboard') }}">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active">Groups</li>
                </ol>
            </nav>
        </div>
<button id="backButton" class="btn btn-outline-secondary back-btn">
            <i class="material-icons-outlined me-1">arrow_back</i> Back
        </button>
    </div>

    <!-- Flash Messages -->
    @if (session('success'))
        <div class="alert alert-success" id="flash-message">
            <i class="material-icons-outlined">check_circle</i>
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger" id="flash-error-message">
            <i class="material-icons-outlined">error</i>
            {{ session('error') }}
        </div>
    @endif

    <!-- Information Card -->
    <div class="info-card">
        <h5>
            <i class="material-icons-outlined">info</i>
            Manage Contact Groups
        </h5>
        <p>
            Groups help you organize your contacts for easy bulk SMS sending. Create groups for different categories like customers, friends, or business contacts. You can add multiple numbers to each group and send messages to all members at once.
        </p>
    </div>

    <!-- Create Button -->
    <a href="{{ route('groups.create') }}" class="create-button">
        <i class="material-icons-outlined">add</i>
        Create New Group
    </a>

    <!-- Main Content -->
    <div class="groups-card">
        @if(isset($groups) && count($groups) > 0)
            <!-- Statistics Summary -->
            <div class="stats-summary">
                <div class="stat-item">
                    <div class="stats-number">{{ count($groups) }}</div>
                    <div class="stats-label">Total Groups</div>
                </div>
                <div class="stat-item">
                    <div class="stats-number">{{ array_sum($groupCounts ?? []) }}</div>
                    <div class="stats-label">Total Members</div>
                </div>
                <div class="stat-item">
                    <div class="stats-number">{{ count(array_filter($groupCounts ?? [], fn($count) => $count > 0)) }}</div>
                    <div class="stats-label">Active Groups</div>
                </div>
            </div>

            <!-- Groups Grid View -->
            <div class="groups-grid">
                @foreach ($groups as $group)
                    @php
                        $memberCount = $groupCounts[$group->id] ?? 0;
                    @endphp
                    <div class="group-card">
                        <div class="group-card-header">
                            <h6 class="group-card-title">
                                <i class="material-icons-outlined">group</i>
                                {{ $group->name }}
                            </h6>
                        </div>
                        
                        <div class="group-card-members">
                            <span class="member-count {{ $memberCount == 0 ? 'zero-members' : '' }}">
                                <i class="material-icons-outlined">people</i>
                                {{ $memberCount }} {{ $memberCount == 1 ? 'member' : 'members' }}
                            </span>
                        </div>
                        
                        <div class="group-card-actions">
                            <a href="{{ route('group.single.mobile', $group->id) }}" 
                               class="action-button btn-add compact-action-button">
                                <i class="material-icons-outlined">person_add</i>
                                Add
                            </a>
                            <a href="{{ route('groups.edit', $group->id) }}" 
                               class="action-button btn-edit compact-action-button">
                                <i class="material-icons-outlined">edit</i>
                                Edit
                            </a>
                            <form action="{{ route('groups.destroy', $group->id) }}" method="POST" style="display: inline; flex: 1;">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="action-button btn-delete compact-action-button"
                                        style="width: 100%;"
                                        onclick="return confirm('Are you sure you want to delete this group? This action cannot be undone.');">
                                    <i class="material-icons-outlined">delete</i>
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Table View (Alternative) -->
            <div class="section-card" style="display: none;" id="table-view">
                <div class="section-header">
                    <h5 class="section-title">
                        <i class="material-icons-outlined">table_view</i>
                        Groups Table View
                    </h5>
                </div>
                <div class="data-table">
                    <table id="group_all_view" class="table">
                        <thead>
                            <tr class="text-center">
                                <th>
                                    <i class="material-icons-outlined">group</i>
                                    Group Name
                                </th>
                                <th>
                                    <i class="material-icons-outlined">people</i>
                                    Members
                                </th>
                                <th>
                                    <i class="material-icons-outlined">person_add</i>
                                    Add Members
                                </th>
                                <th>
                                    <i class="material-icons-outlined">edit</i>
                                    Edit
                                </th>
                                <th>
                                    <i class="material-icons-outlined">delete</i>
                                    Delete
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($groups as $group)
                                <tr class="text-center">
                                    <td>
                                        <div class="group-name">
                                            <i class="material-icons-outlined">group</i>
                                            {{ $group->name }}
                                        </div>
                                    </td>
                                    <td>
                                        <span class="member-count {{ ($groupCounts[$group->id] ?? 0) == 0 ? 'zero-members' : '' }}">
                                            <i class="material-icons-outlined">people</i>
                                            {{ $groupCounts[$group->id] ?? 0 }}
                                        </span>
                                    </td>
                                    <td>
                                        <a href="{{ route('group.single.mobile', $group->id) }}" class="action-button btn-add">
                                            <i class="material-icons-outlined">person_add</i>
                                            Add Members
                                        </a>
                                    </td>
                                    <td>
                                        <a href="{{ route('groups.edit', $group->id) }}" class="action-button btn-edit">
                                            <i class="material-icons-outlined">edit</i>
                                            Edit Group
                                        </a>
                                    </td>
                                    <td>
                                        <form action="{{ route('groups.destroy', $group->id) }}" method="POST" style="display: inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="action-button btn-delete"
                                                    onclick="return confirm('Are you sure you want to delete this group?');">
                                                <i class="material-icons-outlined">delete</i>
                                                Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- View Toggle -->
            <div class="text-center mt-3">
                <button onclick="toggleView()" class="btn btn-outline-secondary" id="view-toggle">
                    <i class="material-icons-outlined">table_view</i>
                    Switch to Table View
                </button>
            </div>
        @else
            <!-- No Data State -->
            <div class="section-card">
                <div class="no-data">
                    <i class="material-icons-outlined">group_off</i>
                    <h4>No Groups Yet</h4>
                    <p>You haven't created any contact groups yet. Groups help you organize contacts and send bulk SMS messages easily.</p>
                    <div class="mt-3">
                        <a href="{{ route('groups.create') }}" class="create-button" style="display: inline-flex; width: auto;">
                            <i class="material-icons-outlined">add</i>
                            Create Your First Group
                        </a>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection

@push('js')
<script src="{{ asset('assets/plugins/datatable/js/jquery.dataTables.min.js') }}"></script>
<script src="{{ asset('assets/plugins/datatable/js/dataTables.bootstrap5.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize DataTable (if table view is available)
    // if (document.getElementById('group_all_view')) {
    //     $('#group_all_view').DataTable({
    //         responsive: true,
    //         pageLength: 25,
    //         lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "All"]],
    //         dom: 'Bfrtip',
    //         buttons: [
    //             {
    //                 extend: 'csv',
    //                 text: '<i class="material-icons-outlined">download</i> Export CSV',
    //                 className: 'dt-button'
    //             },
    //             {
    //                 extend: 'print',
    //                 text: '<i class="material-icons-outlined">print</i> Print',
    //                 className: 'dt-button'
    //             }
    //         ],
    //         language: {
    //             search: "Search groups:",
    //             lengthMenu: "Show _MENU_ groups per page",
    //             info: "Showing _START_ to _END_ of _TOTAL_ groups",
    //             infoEmpty: "No groups available",
    //             infoFiltered: "(filtered from _MAX_ total groups)",
    //             zeroRecords: "No matching groups found",
    //             emptyTable: "No groups created yet"
    //         },
    //         columnDefs: [
    //             { orderable: false, targets: [2, 3, 4] } // Disable sorting on action columns
    //         ],
    //         order: [[0, 'asc']] // Sort by group name by default
    //     });
    // }

    // Auto-hide flash messages
    setTimeout(function() {
        const flashMessages = document.querySelectorAll('#flash-message, #flash-error-message');
        flashMessages.forEach(msg => {
            if (msg) {
                msg.style.opacity = '0';
                msg.style.transform = 'translateY(-20px)';
                setTimeout(() => msg.style.display = 'none', 300);
            }
        });
    }, 4000);

    // Smooth animations
    const cards = document.querySelectorAll('.group-card, .section-card, .groups-card');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 100);
    });

    // Add hover effects to group cards
    const groupCards = document.querySelectorAll('.group-card');
    groupCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px) scale(1.02)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });

    console.log('Groups page loaded successfully!');
});

// Toggle between grid and table view
function toggleView() {
    const gridView = document.querySelector('.groups-grid');
    const tableView = document.getElementById('table-view');
    const toggleButton = document.getElementById('view-toggle');
    
    if (gridView && tableView && toggleButton) {
        if (gridView.style.display === 'none') {
            // Switch to grid view
            gridView.style.display = 'grid';
            tableView.style.display = 'none';
            toggleButton.innerHTML = '<i class="material-icons-outlined">table_view</i> Switch to Table View';
        } else {
            // Switch to table view
            gridView.style.display = 'none';
            tableView.style.display = 'block';
            toggleButton.innerHTML = '<i class="material-icons-outlined">grid_view</i> Switch to Grid View';
        }
    }
}
</script>
@endpush