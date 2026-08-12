@extends('admin.layouts.app')

@section('title', 'Create Notification')

@push('style')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
    .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice {
        background: linear-gradient(135deg, #ea6118, #d1520e);
        border: none;
        color: white;
    }
    .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice__remove {
        color: white;
    }
    .preview-card {
        background: #f8f9fa;
        border-radius: 10px;
        overflow: hidden;
    }
    .preview-header {
        padding: 15px;
        color: white;
    }
    .preview-header.info { background: #0dcaf0; }
    .preview-header.warning { background: #ffc107; color: #333; }
    .preview-header.success { background: #198754; }
    .preview-header.danger { background: #dc3545; }
    .preview-header.announcement { background: #6f42c1; }
    .preview-body {
        padding: 20px;
        background: white;
    }
</style>
@endpush

@section('content')
<main class="main-wrapper" id="main-wrapper">
    <div class="main-content">
        <!--breadcrumb-->
        <div class="page-breadcrumb d-none d-sm-flex align-items-center mb-3 breadcolor" style="position: relative;">
            <div class="breadcrumb-title pe-3 title-name">Create Notification</div>
            <div class="ps-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-0 p-0" style="background: none;">
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.dashboard') }}" class="text-decoration-none">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="{{ route('admin.notifications.index') }}" class="text-decoration-none">Notifications</a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Create</li>
                    </ol>
                </nav>
            </div>
        </div>
        <!--end breadcrumb-->

        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="material-icons-outlined me-2" style="vertical-align: middle;">error</i>
                {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <strong>Validation Errors:</strong>
                <ul class="mb-0 mt-2">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <form action="{{ route('admin.notifications.store') }}" method="POST" id="notificationForm">
            @csrf
            <div class="row">
                <!-- Left Column - Form -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-gradient text-white" style="background: linear-gradient(135deg, #293b50, #1f2c3d);">
                            <h5 class="mb-0">
                                <i class="material-icons-outlined me-2" style="vertical-align: middle;">edit_notifications</i>
                                Notification Details
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label fw-bold">Title <span class="text-danger">*</span></label>
                                    <input type="text" name="title" class="form-control @error('title') is-invalid @enderror" 
                                           value="{{ old('title') }}" required maxlength="255"
                                           placeholder="Enter notification title" id="notificationTitle">
                                    @error('title')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-12 mb-3">
                                    <label class="form-label fw-bold">Message <span class="text-danger">*</span></label>
                                    <textarea name="message" class="form-control @error('message') is-invalid @enderror" 
                                              rows="5" required maxlength="2000"
                                              placeholder="Enter notification message" id="notificationMessage">{{ old('message') }}</textarea>
                                    <small class="text-muted"><span id="charCount">0</span>/2000 characters</small>
                                    @error('message')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Type <span class="text-danger">*</span></label>
                                    <select name="type" class="form-select @error('type') is-invalid @enderror" id="notificationType">
                                        <option value="info" {{ old('type') == 'info' ? 'selected' : '' }}>ℹ️ Info</option>
                                        <option value="warning" {{ old('type') == 'warning' ? 'selected' : '' }}>⚠️ Warning</option>
                                        <option value="success" {{ old('type') == 'success' ? 'selected' : '' }}>✅ Success</option>
                                        <option value="danger" {{ old('type') == 'danger' ? 'selected' : '' }}>🚨 Danger</option>
                                        <option value="announcement" {{ old('type') == 'announcement' ? 'selected' : '' }}>📢 Announcement</option>
                                    </select>
                                    @error('type')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label fw-bold">Delivery Method <span class="text-danger">*</span></label>
                                    <select name="delivery_method" class="form-select @error('delivery_method') is-invalid @enderror">
                                        <option value="web" {{ old('delivery_method', 'web') == 'web' ? 'selected' : '' }}>🌐 Web Only</option>
                                        <option value="email" {{ old('delivery_method') == 'email' ? 'selected' : '' }}>📧 Email Only</option>
                                        <option value="both" {{ old('delivery_method') == 'both' ? 'selected' : '' }}>🌐📧 Both Web & Email</option>
                                    </select>
                                    @error('delivery_method')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Delivery Options -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="material-icons-outlined me-2" style="vertical-align: middle;">settings</i>
                                Additional Options
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" name="requires_acknowledgment" id="requiresAck" 
                                               {{ old('requires_acknowledgment') ? 'checked' : '' }} style="width: 45px; height: 22px;" value="1">
                                        <label class="form-check-label ms-2" for="requiresAck">
                                            <i class="material-icons-outlined me-1" style="vertical-align: middle;">verified</i>
                                            Require Acknowledgement
                                        </label>
                                    </div>
                                    <small class="text-muted">Customer must click OK to dismiss</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recipients -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="material-icons-outlined me-2" style="vertical-align: middle;">people</i>
                                Recipients
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="target_type" id="targetAll" 
                                           value="all" {{ old('target_type', 'all') == 'all' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="targetAll">
                                        <i class="material-icons-outlined me-1" style="vertical-align: middle;">groups</i>
                                        All Customers
                                    </label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="target_type" id="targetSpecific" 
                                           value="specific" {{ old('target_type') == 'specific' ? 'checked' : '' }}>
                                    <label class="form-check-label" for="targetSpecific">
                                        <i class="material-icons-outlined me-1" style="vertical-align: middle;">person_search</i>
                                        Specific Customers
                                    </label>
                                </div>
                            </div>

                            <div id="customerSelectContainer" class="{{ old('target_type') == 'specific' ? '' : 'd-none' }}">
                                <label class="form-label fw-bold">Select Customers</label>
                                <select name="customer_ids[]" id="customerSelect" class="form-select" multiple>
                                    @foreach($customers as $customer)
                                        <option value="{{ $customer->id }}" 
                                                data-busname="{{ urldecode($customer->busname) }}"
                                                data-email="{{ $customer->contactemail }}"
                                                {{ in_array($customer->id, old('customer_ids', [])) ? 'selected' : '' }}>
                                            {{ urldecode($customer->busname ?: $customer->contactname) }} ({{ $customer->uname }})
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Search and select customers</small>
                            </div>
                        </div>
                    </div>

                    <!-- Scheduling -->
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="material-icons-outlined me-2" style="vertical-align: middle;">schedule</i>
                                Scheduling
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="schedule_option" id="scheduleLater" value="later" checked>
                                    <label class="form-check-label" for="scheduleLater">
                                        Schedule for later
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="schedule_option" id="scheduleNow" value="now">
                                    <label class="form-check-label" for="scheduleNow">
                                        Send immediately
                                    </label>
                                </div>
                            </div>

                            <div id="scheduleDateContainer">
                                <label class="form-label fw-bold">Schedule Date & Time</label>
                                <input type="datetime-local" name="scheduled_at" class="form-control @error('scheduled_at') is-invalid @enderror" 
                                       value="{{ old('scheduled_at') }}" id="scheduledAt"
                                       min="{{ now()->addMinutes(2)->format('Y-m-d\TH:i') }}">
                                <small class="text-muted">Server time now: <strong id="serverTimeDisplay">{{ now()->format('d M Y, H:i:s') }}</strong> (UK Time). Schedule must be at least 2 minutes in future. Leave empty to save as draft.</small>
                                @error('scheduled_at')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column - Preview -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm mb-4 position-sticky" style="top: 20px;">
                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="material-icons-outlined me-2" style="vertical-align: middle;">preview</i>
                                Preview
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="preview-card">
                                <div class="preview-header info" id="previewHeader">
                                    <h6 class="mb-0" id="previewTitle">Notification Title</h6>
                                </div>
                                <div class="preview-body">
                                    <p class="mb-0" id="previewMessage">Notification message will appear here...</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-body">
                            <input type="hidden" name="send_now" id="sendNowInput" value="0">
                            
                            <button type="submit" class="btn w-100 text-white mb-2" style="background: linear-gradient(135deg, #ea6118, #d1520e);">
                                <i class="material-icons-outlined me-1" style="vertical-align: middle;">save</i>
                                <span id="submitBtnText">Save as Draft</span>
                            </button>
                            
                            <a href="{{ route('admin.notifications.index') }}" class="btn btn-outline-secondary w-100">
                                <i class="material-icons-outlined me-1" style="vertical-align: middle;">close</i>
                                Cancel
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</main>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // Initialize Select2
    $('#customerSelect').select2({
        theme: 'bootstrap-5',
        placeholder: 'Search customers...',
        allowClear: true,
        width: '100%'
    });

    // Toggle customer select visibility
    $('input[name="target_type"]').change(function() {
        if ($(this).val() === 'specific') {
            $('#customerSelectContainer').removeClass('d-none');
        } else {
            $('#customerSelectContainer').addClass('d-none');
        }
    });

    // Toggle schedule date visibility
    $('input[name="schedule_option"]').change(function() {
        if ($(this).val() === 'now') {
            $('#scheduleDateContainer').addClass('d-none');
            $('#sendNowInput').val('1');
            $('#submitBtnText').text('Send Now');
        } else {
            $('#scheduleDateContainer').removeClass('d-none');
            $('#sendNowInput').val('0');
            updateSubmitButtonText();
        }
    });

    // Update submit button text based on scheduled_at
    function updateSubmitButtonText() {
        var scheduledAt = $('#scheduledAt').val();
        if (scheduledAt) {
            $('#submitBtnText').text('Schedule Notification');
        } else {
            $('#submitBtnText').text('Save as Draft');
        }
    }

    $('#scheduledAt').change(updateSubmitButtonText);

    // Preview updates
    $('#notificationTitle').on('input', function() {
        $('#previewTitle').text($(this).val() || 'Notification Title');
    });

    $('#notificationMessage').on('input', function() {
        var message = $(this).val() || 'Notification message will appear here...';
        $('#previewMessage').html(message.replace(/\n/g, '<br>'));
        $('#charCount').text($(this).val().length);
    });

    $('#notificationType').change(function() {
        var type = $(this).val();
        $('#previewHeader').removeClass('info warning success danger announcement').addClass(type);
    });

    // Form submission confirmation
    $('#notificationForm').submit(function(e) {
        var sendNow = $('#sendNowInput').val() === '1';
        var targetType = $('input[name="target_type"]:checked').val();
        
        if (sendNow) {
            var msg = targetType === 'all' 
                ? 'This will send the notification to ALL customers immediately. Continue?' 
                : 'This will send the notification to selected customers immediately. Continue?';
            
            if (!confirm(msg)) {
                e.preventDefault();
                return false;
            }
        }
    });
});
</script>
@endpush
