@extends('layouts.app')
@section('title')
    {{ __('Numbers Upload - SMS Expert') }}
@endsection

@push('style')
    <style>
        .upload-container {
            background: #f8fafc;
            min-height: 100vh;
            margin: -2rem;
            padding: 2rem;
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

        .back-btn {
            display: flex;
            align-items: center;
            font-size: 0.85rem;
            padding: 6px 12px;
            border-radius: 6px;
        }

        .back-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(100, 116, 139, 0.3);
            color: white;
        }

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

            0%,
            100% {
                transform: translate(0, 0) rotate(0deg);
            }

            50% {
                transform: translate(-10px, -10px) rotate(1deg);
            }
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

        .icon-primary {
            color: #ea6118;
            font-size: 1.4rem;
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

        .info-section {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border: 1px solid #0891b2;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        .format-image {
            max-width: 100%;
            height: auto;
            border-radius: 12px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            border: 2px solid #e2e8f0;
        }

        .upload-form {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            border: 2px solid #ea6118;
            border-radius: 15px;
            padding: 2rem;
            margin: 2rem 0;
        }

        .form-section {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
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
            color: #293b50;
        }

        .form-control:focus {
            border-color: #ea6118;
            box-shadow: 0 0 0 0.2rem rgba(234, 97, 24, 0.25);
            outline: none;
            transform: translateY(-1px);
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
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
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
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(220, 38, 38, 0.4);
            color: white;
        }

        .requirements-section {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-top: 2rem;
            overflow: hidden;
        }

        .requirements-header {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            padding: 1.5rem;
            border-bottom: 2px solid #e2e8f0;
        }

        .requirements-title {
            color: #293b50;
            font-weight: 700;
            font-size: 1.2rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .requirements-content {
            padding: 2rem;
        }

        .requirements-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .requirements-list li {
            padding: 1rem 0;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            transition: all 0.3s ease;
        }

        .requirements-list li:hover {
            background: #f8fafc;
            margin: 0 -1rem;
            padding-left: 2rem;
            padding-right: 2rem;
        }

        .requirements-list li:last-child {
            border-bottom: none;
        }

        .requirement-icon {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            background: linear-gradient(135deg, #ea6118, #d1520e);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.9rem;
            flex-shrink: 0;
            margin-top: 0.1rem;
        }

        .requirement-text {
            color: #64748b;
            line-height: 1.6;
            font-size: 0.95rem;
        }

        .networks-section {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-top: 2rem;
            overflow: hidden;
        }

        .networks-header {
            background: linear-gradient(135deg, #ea6118, #293b50);
            color: white;
            padding: 1.5rem;
            text-align: center;
        }

        .networks-title {
            color: white;
            font-weight: 700;
            font-size: 1.3rem;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .networks-content {
            padding: 2rem;
        }

        .networks-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .network-item {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .network-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
            border-color: #ea6118;
        }

        .network-name {
            color: #293b50;
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }

        .network-id {
            color: #ea6118;
            font-weight: 700;
            font-size: 1.2rem;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .upload-container {
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

            .back-btn {
                width: 100%;
                justify-content: center;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .networks-grid {
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
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

        .file-drop-zone {
            border: 2px dashed #e2e8f0;
            border-radius: 12px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            background: #f8fafc;
            cursor: pointer;
        }

        .file-drop-zone:hover {
            border-color: #ea6118;
            background: rgba(234, 97, 24, 0.05);
        }

        .file-drop-zone.dragover {
            border-color: #ea6118;
            background: rgba(234, 97, 24, 0.1);
            transform: scale(1.02);
        }

        .upload-icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ea6118, #293b50);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            margin: 0 auto 1rem;
        }
    </style>
@endpush

@section('content')
    <div class="upload-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb-container d-flex align-items-center justify-content-between fade-in">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">
                    <i class="material-icons-outlined icon-primary">upload_file</i>
                    Numbers Upload
                </div>&nbsp;
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="{{ route('dashboard') }}">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="{{ route('numbers.index') }}">Numbers</a>
                        </li>
                        <li class="breadcrumb-item active">Upload</li>
                    </ol>
                </nav>
            </div>
            <button id="backButton" class="btn btn-outline-secondary back-btn">
                <i class="material-icons-outlined me-1">arrow_back</i> Back
            </button>
        </div>

        <!-- Success/Error Messages -->
        @if ($errors->any())
            <div class="alert alert-danger fade-in">
                <i class="material-icons-outlined">error</i>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{!! $error !!}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if (session('success'))
            <div class="alert alert-success fade-in">
                <i class="material-icons-outlined">check_circle</i>
                {!! session('success') !!}
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger fade-in">
                <i class="material-icons-outlined">error</i>
                {!! session('error') !!}
            </div>
        @endif

        <!-- Main Content Card -->
        <div class="main-card fade-in">
            <!-- Page Header -->
            <div class="page-header">
                <div class="page-title">
                    <i class="material-icons-outlined me-2">cloud_upload</i>
                    Bulk Numbers Upload
                </div>
                <div class="page-subtitle">
                    Upload your contact list in CSV format to add multiple numbers at once
                </div>
            </div>

            <!-- Content Section -->
            <div class="content-section">
                <!-- File Format Information -->
                <div class="info-section">
                    <div class="format-image">
                        <img src="{{ asset('assets/images/auth/itagg_fileupload_explain.png') }}"
                            alt="File format explanation" class="img-fluid">
                    </div>
                    <p class="text-muted mb-0">
                        Follow the format shown above. Each line must contain: Name, Mobile Number, Network ID (or 0 if
                        unknown),
                        Favourite status (y/n), and optional Group name.
                    </p>
                </div>

                <!-- Upload Form -->
                <div class="upload-form">
                    <div class="form-section">
                        <form enctype="multipart/form-data" action="{{ route('upload.file') }}" method="POST"
                            id="uploadForm">
                            @csrf

                            <div class="file-drop-zone" onclick="document.getElementById('userfile').click()">
                                <div class="upload-icon">
                                    <i class="material-icons-outlined">cloud_upload</i>
                                </div>
                                <h5 class="mb-2" style="color: #293b50;">Choose File to Upload</h5>
                                <p class="text-muted mb-3">Click here to select your CSV file or drag and drop it</p>
                                <input type="file" class="form-control d-none" id="userfile" name="userfile"
                                    accept=".csv" required>
                                <div id="fileName" class="mt-2" style="display: none;">
                                    <i class="material-icons-outlined">description</i>
                                    <span id="fileNameText"></span>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary" name="submit">
                                    <i class="material-icons-outlined">send</i>
                                    Upload File
                                </button>
                                <a href="{{ route('numbers.index') }}" class="btn btn-danger">
                                    <i class="material-icons-outlined">cancel</i>
                                    Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Requirements Section -->
                <div class="requirements-section">
                    <div class="requirements-header">
                        <h5 class="requirements-title">
                            <i class="material-icons-outlined">checklist</i>
                            File Requirements & Guidelines
                        </h5>
                    </div>
                    <div class="requirements-content">
                        <ul class="requirements-list">
                            <li>
                                <div class="requirement-icon">
                                    <i class="material-icons-outlined">error</i>
                                </div>
                                <div class="requirement-text">
                                    If there is a formatting error, you will be told the problem and the line number to help
                                    you edit and re-submit the file.
                                </div>
                            </li>
                            <li>
                                <div class="requirement-icon">
                                    <i class="material-icons-outlined">block</i>
                                </div>
                                <div class="requirement-text">
                                    Numbers already in the database will be ignored to prevent duplicates.
                                </div>
                            </li>
                            <li>
                                <div class="requirement-icon">
                                    <i class="material-icons-outlined">text_format</i>
                                </div>
                                <div class="requirement-text">
                                    Recipient names and numbers must not contain a comma.
                                </div>
                            </li>
                            <li>
                                <div class="requirement-icon">
                                    <i class="material-icons-outlined">network_cell</i>
                                </div>
                                <div class="requirement-text">
                                    <strong>Remember:</strong> Premium rate messages will require the
                                    <strong>correct</strong> network ID for proper delivery.
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Network IDs Section -->
                <div class="networks-section">
                    <div class="networks-header">
                        <h5 class="networks-title">Network IDs Reference</h5>
                    </div>
                    <div class="networks-content">
                        <div class="networks-grid">
                            @forelse ($mobnetworks as $network)
                                <div class="network-item">
                                    <div class="network-name">{{ $network->Name }}</div>
                                    <div class="network-id">ID: {{ $network->id }}</div>
                                </div>
                            @empty
                                <div class="network-item">
                                    <div class="network-name">Unknown Network</div>
                                    <div class="network-id">ID: 0</div>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
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
            const fileInput = document.getElementById('userfile');
            const dropZone = document.querySelector('.file-drop-zone');
            const fileName = document.getElementById('fileName');
            const fileNameText = document.getElementById('fileNameText');
            const form = document.getElementById('uploadForm');

            // File input change handler
            fileInput.addEventListener('change', function() {
                if (this.files && this.files[0]) {
                    const file = this.files[0];
                    fileNameText.textContent = file.name;
                    fileName.style.display = 'block';
                    dropZone.style.borderColor = '#28a745';
                    dropZone.style.background = 'rgba(40, 167, 69, 0.1)';
                }
            });

            // Drag and drop functionality
            dropZone.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });

            dropZone.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
            });

            dropZone.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');

                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    fileInput.files = files;
                    fileInput.dispatchEvent(new Event('change'));
                }
            });

            // Form submission handling
            form.addEventListener('submit', function() {
                const submitButton = form.querySelector('button[type="submit"]');
                if (submitButton) {
                    const originalText = submitButton.innerHTML;
                    submitButton.innerHTML =
                        '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Uploading...';
                    submitButton.disabled = true;

                    // Re-enable after timeout in case of errors
                    setTimeout(() => {
                        submitButton.innerHTML = originalText;
                        submitButton.disabled = false;
                    }, 10000);
                }
            });

            // Back button functionality
            // document.getElementById('backButton').addEventListener('click', function() {
            //     window.history.back();
            // });

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

            // Network items hover effects
            const networkItems = document.querySelectorAll('.network-item');
            networkItems.forEach(item => {
                item.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-3px) scale(1.02)';
                });

                item.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });

            // Requirements list hover effects
            const requirementItems = document.querySelectorAll('.requirements-list li');
            requirementItems.forEach(item => {
                item.addEventListener('mouseenter', function() {
                    const icon = this.querySelector('.requirement-icon');
                    if (icon) {
                        icon.style.transform = 'scale(1.1) rotate(5deg)';
                    }
                });

                item.addEventListener('mouseleave', function() {
                    const icon = this.querySelector('.requirement-icon');
                    if (icon) {
                        icon.style.transform = 'scale(1) rotate(0deg)';
                    }
                });
            });

            console.log('Modern Numbers Upload page loaded successfully!');
        });
    </script>
@endpush
