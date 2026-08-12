@extends('campaign.layouts.app')

@section('title', 'Submit new SMS campaign (file upload) - Campaign Manager')

@push('style')
<style>
    .dashboard-container {
        background: #f8fafc;
        margin: -2rem;
        padding: 2rem;
    }

    .page-header {
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(22, 163, 74, 0.3);
        padding: 1.5rem 2rem;
        margin-bottom: 2rem;
        color: white;
    }

    .page-header h4 {
        font-weight: 700;
        margin-bottom: 0.25rem;
    }

    .page-header p {
        opacity: 0.9;
        margin-bottom: 0;
        font-size: 0.9rem;
    }

    .form-card {
        background: white;
        border-radius: 15px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }

    .form-card .card-header {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 1rem 1.5rem;
    }

    .form-card .card-header h5 {
        color: #293b50;
        font-weight: 600;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .form-card .card-body {
        padding: 2rem;
    }

    .form-label {
        color: #293b50;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }

    .form-control, .form-select {
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        padding: 0.75rem 1rem;
        transition: all 0.3s ease;
    }

    .form-control:focus, .form-select:focus {
        border-color: #16a34a;
        box-shadow: 0 0 0 0.2rem rgba(22, 163, 74, 0.15);
    }

    .form-text {
        color: #64748b;
        font-size: 0.85rem;
        margin-top: 0.5rem;
    }

    .btn-primary-custom {
        background: linear-gradient(135deg, #16a34a, #15803d);
        border: none;
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 10px;
        font-weight: 600;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-primary-custom:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(22, 163, 74, 0.4);
        color: white;
    }

    .btn-primary-custom:disabled {
        opacity: 0.7;
        cursor: not-allowed;
        transform: none;
    }

    .btn-secondary-custom {
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        color: #64748b;
        padding: 0.75rem 1.5rem;
        border-radius: 10px;
        font-weight: 600;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
    }

    .btn-secondary-custom:hover {
        background: #e2e8f0;
        color: #293b50;
    }

    .upload-area {
        cursor: pointer;
        transition: all 0.3s ease;
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
        border: 2px dashed #d1d5db;
        border-radius: 15px;
        padding: 3rem 2rem;
        text-align: center;
    }

    .upload-area:hover, .upload-area.dragover {
        border-color: #16a34a;
        background: linear-gradient(135deg, rgba(22, 163, 74, 0.05), rgba(21, 128, 61, 0.05));
    }

    .upload-area i {
        font-size: 64px;
        color: #94a3b8;
        margin-bottom: 1rem;
    }

    .upload-area:hover i, .upload-area.dragover i {
        color: #16a34a;
    }

    .upload-btn {
        background: linear-gradient(135deg, #ea6118, #d1520e);
        border: none;
        color: white;
        padding: 0.6rem 1.5rem;
        border-radius: 10px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .upload-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(234, 97, 24, 0.3);
        color: white;
    }

    .download-links {
        background: linear-gradient(135deg, rgba(8, 145, 178, 0.1), rgba(14, 116, 144, 0.1));
        border: 1px solid rgba(8, 145, 178, 0.2);
        border-radius: 12px;
        padding: 1.25rem;
        margin-bottom: 2rem;
    }

    .download-links .download-btn {
        background: white;
        border: 1px solid #e2e8f0;
        color: #293b50;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-weight: 500;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        margin-right: 0.5rem;
        margin-bottom: 0.5rem;
    }

    .download-links .download-btn:hover {
        background: #16a34a;
        color: white;
        border-color: #16a34a;
    }

    .alert-custom-warning {
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(217, 119, 6, 0.1));
        border: 1px solid rgba(245, 158, 11, 0.3);
        border-left: 4px solid #f59e0b;
        border-radius: 10px;
        padding: 1.25rem;
        color: #92400e;
    }

    .alert-custom-success {
        background: linear-gradient(135deg, rgba(22, 163, 74, 0.1), rgba(21, 128, 61, 0.1));
        border: 1px solid rgba(22, 163, 74, 0.3);
        border-left: 4px solid #16a34a;
        border-radius: 10px;
        padding: 1rem 1.25rem;
        color: #166534;
    }

    .alert-custom-danger {
        background: linear-gradient(135deg, rgba(220, 38, 38, 0.1), rgba(185, 28, 28, 0.1));
        border: 1px solid rgba(220, 38, 38, 0.3);
        border-left: 4px solid #dc2626;
        border-radius: 10px;
        padding: 1rem 1.25rem;
        color: #991b1b;
    }

    .guide-table {
        background: white;
        border-radius: 15px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        margin-top: 2rem;
    }

    .guide-table .card-header {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 1rem 1.5rem;
    }

    .guide-table .card-header h6 {
        color: #293b50;
        font-weight: 600;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .guide-table table {
        margin-bottom: 0;
    }

    .guide-table th {
        background: #f8fafc;
        color: #293b50;
        font-weight: 600;
        padding: 1rem;
        border: none;
        border-bottom: 2px solid #e2e8f0;
        font-size: 0.85rem;
    }

    .guide-table td {
        padding: 1rem;
        border: none;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }

    .guide-table tbody tr:last-child td {
        border-bottom: none;
    }

    .guide-table tbody tr:hover {
        background: #f8fafc;
    }

    .badge-required {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
        color: white;
        font-weight: 500;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
    }

    .badge-optional {
        background: #e2e8f0;
        color: #64748b;
        font-weight: 500;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.75rem;
    }

    .contract-link {
        background: #f8fafc;
        border-radius: 10px;
        padding: 1rem;
        margin-top: 1.5rem;
        text-align: center;
    }

    .contract-link a {
        color: #16a34a;
        text-decoration: none;
        font-weight: 500;
    }

    .contract-link a:hover {
        text-decoration: underline;
    }
</style>
@endpush

@section('content')
<div class="dashboard-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4><i class="material-icons-outlined align-middle me-2">upload_file</i>Submit new SMS campaign (file upload)</h4>
                <p>Upload a CSV campaign file and begin sending your SMS</p>
            </div>
             <a href="{{ route('campaign.previous.list') }}" class="btn" style="background: white; color: #16a34a; font-weight: 600; border-radius: 10px; padding: 0.5rem 1rem; display: flex; align-items: center; gap: 0.5rem;">
               <i class="material-icons-outlined">history</i>
              Campaigns History
            </a>
            {{-- <a href="{{ route('campaign.previous.list') }}" class="btn btn-light btn-sm">
                <i class="material-icons-outlined align-middle me-1" style="font-size: 18px;">history</i>
                View Previous Campaigns
            </a> --}}
        </div>
    </div>

    <!-- Alert Messages -->
    @if(session('success'))
        <div class="alert-custom-success mb-4 d-flex align-items-center">
            <i class="material-icons-outlined me-2">check_circle</i>
            <div>{!! session('success') !!}</div>
        </div>
    @endif

    @if(session('error'))
        <div class="alert-custom-danger mb-4 d-flex align-items-center">
            <i class="material-icons-outlined me-2">error</i>
            <div>{!! session('error') !!}</div>
        </div>
    @endif

    <!-- Download Links -->
    <div class="download-links">
        <div class="d-flex align-items-center mb-2">
            <i class="material-icons-outlined me-2" style="color: #0e7490;">info</i>
            <strong style="color: #0e7490;">Download sample files to help format your CSV correctly:</strong>
        </div>
        <div class="mt-2">
            <a href="{{ route('campaign.download.sample.csv') }}" class="download-btn">
                <i class="material-icons-outlined" style="font-size: 18px;">download</i>
                Sample CSV
            </a>
            <a href="{{ route('campaign.download.sample.excel') }}" class="download-btn">
                <i class="material-icons-outlined" style="font-size: 18px;">download</i>
                Sample Excel
            </a>
            <a href="{{ route('campaign.download.instructions') }}" class="download-btn">
                <i class="material-icons-outlined" style="font-size: 18px;">description</i>
                Instructions
            </a>
        </div>
    </div>

    <!-- Form Card -->
    <div class="form-card">
        <div class="card-header">
            <h5>
                <i class="material-icons-outlined">cloud_upload</i>
                Upload Campaign File
            </h5>
        </div>
        <div class="card-body">
            <form action="{{ route('campaign.upload.submit') }}" method="POST" enctype="multipart/form-data" id="uploadCampaignForm">
                @csrf
                
                <!-- Campaign Name -->
                <div class="mb-4">
                    <label class="form-label">Campaign Name <span class="text-danger">*</span></label>
                    <input type="text" name="campaignname" class="form-control" 
                           value="{{ old('campaignname') }}" 
                           placeholder="Enter a name to identify this campaign">
                    <div class="form-text">A simple description to help you identify the campaign in future.</div>
                </div>

                <!-- File Upload -->
                <div class="mb-4">
                    <label class="form-label">Select CSV File <span class="text-danger">*</span></label>
                    <div class="upload-area" id="dropZone">
                        <input type="file" name="userfile" id="fileInput" class="d-none" accept=".csv">
                        <i class="material-icons-outlined">cloud_upload</i>
                        <h5 class="text-muted mb-2">Drag & drop your CSV file here</h5>
                        <p class="text-muted mb-3">or</p>
                        <button type="button" class="upload-btn" onclick="document.getElementById('fileInput').click()">
                            {{-- <i class="material-icons-outlined align-middle me-1" style="font-size: 18px;">folder_open</i> --}}
                            Browse Files
                        </button>
                        <p class="mt-3 mb-0 text-muted" id="selectedFileName">
                            <small>No file selected • CSV format only • Max 100MB</small>
                        </p>
                    </div>
                </div>

                <!-- Warning Box -->
                <div class="alert-custom-warning mb-4">
                    <div class="d-flex align-items-start">
                        <i class="material-icons-outlined me-2" style="color: #d97706;">warning</i>
                        <div>
                            <strong>Important Notes:</strong>
                            <ul class="mt-2 mb-0">
                                <li>Large files may take a few minutes to upload. Please be patient.</li>
                                <li><strong>Do not refresh the page</strong> after clicking submit.</li>
                                <li>Need help with CSV format? Email your file to care@smsexpert.co.uk for review.</li>
                                <li>We can check format but not content or message wording.</li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="d-flex gap-3">
                    <button type="submit" class="btn-primary-custom" id="submitBtn">
                        <i class="material-icons-outlined">upload</i>
                        Upload & Submit Campaign
                    </button>
                    <a href="{{ route('campaign.dashboard') }}" class="btn-secondary-custom">
                        <i class="material-icons-outlined">close</i>
                        Cancel
                    </a>
                </div>

                <!-- Contract Link -->
                {{-- <div class="contract-link">
                    <small class="text-muted">
                        By using SMS Expert services you agree to the latest contract, privacy policy and applicable laws.
                        <a href="#" target="_blank">View your SMS Expert contract</a>
                    </small>
                </div> --}}
            </form>
        </div>
    </div>

    <!-- CSV Format Guide -->
    <div class="guide-table">
        <div class="card-header">
            <h6>
                <i class="material-icons-outlined">help_outline</i>
                CSV Format Guide
            </h6>
        </div>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Column</th>
                        <th>Description</th>
                        <th>Required</th>
                        <th>Example</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><strong>1. Mobile</strong></td>
                        <td>Recipient mobile number (447... format)</td>
                        <td><span class="badge-required">Required</span></td>
                        <td><code>447123456789</code></td>
                    </tr>
                    <tr>
                        <td><strong>2. Custom1</strong></td>
                        <td>Custom field for personalization</td>
                        <td><span class="badge-optional">Optional</span></td>
                        <td><code>John</code></td>
                    </tr>
                    <tr>
                        <td><strong>3. Originator</strong></td>
                        <td>Sender ID (who the SMS comes from)</td>
                        <td><span class="badge-required">Required</span></td>
                        <td><code>YourBrand</code></td>
                    </tr>
                    <tr>
                        <td><strong>4. Message</strong></td>
                        <td>SMS text content</td>
                        <td><span class="badge-required">Required</span></td>
                        <td><code>Hello, this is your message!</code></td>
                    </tr>
                    <tr>
                        <td><strong>5. Send Time</strong></td>
                        <td>Scheduled send time (YYYYMMDDHHmm)</td>
                        <td><span class="badge-optional">Optional</span></td>
                        <td><code>202412251400</code></td>
                    </tr>
                    <tr>
                        <td><strong>6. DLR URL</strong></td>
                        <td>Delivery receipt callback URL</td>
                        <td><span class="badge-optional">Optional</span></td>
                        <td><code>https://yoursite.com/dlr</code></td>
                    </tr>
                    <tr>
                        <td><strong>7. Custom2</strong></td>
                        <td>Additional custom field</td>
                        <td><span class="badge-optional">Optional</span></td>
                        <td><code>-</code></td>
                    </tr>
                    <tr>
                        <td><strong>8. Route</strong></td>
                        <td>Route letter (d, p, e, etc.)</td>
                        <td><span class="badge-optional">Optional</span></td>
                        <td><code>d</code></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const selectedFileName = document.getElementById('selectedFileName');
    const submitBtn = document.getElementById('submitBtn');
    const form = document.getElementById('uploadCampaignForm');

    // Drag and drop
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    ['dragenter', 'dragover'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => dropZone.classList.add('dragover'), false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropZone.addEventListener(eventName, () => dropZone.classList.remove('dragover'), false);
    });

    dropZone.addEventListener('drop', function(e) {
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            fileInput.files = files;
            updateFileName(files[0]);
        }
    });

    // Click on drop zone
    dropZone.addEventListener('click', function(e) {
        if (e.target.tagName !== 'BUTTON') {
            fileInput.click();
        }
    });

    // File input change
    fileInput.addEventListener('change', function() {
        if (this.files.length > 0) {
            updateFileName(this.files[0]);
        }
    });

    function updateFileName(file) {
        const extension = file.name.split('.').pop().toLowerCase();
        if (extension !== 'csv') {
            selectedFileName.innerHTML = '<span class="text-danger"><i class="material-icons-outlined align-middle" style="font-size: 18px;">error</i> Invalid file type. Please select a CSV file.</span>';
            fileInput.value = '';
            return;
        }
        
        const size = (file.size / 1024 / 1024).toFixed(2);
        selectedFileName.innerHTML = `<span class="text-success"><i class="material-icons-outlined align-middle" style="font-size: 18px;margin-bottom:0rem !important;">check_circle</i> <strong>${file.name}</strong> (${size} MB)</span>`;
    }

    // Form submission
    form.addEventListener('submit', function(e) {
        if (!fileInput.files.length) {
            e.preventDefault();
            selectedFileName.innerHTML = '<span class="text-danger"><i class="material-icons-outlined align-middle" style="font-size: 18px;">error</i> Please select a CSV file to upload.</span>';
            return;
        }
        
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Uploading Campaign...';
    });
});
</script>
@endpush
