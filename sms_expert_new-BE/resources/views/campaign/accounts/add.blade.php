@extends('campaign.layouts.app')

@section('title', 'Add New Sub-Account - Campaign Manager')

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
            background: linear-gradient(135deg, rgba(22, 163, 74, 0.1), rgba(21, 128, 61, 0.1));
            border-bottom: 1px solid rgba(22, 163, 74, 0.2);
            padding: 1rem 1.5rem;
        }

        .form-card .card-header h5 {
            /* color: #166534; */
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
            font-size: 0.9rem;
        }

        .form-label .required {
            color: #dc2626;
        }

        .form-control {
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
            font-size: 0.95rem;
        }

        .form-control:focus {
            border-color: #16a34a;
            box-shadow: 0 0 0 0.2rem rgba(22, 163, 74, 0.15);
        }

        .form-control::placeholder {
            color: #94a3b8;
        }

        .form-text {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 0.35rem;
        }

        .btn-submit {
            background: linear-gradient(135deg, #16a34a, #15803d);
            border: none;
            color: white;
            padding: 6px 10px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(22, 163, 74, 0.4);
            color: white;
        }

        .btn-cancel {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            color: #64748b;
            padding: 6px 10px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .btn-cancel:hover {
            background: #e2e8f0;
            color: #475569;
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

        .info-box {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(37, 99, 235, 0.1));
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 10px;
            padding: 1rem 1.5rem;
            color: #1e40af;
            margin-bottom: 1.5rem;
        }

        .info-box i {
            color: #3b82f6;
        }

        .credentials-box {
            background: linear-gradient(135deg, rgba(22, 163, 74, 0.1), rgba(21, 128, 61, 0.1));
            border: 2px solid #16a34a;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .credentials-box h6 {
            color: #166534;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .credential-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
            padding: 0.5rem;
            background: white;
            border-radius: 8px;
        }

        .credential-item strong {
            min-width: 100px;
            color: #293b50;
        }

        .credential-value {
            font-family: monospace;
            background: #f1f5f9;
            padding: 0.25rem 0.75rem;
            border-radius: 5px;
            font-size: 1rem;
            color: #16a34a;
            font-weight: 600;
        }

        .copy-btn-inline {
            background: transparent;
            border: none;
            color: #16a34a;
            padding: 0.25rem 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-left: 0.5rem;
        }

        .copy-btn-inline:hover {
            color: #15803d;
            background: rgba(22, 163, 74, 0.1);
            border-radius: 5px;
        }
    </style>
@endpush

@section('content')
    <div class="dashboard-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4><i class="material-icons-outlined align-middle me-2">person_add</i>Add New Sub-Account</h4>
                    <p>Create a new sub-account under your master account</p>
                </div>
                <a href="{{ route('campaign.accounts') }}" class="btn"
                    style="background: white; color: #16a34a; font-weight: 600; border-radius: 10px; padding: 0.5rem 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i class="material-icons-outlined">people</i>
                    View Accounts
                </a>
            </div>
        </div>

        <!-- Alert Messages -->
        @if (session('success'))
            <div class="alert-custom-success mb-4 d-flex align-items-start">
                <i class="material-icons-outlined me-2" style="margin-top: 2px;">check_circle</i>
                <div>{!! session('success') !!}</div>
            </div>
        @endif

        @if (session('error'))
            <div class="alert-custom-danger mb-4 d-flex align-items-center">
                <i class="material-icons-outlined me-2">error</i>
                <div>{!! session('error') !!}</div>
            </div>
        @endif

        @if (session('new_account'))
            @php $newAccount = session('new_account'); @endphp
            <div class="credentials-box">
                <h6><i class="material-icons-outlined align-middle me-2">vpn_key</i>New Account Credentials</h6>
                <p class="mb-3 text-muted">Please save these credentials securely. The password cannot be retrieved later.
                </p>
                <div class="credential-item">
                    <strong>Username:</strong>
                    <span class="credential-value" id="newUsername">{{ $newAccount['username'] }}</span>
                    <button type="button" class="copy-btn-inline" onclick="copyCredential('newUsername')">
                        <i class="material-icons-outlined" style="font-size: 18px;">content_copy</i>
                    </button>
                </div>
                <div class="credential-item">
                    <strong>Password:</strong>
                    <span class="credential-value" id="newPassword">{{ $newAccount['password'] }}</span>
                    <button type="button" class="copy-btn-inline" onclick="copyCredential('newPassword')">
                        <i class="material-icons-outlined" style="font-size: 18px;">content_copy</i>
                    </button>
                </div>
                <div class="mt-3">
                    <a href="{{ route('campaign.dashboard.link', ['username' => $newAccount['username']]) }}"
                        {{-- <a href="https://www.itagg.com/platforme.net?itaggpage=login_process&user={{ $newAccount['username'] }}&pass={{ $newAccount['password'] }}&autologin=0&current_page=controlpanel"  --}} target="_blank" class="btn-submit"
                        style="font-size: 0.9rem; padding: 0.5rem 1rem;">
                        <i class="material-icons-outlined">open_in_new</i>
                        Login to New Account
                    </a>
                </div>
            </div>
        @endif

        <!-- Info Box -->
        <div class="info-box d-flex align-items-start">
            <i class="material-icons-outlined me-3" style="margin-top: 2px;">info</i>
            <div>
                <strong>Important:</strong> Please only set the email to that of your client if you are happy for SMS Expert
                to contact them.
                Otherwise, set it to your own email address.
            </div>
        </div>

        <!-- Add Account Form -->
        <div class="form-card">
            <div class="card-header">
                <h5>
                    <i class="material-icons-outlined">badge</i>
                    Sub-Account Details
                </h5>
            </div>
            <div class="card-body">
                <form action="{{ route('campaign.accounts.store') }}" method="POST" id="addAccountForm">
                    @csrf
                    <div class="row g-4">
                        <!-- Contact Name -->
                        <div class="col-md-6">
                            <label class="form-label">
                                Contact Name <span class="required">*</span>
                            </label>
                            <input type="text" name="contactname"
                                class="form-control @error('contactname') is-invalid @enderror"
                                value="{{ old('contactname') }}" placeholder="Enter contact name" required>
                            <div class="form-text">The name of the person who will use this account.</div>
                            @error('contactname')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Business Name -->
                        <div class="col-md-6">
                            <label class="form-label">
                                Business Name <span class="required">*</span>
                            </label>
                            <input type="text" name="busname" class="form-control @error('busname') is-invalid @enderror"
                                value="{{ old('busname') }}" placeholder="Enter business name" required>
                            <div class="form-text">The company or business name for this account.</div>
                            @error('busname')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Email -->
                        <div class="col-md-6">
                            <label class="form-label">
                                Email Address <span class="required">*</span>
                            </label>
                            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                                value="{{ old('email') }}" placeholder="Enter email address" required>
                            <div class="form-text">Email address for account notifications and communications.</div>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Phone -->
                        <div class="col-md-6">
                            <label class="form-label">
                                Phone Number <span class="required">*</span>
                            </label>
                            <input type="tel" name="phone" id="phone" class="form-control"
                                value="{{ old('phone') }}" placeholder="Enter phone number" required
                                oninput="this.value = this.value.replace(/[^0-9+]/g, '')">
                            <div class="form-text">Enter Phone number (numbers only)</div>
                            <div id="phone-error" class="text-danger mt-1" style="display:none;">
                                Please enter a valid phone number.<br>
                                Accepted formats:<br>
                                • +44XXXXXXXXXX<br>
                                • 44XXXXXXXXXX<br>
                                • 07XXXXXXXXX<br>
                                • 01932XXXXXX<br>
                            </div>
                            {{-- @error('phone')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror --}}
                        </div>

                        <!-- Mobile -->
                        <div class="col-md-6">
                            <label class="form-label">
                                Mobile Number <span class="text-muted">(optional)</span>
                            </label>
                            <input type="tel" name="mobile" class="form-control @error('mobile') is-invalid @enderror"
                                value="{{ old('mobile') }}" placeholder="Enter mobile number"
                                oninput="this.value = this.value.replace(/[^0-9+]/g, '')">
                            <div class="form-text">Enter Mobile number (numbers only)</div>
                            @error('mobile')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="d-flex gap-3 mt-4 pt-3 border-top">
                        <button type="submit" class="btn-submit">
                            <i class="material-icons-outlined">person_add</i>
                            Create Sub-Account
                        </button>
                        <a href="{{ route('campaign.dashboard') }}" class="btn-cancel">
                            <i class="material-icons-outlined">close</i>
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Toast Notification -->
    <div class="toast-notification" id="toastNotification"
        style="position: fixed; bottom: 20px; right: 20px; background: linear-gradient(135deg, #16a34a, #15803d); color: white; padding: 1rem 1.5rem; border-radius: 10px; box-shadow: 0 4px 15px rgba(22, 163, 74, 0.4); display: none; z-index: 9999;">
        <i class="material-icons-outlined align-middle me-2">check_circle</i>
        <span id="toastMessage">Copied!</span>
    </div>
@endsection

@push('js')
    <script>
        function copyCredential(elementId) {
            const element = document.getElementById(elementId);
            const text = element.textContent;

            navigator.clipboard.writeText(text).then(function() {
                showToast('Copied: ' + text);
            }).catch(function(err) {
                console.error('Could not copy text: ', err);
                // Fallback for older browsers
                const textarea = document.createElement('textarea');
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
                showToast('Copied: ' + text);
            });
        }

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

        function showToast(message) {
            const toast = document.getElementById('toastNotification');
            const toastMessage = document.getElementById('toastMessage');
            toastMessage.textContent = message;
            toast.style.display = 'flex';
            toast.style.alignItems = 'center';

            setTimeout(function() {
                toast.style.display = 'none';
            }, 3000);
        }

        // Form validation
        document.getElementById('addAccountForm').addEventListener('submit', function(e) {
            const contactname = document.querySelector('input[name="contactname"]').value.trim();
            const busname = document.querySelector('input[name="busname"]').value.trim();
            const email = document.querySelector('input[name="email"]').value.trim();

            if (!contactname || !busname || !email) {
                e.preventDefault();
                alert('Please fill in all required fields (Contact Name, Business Name, and Email).');
                return false;
            }

            if (!confirm('Are you sure you want to create this new sub-account?')) {
                e.preventDefault();
                return false;
            }

            const phoneInput = document.getElementById('phone');
            if (!validatePhone(phoneInput)) {
                e.preventDefault();
                phoneInput.focus();
            }
        });
    </script>
@endpush
