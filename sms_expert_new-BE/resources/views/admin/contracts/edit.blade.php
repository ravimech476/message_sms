@extends('admin.layouts.app')
@section('title', 'CRM')

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
    
    .card-header-custom {
        background: #293b50;
        color: white;
        padding: 15px 20px;
        border-radius: 10px 10px 0 0;
        font-weight: 500;
        font-size: 18px;
    }
    
    .info-card {
        background: #f8f9fa;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 20px;
    }
    
    .info-item {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        margin-right: 20px;
        color: #293b50;
        font-size: 14px;
    }
    
    .info-item i {
        color: #ea6118;
        font-size: 18px;
    }
    
    .form-label {
        color: #293b50;
        font-weight: 600;
        margin-bottom: 8px;
    }
    
    .form-control, .form-select {
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        padding: 10px 15px;
        transition: all 0.3s ease;
    }
    
    .form-control:focus, .form-select:focus {
        border-color: #ea6118;
        box-shadow: 0 0 0 0.2rem rgba(234, 97, 24, 0.25);
    }
    
    .form-check-input:checked {
        background-color: #ea6118;
        border-color: #ea6118;
    }
    
    .btn-submit {
        background: linear-gradient(135deg, #ea6118, #d1520e);
        color: white;
        border: none;
        padding: 10px 30px;
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
        padding: 10px 30px;
        border-radius: 8px;
        font-weight: 500;
    }
    
    .btn-cancel:hover {
        background: #5a6268;
        color: white;
    }
    
    .form-section {
        padding: 20px;
    }
    
    .info-alert {
        background: #e7f3ff;
        border: 1px solid #b3d9ff;
        border-radius: 8px;
        padding: 12px 15px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .info-alert i {
        color: #0066cc;
        font-size: 20px;
    }

    /* File Upload Styles */
    .file-upload-container {
        border: 2px dashed #e2e8f0;
        border-radius: 10px;
        padding: 30px;
        text-align: center;
        transition: all 0.3s ease;
        background: #f8fafc;
        cursor: pointer;
    }

    .file-upload-container:hover {
        border-color: #ea6118;
        background: #fff5f0;
    }

    .file-upload-container.dragover {
        border-color: #ea6118;
        background: #fff5f0;
    }

    .file-upload-container .upload-icon {
        font-size: 48px;
        color: #ea6118;
        margin-bottom: 15px;
    }

    .file-upload-container .upload-text {
        color: #64748b;
        margin-bottom: 10px;
    }

    .file-upload-container .upload-hint {
        color: #94a3b8;
        font-size: 0.85rem;
    }

    .file-preview {
        display: none;
        background: #f0fdf4;
        border: 2px solid #16a34a;
        border-radius: 10px;
        padding: 15px;
        margin-top: 15px;
    }

    .file-preview.show {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .file-preview .file-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .file-preview .file-icon {
        font-size: 36px;
        color: #16a34a;
    }

    .file-preview .file-name {
        font-weight: 600;
        color: #293b50;
    }

    .file-preview .file-size {
        color: #64748b;
        font-size: 0.85rem;
    }

    .file-preview .remove-file {
        background: #dc2626;
        color: white;
        border: none;
        border-radius: 50%;
        width: 32px;
        height: 32px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
    }

    .file-preview .remove-file:hover {
        background: #b91c1c;
        transform: scale(1.1);
    }

    /* Existing file display */
    .existing-file {
        background: #f0f9ff;
        border: 2px solid #0ea5e9;
        border-radius: 10px;
        padding: 15px;
        margin-bottom: 15px;
    }

    .existing-file .file-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .existing-file .file-icon {
        font-size: 36px;
        color: #0ea5e9;
    }

    .existing-file .file-name {
        font-weight: 600;
        color: #293b50;
    }

    .existing-file .file-size {
        color: #64748b;
        font-size: 0.85rem;
    }
    
    /* Select2 customization for better multi-select display */
    .select2-container--bootstrap-5 .select2-selection--multiple {
        min-height: 45px !important;
        max-height: 120px !important;
        overflow-y: auto !important;
        border: 2px solid #e2e8f0 !important;
        border-radius: 8px !important;
        padding: 5px !important;
    }
    
    .select2-container--bootstrap-5 .select2-selection--multiple:focus-within {
        border-color: #ea6118 !important;
        box-shadow: 0 0 0 0.2rem rgba(234, 97, 24, 0.25) !important;
    }
    
    .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
        background-color: #ea6118 !important;
        border: 1px solid #d1520e !important;
        color: white !important;
        padding: 3px 8px !important;
        margin: 3px !important;
        border-radius: 4px !important;
        font-size: 13px !important;
    }
    
    .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice__remove {
        color: white !important;
        margin-right: 5px !important;
        font-weight: bold !important;
    }
    
    .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice__remove:hover {
        color: #ffcccc !important;
    }

    /* Select2 dropdown z-index fix */
    .select2-container {
        z-index: 1050 !important;
    }
    
    .select2-dropdown {
        z-index: 1060 !important;
        border: 2px solid #ea6118 !important;
        border-radius: 8px !important;
    }
    
    .select2-results__option--highlighted {
        background-color: #ea6118 !important;
    }
    
    /* Summernote customization */
    .note-editor.note-frame {
        border: 2px solid #e2e8f0;
        border-radius: 8px;
    }
    
    .note-editor.note-frame:focus-within {
        border-color: #ea6118;
    }
    
    .note-toolbar {
        background: #f8f9fa !important;
        border-bottom: 2px solid #e2e8f0 !important;
    }
    
    .note-btn {
        background: white !important;
        border: 1px solid #e2e8f0 !important;
    }
    
    .note-btn:hover {
        background: #ea6118 !important;
        color: white !important;
        border-color: #ea6118 !important;
    }
    
    /* Fix ALL Summernote dropdown menus z-index */
    .note-popover.popover {
        z-index: 9999 !important;
    }
    
    .note-modal {
        z-index: 10000 !important;
    }
    
    .dropdown-menu.show,
    .note-dropdown-menu.show {
        z-index: 9999 !important;
        display: block !important;
    }
    
    .note-color .dropdown-menu,
    .note-fontname .dropdown-menu,
    .note-fontsize .dropdown-menu {
        z-index: 9999 !important;
    }
</style>
@endpush

@section('content')
<main class="main-wrapper" id="main-wrapper">
    <div class="main-content">
        <!--breadcrumb-->
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3 breadcolor" style="position: relative;">
            <div class="breadcrumb-title pe-3 title-name">Edit Contract</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0">
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.dashboard') }}">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.contracts.index') }}">Contracts</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Edit</li>
                    </ol>
                </nav>
            </div>
            <div class="me-2 back-button-container" style="position: absolute; right: 0; top: 50%; transform: translateY(-50%);">
                <a href="{{ route('admin.contracts.index') }}" class="btn btn-primary btn-sm">
                    <i class="bx bx-arrow-back"></i> Back
                </a>
            </div>
        </div>
        <!--end breadcrumb-->

        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="material-icons-outlined align-middle me-2">check_circle</i>
                {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="material-icons-outlined align-middle me-2">error</i>
                <strong>Please fix the following errors:</strong>
                <ul class="mb-0 mt-2">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        @php
            // Get assigned customer IDs from many-to-many relationship
            $assignedCustomerIds = $contract->customers->pluck('id')->toArray();
            
            // Also check legacy customer_id if no customers in pivot
            if (empty($assignedCustomerIds) && $contract->customer_id) {
                $assignedCustomerIds = [$contract->customer_id];
            }
            
            // Build assigned info text
            $assignedCount = count($assignedCustomerIds);
            if ($assignedCount == 0) {
                $assignedToText = 'All Customers';
            } elseif ($assignedCount == 1) {
                $cust = $contract->customers->first() ?? $contract->customer;
                if ($cust) {
                    $assignedToText = $cust->contactname ? urldecode(str_replace('+', ' ', $cust->contactname)) : ($cust->busname ? urldecode(str_replace('+', ' ', $cust->busname)) : $cust->uname);
                } else {
                    $assignedToText = '1 Customer';
                }
            } else {
                $assignedToText = $assignedCount . ' Customers';
            }
        @endphp

        <!-- Contract Info Card -->
        <div class="info-card">
            <div class="info-item">
                <i class="material-icons-outlined">info</i>
                <span><strong>Version:</strong> {{ $contract->version }}</span>
            </div>
            <div class="info-item">
                <i class="material-icons-outlined">calendar_today</i>
                <span><strong>Created:</strong> {{ $contract->created_at->format('d M Y') }}</span>
            </div>
            <div class="info-item">
                <i class="material-icons-outlined">update</i>
                <span><strong>Last Updated:</strong> {{ $contract->updated_at->format('d M Y H:i') }}</span>
            </div>
            <div class="info-item">
                <i class="material-icons-outlined">how_to_reg</i>
                <span><strong>Signatures:</strong> {{ $contract->signatures->count() }}</span>
            </div>
            <div class="info-item">
                <i class="material-icons-outlined">person</i>
                <span><strong>Assigned To:</strong> {{ $assignedToText }}</span>
            </div>
            @if($contract->hasFile())
            <div class="info-item">
                <i class="material-icons-outlined">attach_file</i>
                <span><strong>File:</strong> {{ $contract->file_name }} ({{ $contract->getFileSizeFormatted() }})</span>
            </div>
            @endif
        </div>

        <!-- Form Card -->
        <div class="card form-card">
            <div class="card-header-custom">
                <i class="material-icons-outlined align-middle me-2">edit</i>
                Edit Contract: {{ $contract->title }}
            </div>
            <div class="card-body form-section">
                <form action="{{ route('admin.contracts.update', $contract->id) }}" method="POST" enctype="multipart/form-data" id="contractForm">
                    @csrf
                    @method('PUT')

                    <div class="row">
                        <!-- Title -->
                        <div class="col-md-12 mb-3">
                            <label for="title" class="form-label">
                                Contract Title <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control @error('title') is-invalid @enderror" 
                                   id="title" 
                                   name="title" 
                                   value="{{ old('title', $contract->title) }}" 
                                   required>
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Type -->
                        <div class="col-md-4 mb-3">
                            <label for="type" class="form-label">
                                Contract Type <span class="text-danger">*</span>
                            </label>
                            <select class="form-select @error('type') is-invalid @enderror" 
                                    id="type" 
                                    name="type" 
                                    required>
                                <option value="">Select Type</option>
                                <option value="main" {{ old('type', $contract->type) == 'main' ? 'selected' : '' }}>Main Client Contract</option>
                                <option value="addendum" {{ old('type', $contract->type) == 'addendum' ? 'selected' : '' }}>Addendum</option>
                                <option value="privacy_policy" {{ old('type', $contract->type) == 'privacy_policy' ? 'selected' : '' }}>Privacy Policy</option>
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Customer Assignment (Multi-Select with Search) -->
                        <div class="col-md-8 mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <label for="customer_ids" class="form-label mb-0">
                                    <i class="material-icons-outlined align-middle" style="font-size: 18px;">group</i>
                                    Assign To Customers
                                </label>
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="select-all-customers">
                                        <i class="material-icons-outlined" style="font-size: 14px;">done_all</i>
                                        Select All
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" id="clear-all-customers">
                                        <i class="material-icons-outlined" style="font-size: 14px;">clear</i>
                                        Clear All
                                    </button>
                                </div>
                            </div>
                            <select class="form-select @error('customer_ids') is-invalid @enderror" 
                                    id="customer_ids" 
                                    name="customer_ids[]" 
                                    multiple="multiple"
                                    data-placeholder="Select customers (leave empty for all customers)">
                                @forelse($customers as $customer)
                                    <option value="{{ $customer->id }}" 
                                            {{ in_array($customer->id, old('customer_ids', $assignedCustomerIds)) ? 'selected' : '' }}>
                                        {{ $customer->display_name }} ({{ $customer->display_email }})
                                    </option>
                                @empty
                                    <option value="" disabled>No customers with valid contact info found</option>
                                @endforelse
                            </select>
                            <small class="text-muted d-flex justify-content-between align-items-center mt-1">
                                <span>
                                    <i class="material-icons-outlined align-middle" style="font-size: 14px;">info</i>
                                    Leave empty to assign to all customers
                                </span>
                                <span id="customer-count" class="badge bg-primary" style="display: none;">
                                    0 selected
                                </span>
                            </small>
                            @error('customer_ids')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Status Options -->
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Options</label>
                            <div class="d-flex gap-4">
                                <div class="form-check">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           id="is_active" 
                                           name="is_active" 
                                           value="1" 
                                           {{ old('is_active', $contract->is_active) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="is_active">
                                        Active
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           id="requires_signature" 
                                           name="requires_signature" 
                                           value="1" 
                                           {{ old('requires_signature', $contract->requires_signature) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="requires_signature">
                                        Requires Signature
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Info Alert -->
                        <div class="col-md-12">
                            <div class="info-alert">
                                <i class="material-icons-outlined">info</i>
                                <div>
                                    <strong>Multi-Customer Assignment:</strong> 
                                    Leave customers field empty to make this contract available to ALL customers, 
                                    or select specific customers to assign it only to them.
                                </div>
                            </div>
                        </div>

                        <!-- Contract File Section -->
                        <div class="col-md-12 mb-3">
                            <label class="form-label">
                                <i class="material-icons-outlined align-middle" style="font-size: 18px;">attach_file</i>
                                Contract File
                            </label>
                            
                            @if($contract->hasFile())
                                <!-- Existing File Display -->
                                <div class="existing-file" id="existingFile">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div class="file-info">
                                            <i class="material-icons-outlined file-icon">
                                                {{ strtolower($contract->file_type) == 'pdf' ? 'picture_as_pdf' : 'description' }}
                                            </i>
                                            <div>
                                                <div class="file-name">{{ $contract->file_name }}</div>
                                                <div class="file-size">{{ $contract->getFileSizeFormatted() }} | {{ strtoupper($contract->file_type) }}</div>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <a href="{{ route('admin.contracts.download', $contract->id) }}" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="material-icons-outlined" style="font-size: 16px;">download</i>
                                                Download
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-warning" id="replaceFileBtn">
                                                <i class="material-icons-outlined" style="font-size: 16px;">swap_horiz</i>
                                                Replace
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" id="removeExistingFile">
                                                <i class="material-icons-outlined" style="font-size: 16px;">delete</i>
                                                Remove
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="remove_file" id="removeFileInput" value="">
                            @endif

                            <!-- File Upload Container (hidden if existing file) -->
                            <div class="file-upload-container {{ $contract->hasFile() ? 'd-none' : '' }}" id="dropZone">
                                <i class="material-icons-outlined upload-icon">cloud_upload</i>
                                <p class="upload-text">
                                    <strong>Drag & drop</strong> your contract file here, or <strong>click to browse</strong>
                                </p>
                                <p class="upload-hint">
                                    Supported formats: PDF, DOC, DOCX (Maximum size: 10MB)
                                </p>
                                <input type="file" 
                                       name="contract_file" 
                                       id="contract_file" 
                                       class="d-none" 
                                       accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                            </div>

                            <!-- New File Preview -->
                            <div class="file-preview" id="filePreview">
                                <div class="file-info">
                                    <i class="material-icons-outlined file-icon" id="fileIcon">description</i>
                                    <div>
                                        <div class="file-name" id="fileName">filename.pdf</div>
                                        <div class="file-size" id="fileSize">2.5 MB</div>
                                    </div>
                                </div>
                                <button type="button" class="remove-file" id="removeFile">
                                    <i class="material-icons-outlined" style="font-size: 18px;">close</i>
                                </button>
                            </div>
                            @error('contract_file')
                                <div class="text-danger mt-2">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Content -->
                        <div class="col-md-12 mb-3">
                            <label for="content" class="form-label">
                                Contract Content
                            </label>
                            <textarea class="form-control @error('content') is-invalid @enderror" 
                                      id="content" 
                                      name="content">{{ old('content', $contract->content) }}</textarea>
                            @error('content')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Info Alert -->
                    <div class="alert alert-warning" role="alert">
                        <i class="material-icons-outlined align-middle me-2">info</i>
                        <strong>Note:</strong> Version will be automatically incremented if content is changed.
                    </div>

                    <!-- Buttons -->
                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-submit">
                            <i class="material-icons-outlined align-middle" style="font-size: 18px;">save</i>
                            Update Contract
                        </button>
                        <a href="{{ route('admin.contracts.index') }}" class="btn btn-cancel">
                            <i class="material-icons-outlined align-middle" style="font-size: 18px;">cancel</i>
                            Cancel
                        </a>
                        <a href="{{ route('admin.contracts.signatures', $contract->id) }}" class="btn btn-info">
                            <i class="material-icons-outlined align-middle" style="font-size: 18px;">how_to_reg</i>
                            View Signatures ({{ $contract->signatures->count() }})
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

@include('admin.layouts.footer')

@push('js')
<!-- Select2 CSS & JS (Multi-select with Search) -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Summernote CSS & JS (Free, No API Key Required) -->
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-bs4.min.js"></script>

<script>
    $(document).ready(function() {
        // File upload handling
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('contract_file');
        const filePreview = document.getElementById('filePreview');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const fileIcon = document.getElementById('fileIcon');
        const removeFile = document.getElementById('removeFile');
        const existingFile = document.getElementById('existingFile');
        const removeFileInput = document.getElementById('removeFileInput');
        const replaceFileBtn = document.getElementById('replaceFileBtn');
        const removeExistingFile = document.getElementById('removeExistingFile');

        // Click to upload
        if (dropZone) {
            dropZone.addEventListener('click', () => fileInput.click());

            // Drag and drop handlers
            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.classList.add('dragover');
            });

            dropZone.addEventListener('dragleave', () => {
                dropZone.classList.remove('dragover');
            });

            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.classList.remove('dragover');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    handleFile(files[0]);
                }
            });
        }

        // File input change
        if (fileInput) {
            fileInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    handleFile(e.target.files[0]);
                }
            });
        }

        // Handle file selection
        function handleFile(file) {
            const validTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            const maxSize = 10 * 1024 * 1024; // 10MB

            if (!validTypes.includes(file.type)) {
                alert('Invalid file type. Please upload a PDF, DOC, or DOCX file.');
                return;
            }

            if (file.size > maxSize) {
                alert('File is too large. Maximum size is 10MB.');
                return;
            }

            // Update preview
            fileName.textContent = file.name;
            fileSize.textContent = formatFileSize(file.size);
            
            // Set icon based on file type
            if (file.type === 'application/pdf') {
                fileIcon.textContent = 'picture_as_pdf';
                fileIcon.style.color = '#dc2626';
            } else {
                fileIcon.textContent = 'description';
                fileIcon.style.color = '#2563eb';
            }

            // Create a DataTransfer to update the file input
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);
            fileInput.files = dataTransfer.files;

            // Show preview, hide upload zone and existing file
            filePreview.classList.add('show');
            dropZone.classList.add('d-none');
            if (existingFile) {
                existingFile.classList.add('d-none');
            }
            
            // Clear remove flag since we're uploading new file
            if (removeFileInput) {
                removeFileInput.value = '';
            }
        }

        // Format file size
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        // Remove new file
        if (removeFile) {
            removeFile.addEventListener('click', () => {
                fileInput.value = '';
                filePreview.classList.remove('show');
                
                // Show appropriate section
                if (existingFile && removeFileInput && removeFileInput.value !== '1') {
                    existingFile.classList.remove('d-none');
                } else {
                    dropZone.classList.remove('d-none');
                }
            });
        }

        // Replace existing file button
        if (replaceFileBtn) {
            replaceFileBtn.addEventListener('click', () => {
                existingFile.classList.add('d-none');
                dropZone.classList.remove('d-none');
                fileInput.click();
            });
        }

        // Remove existing file button
        if (removeExistingFile) {
            removeExistingFile.addEventListener('click', () => {
                if (confirm('Are you sure you want to remove the existing file?')) {
                    existingFile.classList.add('d-none');
                    dropZone.classList.remove('d-none');
                    removeFileInput.value = '1';
                }
            });
        }

        // Initialize Select2 for multi-select with search
        $('#customer_ids').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: 'Select customers (leave empty for all customers)',
            allowClear: true,
            closeOnSelect: false,
            maximumSelectionLength: 50,
            templateResult: formatCustomer,
            templateSelection: formatCustomerSelection
        });

        // Update customer count badge
        function updateCustomerCount() {
            var count = $('#customer_ids').val() ? $('#customer_ids').val().length : 0;
            var badge = $('#customer-count');
            
            if (count > 0) {
                badge.text(count + ' selected').show();
                if (count > 20) {
                    badge.removeClass('bg-primary').addClass('bg-warning');
                } else {
                    badge.removeClass('bg-warning').addClass('bg-primary');
                }
            } else {
                badge.hide();
            }
        }

        // Update count on selection change
        $('#customer_ids').on('change', function() {
            updateCustomerCount();
        });

        // Initial count update
        updateCustomerCount();

        // Select All Customers button
        $('#select-all-customers').on('click', function() {
            $('#customer_ids option:not([disabled])').prop('selected', true);
            $('#customer_ids').trigger('change');
        });

        // Clear All Customers button
        $('#clear-all-customers').on('click', function() {
            $('#customer_ids').val(null).trigger('change');
        });

        // Format customer option display
        function formatCustomer(customer) {
            if (!customer.id) {
                return customer.text;
            }
            var $customer = $(
                '<span><i class="material-icons-outlined align-middle" style="font-size: 14px;">person</i> ' + customer.text + '</span>'
            );
            return $customer;
        }

        // Format selected customer display (shows as tags/chips)
        function formatCustomerSelection(customer) {
            if (!customer.id) {
                return customer.text;
            }
            // Extract just the name (before the email)
            var text = customer.text;
            var nameMatch = text.match(/^(.+?)\s*\(/);
            return nameMatch ? nameMatch[1].trim() : text;
        }

        // Close Select2 dropdown when clicking on Summernote editor
        $(document).on('click', '.note-editor, .note-toolbar, .note-editable', function(e) {
            $('#customer_ids').select2('close');
        });

        // Initialize Summernote
        $('#content').summernote({
            height: 400,
            placeholder: 'Enter contract content here...',
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'italic', 'underline', 'strikethrough', 'clear']],
                ['fontname', ['fontname']],
                ['fontsize', ['fontsize']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['height', ['height']],
                ['table', ['table']],
                ['insert', ['link', 'picture', 'hr']],
                ['view', ['fullscreen', 'codeview', 'help']]
            ],
            fontNames: ['Arial', 'Arial Black', 'Comic Sans MS', 'Courier New', 'Helvetica', 'Impact', 'Tahoma', 'Times New Roman', 'Verdana'],
            fontSizes: ['8', '9', '10', '11', '12', '14', '16', '18', '20', '24', '36', '48'],
            dialogsInBody: true,
            callbacks: {
                onInit: function() {
                    console.log('Summernote initialized');
                    setTimeout(function() {
                        $('.note-dropdown-menu').css('z-index', '9999');
                        $('.dropdown-menu').css('z-index', '9999');
                    }, 100);
                },
                onBlur: function() {
                    $('#content').val($('#content').summernote('code'));
                }
            }
        });

        // Form validation before submit
        $('#contractForm').on('submit', function(e) {
            const content = $('#content').summernote('code');
            const hasContent = content && content.trim() !== '' && content.trim() !== '<p><br></p>';
            const hasNewFile = fileInput && fileInput.files.length > 0;
            const hasExistingFile = existingFile && !existingFile.classList.contains('d-none') && removeFileInput && removeFileInput.value !== '1';

            if (!hasContent && !hasNewFile && !hasExistingFile) {
                e.preventDefault();
                alert('Please provide either contract content or upload a contract file.');
                return false;
            }
        });
    });
</script>
@endpush
@endsection
