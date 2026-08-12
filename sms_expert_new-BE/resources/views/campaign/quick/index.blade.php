@extends('campaign.layouts.app')

@section('title', 'Submit new SMS campaign (quick) - Campaign Manager')

@push('style')
    <style>
        .dashboard-container {
            background: #f8fafc;
            margin: -2rem;
            padding: 2rem;
        }

        .page-header {
            background: linear-gradient(135deg, #ea6118 0%, #293b50 100%);
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(234, 97, 24, 0.3);
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

        .form-control,
        .form-select {
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: #ea6118;
            box-shadow: 0 0 0 0.2rem rgba(234, 97, 24, 0.15);
        }

        .form-control::placeholder {
            color: #94a3b8;
        }

        .form-text {
            color: #64748b;
            font-size: 0.85rem;
            margin-top: 0.5rem;
        }

        .btn-primary-custom {
            background: linear-gradient(135deg, #ea6118, #d1520e);
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
            box-shadow: 0 4px 15px rgba(234, 97, 24, 0.4);
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

        .alert-custom-warning {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(217, 119, 6, 0.1));
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-left: 4px solid #f59e0b;
            border-radius: 10px;
            padding: 1.25rem;
            color: #92400e;
        }

        .alert-custom-warning ul {
            margin-bottom: 0;
            padding-left: 1.25rem;
        }

        .alert-custom-warning li {
            margin-bottom: 0.5rem;
        }

        .alert-custom-warning li:last-child {
            margin-bottom: 0;
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

        .char-counter {
            background: #f8fafc;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            display: inline-block;
        }

        .char-counter.warning {
            background: rgba(245, 158, 11, 0.1);
            color: #d97706;
        }

        .input-group-text {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px 0 0 10px;
            color: #64748b;
        }

        .contract-link {
            background: #f8fafc;
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1.5rem;
            text-align: center;
        }

        .contract-link a {
            color: #ea6118;
            text-decoration: none;
            font-weight: 500;
        }

        .contract-link a:hover {
            text-decoration: underline;
        }

        .section-divider {
            border-top: 1px solid #e2e8f0;
            margin: 1.5rem 0;
        }

        /* Sender ID badge styling */
        .sender-id-badge {
            background: linear-gradient(135deg, #ea6118 0%, #d1520e 100%);
            color: white;
            font-size: 0.75rem;
            padding: 0.15rem 0.5rem;
            border-radius: 4px;
            margin-left: 0.5rem;
        }

        .sender-id-count {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            margin-top: 0.5rem;
            font-size: 0.85rem;
            color: #64748b;
        }
    </style>
@endpush

@section('content')
    <div class="dashboard-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4><i class="material-icons-outlined align-middle me-2">send</i>Submit new SMS campaign (quick)</h4>
                    <p>Quickly send an SMS campaign to a list of mobile numbers</p>
                </div>
                <a href="{{ route('campaign.previous.list') }}" class="btn"
                    style="background: white; color: #ea6118; font-weight: 600; border-radius: 10px; padding: 0.5rem 1rem; display: flex; align-items: center; gap: 0.5rem;">
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
        @if (session('success'))
            <div class="alert-custom-success mb-4 d-flex align-items-center">
                <i class="material-icons-outlined me-2">check_circle</i>
                <div>{!! session('success') !!}</div>
            </div>
        @endif

        @if (session('error'))
            <div class="alert-custom-danger mb-4 d-flex align-items-center">
                <i class="material-icons-outlined me-2">error</i>
                <div>{!! session('error') !!}</div>
            </div>
        @endif

        <!-- Form Card -->
        <div class="form-card">
            <div class="card-header">
                <h5>
                    <i class="material-icons-outlined">edit_note</i>
                    Campaign Details
                </h5>
            </div>
            <div class="card-body">
                <form action="{{ route('campaign.quick.submit') }}" method="POST" id="quickCampaignForm">
                    @csrf

                    <!-- Campaign Name & Route -->
                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Campaign Name <span class="text-danger">*</span></label>
                            <input type="text" name="campaignname" class="form-control" value="{{ old('campaignname') }}"
                                placeholder="Enter a name to identify this campaign">
                            <div class="form-text">A simple description to help you identify the campaign in future.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Route Letter</label>
                            <input type="text" name="routeletter" class="form-control" value="{{ old('routeletter') }}"
                                placeholder="e.g., d, p, e" maxlength="1" style="text-transform: lowercase;">
                            <div class="form-text">Single letter (d, p, e) for SMS delivery route. Leave blank for default.
                            </div>
                        </div>
                    </div>

                    <div class="section-divider"></div>

                    <!-- Sender ID -->
                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Sender ID (Originator) <span class="text-danger">*</span></label>
                            <select name="quicksenderid1" class="form-select" id="senderIdSelect">
                                <option selected="selected" value="choose" disabled="disabled">Choose...</option>
                                <option value="useotherbelow">use 'other sender id'</option>
                                @if (isset($senderIds) && count($senderIds) > 0)
                                    @foreach ($senderIds as $senderId)
                                        <option value="{{ $senderId }}"
                                            {{ old('quicksenderid1') == $senderId ? 'selected' : '' }}>
                                            {{ $senderId }}
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                            <div class="form-text">This is who the SMS comes "from".</div>
                            @if (isset($senderIds) && count($senderIds) > 0)
                                <div class="sender-id-count">
                                    <i class="material-icons-outlined align-middle me-1"
                                        style="font-size: 16px;">verified</i>
                                    You have <strong>{{ count($senderIds) }}</strong> registered sender ID(s) available
                                </div>
                            @else
                                <div class="sender-id-count">
                                    <i class="material-icons-outlined align-middle me-1" style="font-size: 16px;">info</i>
                                    No registered sender IDs found. Use 'other sender id' option below.
                                </div>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Other Sender ID</label>
                            <input type="text" name="quicksenderid2" class="form-control"
                                value="{{ old('quicksenderid2') }}" placeholder="Enter custom sender ID (max 11 chars)"
                                maxlength="11" id="otherSenderId" disabled="disabled">
                            <div class="form-text">If using custom sender ID, words must be 11 characters or less. Numbers
                                can be up to 15 digits.</div>
                        </div>
                    </div>

                    <div class="section-divider"></div>

                    <!-- Recipients -->
                    <div class="mb-4">
                        <label class="form-label">Recipient Mobile Numbers <span class="text-danger">*</span></label>
                        <textarea name="quickrecipients" class="form-control" rows="6" id="recipientsTextarea"
                            placeholder="Enter mobile numbers (one per line)&#10;&#10;Example:&#10;447123456789&#10;447987654321&#10;07555555555">{{ old('quickrecipients') }}</textarea>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <div class="form-text mb-0">
                                <i class="material-icons-outlined align-middle me-1" style="font-size: 16px;">info</i>
                                UK numbers must begin with 447, 07 or 7. Only use digits - no spaces, plus signs, or special
                                characters.
                            </div>
                            <div class="char-counter">
                                <strong><span id="recipientCount">0</span></strong> recipient(s)
                            </div>
                        </div>
                    </div>

                    <div class="section-divider"></div>

                    <!-- Message -->
                    <div class="mb-4">
                        <label class="form-label">SMS Message <span class="text-danger">*</span></label>
                        <textarea name="quickmsg" class="form-control" rows="4" placeholder="Type your SMS message here..."
                            id="smsMessage">{{ old('quickmsg') }}</textarea>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <div class="form-text mb-0">
                                <i class="material-icons-outlined align-middle me-1" style="font-size: 16px;">info</i>
                                Messages over 160 characters will cost extra.
                            </div>
                            <div class="char-counter" id="charCounterBox">
                                <strong><span id="charCount">0</span></strong> / 160 chars &nbsp;|&nbsp; <strong><span
                                        id="smsCount">1</span></strong> SMS
                            </div>
                        </div>
                    </div>

                    <!-- Warning Box -->
                    <div class="alert-custom-warning mb-4">
                        <div class="d-flex align-items-start">
                            <i class="material-icons-outlined me-2" style="color: #d97706;">warning</i>
                            <div>
                                <strong>Important Notes:</strong>
                                <ul class="mt-2">
                                    <li>SMS will be submitted for sending <strong>immediately</strong>. Large campaigns may
                                        take a few minutes to upload.</li>
                                    <li><strong>Do not refresh the page</strong> after clicking submit.</li>
                                    <li>Leave Route letter blank to use your account's default route.</li>
                                    <li>Some validation occurs after submission - check the previous campaigns page for
                                        status.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex gap-3">
                        <button type="submit" class="btn-primary-custom" id="submitBtn">
                            <i class="material-icons-outlined">send</i>
                            Submit Campaign
                        </button>
                        <a href="{{ route('campaign.dashboard') }}" class="btn-secondary-custom">
                            <i class="material-icons-outlined">close</i>
                            Cancel
                        </a>
                    </div>

                    <!-- Contract Link -->
                    {{-- <div class="contract-link">
                        <small class="text-muted">
                            By using SMS Expert services you agree to the latest contract, privacy policy and applicable
                            laws.
                            <a href="#" target="_blank">View your SMS Expert contract</a>
                        </small>
                    </div> --}}
                </form>
            </div>
        </div>
    </div>
@endsection

@push('js')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const smsMessage = document.getElementById('smsMessage');
            const charCount = document.getElementById('charCount');
            const smsCount = document.getElementById('smsCount');
            const charCounterBox = document.getElementById('charCounterBox');
            const senderIdSelect = document.getElementById('senderIdSelect');
            const otherSenderId = document.getElementById('otherSenderId');
            const submitBtn = document.getElementById('submitBtn');
            const form = document.getElementById('quickCampaignForm');
            const recipientsTextarea = document.getElementById('recipientsTextarea');
            const recipientCount = document.getElementById('recipientCount');

            // Character count for SMS message
            function updateCharCount() {
                const length = smsMessage.value.length;
                charCount.textContent = length;

                if (length <= 160) {
                    smsCount.textContent = '1';
                } else if (length <= 306) {
                    smsCount.textContent = '2';
                } else if (length <= 459) {
                    smsCount.textContent = '3';
                } else {
                    smsCount.textContent = Math.ceil(length / 153);
                }

                if (length > 160) {
                    charCounterBox.classList.add('warning');
                } else {
                    charCounterBox.classList.remove('warning');
                }
            }

            smsMessage.addEventListener('input', updateCharCount);
            updateCharCount(); // Initial count

            // Recipient count
            function updateRecipientCount() {
                const text = recipientsTextarea.value.trim();
                if (text === '') {
                    recipientCount.textContent = '0';
                    return;
                }

                // Split by newlines, commas, or spaces and filter empty entries
                const numbers = text.split(/[\n\r,\s]+/).filter(function(num) {
                    return num.trim() !== '';
                });
                recipientCount.textContent = numbers.length;
            }

            recipientsTextarea.addEventListener('input', updateRecipientCount);
            updateRecipientCount(); // Initial count

            // Sender ID toggle - matches old PHP behavior
            senderIdSelect.addEventListener('change', function() {
                if (this.value === 'useotherbelow') {
                    // Enable "other sender id" input
                    otherSenderId.disabled = false;
                    otherSenderId.focus();
                } else if (this.value === 'choose') {
                    // Disabled state - don't enable other input
                    otherSenderId.disabled = true;
                    otherSenderId.value = '';
                } else {
                    // Selected a registered sender ID - disable and clear other input
                    otherSenderId.disabled = true;
                    otherSenderId.value = '';
                }
            });

            // Check if old value was set for sender ID
            @if (old('quicksenderid1') == 'useotherbelow')
                otherSenderId.disabled = false;
            @endif

            // Form submission with validation
            form.addEventListener('submit', function(e) {
                // Basic validation
                const campaignName = document.querySelector('input[name="campaignname"]').value.trim();
                const senderIdValue = senderIdSelect.value;
                const otherSenderIdValue = otherSenderId.value.trim();
                const recipients = recipientsTextarea.value.trim();
                const message = smsMessage.value.trim();

                let errors = [];

                if (campaignName === '') {
                    errors.push('Campaign name is required.');
                }

                if (senderIdValue === 'choose') {
                    errors.push('Please select a sender ID.');
                } else if (senderIdValue === 'useotherbelow' && otherSenderIdValue === '') {
                    errors.push('Please enter a custom sender ID or select one from the dropdown.');
                }

                if (recipients === '') {
                    errors.push('At least one recipient is required.');
                }

                if (message === '') {
                    errors.push('SMS message cannot be empty.');
                }

                if (errors.length > 0) {
                    e.preventDefault();
                    alert('Please fix the following errors:\n\n' + errors.join('\n'));
                    return false;
                }

                // Disable submit button and show loading
                submitBtn.disabled = true;
                submitBtn.innerHTML =
                    '<span class="spinner-border spinner-border-sm me-2"></span>Submitting Campaign...';
            });
        });
    </script>
@endpush
