@extends('layouts.app')

@section('title', $contract->title . ' - SMS Expert')

@push('style')
    <style>
        .contract-view-container {
            background: #f8fafc;
            min-height: 100vh;
            margin: -2rem;
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

        .breadcrumb-title {
            color: #293b50;
            font-weight: 700;
            font-size: 1.1rem;
        }

        .breadcrumb-item a {
            color: #ea6118;
            text-decoration: none;
        }

        .breadcrumb-item.active {
            color: #64748b;
        }

        .contract-content-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            overflow: hidden;
            position: relative;
        }

        .contract-content-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #ea6118, #293b50);
        }

        .contract-header-section {
            background: linear-gradient(135deg, #293b50, #1f2c3d);
            color: white;
            padding: 2rem;
        }

        .contract-body-content {
            padding: 2rem;
            font-size: 1rem;
            line-height: 1.8;
            color: #334155;
        }

        .contract-body-content h1,
        .contract-body-content h2,
        .contract-body-content h3,
        .contract-body-content h4 {
            color: #293b50;
            margin-top: 2rem;
            margin-bottom: 1rem;
        }

        .contract-body-content ul,
        .contract-body-content ol {
            margin-left: 2rem;
        }

        /* PDF Viewer Styles */
        .pdf-viewer-container {
            background: #f1f5f9;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .pdf-viewer {
            width: 100%;
            height: 700px;
            border: none;
            border-radius: 8px;
            background: white;
        }

        .file-download-section {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border: 2px solid #0ea5e9;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            margin-bottom: 2rem;
        }

        .file-download-section .file-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }

        .file-download-section .file-icon i {
            font-size: 40px;
            color: white;
        }

        .file-download-section h4 {
            color: #0c4a6e;
            margin-bottom: 0.5rem;
        }

        .file-download-section p {
            color: #64748b;
            margin-bottom: 1.5rem;
        }

        .btn-download {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(14, 165, 233, 0.4);
            color: white;
        }

        .btn-view-pdf {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-left: 1rem;
        }

        .btn-view-pdf:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(22, 163, 74, 0.4);
            color: white;
        }

        .signature-section {
            background: white;
            border-radius: 15px;
            border: 2px solid #ea6118;
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
        }

        .signature-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #ea6118, #d1520e);
            border-radius: 15px 15px 0 0;
        }

        .signature-pad-container {
            border: 2px dashed #e2e8f0;
            border-radius: 12px;
            background: #f8fafc;
            padding: 1rem;
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .signature-pad {
            width: 100%;
            height: 200px;
            border: 2px solid #cbd5e1;
            border-radius: 8px;
            background: white;
            cursor: crosshair;
        }

        .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #ea6118;
            box-shadow: 0 0 0 0.2rem rgba(234, 97, 24, 0.25);
        }

        .form-label {
            color: #293b50;
            font-weight: 600;
            margin-bottom: 0.75rem;
        }

        .form-check-input:checked {
            background-color: #ea6118;
            border-color: #ea6118;
        }

        .btn-primary {
            background: linear-gradient(135deg, #ea6118, #d1520e);
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(234, 97, 24, 0.4);
            background: linear-gradient(135deg, #d1520e, #b8450c);
        }

        .btn-outline-secondary {
            border: 2px solid #6c757d;
            color: #6c757d;
            padding: 0.75rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-outline-secondary:hover {
            background: #6c757d;
            color: white;
        }

        .signed-badge {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 2rem;
        }

        .signed-icon {
            font-size: 64px !important;
            margin-bottom: 1rem;
        }

        .signature-preview {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
        }

        .signature-preview img {
            max-width: 300px;
            max-height: 100px;
        }

        .alert {
            border-radius: 12px;
            border: none;
        }

        .alert-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
        }

        .alert-success {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
        }

        .back-btn {
            display: flex;
            align-items: center;
            font-size: 0.85rem;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .info-box {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .info-box i {
            color: #0066cc;
        }

        .content-tabs {
            display: flex;
            gap: 0;
            margin-bottom: 0;
            border-bottom: 2px solid #e2e8f0;
        }

        .content-tab {
            padding: 1rem 2rem;
            background: #f8fafc;
            border: none;
            cursor: pointer;
            font-weight: 600;
            color: #64748b;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .content-tab:first-child {
            border-radius: 10px 0 0 0;
        }

        .content-tab:last-child {
            border-radius: 0 10px 0 0;
        }

        .content-tab.active {
            background: white;
            color: #ea6118;
            border-bottom: 3px solid #ea6118;
        }

        .content-tab:hover:not(.active) {
            background: #f1f5f9;
            color: #293b50;
        }

        .tab-content-section {
            display: none;
        }

        .tab-content-section.active {
            display: block;
        }
    </style>
@endpush

@section('content')
    <div class="contract-view-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb-container d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">{{ $contract->title }}</div> &nbsp;
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0">
                        <li class="breadcrumb-item">
                            <a href="{{ route('dashboard') }}">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="{{ route('customer.contracts.index') }}">Contracts</a>
                        </li>
                        <li class="breadcrumb-item active">{{ $contract->title }}</li>
                    </ol>
                </nav>
            </div>
            <button id="backButton" class="btn btn-outline-secondary back-btn">
                <i class="material-icons-outlined me-1">arrow_back</i> Back
            </button>
            {{-- <a href="{{ route('customer.contracts.index') }}" class="btn btn-outline-secondary back-btn">
            <i class="material-icons-outlined align-middle me-1">arrow_back</i>
            Back to Contracts
        </a> --}}
        </div>

        <!-- Alert Messages -->
        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                <i class="material-icons-outlined align-middle me-2">error</i>
                {{ session('error') }}
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"
                    aria-label="Close"></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                <i class="material-icons-outlined align-middle me-2">error</i>
                <strong>Please fix the following errors:</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"
                    aria-label="Close"></button>
            </div>
        @endif

        <!-- Contract Content -->
        <div class="contract-content-card">
            <div class="contract-header-section">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h2 class="mb-2">
                            <i class="material-icons-outlined align-middle me-2">description</i>
                            {{ $contract->title }}
                        </h2>
                        <p class="mb-0" style="opacity: 0.9;">
                            Type: <strong>{{ ucfirst($contract->type) }}</strong> |
                            Version: <strong>{{ $contract->version }}</strong> |
                            Last Updated: <strong>{{ $contract->updated_at->format('d M Y') }}</strong>
                            @if ($contract->hasFile())
                                | <i class="material-icons-outlined align-middle" style="font-size: 16px;">attach_file</i>
                                <strong>{{ $contract->file_name }}</strong> ({{ $contract->getFileSizeFormatted() }})
                            @endif
                        </p>
                    </div>
                    @if ($isSigned)
                        <span style="background: rgba(22, 163, 74, 0.3); padding: 0.5rem 1rem; border-radius: 20px;">
                            <i class="material-icons-outlined align-middle me-1">check_circle</i>
                            Signed
                        </span>
                    @elseif($contract->requires_signature)
                        <span style="background: rgba(245, 158, 11, 0.3); padding: 0.5rem 1rem; border-radius: 20px;">
                            <i class="material-icons-outlined align-middle me-1">pending</i>
                            Signature Required
                        </span>
                    @endif
                </div>
            </div>

            @php
                $hasContent = !empty($contract->content) && $contract->content !== '<p><br></p>';
                $hasFile = $contract->hasFile();
                $isPdf = $hasFile && strtolower($contract->file_type) === 'pdf';
            @endphp

            @if ($hasContent && $hasFile)
                <!-- Both content and file - show tabs -->
                <div class="content-tabs">
                    <button class="content-tab active" data-tab="content">
                        <i class="material-icons-outlined" style="font-size: 18px;">article</i>
                        Contract Content
                    </button>
                    <button class="content-tab" data-tab="file">
                        <i class="material-icons-outlined" style="font-size: 18px;">
                            @if ($isPdf)
                                picture_as_pdf
                            @else
                                description
                            @endif
                        </i>
                        Contract File
                    </button>
                </div>

                <div id="tab-content" class="tab-content-section active">
                    <div class="contract-body-content">
                        {!! $contract->content !!}
                    </div>
                </div>

                <div id="tab-file" class="tab-content-section">
                    @if ($isPdf)
                        <div class="pdf-viewer-container">
                            <iframe src="{{ route('customer.contracts.view-file', $contract->id) }}"
                                class="pdf-viewer"></iframe>
                        </div>
                    @endif
                    <div class="file-download-section" style="margin: 1rem;">
                        <div class="file-icon">
                            <i class="material-icons-outlined">
                                @if ($isPdf)
                                    picture_as_pdf
                                @else
                                    description
                                @endif
                            </i>
                        </div>
                        <h4>{{ $contract->file_name }}</h4>
                        <p>File Size: {{ $contract->getFileSizeFormatted() }} | Type:
                            {{ strtoupper($contract->file_type) }}</p>
                        <a href="{{ route('customer.contracts.download', $contract->id) }}" class="btn-download">
                            <i class="material-icons-outlined">download</i>
                            Download Contract
                        </a>
                        @if ($isPdf)
                            <a href="{{ route('customer.contracts.view-file', $contract->id) }}" target="_blank"
                                class="btn-view-pdf">
                                <i class="material-icons-outlined">open_in_new</i>
                                Open in New Tab
                            </a>
                        @endif
                    </div>
                </div>
            @elseif($hasFile)
                <!-- Only file - show file viewer/download -->
                @if ($isPdf)
                    <div class="pdf-viewer-container" style="margin: 1rem;">
                        <iframe src="{{ route('customer.contracts.view-file', $contract->id) }}"
                            class="pdf-viewer"></iframe>
                    </div>
                @endif
                <div class="file-download-section" style="margin: 1rem;">
                    <div class="file-icon">
                        <i class="material-icons-outlined">
                            @if ($isPdf)
                                picture_as_pdf
                            @else
                                description
                            @endif
                        </i>
                    </div>
                    <h4>{{ $contract->file_name }}</h4>
                    <p>File Size: {{ $contract->getFileSizeFormatted() }} | Type: {{ strtoupper($contract->file_type) }}
                    </p>
                    <a href="{{ route('customer.contracts.download', $contract->id) }}" class="btn-download">
                        <i class="material-icons-outlined">download</i>
                        Download Contract
                    </a>
                    @if ($isPdf)
                        <a href="{{ route('customer.contracts.view-file', $contract->id) }}" target="_blank"
                            class="btn-view-pdf">
                            <i class="material-icons-outlined">open_in_new</i>
                            Open in New Tab
                        </a>
                    @endif
                </div>
            @elseif($hasContent)
                <!-- Only content -->
                <div class="contract-body-content">
                    {!! $contract->content !!}
                </div>
            @else
                <!-- No content or file -->
                <div class="contract-body-content text-center py-5">
                    <i class="material-icons-outlined" style="font-size: 64px; color: #cbd5e1;">description</i>
                    <h5 class="mt-3 text-muted">No contract content available</h5>
                </div>
            @endif
        </div>

        @if ($isSigned)
            <!-- Already Signed -->
            <div class="signed-badge">
                <i class="material-icons-outlined signed-icon">verified</i>
                <h3 class="mb-2">Contract Signed Successfully</h3>
                <p class="mb-1">You signed this contract on
                    <strong>{{ $signature->signed_at->format('d M Y, h:i A') }}</strong></p>
                <p class="mb-0">Signed by: <strong>{{ $signature->signee_name }}</strong>
                    ({{ $signature->signee_position }})</p>
                @if ($signature->signature_data)
                    <div class="signature-preview mt-3 mx-auto" style="max-width: 350px;">
                        <p class="mb-2 text-dark"><small>Your Signature:</small></p>
                        <img src="{{ $signature->signature_data }}" alt="Digital Signature" class="img-fluid">
                    </div>
                @endif
            </div>
        @else
            @if ($contract->requires_signature)
                <!-- Signature Form -->
                <div class="signature-section">
                    <h4 class="mb-3" style="color: #293b50;">
                        <i class="material-icons-outlined align-middle me-2">edit</i>
                        Sign this Contract Electronically
                    </h4>

                    <div class="info-box">
                        <i class="material-icons-outlined align-middle me-2">info</i>
                        By signing below, you acknowledge that you have read, understood, and agree to all the terms and
                        conditions outlined in this contract. Your digital signature is legally binding.
                    </div>

                    <form action="{{ route('customer.contracts.sign', $contract->id) }}" method="POST"
                        id="signatureForm">
                        @csrf

                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label for="signee_name" class="form-label">
                                    <i class="material-icons-outlined align-middle me-1"
                                        style="font-size: 18px;">person</i>
                                    Full Name <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control @error('signee_name') is-invalid @enderror"
                                    id="signee_name" name="signee_name"
                                    value="{{ old('signee_name', session('user_info.contactname') ? urldecode(str_replace('+', ' ', session('user_info.contactname'))) : '') }}"
                                    placeholder="Enter your full name" required>
                                @error('signee_name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="signee_email" class="form-label">
                                    <i class="material-icons-outlined align-middle me-1"
                                        style="font-size: 18px;">email</i>
                                    Email Address <span class="text-danger">*</span>
                                </label>
                                <input type="email" class="form-control @error('signee_email') is-invalid @enderror"
                                    id="signee_email" name="signee_email"
                                    value="{{ old('signee_email', session('user_info.contactemail') ?? '') }}"
                                    placeholder="your.email@company.com" required>
                                @error('signee_email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-md-4">
                                <label for="signee_position" class="form-label">
                                    <i class="material-icons-outlined align-middle me-1" style="font-size: 18px;">work</i>
                                    Position/Title <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control @error('signee_position') is-invalid @enderror"
                                    id="signee_position" name="signee_position" value="{{ old('signee_position') }}"
                                    placeholder="e.g., Director, Manager, CEO" required>
                                @error('signee_position')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Signature Pad -->
                        <div class="signature-pad-container">
                            <label class="form-label">
                                <i class="material-icons-outlined align-middle me-1">gesture</i>
                                Digital Signature <span class="text-danger">*</span>
                            </label>
                            <p class="text-muted small mb-3">Please draw your signature in the box below using your mouse
                                or touchscreen</p>
                            <canvas id="signaturePad" class="signature-pad"></canvas>
                            <input type="hidden" name="signature_data" id="signatureData">
                            @error('signature_data')
                                <div class="text-danger mt-2">{{ $message }}</div>
                            @enderror
                            <div class="mt-3">
                                <button type="button" class="btn btn-sm btn-outline-danger" id="clearSignature">
                                    <i class="material-icons-outlined align-middle" style="font-size: 16px;">clear</i>
                                    Clear Signature
                                </button>
                            </div>
                        </div>

                        <!-- Agreement Checkbox -->
                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="agreeTerms" name="agree_terms" required>
                            <label class="form-check-label" for="agreeTerms">
                                <strong>I confirm that I have read, understood, and agree to all terms and conditions
                                    outlined in this contract.</strong>
                                <span class="text-danger">*</span>
                            </label>
                        </div>

                        <!-- Submit Button -->
                        <div class="d-flex gap-3">
                            <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                                <i class="material-icons-outlined align-middle me-2">check_circle</i>
                                Sign Contract
                            </button>
                            <a href="{{ route('customer.contracts.index') }}" class="btn btn-outline-secondary">
                                <i class="material-icons-outlined align-middle me-2">cancel</i>
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            @else
                <!-- No Signature Required -->
                <div class="info-box">
                    <i class="material-icons-outlined align-middle me-2">info</i>
                    <strong>Information:</strong> This contract is for your reference only and does not require a digital
                    signature.
                </div>
            @endif
        @endif
    </div>
@endsection

@push('js')
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab switching functionality
            const tabs = document.querySelectorAll('.content-tab');
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    const targetTab = this.dataset.tab;

                    // Update active tab
                    tabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');

                    // Show/hide content
                    document.querySelectorAll('.tab-content-section').forEach(section => {
                        section.classList.remove('active');
                    });
                    document.getElementById('tab-' + targetTab).classList.add('active');
                });
            });

            // Signature Pad functionality
            const canvas = document.getElementById('signaturePad');
            const form = document.getElementById('signatureForm');
            const clearButton = document.getElementById('clearSignature');
            const submitBtn = document.getElementById('submitBtn');
            const agreeTerms = document.getElementById('agreeTerms');

            if (canvas && form) {
                // Initialize Signature Pad
                const signaturePad = new SignaturePad(canvas, {
                    backgroundColor: 'rgb(255, 255, 255)',
                    penColor: 'rgb(0, 0, 0)',
                    minWidth: 1,
                    maxWidth: 3
                });

                // Resize canvas to fit container
                function resizeCanvas() {
                    const ratio = Math.max(window.devicePixelRatio || 1, 1);
                    const containerWidth = canvas.parentElement.offsetWidth - 32; // Account for padding
                    canvas.width = containerWidth * ratio;
                    canvas.height = 200 * ratio;
                    canvas.style.width = containerWidth + 'px';
                    canvas.style.height = '200px';
                    canvas.getContext("2d").scale(ratio, ratio);
                    signaturePad.clear();
                }

                window.addEventListener("resize", resizeCanvas);
                resizeCanvas();

                // Clear signature
                clearButton.addEventListener('click', function() {
                    signaturePad.clear();
                    updateSubmitButton();
                });

                // Check if signature pad has content
                function hasSignature() {
                    return !signaturePad.isEmpty();
                }

                // Update submit button state
                function updateSubmitButton() {
                    submitBtn.disabled = !(agreeTerms.checked && hasSignature());
                }

                // Listen for signature changes
                signaturePad.addEventListener('endStroke', function() {
                    updateSubmitButton();
                });

                // Agreement checkbox change
                agreeTerms.addEventListener('change', function() {
                    updateSubmitButton();
                });

                // Form submission
                form.addEventListener('submit', function(e) {
                    if (signaturePad.isEmpty()) {
                        e.preventDefault();
                        alert('Please provide your signature before submitting.');
                        return false;
                    }

                    if (!agreeTerms.checked) {
                        e.preventDefault();
                        alert('Please confirm that you agree to the terms and conditions.');
                        return false;
                    }

                    // Save signature data as base64 PNG
                    document.getElementById('signatureData').value = signaturePad.toDataURL('image/png');

                    // Disable button to prevent double submission
                    submitBtn.disabled = true;
                    submitBtn.innerHTML =
                        '<span class="spinner-border spinner-border-sm me-2"></span> Processing...';
                });

                // Initial state
                updateSubmitButton();
            }

            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                const alerts = document.querySelectorAll('.alert');
                alerts.forEach(function(alert) {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    alert.style.transition = 'all 0.3s ease';
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                });
            }, 5000);

            console.log('Contract view page loaded successfully!');
        });
    </script>
@endpush
