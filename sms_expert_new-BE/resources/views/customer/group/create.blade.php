@extends('layouts.app')
@section('title', 'Create Group - SMS Expert')

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
    .create-container {
        background: #f8fafc;
        min-height: 100vh;
        margin: -2rem;
        padding: 2rem;
    }

    .create-card {
        background: white;
        border-radius: 15px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        overflow: hidden;
        position: relative;
    }

    .create-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, #ea6118, #293b50);
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

    .form-group {
        margin-bottom: 2rem;
    }

    .form-label {
        color: #293b50;
        font-weight: 600;
        margin-bottom: 0.75rem;
        font-size: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .form-control {
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        padding: 0.75rem 1rem;
        font-size: 0.95rem;
        transition: all 0.3s ease;
        background: white;
    }

    .form-control:focus {
        border-color: #ea6118;
        box-shadow: 0 0 0 0.2rem rgba(234, 97, 24, 0.25);
        outline: none;
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
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(234, 97, 24, 0.4);
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

    .input-icon {
        position: relative;
    }

    .input-icon .form-control {
        padding-left: 3rem;
    }

    .input-icon .icon {
        position: absolute;
        left: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: #64748b;
        z-index: 10;
    }

    .form-help-text {
        color: #64748b;
        font-size: 0.85rem;
        margin-top: 0.5rem;
        line-height: 1.4;
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

    .form-actions {
        display: flex;
        gap: 1rem;
        justify-content: flex-start;
        padding-top: 1rem;
        border-top: 1px solid #e2e8f0;
        margin-top: 2rem;
    }

    .required-field::after {
        content: '*';
        color: #dc2626;
        margin-left: 0.25rem;
    }

    .group-preview {
        background: linear-gradient(135deg, rgba(234, 97, 24, 0.1), rgba(41, 59, 80, 0.1));
        border: 2px solid #ea6118;
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 2rem;
        text-align: center;
    }

    .group-preview h5 {
        color: #293b50;
        font-weight: 700;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .group-preview-content {
        color: #64748b;
        font-style: italic;
    }

    .tips-card {
        background: linear-gradient(135deg, #fef3c7, #fde68a);
        border: 2px solid #f59e0b;
        border-radius: 15px;
        padding: 1.5rem;
        margin-bottom: 2rem;
    }

    .tips-card h5 {
        color: #92400e;
        font-weight: 700;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .tips-list {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .tips-list li {
        color: #78350f;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        line-height: 1.4;
    }

    .tips-list li:last-child {
        margin-bottom: 0;
    }

    .character-counter {
        font-size: 0.8rem;
        color: #64748b;
        text-align: right;
        margin-top: 0.25rem;
    }

    .character-counter.warning {
        color: #f59e0b;
    }

    .character-counter.danger {
        color: #dc2626;
    }
</style>
@endpush

@section('content')
<div class="create-container">
    <!-- Breadcrumb -->
    <div class="breadcrumb-container d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <div class="breadcrumb-title pe-3">
                <i class="material-icons-outlined icon-primary">group_add</i>
                Create New Group
            </div>
            &nbsp;
<nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                   {{-- <li class="breadcrumb-item">
                        <i class="material-icons-outlined">home</i>
                    </li>--}}
                    <li class="breadcrumb-item">
                        <a href="{{ route('dashboard') }}">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="{{ route('groups.index') }}">Groups</a>
                    </li>
                    <li class="breadcrumb-item active">Create Group</li>
                </ol>
            </nav>
        </div>
<button id="backButton" class="btn btn-outline-secondary back-btn">
            <i class="material-icons-outlined me-1">arrow_back</i> Back
        </button>
    </div>

    <!-- Information Card -->
    <div class="info-card">
        <h5>
            <i class="material-icons-outlined">info</i>
            Creating a New Contact Group
        </h5>
        <p>
            Contact groups help you organize your contacts for efficient bulk SMS messaging. Choose a descriptive name that helps you easily identify the purpose of this group. You can add contacts to the group after creating it.
        </p>
    </div>

    <!-- Tips Card -->
    <div class="tips-card">
        <h5>
            <i class="material-icons-outlined">lightbulb</i>
            Group Naming Tips
        </h5>
        <ul class="tips-list">
            <li>
                <i class="material-icons-outlined">check_circle</i>
                Use descriptive names like "VIP Customers" or "Marketing List"
            </li>
            <li>
                <i class="material-icons-outlined">check_circle</i>
                Keep names short and memorable for easy selection
            </li>
            <li>
                <i class="material-icons-outlined">check_circle</i>
                Avoid special characters that might cause issues
            </li>
            <li>
                <i class="material-icons-outlined">check_circle</i>
                Consider using categories like "Business", "Personal", etc.
            </li>
        </ul>
    </div>

    <!-- Group Preview -->
    <div class="group-preview">
        <h5>
            <i class="material-icons-outlined">preview</i>
            Group Preview
        </h5>
        <div class="group-preview-content" id="group-preview-name">
            Enter a group name to see preview...
        </div>
    </div>

    <!-- Main Content -->
    <div class="create-card">
        <div class="section-header">
            <h5 class="section-title">
                <i class="material-icons-outlined">group</i>
                Group Information
            </h5>
        </div>
        
        <div class="section-content">
            <form action="{{ route('groups.store') }}" method="POST">
                @csrf
                
                <!-- Group Name Field -->
                <div class="form-group">
                    <label class="form-label required-field">
                        <i class="material-icons-outlined">group</i>
                        Group Name
                    </label>
                    <div class="input-icon">
                        <i class="material-icons-outlined icon">group</i>
                        <input type="text" class="form-control" id="group_name" name="group_name" required
                               placeholder="Enter group name" autocomplete="off" maxlength="50">
                    </div>
                    <div class="character-counter" id="char-counter">0 / 50 characters</div>
                    <div class="form-help-text">
                        Choose a descriptive name for your contact group (e.g., "VIP Customers", "Newsletter Subscribers")
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="material-icons-outlined">save</i>
                        Create Group
                    </button>
                    <a href="{{ route('groups.index') }}" class="btn btn-danger">
                        <i class="material-icons-outlined">cancel</i>
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const groupNameInput = document.getElementById('group_name');
    const groupPreview = document.getElementById('group-preview-name');
    const charCounter = document.getElementById('char-counter');

    // Real-time preview and character counter
    groupNameInput.addEventListener('input', function(e) {
        const value = e.target.value.trim();
        const length = value.length;
        
        // Update preview
        if (value) {
            groupPreview.innerHTML = `<strong>Group:</strong> ${value}`;
            groupPreview.style.fontStyle = 'normal';
        } else {
            groupPreview.innerHTML = 'Enter a group name to see preview...';
            groupPreview.style.fontStyle = 'italic';
        }
        
        // Update character counter
        charCounter.textContent = `${length} / 50 characters`;
        
        // Color coding for character counter
        if (length > 40) {
            charCounter.className = 'character-counter warning';
        } else if (length > 45) {
            charCounter.className = 'character-counter danger';
        } else {
            charCounter.className = 'character-counter';
        }
        
        // Input validation - remove special characters except spaces, hyphens, and underscores
        const cleanValue = value.replace(/[^a-zA-Z0-9\s\-_]/g, '');
        if (cleanValue !== value) {
            e.target.value = cleanValue;
            // Show temporary feedback
            e.target.style.borderColor = '#f59e0b';
            setTimeout(() => {
                e.target.style.borderColor = '#e2e8f0';
            }, 1000);
        }
    });

    // Form focus effects
    const inputs = document.querySelectorAll('.form-control');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.style.transform = 'scale(1.02)';
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.style.transform = 'scale(1)';
        });
    });

    // Smooth animations
    const cards = document.querySelectorAll('.create-card, .info-card, .tips-card, .group-preview');
    cards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        
        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, index * 200);
    });

    // Button hover effects
    const buttons = document.querySelectorAll('.btn-primary, .btn-danger');
    buttons.forEach(button => {
        button.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px) scale(1.05)';
        });
        
        button.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
        });
    });

    // Focus on group name input
    setTimeout(() => {
        groupNameInput.focus();
    }, 500);

    console.log('Create group page loaded successfully!');
});
</script>
@endpush