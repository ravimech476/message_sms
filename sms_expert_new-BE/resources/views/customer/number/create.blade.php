@extends('layouts.app')
@section('title', 'Add New Contact - SMS Expert')

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

        .form-control,
        .form-select {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #ea6118;
            box-shadow: 0 0 0 0.2rem rgba(234, 97, 24, 0.25);
            outline: none;
        }

        .form-check-input {
            width: 1.5rem;
            height: 1.5rem;
            margin-top: 0;
        }

        .form-check-input:checked {
            background-color: #ea6118;
            border-color: #ea6118;
        }

        .form-check-label {
            color: #475569;
            font-weight: 500;
            margin-left: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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

        .error-message {
            color: #dc2626;
            font-size: 0.85rem;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
            font-weight: 500;
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

        .favourite-section {
            background: #fef7ed;
            border: 1px solid #fed7aa;
            border-radius: 10px;
            padding: 1rem;
        }

        .favourite-section .form-check {
            margin: 0;
        }

        .required-field::after {
            content: '*';
            color: #dc2626;
            margin-left: 0.25rem;
        }
    </style>
@endpush

@section('content')
    <div class="create-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb-container d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">
                    <i class="material-icons-outlined icon-primary">person_add</i>
                    Add New Contact
                </div>
                &nbsp;
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        {{-- <li class="breadcrumb-item">
                        <i class="material-icons-outlined">home</i>
                    </li> --}}
                        <li class="breadcrumb-item">
                            <a href="{{ route('dashboard') }}">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="{{ route('numbers.index') }}">Numbers</a>
                        </li>
                        <li class="breadcrumb-item active">Add Contact</li>
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
                Adding a New Contact
            </h5>
            <p>
                Fill in the contact details below to add a new number to your address book.
                You can mark contacts as favourites for quick access when sending SMS messages.
            </p>
        </div>

        <!-- Main Content -->
        <div class="create-card">
            <div class="section-header">
                <h5 class="section-title">
                    <i class="material-icons-outlined">contact_phone</i>
                    Contact Information
                </h5>
            </div>

            <div class="section-content">
                <form action="{{ route('numbers.store') }}" method="POST" id="myForm">
                    @csrf

                    <div class="row">
                        <!-- Name Field -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label required-field">
                                    <i class="material-icons-outlined">person</i>
                                    Contact Name
                                </label>
                                <div class="input-icon">
                                    <i class="material-icons-outlined icon">account_circle</i>
                                    <input type="text" class="form-control" id="name" name="name" required
                                        placeholder="Enter contact name" autocomplete="off">
                                </div>
                                <div id="nameError" class="error-message" style="display: none;">
                                    <i class="material-icons-outlined">error</i>
                                    Only letters and spaces are allowed
                                </div>
                                <div class="form-help-text">
                                    Enter the full name of the contact (letters and spaces only)
                                </div>
                            </div>
                        </div>

                        <!-- Number Field -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label required-field">
                                    <i class="material-icons-outlined">phone</i>
                                    Phone Number
                                </label>
                                <div class="input-icon">
                                    <i class="material-icons-outlined icon">phone</i>
                                    <input type="text" class="form-control" id="number" name="number" required
                                        placeholder="Enter phone number" autocomplete="off"
                                        oninput="this.value = this.value.replace(/[^0-9+]/g, '')">
                                </div>
                                {{-- <div id="numberError" class="error-message" style="display: none;">
                                    <i class="material-icons-outlined">error</i>
                                    Please enter a valid phone number (digits only)
                                </div> --}}
                                <div id="phone-error" class="text-danger mt-1" style="display:none;">
                                    Please enter a valid phone number.<br>
                                    Accepted formats:<br>
                                    • +44XXXXXXXXXX<br>
                                    • 44XXXXXXXXXX<br>
                                    • 07XXXXXXXXX<br>
                                    • 01932XXXXXX<br>
                                </div>
                                <div class="form-help-text">
                                    Enter the phone number (digits only, no spaces or special characters)
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Network Field -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label fw-semibold theme-label-color">
                                    <i class="material-icons-outlined">network_cell</i>
                                    Mobile Network
                                </label>
                                <select class="form-select" id="net_id" name="net_id">
                                    <option value="0">Unknown Network</option>
                                    @foreach ($mobnetworks as $network)
                                        <option value="{{ $network->id }}">{{ $network->Name }}</option>
                                    @endforeach
                                </select>
                                <div class="form-help-text">
                                    Select the mobile network provider (optional)
                                </div>
                            </div>
                        </div>

                        <!-- Favourite Field -->
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="form-label fw-semibold theme-label-color">
                                    <i class="material-icons-outlined">star</i>
                                    Favourite Contact
                                </label>
                                <div class="favourite-section">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="favourite" name="favourite"
                                            value="1">
                                        <label class="form-check-label" for="favourite">
                                            <i class="material-icons-outlined">star</i>
                                            Mark as favourite for quick access
                                        </label>
                                    </div>
                                </div>
                                <div class="form-help-text">
                                    Favourite contacts appear at the top of your contact list for easy selection
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="material-icons-outlined">save</i>
                            Save Contact
                        </button>
                        <a href="{{ route('numbers.index') }}" class="btn btn-danger">
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
        const ukPattern = /^(?:\+44\d{10}|44\d{10}|07\d{9}|01932\d{6})$/;
        const indiaPattern = /^\+?91\d{10}$/;

        function validatePhone(input) {
            const phone = input.value.trim();
            const errorDiv = document.getElementById('phone-error');

            if (
                phone === '' ||
                ukPattern.test(phone) ||
                indiaPattern.test(phone)
            ) {
                errorDiv.style.display = 'none';
                return true;
            } else {
                errorDiv.style.display = 'block';
                return false;
            }
        }

        // Form submit validation
        document.getElementById('myForm').addEventListener('submit', function(e) {
            const phoneInput = document.getElementById('number');
            if (!validatePhone(phoneInput)) {
                e.preventDefault();
                phoneInput.focus();
            }
        });

        document.addEventListener('DOMContentLoaded', function() {
            // Name validation
            document.getElementById('name').addEventListener('input', function(e) {
                const nameInput = e.target.value;
                const nameError = document.getElementById('nameError');

                if (!/^[a-zA-Z\s]*$/.test(nameInput)) {
                    nameError.style.display = 'flex';
                    e.target.value = nameInput.replace(/[^a-zA-Z\s]/g, '');
                    e.target.style.borderColor = '#dc2626';
                } else {
                    nameError.style.display = 'none';
                    e.target.style.borderColor = '#e2e8f0';
                }
            });

            // Number validation
            // document.getElementById('number').addEventListener('input', function(e) {
            //     const numberInput = e.target.value;
            //     const numberError = document.getElementById('numberError');

            //     if (!/^\d*$/.test(numberInput)) {
            //         numberError.style.display = 'flex';
            //         e.target.value = numberInput.replace(/\D/g, '');
            //         e.target.style.borderColor = '#dc2626';
            //     } else {
            //         numberError.style.display = 'none';
            //         e.target.style.borderColor = '#e2e8f0';
            //     }
            // });

            // Form focus effects
            const inputs = document.querySelectorAll('.form-control, .form-select');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'scale(1.02)';
                });

                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'scale(1)';
                });
            });

            // Smooth animations
            const cards = document.querySelectorAll('.create-card, .info-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';

                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 200);
            });

            console.log('Create contact page loaded successfully!');
        });
    </script>
@endpush
