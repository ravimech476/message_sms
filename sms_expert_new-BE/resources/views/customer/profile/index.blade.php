@extends('layouts.app')
@section('title', 'Client Profile - SMS Expert')

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

        .profile-container {
            background: #f8fafc;
            min-height: 100vh;
            margin: -2rem;
            padding: 2rem;
        }

        .profile-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
            margin-bottom: 2rem;
        }

        .profile-card::before {
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

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            color: #293b50;
            font-weight: 600;
            margin-bottom: 0.75rem;
            font-size: 0.95rem;
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
            padding: 0.5rem 1rem;
            font-weight: 600;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.4);
            color: white;
        }

        .required-field::after {
            content: '*';
            color: #dc2626;
            margin-left: 0.25rem;
        }

        .icon-primary {
            color: #ea6118;
            font-size: 1.2rem;
        }

        .welcome-card {
            background: linear-gradient(135deg, rgba(234, 97, 24, 0.1), rgba(41, 59, 80, 0.1));
            border: 2px solid #ea6118;
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            text-align: center;
        }

        .welcome-card h5 {
            color: #293b50;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .welcome-card p {
            color: #64748b;
            margin-bottom: 1rem;
            line-height: 1.6;
        }

        .expiry-info {
            background: linear-gradient(135deg, #fef2f2, #fee2e2);
            border: 2px solid #dc2626;
            border-radius: 10px;
            padding: 1rem;
            color: #dc2626;
            font-weight: 600;
            margin-top: 1rem;
        }

        .limit-info {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border: 2px solid #0891b2;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .limit-info h5 {
            color: #0891b2;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .limit-info p,
        .limit-info ul {
            color: #64748b;
            margin-bottom: 1rem;
            line-height: 1.6;
        }

        .limit-info li {
            color: #293b50;
            font-weight: 600;
        }

        .sender-id-section {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .input-icon {
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .help-icon {
            cursor: pointer;
            color: #64748b;
            transition: all 0.3s ease;
        }

        .help-icon:hover {
            color: #ea6118;
            transform: scale(1.1);
        }

        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
        }

        .modal-header {
            background: linear-gradient(135deg, #ea6118, #293b50);
            color: white;
            border-radius: 15px 15px 0 0;
            border-bottom: none;
            padding: 1.5rem;
        }

        .modal-title {
            color: white !important;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .modal-body {
            padding: 2rem;
            line-height: 1.6;
        }

        .modal-footer {
            border-top: 1px solid #e2e8f0;
            padding: 1.5rem;
        }

        .btn-close {
            filter: invert(1);
        }

        .ip-list-section {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 1.5rem;
        }

        .input-group .btn {
            border-radius: 0 8px 8px 0;
        }

        .input-group .form-control {
            border-radius: 8px 0 0 8px;
        }

        .error-message {
            color: #dc2626;
            font-size: 0.85rem;
            font-weight: 500;
            margin-top: 0.5rem;
        }

        .push-delivery-section {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            border: 2px solid #f59e0b;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .push-delivery-section h5 {
            color: #92400e;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .push-delivery-section p {
            color: #78350f;
            margin-bottom: 0;
            line-height: 1.6;
        }

        .submit-section {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 2rem;
            text-align: center;
            margin-top: 2rem;
        }

        .submit-button {
            background: linear-gradient(135deg, #ea6118, #293b50);
            border: none;
            color: white;
            padding: 1rem 3rem;
            border-radius: 12px;
            font-weight: 700;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .submit-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 30px rgba(234, 97, 24, 0.4);
            color: white;
        }

        .submit-button:disabled {
            background: #cbd5e1;
            transform: none;
            box-shadow: none;
            color: #94a3b8;
        }

        .toast-container {
            z-index: 1060;
        }

        .toast {
            border-radius: 10px;
            border: none;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
        }

        .toast-header {
            background: linear-gradient(135deg, #ea6118, #293b50);
            color: white;
            border-bottom: none;
            border-radius: 10px 10px 0 0;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .password-section {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 1.5rem;
        }

        .advanced-header {
            text-align: center;
            margin: 3rem 0 2rem 0;
            position: relative;
        }

        .advanced-header::before,
        .advanced-header::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 30%;
            height: 2px;
            background: linear-gradient(90deg, transparent, #ea6118, transparent);
        }

        .advanced-header::before {
            left: 0;
        }

        .advanced-header::after {
            right: 0;
        }

        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-active {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
        }

        .status-inactive {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .status-active {
            background: #dcfce7;
            color: #16a34a;
            border: 1px solid #86efac;
        }
    </style>
@endpush

@section('content')
    <div class="profile-container">
        <!-- Toast Container -->
        <div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer">
            <div id="ipToast" class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-bs-autohide="true">
                <div class="toast-header">
                    <strong class="me-auto">
                        <i class="material-icons-outlined">notifications</i>
                        Notification
                    </strong>
                    <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
                <div class="toast-body">
                    <!-- Toast message will go here -->
                </div>
            </div>
        </div>

        <!-- Breadcrumb -->
        <div class="breadcrumb-container d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">
                    <i class="material-icons-outlined icon-primary">account_circle</i>
                    Client Profile
                </div>&nbsp;
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        {{-- <li class="breadcrumb-item">
                        <i class="material-icons-outlined">home</i>
                    </li> --}}
                        <li class="breadcrumb-item">
                            <a href="{{ route('dashboard') }}">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item active">Profile</li>
                    </ol>
                </nav>
            </div>
            <button id="backButton" class="btn btn-outline-secondary back-btn">
                <i class="material-icons-outlined me-1">arrow_back</i> Back
            </button>
        </div>

        <!-- Flash Messages -->
        @if (session('success'))
            <div class="alert alert-success" id="flash-success-message">
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

        <!-- Welcome Card -->
        <div class="welcome-card">
            <h5>
                <i class="material-icons-outlined">waving_hand</i>
                Welcome to Your Profile
            </h5>
            <p>
                Update your profile information below and click '<strong>Confirm all Details</strong>' to save changes.
                An email will be sent to your existing email address (<strong>{{ $user->contactemail }}</strong>)
                to confirm the changes. Your account access will be temporarily blocked until you confirm the changes via
                email.
            </p>
            <div class="expiry-info">
                <i class="material-icons-outlined">schedule</i>
                Account Package Expires: {{ $datefrozenstr ?? 'Not Set' }}
            </div>
        </div>

        <form action="{{ route('profile.submit') }}" id="confirm-form" method="POST">
            @csrf

            <!-- Service Description -->
            <div class="profile-card">
                <div class="section-header">
                    <h5 class="section-title">
                        <i class="material-icons-outlined">description</i>
                        Service Description
                    </h5>
                </div>
                <div class="section-content">
                    <div class="form-group">
                        <label class="form-label required-field">
                            <i class="material-icons-outlined">edit_note</i>
                            Service Description
                        </label>

                        <textarea class="form-control" required id="exampleTextarea" name="service_description" rows="4"
                            placeholder="Describe your SMS service usage...">{{ $get_description->explanation ?? '' }}</textarea>
                        <small class="text-muted">
                            <i class="material-icons-outlined">info</i>
                            Provide a brief description of how you plan to use SMS services
                        </small>
                    </div>
                </div>
            </div>

            <!-- Client Details -->
            <div class="profile-card">
                <div class="section-header">
                    <h5 class="section-title">
                        <i class="material-icons-outlined">business</i>
                        Client Details
                    </h5>
                </div>
                <div class="section-content">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label fw-semibold theme-label-color required-field">
                                <i class="material-icons-outlined">business</i>
                                Business Name
                            </label>
                            <input type="text" class="form-control" name="busname" required
                                placeholder="Enter business name" value="{{ urldecode($user->busname ?? '') }}">
                        </div>

                        <div class="form-group">
                            <label class="form-label fw-semibold theme-label-color required-field">
                                <i class="material-icons-outlined">person</i>
                                Contact Name
                            </label>
                            <input type="text" class="form-control" name="contactname" required
                                placeholder="Enter contact name"
                                value="{{ urldecode($user->contactname ?? '') }}">
                        </div>

                        <div class="form-group">
                            <label class="form-label fw-semibold theme-label-color required-field">
                                <i class="material-icons-outlined">location_on</i>
                                Address Line 1
                            </label>
                            <input type="text" class="form-control" name="address1" required
                                placeholder="Enter address line 1"
                                value="{{ urldecode($user->address1 ?? '') }}">
                        </div>

                        <div class="form-group">
                            <label class="form-label fw-semibold theme-label-color">
                                <i class="material-icons-outlined">location_on</i>
                                Address Line 2
                            </label>
                            <input type="text" class="form-control" name="address2" placeholder="Enter address line 2"
                                value="{{ urldecode($user->address2 ?? '') }}">
                        </div>

                        <div class="form-group">
                            <label class="form-label fw-semibold theme-label-color required-field">
                                <i class="material-icons-outlined">location_city</i>
                                Town/City
                            </label>
                            <input type="text" class="form-control" name="town" required placeholder="Enter town"
                                value="{{ urldecode($user->town ?? '') }}">
                        </div>

                        {{-- <div class="form-group">
                        <label class="form-label required-field">
                            <i class="material-icons-outlined">location_city</i>
                            City
                        </label>
                        <input type="text" class="form-control" name="city" required
                               placeholder="Enter city" value="{{ $user->city ?? '' }}">
                    </div> --}}

                        {{-- <div class="form-group">
                        <label class="form-label fw-semibold theme-label-color">
                            <i class="material-icons-outlined">map</i>
                            County
                        </label>
                        <input type="text" class="form-control" name="county"
                               placeholder="Enter county" value="{{ $user->county ?? '' }}">
                    </div> --}}
                        <div class="form-group">
                            <label class="form-label fw-semibold theme-label-color">
                                <i class="material-icons-outlined">public</i>
                                Country
                            </label>
                            <input type="text" class="form-control" name="country" placeholder="Enter country"
                                value="{{ urldecode($user->country ?? '') }}">
                        </div>

                        <div class="form-group">
                            <label class="form-label fw-semibold theme-label-color required-field">
                                <i class="material-icons-outlined">local_post_office</i>
                                Post Code
                            </label>
                            {{-- Post codes are alphanumeric (UK e.g. "LE12 7PU"): allow letters,
                                 digits and spaces. The old digit-only mask stripped the letters. --}}
                            <input type="text" class="form-control" name="pcode" required
                                placeholder="Enter post code" value="{{ urldecode($user->pcode ?? '') }}"
                                oninput="this.value = this.value.replace(/[^A-Za-z0-9 ]/g, '').toUpperCase()">
                        </div>



                        <div class="form-group">
                            <label class="form-label fw-semibold theme-label-color">
                                <i class="material-icons-outlined">phone_android</i>
                                Mobile Number
                            </label>
                            <input type="text" class="form-control" name="mobilenumber"
                                placeholder="Enter mobile number" value="{{ $user->mobilenumber ?? '' }}"
                                oninput="this.value = this.value.replace(/[^0-9+]/g, '')">
                        </div>

                        <div class="form-group">
                            <label class="form-label fw-semibold theme-label-color required-field">
                                <i class="material-icons-outlined">phone</i>
                                Phone Number
                            </label>
                            <input type="text" class="form-control" name="phone" id="phone"required
                                placeholder="Enter phone number" value="{{ $user->phone ?? '' }}"
                                oninput="this.value = this.value.replace(/[^0-9+]/g, '')">
                            <div id="phone-error" class="text-danger mt-1" style="display:none;">
                                Please enter a valid phone number.<br>
                                Accepted formats:<br>
                                • +44XXXXXXXXXX<br>
                                • 44XXXXXXXXXX<br>
                                • 07XXXXXXXXX<br>
                                • 01932XXXXXX<br>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label fw-semibold theme-label-color required-field">
                                <i class="material-icons-outlined">email</i>
                                Email Address
                            </label>
                            <input type="email" class="form-control" name="contactemail" required
                                placeholder="Enter email address" value="{{ $user->contactemail ?? '' }}">
                        </div>
                    </div>

                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="material-icons-outlined">info</i>
                            <strong>*</strong> Denotes a required field &nbsp;&nbsp;&nbsp;
                            {{-- <strong>**</strong> You must supply either a Town or City name --}}
                        </small>
                    </div>
                </div>
            </div>

            <!-- SMS Limits -->
            <div class="limit-info">
                <h5>
                    <i class="material-icons-outlined">speed</i>
                    Daily SMS Sending Limits
                </h5>
                <p>Each day you are currently allowed to send up to:</p>
                <ul>
                    <li>{{ $user->bulk_throughput ?? 'Unlimited' }} SMS messages</li>
                </ul>
                <p>To increase your limit, please contact the helpdesk or your account director.</p>
            </div>

            <!-- Sender ID Section -->
            <div class="profile-card">
                <div class="section-header">
                    <h5 class="section-title">
                        <i class="material-icons-outlined">perm_identity</i>
                        Sender ID Configuration
                    </h5>
                </div>
                <div class="section-content">
                    <div class="sender-id-section">
                        <div class="form-group">
                            <label class="form-label fw-semibold theme-label-color">
                                <i class="material-icons-outlined">send</i>
                                Default Sender ID
                            </label>
                            <div class="input-icon">
                                <input type="text" class="form-control" id="defaultsenderid" name="defaultsenderid"
                                    value="{{ $get_description->defaultsenderid ?? '' }}" placeholder="Enter sender ID">
                                <i class="material-icons-outlined help-icon" data-bs-toggle="modal"
                                    data-bs-target="#SenderIdModel">
                                    help
                                </i>
                            </div>
                            <small class="text-muted">
                                <i class="material-icons-outlined">info</i>
                                This will be used as the default sender for your SMS messages
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Advanced Options Header -->
            <div>
                <h4 class="section-title" style="margin: 0;">
                    <i class="material-icons-outlined">tune</i>
                    Advanced Options
                </h4>
            </div><br>

            <!-- Security Section -->
            <div class="profile-card">
                <div class="section-header">
                    <h5 class="section-title">
                        <i class="material-icons-outlined">security</i>
                        Security Settings
                    </h5>
                </div>
                <div class="section-content">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="password-section">
                                <h6 class="mb-3">
                                    <i class="material-icons-outlined">lock</i>
                                    Change Password
                                    <i class="material-icons-outlined help-icon" data-bs-toggle="modal"
                                        data-bs-target="#PasswordModel">
                                        help
                                    </i>
                                </h6>

                                <div class="form-group">
                                    <label class="form-label fw-semibold theme-label-color">Current Password</label>
                                    <input type="password" id="currentPassword" class="form-control"
                                        name="currentpassword">
                                    <div id="currentPasswordError" class="error-message"></div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label fw-semibold theme-label-color">New Password</label>
                                    <input type="password" id="newPassword" class="form-control" name="newpassword1">
                                    <div id="newPasswordError" class="error-message"></div>
                                </div>

                                <div class="form-group">
                                    <label class="form-label fw-semibold theme-label-color">Confirm New Password</label>
                                    <input type="password" id="retypePassword" class="form-control" name="newpassword2">
                                    <div id="retypePasswordError" class="error-message"></div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="ip-list-section">
                                <h6 class="mb-3">
                                    <i class="material-icons-outlined">vpn_lock</i>
                                    IP Access Control
                                    <i class="material-icons-outlined help-icon" data-bs-toggle="modal"
                                        data-bs-target="#IpAccessModel">
                                        help
                                    </i>
                                </h6>

                                <div class="form-group">
                                    <label class="form-label fw-semibold theme-label-color">Authorized IP Addresses</label>
                                    <select size="6" multiple id="iplist" name="iplist[]" class="form-select">
                                        @foreach ($ips as $ip)
                                            @if (!empty($ip))
                                                <option value="{{ $ip }}">{{ $ip }}</option>
                                            @endif
                                        @endforeach
                                    </select>
                                </div>

                                <div class="input-group">
                                    <input type="text" class="form-control" id="newip" name="newip"
                                        placeholder="Enter IP address">
                                    <button class="btn btn-primary" type="button" onclick="add_ip()">
                                        <i class="material-icons-outlined">add</i>
                                    </button>
                                    <button class="btn btn-danger" type="button" onclick="delete_ip()">
                                        <i class="material-icons-outlined">delete</i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Push Delivery Receipts -->
            <div class="push-delivery-section">
                <h5>
                    <i class="material-icons-outlined">receipt</i>
                    Push Delivery Receipts
                </h5>
                @if(!empty($get_description->dreceipt_push_url))
                <p>
                    <span class="status-indicator status-active">
                        <i class="material-icons-outlined">check_circle</i>
                        Active
                    </span>
                    Delivery receipts are being pushed to your configured URL.
                    <a href="{{ route('delivery-receipt') }}" class="ms-2" style="color: #ea6118;">
                        <i class="material-icons-outlined" style="font-size: 14px; vertical-align: middle;">settings</i>
                        Manage Settings
                    </a>
                </p>
                <div class="mt-2" style="background: #f8f9fa; padding: 10px; border-radius: 8px; font-size: 13px;">
                    <strong>URL:</strong> <code style="word-break: break-all;">{{ $get_description->dreceipt_push_url }}</code>
                </div>
                @else
                <p>
                    <span class="status-indicator status-inactive">
                        <i class="material-icons-outlined">pending</i>
                        Not Active
                    </span>
                    Delivery receipt push is not yet configured.
                    <a href="{{ route('delivery-receipt') }}" class="ms-2" style="color: #ea6118;">
                        <i class="material-icons-outlined" style="font-size: 14px; vertical-align: middle;">add_circle</i>
                        Configure Now
                    </a>
                </p>
                @endif
            </div>

            <!-- Submit Section -->
            <div class="submit-section">
                <button type="submit" class="submit-button" id="confirm-button">
                    <i class="material-icons-outlined">verified</i>
                    Confirm All Details
                    <i class="material-icons-outlined">arrow_forward</i>
                </button>
                <div class="mt-3">
                    <small class="text-muted">
                        <i class="material-icons-outlined">info</i>
                        Changes will require email confirmation before taking effect
                    </small>
                </div>
            </div>
        </form>
    </div>

    <!-- Modals -->
    <!-- Sender ID Modal -->
    <div class="modal fade" id="SenderIdModel" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="material-icons-outlined">help</i>
                        Sender ID Information
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>The Sender ID is the sender name the recipient sees when they receive a standard rate SMS message.
                        You cannot set the sender ID for premium rate messages - these will automatically have a shortcode
                        ID.</p>
                    <p><strong>There are three different types of Sender ID:</strong></p>
                    <ul>
                        <li><strong>Mobile number:</strong> A mobile number (11 to 15 characters) starting with the country
                            code (44 for the UK) or 0.</li>
                        <li><strong>Alphanumeric:</strong> A string of up to 11 characters starting with a letter and
                            consisting of letters, numbers, spaces, full stops, and hyphens.</li>
                        <li><strong>Shortcode:</strong> A shortcode number which can be up to 5 digits long, such as 83248.
                        </li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                        <i class="material-icons-outlined">close</i>
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Password Modal -->
    <div class="modal fade" id="PasswordModel" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="material-icons-outlined">lock</i>
                        Password Security
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>This section allows you to change your account password. Please note that passwords are
                        case-sensitive.</p>
                    <p>When changing your password, you'll be asked for the new password twice to prevent typing errors.</p>
                    <p><strong>Security Recommendation:</strong> We recommend changing your password frequently (at least
                        once a month) to ensure maximum account security.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                        <i class="material-icons-outlined">close</i>
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- IP Access Modal -->
    <div class="modal fade" id="IpAccessModel" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="material-icons-outlined">vpn_lock</i>
                        IP Access Control
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>The IP Security feature allows you to specify which IP addresses can access your SMS APIs.</p>
                    <p>When this feature is enabled, only connections from the specified IP addresses will be allowed to use
                        your SMS APIs.</p>
                    <p><strong>Benefits:</strong></p>
                    <ul>
                        <li>Enhanced security for your SMS account</li>
                        <li>Protection against unauthorized API access</li>
                        <li>Control over who can send SMS through your account</li>
                    </ul>
                    <p>Leave the list empty to allow access from any IP address.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
                        <i class="material-icons-outlined">close</i>
                        Close
                    </button>
                </div>
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


        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide flash messages
            setTimeout(function() {
                const flashMessages = document.querySelectorAll(
                    '#flash-success-message, #flash-error-message');
                flashMessages.forEach(msg => {
                    if (msg) {
                        msg.style.opacity = '0';
                        msg.style.transform = 'translateY(-20px)';
                        setTimeout(() => msg.style.display = 'none', 300);
                    }
                });
            }, 4000);

            // Automatically select all IP options on page load
            const ipList = document.getElementById('iplist');
            for (let i = 0; i < ipList.options.length; i++) {
                ipList.options[i].selected = true;
            }

            // Smooth animations
            const cards = document.querySelectorAll('.profile-card, .welcome-card, .limit-info');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';

                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            console.log('Profile page loaded successfully!');
        });

        // Toast message function
        function showToast(message) {
            const toastEl = document.getElementById('ipToast');
            const toastBody = toastEl.querySelector('.toast-body');
            toastBody.textContent = message;

            const toast = new bootstrap.Toast(toastEl, {
                delay: 3000
            });

            toast.show();
        }

        // Function to add IP address
        function add_ip() {
            const ipInput = document.getElementById('newip');
            const ipList = document.getElementById('iplist');
            const ipValue = ipInput.value.trim();
            const regExp = new RegExp(
                "\\b(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\\b"
                );

            if (ipValue.match(regExp)) {
                let found = false;
                const options = ipList.options;
                for (let i = 0; i < options.length; i++) {
                    if (options[i].value === ipValue) {
                        found = true;
                        break;
                    }
                }

                if (!found) {
                    const newOption = document.createElement('option');
                    newOption.value = ipValue;
                    newOption.text = ipValue;
                    newOption.selected = true;
                    ipList.add(newOption);

                    ipInput.value = '';
                    showToast('IP address added successfully.');
                } else {
                    showToast('This IP address is already in the list.');
                }
            } else {
                showToast('Please enter a valid IP address.');
            }
        }

        // Function to delete selected IP
        function delete_ip() {
            const ipList = document.getElementById('iplist');
            let ipSelected = false;

            for (let i = 0; i < ipList.options.length; i++) {
                if (ipList.options[i].selected) {
                    ipSelected = true;
                    ipList.remove(i);
                    showToast('IP address removed successfully.');
                    break;
                }
            }

            if (!ipSelected) {
                showToast('Please select an IP address to delete.');
            }
        }

        // Form submission validation
        document.getElementById('confirm-form').addEventListener('submit', function(e) {
            const correctCurrentPassword = @json($user->pword);
            const currentPasswordInput = document.getElementById('currentPassword');
            const newPasswordInput = document.getElementById('newPassword');
            const retypePasswordInput = document.getElementById('retypePassword');
            const currentPasswordError = document.getElementById('currentPasswordError');
            const newPasswordError = document.getElementById('newPasswordError');
            const retypePasswordError = document.getElementById('retypePasswordError');
            const confirmButton = document.getElementById('confirm-button');
            const phoneInput = document.getElementById('phone');

            if (!validatePhone(phoneInput)) {
                e.preventDefault();
                phoneInput.focus();
            }

            // Clear previous errors
            currentPasswordError.textContent = '';
            newPasswordError.textContent = '';
            retypePasswordError.textContent = '';

            let hasErrors = false;

            // Only validate password if current password is entered
            if (currentPasswordInput.value.trim() !== '') {
                if (newPasswordInput.value.trim() === '' || retypePasswordInput.value.trim() === '') {
                    retypePasswordError.textContent = 'Please fill in both new password fields.';
                    hasErrors = true;
                }

                if (newPasswordInput.value !== retypePasswordInput.value) {
                    retypePasswordError.textContent = 'The new passwords do not match.';
                    hasErrors = true;
                }

                if (currentPasswordInput.value !== correctCurrentPassword) {
                    currentPasswordError.textContent = 'The current password is incorrect.';
                    hasErrors = true;
                }
            }

            if (hasErrors) {
                e.preventDefault();
                return;
            }

            // Disable button and show processing state
            confirmButton.disabled = true;
            confirmButton.innerHTML = '<i class="material-icons-outlined">hourglass_empty</i> Processing...';

            // Re-enable button after timeout
            setTimeout(function() {
                confirmButton.disabled = false;
                confirmButton.innerHTML =
                    '<i class="material-icons-outlined">verified</i> Confirm All Details <i class="material-icons-outlined">arrow_forward</i>';
            }, 9000);
        });
    </script>
@endpush
