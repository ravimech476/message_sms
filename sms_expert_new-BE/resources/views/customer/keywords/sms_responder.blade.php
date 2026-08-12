@extends('layouts.app')
@section('title', 'SMS Auto-Responder - SMS Expert')

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

        .config-container {
            background: #f8fafc;
            min-height: 100vh;
            margin: -2rem;
            padding: 2rem;
        }

        .config-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
        }

        .config-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #ea6118, #293b50);
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

        .icon-primary {
            color: #ea6118;
            font-size: 1.2rem;
        }

        .info-icon {
            color: #64748b;
            font-size: 1.2rem;
            cursor: pointer;
            vertical-align: middle;
            transition: color 0.3s ease;
        }

        .info-icon:hover {
            color: #ea6118;
        }

        .char-counter {
            font-size: 0.875rem;
            color: #64748b;
            margin-top: 0.25rem;
        }

        .info-card {
            background: linear-gradient(135deg, rgba(234, 97, 24, 0.05), rgba(41, 59, 80, 0.05));
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 1rem;
        }

        .info-card h6 {
            color: #293b50;
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .info-card p {
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 0.75rem;
        }

        .info-card p:last-child {
            margin-bottom: 0;
        }

        .btn-primary {
            background: linear-gradient(135deg, #ea6118, #293b50);
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(234, 97, 24, 0.3);
        }

        .btn-secondary {
            background: #64748b;
            border: none;
            padding: 0.5rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
            color: white;
        }

        .btn-secondary:hover {
            background: #475569;
            transform: translateY(-2px);
            color: white;
        }

        .form-label {
            color: #293b50;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .required-field::after {
            content: '*';
            color: #dc2626;
            margin-left: 0.25rem;
        }
    </style>
@endpush

@section('content')
    <div class="config-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb-container d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">
                    <i class="material-icons-outlined icon-primary">reply</i>
                    SMS Auto-Responder - {{ $itaggData->keyword ?? 'N/A' }}
                </div>
                &nbsp;
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="{{ route('dashboard') }}">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="{{ route('keywords') }}">Keywords</a>
                        </li>
                        <li class="breadcrumb-item active">SMS Auto-Responder</li>
                    </ol>
                </nav>
            </div>
            <a href="{{ route('keywords') }}" class="btn btn-outline-secondary back-btn">
                <i class="material-icons-outlined me-1">arrow_back</i> Back
            </a>
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

        @if ($errors->any())
            <div class="alert alert-danger" id="validation-errors">
                <div>
                    <i class="material-icons-outlined">error</i>
                    <strong>Validation Errors:</strong>
                    <ul class="mb-0 mt-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <!-- Main Content -->
        <div class="config-card">
            <div class="section-content">
                <form method="POST" action="{{ route('keywords.sms-responder.save') }}" id="smsResponderForm">
                    @csrf

                    <input type="hidden" name="itagg_id" value="{{ $itagg_id }}">

                    <!-- Response Route - HIDDEN FIELD -->
                    <input type="hidden" 
                           name="responseroute" 
                           id="responseroute" 
                           value="{{ old('responseroute', $itaggData->response_smsshortcodes_id ?? ($smsShortcodes->first()->id ?? '')) }}">

                    <!-- Sender ID -->
                    <div class="mb-4">
                        <label for="senderid" class="form-label required-field">
                            Set the Sender ID to be
                            <i class="material-icons-outlined info-icon" data-bs-toggle="modal" data-bs-target="#SenderIdModal">help</i>
                        </label>
                        <input type="text" 
                               name="senderid" 
                               id="senderid" 
                               class="form-control @error('senderid') is-invalid @enderror" 
                               value="{{ old('senderid', $itaggData->response_sender_id ?? $itaggData->keyword ?? '') }}"
                               maxlength="11"
                               required>
                        @error('senderid')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">The sender ID that will appear on the response message (max 11 characters)</small>
                    </div>

                    <!-- Response Text -->
                    <div class="mb-4">
                        <label for="responsetext" class="form-label required-field">
                            The text I want to send is
                        </label>
                        <textarea name="responsetext" 
                                  id="responsetext" 
                                  class="form-control @error('responsetext') is-invalid @enderror" 
                                  rows="5"
                                  required>{{ old('responsetext', urldecode($itaggData->response_content ?? 'This is a demo auto-response for your keyword ' . ($itaggData->keyword ?? ''))) }}</textarea>
                        @error('responsetext')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="char-counter">
                            <span id="charCount">0</span> characters | <span id="smsCount">0</span> SMS message(s)
                        </div>
                    </div>

                    <!-- Allowed Mobile Update Numbers -->
                    <div class="mb-4">
                        <label for="allowedUpdateNumbers" class="form-label">
                            Allow Mobile Updates from
                        </label>
                        <input type="text" 
                               name="allowedUpdateNumbers" 
                               id="allowedUpdateNumbers" 
                               class="form-control @error('allowedUpdateNumbers') is-invalid @enderror"
                               value="{{ old('allowedUpdateNumbers', $itaggData->allowed_mobile_update_numbers ?? '') }}"
                               placeholder="e.g., 447700900123,447700900456">
                        @error('allowedUpdateNumbers')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="text-muted">Enter comma-separated mobile numbers that can update this responder</small>
                    </div>

                    <!-- Allow Updates for Subkeywords -->
                    <div class="mb-4">
                        <div class="form-check">
                            <input type="hidden" name="allowSubkeys" value="0">
                            <input type="checkbox" 
                                   name="allowSubkeys" 
                                   id="allowSubkeys" 
                                   class="form-check-input" 
                                   value="1"
                                   {{ old('allowSubkeys', $itaggData->allow_mobile_update_across_subkeys ?? 0) == 1 ? 'checked' : '' }}>
                            <label class="form-check-label" for="allowSubkeys">
                                Allow for ALL 'SMS Auto-Responder' subkeywords
                            </label>
                        </div>
                    </div>

                    <!-- Information Card -->
                    <div class="info-card">
                        <h6>
                            <i class="material-icons-outlined" style="vertical-align: middle;">info</i>
                            Mobile Updates Feature
                        </h6>
                        <p>
                            This allows you to update the SMS Auto-Responder response text via your mobile phone.
                            Simply enter the mobile phone number to be allowed to update the content
                            (or comma-separated numbers if more than one).
                        </p>
                        <p>
                            To update this iTAGG, simply send in a message from the specified phone to
                            <strong>{{ $itaggData->shortcode_number ?? 'Number' }}</strong> starting with <strong>{{ $itaggData->keyword ?? 'Keyword' }}</strong> then a space, then your new content.
                            The content will be immediately updated.
                        </p>
                        <p>
                            Tick the <strong>"Allow for ALL subkeywords"</strong> box to enable this facility for all
                            subkeywords of this iTAGG. Please note: a confirmation SMS will be sent at cost to
                            your wallet when you use this service.
                        </p>
                    </div>

                    <!-- Form Actions -->
                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="material-icons-outlined" style="vertical-align: middle; font-size: 1.2rem;">save</i>
                            Save Settings
                        </button>
                        <a href="{{ route('keywords.config', ['itaggId' => $itaggData->id, 'keyword' => $itaggData->keyword]) }}" class="btn btn-secondary">
                            <i class="material-icons-outlined" style="vertical-align: middle; font-size: 1.2rem;">close</i>
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Sender ID Information Modal -->
    <div class="modal fade" id="SenderIdModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="material-icons-outlined me-2">help_outline</i>
                        Sender ID Information
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <p>The Sender ID is the sender name the recipient sees when they receive a standard rate SMS message.
                        You cannot set the sender ID for premium rate messages - these will automatically have a shortcode ID.</p>

                    <p><strong>The Sender ID for your iTAGG response must be either:</strong></p>

                    <p><strong>1. Mobile number.</strong> This is simply a mobile number (11 to 15 characters) starting with
                        the country code (44 for the UK) or 0.</p>

                    <p><strong>2. Alphanumeric.</strong> This is a string of up to 11 characters starting with a letter and
                        consisting of letters, numbers, spaces, full stops and hyphens.</p>

                    <p><strong>3. Shortcode.</strong> This is a Shortcode number which can be up to 5 digits long, such as
                        83248.</p>

                    <p>You will need to purchase messages in order to send messages from your iTAGG Wallet - you can buy any
                        amount from the Control Panel 'SMS Wallet' page.</p>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Character and SMS counter
        function updateSMSCount() {
            const text = document.getElementById('responsetext').value;
            const length = text.length;
            let smsCount = 0;
            
            if (length === 0) {
                smsCount = 0;
            } else if (length <= 160) {
                smsCount = 1;
            } else {
                smsCount = Math.ceil(length / 153);
            }
            
            document.getElementById('charCount').textContent = length;
            document.getElementById('smsCount').textContent = smsCount;
        }
        
        // Update count on page load
        updateSMSCount();
        
        // Update count on textarea input
        document.getElementById('responsetext').addEventListener('input', updateSMSCount);
        
        // Auto-hide flash messages after 5 seconds
        setTimeout(function() {
            const flashMessages = document.querySelectorAll('#flash-message, #flash-error-message, #validation-errors');
            flashMessages.forEach(msg => {
                if (msg) {
                    msg.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
                    msg.style.opacity = '0';
                    msg.style.transform = 'translateY(-20px)';
                    setTimeout(() => msg.remove(), 300);
                }
            });
        }, 5000);
        
        // Form validation
        document.getElementById('smsResponderForm').addEventListener('submit', function(e) {
            const senderid = document.getElementById('senderid').value.trim();
            const responsetext = document.getElementById('responsetext').value.trim();
            const responseroute = document.getElementById('responseroute').value;
            
            if (!senderid || !responsetext || !responseroute) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return false;
            }
            
            if (senderid.length > 11) {
                e.preventDefault();
                alert('Sender ID must not exceed 11 characters.');
                return false;
            }
        });
    });
</script>
@endpush
