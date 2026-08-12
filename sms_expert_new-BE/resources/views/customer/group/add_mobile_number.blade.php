@extends('layouts.app')
@section('title')
    {{ __('Manage Group Contacts - SMS Expert') }}
@endsection

@push('style')
<style>
    .group-manage-container {
        background: #f8fafc;
        min-height: 100vh;
        margin: -2rem;
        padding: 2rem;
    }

     .back-btn {
            display: flex;
            align-items: center;
            font-size: 0.85rem;
            padding: 6px 12px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

    .main-card {
        background: white;
        border-radius: 15px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        overflow: hidden;
        position: relative;
    }

    .main-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, #ea6118, #293b50);
    }

    .main-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }

    .breadcrumb-container {
        background: white;
        border-radius: 15px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        padding: 1rem 1.5rem;
        margin-bottom: 2rem;
    }

    .breadcrumb-title {
        color: #293b50;
        font-weight: 700;
        font-size: 1.2rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .breadcrumb {
        margin: 0;
        background: transparent;
    }

    .breadcrumb-item a {
        color: #ea6118;
        text-decoration: none;
        transition: color 0.2s ease;
    }

    .breadcrumb-item a:hover {
        color: #293b50;
    }

    .breadcrumb-item.active {
        color: #64748b;
    }

    /* .back-btn {
        display: flex;
        align-items: center;
        font-size: 0.85rem;
        padding: 6px 12px;
        border-radius: 6px;
        transition: all 0.2s ease;
        background: linear-gradient(135deg, #64748b, #475569);
        border: none;
        color: white;
        font-weight: 500;
    }

    .back-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(100, 116, 139, 0.3);
        color: white;
    } */

    .page-header {
        background: linear-gradient(135deg, #ea6118, #293b50);
        color: white;
        padding: 2rem;
        text-align: center;
        position: relative;
        overflow: hidden;
    }

    .page-header::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: 
            radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%),
            radial-gradient(circle at 80% 20%, rgba(255, 255, 255, 0.05) 0%, transparent 50%);
        animation: float 6s ease-in-out infinite;
    }

    @keyframes float {
        0%, 100% { transform: translate(0, 0) rotate(0deg); }
        50% { transform: translate(-10px, -10px) rotate(1deg); }
    }

    .page-title {
        font-size: 1.8rem;
        font-weight: 700;
        margin: 0;
        text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        position: relative;
        z-index: 2;
    }

    .page-subtitle {
        color: rgba(255, 255, 255, 0.9);
        font-size: 1rem;
        margin: 0.5rem 0 0 0;
        position: relative;
        z-index: 2;
    }

    .content-section {
        padding: 2rem;
    }

    .section-title {
        color: #293b50;
        font-weight: 700;
        font-size: 1.3rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .icon-primary {
        color: #ea6118;
        font-size: 1.4rem;
    }

    .table-header {
        background: linear-gradient(135deg, #f8fafc, #e2e8f0);
        padding: 1rem 1.5rem;
        border-bottom: 2px solid #e2e8f0;
        border-radius: 12px 12px 0 0;
    }

    .table-header .form-label {
        color: #293b50;
        font-weight: 700;
        font-size: 1rem;
        margin: 0;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .contact-row {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid #f1f5f9;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .contact-row:hover {
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
        transform: translateX(3px);
        border-left: 4px solid #ea6118;
    }

    .contact-row:last-child {
        border-bottom: none;
        border-radius: 0 0 12px 12px;
    }

    .contact-info {
        flex: 1;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .contact-field {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 0.75rem 1rem;
        color: #293b50;
        font-weight: 500;
        text-align: center;
        transition: all 0.3s ease;
        flex: 1;
        min-height: 45px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .contact-field:hover {
        background: white;
        border-color: #ea6118;
        transform: scale(1.02);
    }

    .phone-number {
        background: linear-gradient(135deg, rgba(234, 97, 24, 0.1), rgba(41, 59, 80, 0.1));
        border-color: rgba(234, 97, 24, 0.3);
        font-family: 'Courier New', monospace;
        font-weight: 600;
        color: #ea6118;
    }

    .contact-name {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.1));
        border-color: rgba(16, 185, 129, 0.3);
        color: #059669;
    }

    .checkbox-container {
        display: flex;
        align-items: center;
        justify-content: center;
        min-width: 80px;
    }

    .form-check {
        margin: 0;
    }

    .form-check-input {
        width: 1.5rem;
        height: 1.5rem;
        margin: 0;
        border: 2px solid #e2e8f0;
        border-radius: 6px;
        transition: all 0.3s ease;
    }

    .form-check-input:checked {
        background-color: #ea6118;
        border-color: #ea6118;
        box-shadow: 0 4px 12px rgba(234, 97, 24, 0.3);
        transform: scale(1.1);
    }

    .form-check-input:focus {
        border-color: #ea6118;
        box-shadow: 0 0 0 0.2rem rgba(234, 97, 24, 0.25);
    }

    .form-check-label {
        color: #64748b;
        font-weight: 500;
        margin-left: 0.5rem;
        cursor: pointer;
    }

    .form-actions {
        background: #f8fafc;
        padding: 1.5rem;
        border-radius: 0 0 15px 15px;
        border-top: 1px solid #e2e8f0;
    }

    .btn-primary {
        background: linear-gradient(135deg, #ea6118, #293b50);
        border: none;
        border-radius: 10px;
        padding: 0.75rem 2rem;
        font-weight: 600;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        color: white;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(234, 97, 24, 0.4);
        color: white;
    }

    .btn-secondary {
        background: linear-gradient(135deg, #64748b, #475569);
        border: none;
        border-radius: 10px;
        padding: 0.75rem 2rem;
        font-weight: 600;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        color: white;
    }

    .btn-secondary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(100, 116, 139, 0.4);
        color: white;
    }

    .btn-danger {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
        border: none;
        border-radius: 10px;
        padding: 0.75rem 2rem;
        font-weight: 600;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        color: white;
    }

    .btn-danger:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(220, 38, 38, 0.4);
        color: white;
    }

    .btn-outline-secondary-toogle {
        background: transparent;
        border: 2px solid #64748b;
        border-radius: 10px;
        padding: 0.75rem 2rem;
        font-weight: 600;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        color: #64748b;
    }

    .btn-outline-secondary-toogle:hover {
        background: #293b50;
        border-color: #293b50;
        color: white;
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(41, 59, 80, 0.4);
    }

    .pagination-container {
        background: white;
        border-radius: 12px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        padding: 1rem 1.5rem;
        margin-top: 1.5rem;
    }

    .pagination {
        margin: 0;
        justify-content: center;
    }

    .page-link {
        color: #64748b;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        margin: 0 0.2rem;
        transition: all 0.3s ease;
    }

    .page-link:hover {
        background: #ea6118;
        border-color: #ea6118;
        color: white;
        transform: translateY(-1px);
    }

    .page-item.active .page-link {
        background: linear-gradient(135deg, #ea6118, #293b50);
        border-color: #ea6118;
        color: white;
    }

    .alert {
        border: none;
        border-radius: 12px;
        padding: 1rem 1.25rem;
        margin-bottom: 1.5rem;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        box-shadow: 0 4px 16px rgba(0, 0, 0, 0.05);
    }

    .alert-success {
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
    }

    .alert-danger {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
    }

    .stats-bar {
        background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
        border: 1px solid #0891b2;
        border-radius: 12px;
        padding: 1rem 1.5rem;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 1rem;
    }

    .stat-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        color: #0891b2;
        font-weight: 600;
    }

    .stat-icon {
        width: 32px;
        height: 32px;
        border-radius: 6px;
        background: #0891b2;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
    }

    /* Responsive design */
    @media (max-width: 768px) {
        .group-manage-container {
            padding: 1rem;
        }
        
        .page-title {
            font-size: 1.4rem;
        }
        
        .content-section {
            padding: 1.5rem;
        }
        
        .breadcrumb-container {
            flex-direction: column;
            text-align: center;
            gap: 1rem;
        }
        
        /* .back-btn {
            width: 100%;
            justify-content: center;
        } */
        
        .contact-row {
            flex-direction: column;
            align-items: stretch;
            gap: 0.75rem;
        }
        
        .contact-info {
            flex-direction: column;
        }
        
        .checkbox-container {
            min-width: auto;
            justify-content: flex-start;
        }
        
        .form-actions .d-flex {
            flex-direction: column;
            gap: 0.75rem;
        }
        
        .btn {
            width: 100%;
            justify-content: center;
        }
    }

    /* Animation classes */
    .fade-in {
        animation: fadeIn 0.6s ease-out;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(20px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
</style>
@endpush

@section('content')
<div class="group-manage-container">
    <!-- Breadcrumb -->
    <div class="breadcrumb-container d-flex align-items-center justify-content-between fade-in">
        <div class="d-flex align-items-center">
            <div class="breadcrumb-title pe-3">
                <i class="material-icons-outlined icon-primary">group_add</i>
                Manage Group Contacts
            </div>&nbsp;
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="{{ route('dashboard') }}">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="{{ route('groups.index') }}">Groups</a>
                    </li>
                    <li class="breadcrumb-item active">Manage Contacts</li>
                </ol>
            </nav>
        </div>
        <button id="backButton" class="btn btn-outline-secondary back-btn">
                <i class="material-icons-outlined me-1">arrow_back</i> Back
            </button>
    </div>

    <!-- Success/Error Messages -->
    @if (session('success'))
        <div id="flash-message" class="alert alert-success fade-in">
            <i class="material-icons-outlined">check_circle</i>
            {{ session('success') }}
        </div>
    @endif
    
    @if (session('error'))
        <div id="flash-error-message" class="alert alert-danger fade-in">
            <i class="material-icons-outlined">error</i>
            {{ session('error') }}
        </div>
    @endif
    @if (session('info'))
        <div id="flash-message" class="alert alert-info fade-in">
            <i class="material-icons-outlined">error</i>
            {{ session('info') }}
        </div>
    @endif

    <!-- Main Content Card -->
    <div class="main-card fade-in">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-title">
                <i class="material-icons-outlined me-2">group</i>
                Editing Group: {{ $group->name ?? 'Unknown Group' }}
            </div>
            <div class="page-subtitle">
                Select which contacts should be included in this group
            </div>
        </div>

        <!-- Stats Bar -->
        <div class="content-section">
            <div class="stats-bar">
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="material-icons-outlined">contacts</i>
                    </div>
                    <span>Total Contacts: {{ $addressBook->total() ?? 0 }}</span>
                </div>
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="material-icons-outlined">group</i>
                    </div>
                    <span>In Group: {{ count($inGroupIds ?? []) }}</span>
                </div>
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="material-icons-outlined">person_add</i>
                    </div>
                    <span>Available: {{ ($addressBook->total() ?? 0) - count($inGroupIds ?? []) }}</span>
                </div>
            </div>

            <form action="{{ route('save.addressbook') }}" method="POST">
                @csrf
                
                <!-- Table Header -->
                <div class="table-header">
                    <div class="row">
                        <div class="col-12 col-md-5">
                            <label class="form-label">
                                <i class="material-icons-outlined me-1">phone</i>
                                Phone Number
                            </label>
                        </div>
                        <div class="col-12 col-md-5">
                            <label class="form-label">
                                <i class="material-icons-outlined me-1">person</i>
                                Contact Name
                            </label>
                        </div>
                        <div class="col-12 col-md-2">
                            <label class="form-label">
                                <i class="material-icons-outlined me-1">group_add</i>
                                In Group?
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Contact Rows -->
                <div class="contact-list">
                    @forelse ($addressBook as $entry)
                        <div class="contact-row">
                            <div class="contact-info">
                                <div class="contact-field phone-number">
                                    <i class="material-icons-outlined me-2">phone</i>
                                    {{ $entry->mobileDetail->msisdn ?? 'N/A' }}
                                </div>
                                <div class="contact-field contact-name">
                                    <i class="material-icons-outlined me-2">person</i>
                                    {{ $entry->name ?? 'Unknown' }}
                                </div>
                            </div>
                            <div class="checkbox-container">
                                <div class="form-check">
                                    <input name="check_{{ $entry->id }}" 
                                           value="yes" 
                                           type="checkbox"
                                           class="form-check-input" 
                                           id="isInGroupCheckbox{{ $entry->id }}"
                                           {{ in_array($entry->id, $inGroupIds ?? []) ? 'checked' : '' }}>
                                    <label for="isInGroupCheckbox{{ $entry->id }}" class="form-check-label">
                                        Include
                                    </label>
                                    <input type="hidden" name="row_id_{{ $entry->id }}" value="{{ $entry->id }}">
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="contact-row">
                            <div class="text-center py-4">
                                <i class="material-icons-outlined" style="font-size: 3rem; color: #64748b;">contacts</i>
                                <p class="text-muted mt-2">No contacts found in your address book.</p>
                                <a href="{{ route('numbers.create') }}" class="btn btn-primary">
                                    <i class="material-icons-outlined me-1">person_add</i>
                                    Add Contacts
                                </a>
                            </div>
                        </div>
                    @endforelse
                </div>

                <input type="hidden" name="group_id" value="{{ $group->id ?? '' }}">
                @if($addressBook->count())
                <!-- Form Actions -->
                <div class="form-actions">
                    <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                        <!-- Left Side Actions -->
                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" name="Submit" class="btn btn-primary">
                                <i class="material-icons-outlined me-1">save</i>
                                Save Changes
                            </button>
                            <button type="reset" name="Reset" class="btn btn-secondary">
                                <i class="material-icons-outlined me-1">refresh</i>
                                Reset
                            </button>
                            <a href="{{ route('groups.index') }}" class="btn btn-danger">
                                <i class="material-icons-outlined me-1">cancel</i>
                                Cancel
                            </a>
                        </div>

                        <!-- Right Side Action -->
                        <div>
                            <button type="button" name="toggleButton" class="btn btn-outline-secondary-toogle" onclick="doToggleSelect()">
                                <i class="material-icons-outlined me-1">select_all</i>
                                Toggle All
                            </button>
                        </div>
                    </div>
                </div>
            </form>
            @endif

            <!-- Pagination -->
            {{-- @if(isset($addressBook) && method_exists($addressBook, 'links'))
                <div class="pagination-container">
                    <nav aria-label="Page navigation">
                        {{ $addressBook->links('pagination::bootstrap-5') }}
                    </nav>
                </div>
            @endif --}}
        </div>
    </div>
</div>

<!-- Footer -->
@include('layouts.footer')
<!-- End Footer -->
@endsection

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle all checkboxes
    window.doToggleSelect = function() {
        const checkboxes = document.querySelectorAll('input[type="checkbox"][name^="check_"]');
        const toggleButton = document.querySelector('button[name="toggleButton"]');
        const icon = toggleButton.querySelector('.material-icons-outlined');
        
        let allChecked = Array.from(checkboxes).every(checkbox => checkbox.checked);
        
        checkboxes.forEach(checkbox => {
            checkbox.checked = !allChecked;
            // Trigger change event for animations
            checkbox.dispatchEvent(new Event('change'));
        });
        
        // Update button text and icon
        if (allChecked) {
            icon.textContent = 'select_all';
            toggleButton.querySelector('.me-1').nextSibling.textContent = 'Select All';
        } else {
            icon.textContent = 'deselect';
            toggleButton.querySelector('.me-1').nextSibling.textContent = 'Deselect All';
        }
    };

    // Enhanced checkbox interactions
    const checkboxes = document.querySelectorAll('.form-check-input');
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const row = this.closest('.contact-row');
            if (this.checked) {
                row.style.background = 'linear-gradient(135deg, rgba(234, 97, 24, 0.1), rgba(41, 59, 80, 0.1))';
                row.style.borderLeft = '4px solid #ea6118';
            } else {
                row.style.background = '';
                row.style.borderLeft = '';
            }
            
            // Update stats
            updateStats();
        });
        
        // Initialize state
        if (checkbox.checked) {
            const row = checkbox.closest('.contact-row');
            row.style.background = 'linear-gradient(135deg, rgba(234, 97, 24, 0.1), rgba(41, 59, 80, 0.1))';
            row.style.borderLeft = '4px solid #ea6118';
        }
    });

    // Update statistics
    function updateStats() {
        const totalContacts = checkboxes.length;
        const inGroup = Array.from(checkboxes).filter(cb => cb.checked).length;
        const available = totalContacts - inGroup;
        
        const statItems = document.querySelectorAll('.stat-item span');
        if (statItems.length >= 3) {
            statItems[1].textContent = `In Group: ${inGroup}`;
            statItems[2].textContent = `Available: ${available}`;
        }
    }

    // Enhanced form submission
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function() {
            const submitButton = form.querySelector('button[type="submit"]');
            if (submitButton) {
                const originalText = submitButton.innerHTML;
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Saving...';
                submitButton.disabled = true;
                
                // Re-enable after timeout in case of errors
                setTimeout(() => {
                    submitButton.innerHTML = originalText;
                    submitButton.disabled = false;
                }, 5000);
            }
        });
    }

    // Auto-hide flash messages
    setTimeout(function() {
        const messages = document.querySelectorAll('#flash-message, #flash-error-message');
        messages.forEach(message => {
            if (message) {
                message.style.transition = 'all 0.5s ease';
                message.style.opacity = '0';
                message.style.transform = 'translateY(-20px)';
                setTimeout(() => {
                    message.remove();
                }, 500);
            }
        });
    }, 4000);

    // Smooth reveal animations
    const cards = document.querySelectorAll('.main-card, .breadcrumb-container');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(30px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.6s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 200);
    });

    // Contact row hover effects
    const contactRows = document.querySelectorAll('.contact-row');
    contactRows.forEach(row => {
        row.addEventListener('mouseenter', function() {
            if (!this.style.background) {
                this.style.background = 'linear-gradient(135deg, #f8fafc, #f1f5f9)';
            }
        });
        
        row.addEventListener('mouseleave', function() {
            const checkbox = this.querySelector('.form-check-input');
            if (!checkbox.checked) {
                this.style.background = '';
            }
        });
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'a') {
            e.preventDefault();
            doToggleSelect();
        }
        
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            form.submit();
        }
    });

    console.log('Modern Group Management page loaded successfully!');
});
</script>
@endpush