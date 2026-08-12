@extends('layouts.app')
@section('title', 'Numbers - SMS Expert')

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

        .numbers-container {
            background: #f8fafc;
            min-height: 100vh;
            margin: -2rem;
            padding: 2rem;
        }

        .numbers-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
        }

        .numbers-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #ea6118, #293b50);
        }

        .numbers-card:hover {
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

        .phone-number {
            font-family: 'Courier New', monospace;
            background: #f8fafc;
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            color: #293b50;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .network-badge {
            background: linear-gradient(135deg, #0891b2, #0e7490);
            color: white;
            padding: 0.35rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .favourite-badge {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            padding: 0.35rem 0.75rem;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .not-favourite {
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
            text-align: center;
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

        .action-buttons-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .primary-action-button {
            background: linear-gradient(135deg, #ea6118, #293b50);
            border: none;
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-align: center;
        }

        .primary-action-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(234, 97, 24, 0.4);
            color: white;
        }

        .secondary-action-button {
            background: linear-gradient(135deg, #16a34a, #15803d);
            border: none;
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-align: center;
        }

        .secondary-action-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(22, 163, 74, 0.4);
            color: white;
        }

        .utility-action-button {
            background: linear-gradient(135deg, #64748b, #475569);
            border: none;
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-align: center;
        }

        .utility-action-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(100, 116, 139, 0.4);
            color: white;
        }

        .backup-section {
            background: #f8fafc;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            padding: 2rem;
            margin-top: 2rem;
        }

        .backup-title {
            color: #293b50;
            font-weight: 700;
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .backup-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .backup-link {
            color: #ea6118;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            border: 2px solid #ea6118;
            background: white;
            transition: all 0.3s ease;
        }

        .backup-link:hover {
            background: #ea6118;
            color: white;
            transform: translateY(-1px);
        }

        .danger-link {
            border-color: #dc2626;
            color: #dc2626;
        }

        .danger-link:hover {
            background: #dc2626;
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

        .contact-name {
            color: #293b50;
            font-weight: 600;
            font-size: 1rem;
        }
    </style>
@endpush

@section('content')
    <div class="numbers-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb-container d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">
                    <i class="material-icons-outlined icon-primary">contacts</i>
                    Numbers
                </div>&nbsp;
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        {{-- <li class="breadcrumb-item">
                        <i class="material-icons-outlined">home</i>
                    </li> --}}
                        <li class="breadcrumb-item">
                            <a href="{{ route('dashboard') }}">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item active">Numbers</li>
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
                Manage Your Contact Numbers
            </h5>
            <p>
                This is your personal address book where you can store and manage phone numbers for easy SMS sending.
                You can add individual contacts, upload files with multiple numbers, and organize them by marking
                favourites.
            </p>
        </div>

        <!-- Statistics Summary -->
        @if (isset($userData) && count($userData) > 0)
            <div class="stats-summary">
                <div class="stats-number">{{ count($userData) }}</div>
                <div class="stats-label">Total Contacts</div>
            </div>
        @endif

        <!-- Action Buttons -->
        <div class="action-buttons-grid">
            <a href="{{ route('numbers.create') }}" class="primary-action-button">
                <i class="material-icons-outlined">add</i>
                Add New Contact
            </a>
            <a href="{{ route('number.upload') }}" class="secondary-action-button">
                <i class="material-icons-outlined">upload_file</i>
                Upload File
            </a>
            <a href="{{ route('clean.bad.numbers') }}" class="utility-action-button">
                <i class="material-icons-outlined">cleaning_services</i>
                Clean Bad Numbers
            </a>
        </div>

        <!-- Main Content -->
        <div class="numbers-card">
            @if (isset($userData) && count($userData) > 0)
                <div class="section-header">
                    <h5 class="section-title">
                        <i class="material-icons-outlined">contact_phone</i>
                        Your Contacts
                    </h5>
                </div>

                <div class="data-table">
                    <table id="number_all_view" class="table">
                        <thead>
                            <tr class="text-center">
                                <th>
                                    <i class="material-icons-outlined">person</i>
                                    Name
                                </th>
                                <th>
                                    <i class="material-icons-outlined">phone</i>
                                    Number
                                </th>
                                <th>
                                    <i class="material-icons-outlined">network_cell</i>
                                    Network
                                </th>
                                <th>
                                    <i class="material-icons-outlined">star</i>
                                    Favourite
                                </th>
                                <th>
                                    <i class="material-icons-outlined">settings</i>
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($userData as $record)
                                <tr class="text-center">
                                    <td>
                                        <div class="contact-name">
                                            <i class="material-icons-outlined">account_circle</i>
                                             {{ str_replace('+', ' ', $record->name ?? 'Unnamed Contact') }}
                                        </div>
                                    </td>
                                    <td>
                                        <div class="phone-number">
                                            <i class="material-icons-outlined">phone</i>
                                            {{ $record->mobileDetail->msisdn ?? 'No Number' }}
                                        </div>
                                    </td>
                                    <td>
                                        <span class="network-badge">
                                            <i class="material-icons-outlined">signal_cellular_4_bar</i>
                                            {{ $record->mobileDetail->network->Name ?? 'Unknown' }}
                                        </span>
                                    </td>
                                    <td>
                                        @if ($record->is_favourite === 'y')
                                            <span class="favourite-badge">
                                                <i class="material-icons-outlined">star</i>
                                                Yes
                                            </span>
                                        @else
                                            <span class="favourite-badge not-favourite">
                                                <i class="material-icons-outlined">star_border</i>
                                                No
                                            </span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap">
                                            <a href="{{ route('numbers.edit', $record->id) }}"
                                                class="action-button btn-edit">
                                                <i class="material-icons-outlined">edit</i>
                                                Edit
                                            </a>
                                            <form action="{{ route('numbers.destroy', $record->id) }}" method="POST"
                                                style="display: inline;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="action-button btn-delete"
                                                    onclick="return confirm('Are you sure you want to delete this contact?');">
                                                    <i class="material-icons-outlined">delete</i>
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <!-- No Data State -->
                <div class="section-card">
                    <div class="no-data">
                        <i class="material-icons-outlined">contacts</i>
                        <h4>No Contacts Yet</h4>
                        <p>You haven't added any contacts to your address book yet. Start by adding your first contact or
                            uploading a file with multiple numbers.</p>
                        <div class="mt-3">
                            <a href="{{ route('numbers.create') }}" class="primary-action-button"
                                style="display: inline-flex; width: auto;">
                                <i class="material-icons-outlined">add</i>
                                Add Your First Contact
                            </a>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Backup Section -->
            <div class="backup-section">
                <div class="backup-title">
                    <i class="material-icons-outlined">backup</i>
                    Backup & Maintenance
                </div>
                <div class="backup-actions">
                    <a href="{{ route('number.download.csv') }}" class="backup-link">
                        <i class="material-icons-outlined">download</i>
                        Download All Contacts
                    </a>
                    <form action="{{ route('numbers.destroyAll') }}" method="POST" style="display: inline;">
                        @csrf
                        <button type="submit" class="backup-link danger-link"
                            onclick="return confirm('You are about to delete all of your addressbook contacts.\n\nIf you have not already downloaded all information you should consider cancelling this action and click Download all.');">
                            <i class="material-icons-outlined">delete_forever</i>
                            Delete ALL Contacts
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
    <script src="{{ asset('assets/plugins/datatable/js/jquery.dataTables.min.js') }}"></script>
    <script src="{{ asset('assets/plugins/datatable/js/dataTables.bootstrap5.min.js') }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
                    // Initialize DataTable with custom styling
                    if (document.getElementById('number_all_view')) {
                        $('#number_all_view').DataTable({
                            responsive: true,
                            pageLength: 25,
                            lengthMenu: [
                                [10, 25, 50, 100, -1],
                                [10, 25, 50, 100, "All"]
                            ],
                            dom: 'Bfrtip',
                            buttons: [{
                                    extend: 'csv',
                                    text: '<i class="material-icons-outlined">download</i> Export CSV',
                                    className: 'dt-button'
                                },
                                {
                                    extend: 'print',
                                    text: '<i class="material-icons-outlined">print</i> Print',
                                    className: 'dt-button'
                                }
                            ],
                            language: {
                                search: "Search contacts:",
                                lengthMenu: "Show _MENU_ contacts per page",
                                info: "Showing _START_ to _END_ of _TOTAL_ contacts",
                                infoEmpty: "No contacts available",
                                infoFiltered: "(filtered from _MAX_ total contacts)",
                                zeroRecords: "No matching contacts found",
                                emptyTable: "No contacts in your address book"
                            },
                            columnDefs: [{
                                    orderable: false,
                                    targets: 4
                                } // Disable sorting on Actions column
                            ],
                            order: [
                                [0, 'asc']
                            ] // Sort by name by default
                        });
                    }

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
                    const cards = document.querySelectorAll('.section-card, .numbers-card, '.action - buttons - grid > * ');
                        cards.forEach((card, index) => {
                            card.style.opacity = '0';
                            card.style.transform = 'translateY(20px)';

                            setTimeout(() => {
                                card.style.transition = 'all 0.5s ease';
                                card.style.opacity = '1';
                                card.style.transform = 'translateY(0)';
                            }, index * 100);
                        });

                        // Add hover effects to action buttons
                        const actionButtons = document.querySelectorAll(
                            '.primary-action-button, .secondary-action-button, .utility-action-button'); actionButtons
                        .forEach(button => {
                            button.addEventListener('mouseenter', function() {
                                this.style.transform = 'translateY(-2px) scale(1.02)';
                            });

                            button.addEventListener('mouseleave', function() {
                                this.style.transform = 'translateY(0) scale(1)';
                            });
                        });

                        console.log('Numbers page loaded successfully!');
                    });
    </script>
@endpush
