@extends('layouts.app')
@section('title')
    {{ __('STOP Commands') }}
@endsection
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

        .modern-card {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: none;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
            margin-bottom: 2rem;
        }

        .modern-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #ea6118, #ff8a50);
        }

        .modern-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
        }

        .page-header {
            background: linear-gradient(135deg, #ea6118 0%, #293b50 100%);
            border-radius: 20px;
            padding: 2rem;
            color: white;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-20px);
            }
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            position: relative;
            z-index: 2;
        }

        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin: 0.5rem 0 0 0;
            position: relative;
            z-index: 2;
        }

        .icon-wrapper {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            position: relative;
            z-index: 2;
        }

        .icon-wrapper i {
            font-size: 1.8rem;
            color: white;
        }

        .form-section {
            margin-bottom: 2.5rem;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: #293b50;
            margin-bottom: 1.5rem;
            position: relative;
            padding-left: 1rem;
        }

        .section-title::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 100%;
            background: linear-gradient(135deg, #ea6118, #ff8a50);
            border-radius: 2px;
        }

        .modern-input {
            border: 2px solid #e9ecef;
            border-radius: 12px;
            padding: 0.8rem 1rem;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: #fff;
        }

        .modern-input:focus {
            border-color: #ea6118;
            box-shadow: 0 0 0 0.2rem rgba(234, 97, 24, 0.15);
            transform: translateY(-1px);
        }

        .modern-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .btn-modern {
            background: linear-gradient(135deg, #ea6118, #ff8a50);
            border: none;
            border-radius: 12px;
            padding: 0.8rem 2rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(234, 97, 24, 0.3);
        }

        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(234, 97, 24, 0.4);
            color: white;
        }

        .info-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 1.5rem;
            border-left: 4px solid #ea6118;
            margin-top: 1.5rem;
        }

        .info-card h6 {
            color: #293b50;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .info-card p {
            color: #6c757d;
            line-height: 1.6;
            margin: 0;
        }

        .breadcrumb-modern {
            background: rgba(255, 255, 255, 0.9);
            border-radius: 12px;
            padding: 0.8rem 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
        }

        .breadcrumb-modern .breadcrumb {
            margin: 0;
            background: none;
        }

        .breadcrumb-modern .breadcrumb-item {
            color: #6c757d;
        }

        .breadcrumb-modern .breadcrumb-item.active {
            color: #ea6118;
            font-weight: 600;
        }

        /* .back-btn {
                background: rgba(255, 255, 255, 0.2);
                border: 1px solid rgba(255, 255, 255, 0.3);
                color: white;
                border-radius: 10px;
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
                transition: all 0.3s ease;
            }

            .back-btn:hover {
                background: rgba(255, 255, 255, 0.3);
                color: white;
                transform: translateX(-3px);
            } */

        .alert-modern {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .alert-success.alert-modern {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
        }

        .alert-danger.alert-modern {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
        }

        .main-content {
            padding: 2rem;
            min-height: 100vh;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .page-header {
                padding: 1.5rem;
                text-align: center;
            }

            .page-title {
                font-size: 1.5rem;
            }

            .modern-card {
                border-radius: 15px;
            }

            .main-content {
                padding: 1rem;
            }
        }

        /* Dark theme support */
        [data-bs-theme="dark"] .modern-card {
            background: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);
            color: white;
        }

        [data-bs-theme="dark"] .section-title {
            color: #e2e8f0;
        }

        [data-bs-theme="dark"] .modern-input {
            background: #2d3748;
            border-color: #4a5568;
            color: white;
        }

        [data-bs-theme="dark"] .info-card {
            background: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);
            color: #e2e8f0;
        }

        /* Breadcrumb Container Styling */
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

        .icon-primary {
            color: #ea6118;
            font-size: 1.2rem;
        }

        .breadcrumb {
            margin: 0;
            background: transparent;
            padding: 0;
        }

        .breadcrumb-item {
            color: #6c757d;
        }

        .breadcrumb-item a {
            color: #ea6118;
            text-decoration: none;
        }

        .breadcrumb-item.active {
            color: #6c757d;
        }

        .back-button {
            background: linear-gradient(135deg, #64748b, #475569);
            border: none;
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .back-button:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(100, 116, 139, 0.3);
            color: white;
        }
    </style>
@endpush
@section('content')
    <!-- Breadcrumb Container -->
    <div class="breadcrumb-container d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <div class="breadcrumb-title pe-3">
                <i class="material-icons-outlined icon-primary">support</i>
                STOP Commands
            </div>&nbsp;
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    {{-- <li class="breadcrumb-item">
                        <i class="material-icons-outlined">home</i>
                    </li> --}}
                    <li class="breadcrumb-item">
                        <a href="{{ route('dashboard') }}">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item active">STOP Commands</li>
                </ol>
            </nav>
        </div>
        <button id="backButton" class="btn btn-outline-secondary back-btn">
            <i class="material-icons-outlined me-1">arrow_back</i> Back
        </button>
    </div>

    <!-- Flash Messages -->
    @if (session('success'))
        <div id="flash-message" class="alert alert-success alert-modern">
            <i class="material-icons-outlined me-2" style="vertical-align: middle;">check_circle</i>
            {{ session('success') }}
        </div>
    @endif
    @if (session('error'))
        <div id="flash-error-message" class="alert alert-danger alert-modern">
            <i class="material-icons-outlined me-2" style="vertical-align: middle;">error</i>
            {{ session('error') }}
        </div>
    @endif

    <div class="row">
        <div class="col-12">
            <div class="modern-card">
                <div class="card-body p-4">
                    <!-- STOP Command Settings Section -->
                    <div class="form-section">
                        <h5 class="section-title">STOP Command Configuration</h5>
                        <form action="{{ route('stopcommand.update') }}" method="POST">
                            @csrf
                            <div class="row g-4">
                                <div class="col-12 col-lg-6">
                                    <label class="form-label modern-label">
                                        <i class="material-icons-outlined me-2"
                                            style="font-size: 1rem; vertical-align: middle;">link</i>
                                        STOP Command URL
                                    </label>
                                    <input type="text" class="form-control modern-input" name="txtURL" id="txtURL"
                                        maxlength="200" placeholder="Enter webhook URL for STOP commands"
                                        value="{{ $userOption->stop_command_url ?? '' }}">
                                    <small class="text-muted">URL to receive STOP command notifications</small>
                                </div>
                                <div class="col-12 col-lg-6">
                                    <label class="form-label modern-label">
                                        <i class="material-icons-outlined me-2"
                                            style="font-size: 1rem; vertical-align: middle;">email</i>
                                        STOP Command E-mail
                                    </label>
                                    {{-- Stored URL-encoded for OLD parity (e.g. steve%40itagg.com); rawurldecode
                                         shows the @ back while preserving any literal + (plus-addressing). --}}
                                    <input type="email" class="form-control modern-input" name="txtEmail" id="txtEmail"
                                        maxlength="50" placeholder="notifications@example.com"
                                        value="{{ rawurldecode($userOption->stopcommand_contactemail ?? '') }}">
                                    <small class="text-muted">Email address for STOP notifications</small>
                                </div>
                                <div class="col-12 col-lg-6">
                                    <label class="form-label modern-label">
                                        <i class="material-icons-outlined me-2"
                                            style="font-size: 1rem; vertical-align: middle;">person</i>
                                        STOP Command Contact Name
                                    </label>
                                    {{-- OLD stored the name URL-encoded (spaces as +); urldecode restores them. --}}
                                    <input type="text" class="form-control modern-input" name="txtName" id="txtName"
                                        maxlength="50" placeholder="Contact person name"
                                        value="{{ urldecode($userOption->stopcommand_contactname ?? '') }}">
                                    <small class="text-muted">Primary contact for STOP command management</small>
                                </div>
                            </div>
                            <div class="mt-4">
                                <button type="submit" class="btn btn-modern">
                                    <i class="material-icons-outlined me-2" style="font-size: 1rem;">save</i>
                                    Save Configuration
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Information Section -->
                    <div class="form-section">
                        <h5 class="section-title">Blacklisting Technology</h5>
                        <div class="info-card">
                            <h6>
                                <i class="material-icons-outlined me-2" style="color: #ea6118;">security</i>
                                Automatic STOP Command Protection
                            </h6>
                            <p>
                                The SMS Expert Blacklisting System automatically prevents your account from sending SMS
                                messages to users after they have texted a STOP command to you. This ensures compliance
                                with regulations and respects user preferences.
                            </p>
                            <br>
                            <p class="mb-0">
                                <strong>Need to disable this feature?</strong> Contact our support team at
                                <a href="mailto:care@smsexpert.co.uk" style="color: #ea6118; text-decoration: none;">
                                    care@smsexpert.co.uk
                                </a>
                                or reach out to your account manager.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
@push('js')
    <script>
        // Flash message auto-hide
        setTimeout(function() {
            let flashMessage = document.getElementById('flash-message');
            if (flashMessage) {
                flashMessage.style.opacity = '0';
                flashMessage.style.transition = 'opacity 0.5s ease';
                setTimeout(() => flashMessage.style.display = 'none', 500);
            }
        }, 3000);

        setTimeout(function() {
            let flashMessage = document.getElementById('flash-error-message');
            if (flashMessage) {
                flashMessage.style.opacity = '0';
                flashMessage.style.transition = 'opacity 0.5s ease';
                setTimeout(() => flashMessage.style.display = 'none', 500);
            }
        }, 3000);

        // Form validation enhancement
        document.getElementById('txtEmail').addEventListener('blur', function() {
            const email = this.value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

            if (email && !emailRegex.test(email)) {
                this.style.borderColor = '#dc3545';
                if (!this.nextElementSibling.classList.contains('invalid-feedback')) {
                    const feedback = document.createElement('div');
                    feedback.className = 'invalid-feedback d-block';
                    feedback.textContent = 'Please enter a valid email address';
                    this.parentNode.appendChild(feedback);
                }
            } else {
                this.style.borderColor = '#e9ecef';
                const feedback = this.parentNode.querySelector('.invalid-feedback');
                if (feedback) feedback.remove();
            }
        });

        // URL validation
        document.getElementById('txtURL').addEventListener('blur', function() {
            const url = this.value;
            if (url && !url.startsWith('http')) {
                this.style.borderColor = '#ffc107';
                if (!this.nextElementSibling.classList.contains('warning-feedback')) {
                    const feedback = document.createElement('div');
                    feedback.className = 'warning-feedback d-block text-warning';
                    feedback.style.fontSize = '0.8rem';
                    feedback.textContent = 'URL should start with http:// or https://';
                    this.parentNode.appendChild(feedback);
                }
            } else {
                this.style.borderColor = '#e9ecef';
                const feedback = this.parentNode.querySelector('.warning-feedback');
                if (feedback) feedback.remove();
            }
        });
    </script>
@endpush
