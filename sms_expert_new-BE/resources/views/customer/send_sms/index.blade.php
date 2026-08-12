@extends('layouts.app')
@section('title', 'Send SMS - SMS Expert')

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

        /* Pagination Controls */
        #paginationControls {
            padding: 0.5rem 0;
        }

        #paginationControls .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.4rem 0.75rem;
            font-size: 0.85rem;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        #paginationControls .btn:not(:disabled):hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }

        #btnResetPagination {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        #paginationInfo {
            font-weight: 500;
            color: #64748b;
        }

        .contact-list {
            min-height: 200px;
            max-height: 300px;
        }

        .sendsms-container {
            background: #f8fafc;
            min-height: 100vh;
            margin: -2rem;
            padding: 2rem;
        }

        .sendsms-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
        }

        .sendsms-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, #ea6118, #293b50);
        }

        .sendsms-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .accordion {
            --bs-accordion-active-color: #fff !important;
            --bs-accordion-active-bg: linear-gradient(135deg, #ea6118, #293b50) !important;
            --bs-accordion-btn-active-icon: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23fff'%3e%3cpath fill-rule='evenodd' d='M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
        }

        .accordion-item {
            border: 1px solid #e2e8f0;
            border-radius: 15px !important;
            margin-bottom: 1rem;
            overflow: hidden;
        }

        .accordion-button {
            background: white;
            color: #293b50;
            font-weight: 600;
            font-size: 1.1rem;
            border: none;
            padding: 1.5rem;
            border-radius: 15px !important;
        }

        .accordion-button:not(.collapsed) {
            background: linear-gradient(135deg, #ea6118, #293b50) !important;
            color: white !important;
            box-shadow: none;
        }

        .accordion-button:focus {
            border-color: #ea6118 !important;
            box-shadow: 0 0 0 0.25rem rgba(234, 97, 24, 0.25) !important;
        }

        .accordion-body {
            padding: 2rem;
            background: white;
        }

        .section-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            overflow: hidden;
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

        .form-check-input:checked {
            background-color: #ea6118;
            border-color: #ea6118;
        }

        .form-check-label {
            color: #475569;
            font-weight: 500;
        }

        .btn-primary {
            background: linear-gradient(135deg, #ea6118, #293b50);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
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

        .btn-success {
            background: linear-gradient(135deg, #16a34a, #15803d);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(22, 163, 74, 0.4);
            color: white;
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
        }

        .alert-success {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
        }

        .alert-danger {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            color: white;
        }

        .wallet-display {
            background: linear-gradient(135deg, #ea6118, #293b50);
            color: white;
            padding: 1.5rem;
            border-radius: 15px;
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .wallet-amount {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .wallet-label {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.9rem;
        }

        .info-icon {
            cursor: pointer;
            color: #64748b;
            transition: all 0.3s ease;
        }

        .info-icon:hover {
            color: #ea6118;
            transform: scale(1.1);
        }

        .char-counter {
            color: #64748b;
            font-weight: 600;
            font-size: 0.9rem;
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
        }

        .modal-title {
            color: white !important;
            font-weight: 600;
        }

        .btn-close {
            filter: invert(1);
        }

        .schedule-section {
            background: #f8fafc;
            border-radius: 10px;
            padding: 1rem;
            border: 2px dashed #e2e8f0;
            margin-top: 1rem;
        }

        .contact-list {
            min-height: 200px;
            max-height: 250px;
            overflow-y: auto;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 0.5rem;
        }

        .radio-group {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
            margin-top: 0.5rem;
        }

        .message-type-section {
            background: #f8fafc;
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .icon-primary {
            color: #ea6118;
            font-size: 1.2rem;
        }

        .upload-section {
            background: #f8fafc;
            border: 2px dashed #e2e8f0;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .upload-section:hover {
            border-color: #ea6118;
            background: rgba(234, 97, 24, 0.05);
        }

        .campaign-section {
            background: linear-gradient(135deg, rgba(234, 97, 24, 0.1), rgba(41, 59, 80, 0.1));
            border: 2px solid #ea6118;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
        }

        .calculate-message {
            display: none;
            background: linear-gradient(135deg, #0891b2, #0e7490);
            color: white;
            border-radius: 10px;
            padding: 1rem 1.5rem;
            margin-bottom: 1rem;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-row {
            display: flex;
            gap: 1rem;
            align-items: end;
            flex-wrap: wrap;
        }

        .form-row>div {
            flex: 1;
            min-width: 120px;
        }
    </style>
@endpush

@section('content')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <div class="sendsms-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb-container d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">
                    <i class="material-icons-outlined icon-primary">send</i>
                    Send SMS
                </div>&nbsp;
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        {{-- <li class="breadcrumb-item">
                        <i class="material-icons-outlined">home</i>
                    </li> --}}
                        <li class="breadcrumb-item">
                            <a href="{{ route('dashboard') }}">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item active">Send SMS</li>
                    </ol>
                </nav>
            </div>
            <button id="backButton" class="btn btn-outline-secondary back-btn">
                <i class="material-icons-outlined me-1">arrow_back</i> Back
            </button>
        </div>

        <!-- Flash Messages -->
        @if (session('message'))
            <div class="alert alert-success" id="flash-message">
                <i class="material-icons-outlined">check_circle</i>
                {!! session('message') !!}
            </div>
        @endif

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

        @error('fileUpload')
            <div class="alert alert-danger" id="flash-error-message">
                <i class="material-icons-outlined">error</i>
                {{ $message }}
            </div>
        @enderror

        <!-- Calculate Message Alert -->
        <div id="calculate-message" class="calculate-message"></div>

        <!-- Main Content Card -->
        <div class="sendsms-card">
            <div class="accordion" id="smsAccordion">

                <!-- Option 1: Quick Send SMS -->
                
                <div class="accordion-item">
                    <h2 class="accordion-header" id="headingOne">
                        <button class="accordion-button" type="button" data-bs-toggle="collapse"
                            data-bs-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                            <i class="material-icons-outlined me-2">flash_on</i>
                            Option 1: Quickly Send SMS Messages
                        </button>
                    </h2>
                    <form id="smsForm" action="{{ route('send.sms.client') }}" method="POST">
                        @csrf
                        <input type="hidden" name="send_type" id="send_type" value="send_now">
                        <div id="collapseOne" class="accordion-collapse collapse show" aria-labelledby="headingOne"
                            data-bs-parent="#smsAccordion">
                            <div class="accordion-body">
                                <div class="row g-4">

                                    <!-- Left Column - Message Composition -->
                                    <div class="col-12 col-xl-6">
                                        <div class="section-card">
                                            <div class="section-header">
                                                <h5 class="section-title">
                                                    <i class="material-icons-outlined">create</i>
                                                    Compose Message
                                                </h5>
                                            </div>
                                            <div class="section-content">

                                                <!-- Payment Type -->
                                                <div class="mb-4">
                                                    <label class="form-label fw-semibold theme-label-color">
                                                        <i class="material-icons-outlined">account_balance_wallet</i>
                                                        Who is Paying?
                                                    </label>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="rdoType"
                                                            id="rdoTypeStandard" value="Standard" checked
                                                            onclick="payType()">
                                                        <label class="form-check-label" for="rdoTypeStandard">
                                                            I will pay using my Wallet
                                                        </label>
                                                    </div>
                                                </div>

                                                <!-- Message Types -->
                                                <div class="message-type-section">
                                                    <label class="form-label fw-semibold theme-label-color">
                                                        <i class="material-icons-outlined">message</i>
                                                        Select Message Type
                                                    </label>
                                                    <div class="radio-group">
                                                        <div class="form-check">
                                                            <input class="form-check-input" type="radio" id="chkSms"
                                                                name="messageTypes" value="sms" checked>
                                                            <label class="form-check-label" for="chkSms">
                                                                <i class="material-icons-outlined">sms</i> SMS
                                                            </label>
                                                        </div>
                                                        @if ($whatsapp == 'yes')
                                                            <div class="form-check">
                                                                <input class="form-check-input" type="radio"
                                                                    id="chkEmail" name="messageTypes" value="whatsapp">
                                                                <label class="form-check-label" for="chkEmail">
                                                                    <i class="material-icons-outlined">chat</i> WhatsApp
                                                                </label>
                                                            </div>
                                                        @endif

                                                    </div>
                                                </div>

                                                <!-- Sender ID -->
                                                <div class="mb-4">
                                                    <label class="form-label fw-semibold theme-label-color">
                                                        <i class="material-icons-outlined">person</i>
                                                        From (Sender ID)
                                                        <i class="material-icons-outlined info-icon"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#SMSSenderIdModel">help</i>
                                                    </label>

                                                    @php $get_description = $user->options->first(); @endphp

                                                    <div class="form-check mb-2">
                                                        <input class="form-check-input" type="radio" name="rdoSender"
                                                            id="rdoSenderDefault" value="Default">
                                                        <label class="form-check-label" for="rdoSenderDefault">
                                                            {{ $get_description->defaultsenderid ?? 'Default Sender' }}
                                                        </label>
                                                    </div>

                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="rdoSender"
                                                            id="rdoSenderSpecific" value="Specific" checked>
                                                        <label class="form-check-label"
                                                            for="rdoSenderSpecific">Custom:</label>
                                                    </div>

                                                    <div class="mt-2">
                                                        <input type="text" class="form-control"
                                                            name="txtSenderSpecific" id="txtSenderSpecific"
                                                            value="{{ $user->sms_sender_id ?? '' }}" maxlength="15"
                                                            placeholder="Enter sender ID">
                                                    </div>

                                                    <input type="hidden" name="txtSenderDefault" id="txtSenderDefault"
                                                        value="MYBRANDNAME">
                                                </div>

                                                <!-- SMS Form -->


                                                <!-- Recipients -->
                                                <div class="mb-4">
                                                    <label for="txtTo" class="form-label">
                                                        <i class="material-icons-outlined">people</i>
                                                        To (Recipients)
                                                        <i class="material-icons-outlined info-icon"
                                                            data-bs-toggle="modal" data-bs-target="#ToModel">help</i>
                                                    </label>
                                                    <textarea class="form-control" id="txtTo" name="txtTo" rows="4"
                                                        placeholder="Enter mobile numbers separated by commas...">{{ old('txtTo') }}</textarea>
                                                    @if ($errors->has('txtTo'))
                                                        <div class="text-danger mt-2">{{ $errors->first('txtTo') }}</div>
                                                    @endif
                                                </div>

                                                <!-- Message Content -->
                                                <div class="mb-4">
                                                    <label for="messageContent" class="form-label">
                                                        <i class="material-icons-outlined">edit</i>
                                                        Message Content
                                                    </label>
                                                    <textarea class="form-control" id="messageContent" name="messageContent" rows="4"
                                                        placeholder="Type your message here...">{{ old('messageContent') }}</textarea>
                                                    @if ($errors->has('messageContent'))
                                                        <div class="text-danger mt-2">
                                                            {{ $errors->first('messageContent') }}</div>
                                                    @endif
                                                    <small id="txtCounter" class="char-counter mt-2">0 characters</small>
                                                </div>

                                                <!-- Action Buttons -->
                                                <div class="d-flex gap-3 mb-4">
                                                    <button type="button" class="btn btn-primary"
                                                        onclick="calculateCost(event)">
                                                        <i class="material-icons-outlined">calculate</i>
                                                        Calculate Cost
                                                    </button>
                                                    <button type="button" class="btn btn-success"
                                                        onclick="submitForm('send_now')">
                                                        <i class="material-icons-outlined">send</i>
                                                        Send Now
                                                    </button>
                                                </div>

                                                <!-- Schedule Section -->
                                                <div class="schedule-section">
                                                    <label class="form-label fw-semibold theme-label-color">
                                                        <i class="material-icons-outlined">schedule</i>
                                                        Schedule Message
                                                    </label>
                                                    <div class="form-row">
                                                        <div>
                                                            <button type="button" class="btn btn-primary"
                                                                onclick="submitForm('send_later')">
                                                                <i class="material-icons-outlined">schedule_send</i>
                                                                Send at
                                                            </button>
                                                        </div>
                                                        <div>
                                                            <input type="date" class="form-control" name="send_date"
                                                                value="{{ \Carbon\Carbon::now('Europe/London')->format('Y-m-d') }}">
                                                        </div>
                                                        <div>
                                                            <select name="send_hh" class="form-select">
                                                                @for ($i = 0; $i <= 23; $i++)
                                                                    <option value="{{ sprintf('%02d', $i) }}"
                                                                        {{ $i == $current_hour ? 'selected' : '' }}>
                                                                        {{ sprintf('%02d', $i) }}:00
                                                                    </option>
                                                                @endfor
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <select name="send_mm" class="form-select">
                                                                @for ($i = 0; $i <= 55; $i += 5)
                                                                    <option value="{{ sprintf('%02d', $i) }}"
                                                                        {{ $i == $rounded_minute ? 'selected' : '' }}>
                                                                        :{{ sprintf('%02d', $i) }}
                                                                    </option>
                                                                @endfor
                                                            </select>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                    </form>
                    <!-- Right Column - Wallet & Contacts -->
                    <div class="col-12 col-xl-6">

                        <!-- Wallet Balance -->
                        <div class="wallet-display">
                            <div class="wallet-amount">£ {{ sprintf('%.2f', $remaining_wallet ?? 0) }}</div>
                            <div class="wallet-label">
                                Remaining in your <a href="{{ route('sms_wallet.index') }}"
                                    style="color: white; text-decoration: underline;">Wallet</a>
                            </div>
                        </div>

                        <!-- Contacts & Groups -->
                        <div class="section-card">
                            <div class="section-header">
                                <h5 class="section-title">
                                    <i class="material-icons-outlined">contacts</i>
                                    Contacts & Groups
                                </h5>
                            </div>
                            <div class="section-content">

                                <div class="mb-3">
                                    <label for="selListType" class="form-label">
                                        <i class="material-icons-outlined">list</i>
                                        Select List Type
                                    </label>
                                    <select class="form-select" id="selListType" name="selListType">
                                        <option value="favourites" selected>Favourites</option>
                                        <option value="groups">Groups</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label for="selList" class="form-label">
                                        <i class="material-icons-outlined">person</i>
                                        Available Contacts
                                    </label>
                                    <input type="text" id="searchBox" placeholder="Type here to search.."
                                        class="form-control mb-2">

                                    <select class="form-select contact-list" id="selList" name="selList" multiple style="min-height: 200px;">
                                        <!-- Options will be populated via JavaScript -->
                                    </select>

                                    <!-- Pagination Controls -->
                                    <div class="d-flex justify-content-between align-items-center mt-2" id="paginationControls">
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnPrevPage" disabled>
                                            <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">chevron_left</i> Previous
                                        </button>
                                        <span class="text-muted" id="paginationInfo" style="font-size: 0.85rem;">Page 1 of 1</span>
                                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnNextPage" disabled>
                                            Next <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">chevron_right</i>
                                        </button>
                                    </div>
                                    <div class="text-center mt-2">
                                        <button type="button" class="btn btn-outline-warning btn-sm" id="btnResetPagination">
                                            <i class="material-icons-outlined" style="font-size: 16px; vertical-align: middle;">refresh</i> Reset
                                        </button>
                                    </div>
                                </div>

                                <button class="btn btn-primary w-100" onclick="addThese()" id="btnAddThese">
                                    <i class="material-icons-outlined">add</i>
                                    Add Selected Contacts
                                </button>

                                <input type="hidden" id="bigid" value="{{ $bigid ?? '' }}">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Option 2: Campaign Manager -->
    <div class="accordion-item">
        <h2 class="accordion-header" id="headingTwo">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                data-bs-target="#collapseTwo" aria-expanded="false" aria-controls="collapseTwo">
                <i class="material-icons-outlined me-2">campaign</i>
                Option 2: Send Large Volumes of SMS using the Campaign Manager
            </button>
        </h2>
        <div id="collapseTwo" class="accordion-collapse collapse" aria-labelledby="headingTwo"
            data-bs-parent="#smsAccordion">
            <div class="accordion-body">
                <div class="campaign-section">
                    <h4 class="mb-3" style="color: #293b50; font-weight: 700;">
                        <i class="material-icons-outlined">trending_up</i>
                        SMS Campaign Manager
                    </h4>
                    <p class="mb-4" style="color: #64748b;">
                        The Campaign Manager is perfect for sending very large SMS campaigns with advanced features and
                        detailed analytics.
                    </p>
                    <a href="{{ route('customer.link.redirect', ['username' => $user->uname]) }}" target="_blank"
                        class="btn btn-primary btn-lg">
                        <i class="material-icons-outlined">launch</i>
                        Launch Campaign Manager
                    </a>

                    {{-- <button class="btn btn-primary btn-lg">
                        <i class="material-icons-outlined">launch</i>
                        Launch Campaign Manager
                    </button> --}}
                    <p class="mt-3" style="color: #64748b; font-size: 0.9rem;">
                        SMS, delivery receipts, and replies sent from the Campaign Manager can also be viewed in this
                        Dashboard.
                        <br><strong>Need help?</strong> Please ask if you need assistance using the Campaign Manager for the
                        first time.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Option 3: Upload Blacklisted Numbers -->
    <div class="accordion-item">
        <h2 class="accordion-header" id="headingThree">
            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                data-bs-target="#collapseThree" aria-expanded="false" aria-controls="collapseThree">
                <i class="material-icons-outlined me-2">block</i>
                Option 3: Upload Blacklisted Numbers
            </button>
        </h2>
        <div id="collapseThree" class="accordion-collapse collapse" aria-labelledby="headingThree"
            data-bs-parent="#smsAccordion">
            <div class="accordion-body">
                <div class="upload-section">
                    <form action="{{ route('blacklist.upload') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-4">
                            <i class="material-icons-outlined"
                                style="font-size: 3rem; color: #ea6118; margin-bottom: 1rem;">cloud_upload</i>
                            <h5 class="mb-3" style="color: #293b50; font-weight: 600;">Upload Blacklist File</h5>
                            <input class="form-control" type="file" name="fileUpload" id="fileUpload"
                                accept=".txt">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="material-icons-outlined">upload</i>
                            Upload File
                        </button>
                    </form>

                    <div class="mt-4" style="text-align: left;">
                        <h6 style="color: #293b50; font-weight: 600;">File Requirements:</h6>
                        <ul style="color: #64748b; text-align: left; display: inline-block;">
                            <li>File must be plain text ending in <code>.txt</code></li>
                            <li>One phone number per line</li>
                            <li>Contact <a href="mailto:care@smsexpert.co.uk"
                                    style="color: #ea6118;">care@smsexpert.co.uk</a> for instructions</li>
                            <li><a href="{{ asset('sample.txt') }}" download style="color: #ea6118;">Download sample
                                    file</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
    </div>
    </div>

    <!-- Sender ID Information Modal -->
    <div class="modal fade" id="SMSSenderIdModel" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="material-icons-outlined me-2">info</i>
                        Sender ID Information
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>The Sender ID is the sender name the recipient sees when they receive a standard rate SMS message.
                        You cannot set the sender ID for premium rate messages - these will automatically have a shortcode
                        ID.</p>

                    <h6 class="fw-bold mt-4 mb-3">Three Different Types of Sender ID:</h6>
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="p-3 bg-light rounded">
                                <h6 class="fw-bold text-primary">📱 Mobile Number</h6>
                                <p class="mb-0">A mobile number (11 to 15 characters) starting with the country code
                                    (e.g., 44 for the UK) or 0.</p>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="p-3 bg-light rounded">
                                <h6 class="fw-bold text-success">📝 Alphanumeric</h6>
                                <p class="mb-0">A string of up to 11 characters starting with a letter and consisting of
                                    letters, numbers, spaces, full stops, and hyphens.</p>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="p-3 bg-light rounded">
                                <h6 class="fw-bold text-warning">🔢 Shortcode</h6>
                                <p class="mb-0">A Shortcode number that can be up to 5 digits long, such as 83248.</p>
                            </div>
                        </div>
                    </div>
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

    <!-- Long Messages Information Modal -->
    <div class="modal fade" id="LongMessagesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="material-icons-outlined me-2">info</i>
                        Long (Concatenated) Messages
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info"
                        style="background: linear-gradient(135deg, #0891b2, #0e7490); color: white; border: none;">
                        <h6 class="fw-bold mb-3">Understanding SMS Character Limits</h6>
                        <p><strong>160 characters</strong> is the limit for a single text message to a mobile phone. You can
                            send messages containing more than 160 characters by chopping up the message into chunks, and
                            allowing the phone to reassemble it. SMS Expert will do this for you - simply type in a long
                            message and hit 'send'.</p>
                    </div>

                    <h6 class="fw-bold mt-4 mb-3">How It Works:</h6>
                    <div class="row g-3">
                        <div class="col-12">
                            <div class="p-3 bg-light rounded">
                                <h6 class="fw-bold text-primary">📱 Single SMS</h6>
                                <p class="mb-0">Messages up to <strong>160 characters</strong> are sent as a single SMS.
                                </p>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="p-3 bg-light rounded">
                                <h6 class="fw-bold text-success">📝 Multi-part SMS</h6>
                                <p class="mb-0">Messages over 160 characters are split into parts of <strong>153
                                        characters each</strong> (7 characters are reserved for headers that allow
                                    reassembly).</p>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-success mt-4">
                        <strong>✓ Device Compatibility:</strong><br>
                        Most phones these days can receive long messages. If you are unsure whether a phone can handle this
                        type of message (a 'concatenated' message), send one from this control panel now and watch what
                        happens.
                    </div>

                    <div class="alert alert-warning">
                        <strong>⚠️ Important Notes:</strong>
                        <ul class="mb-0">
                            <li>Long messages will not damage the phone</li>
                            <li>You can only send them at cost to your wallet, not premium rate</li>
                            <li>The maximum length of a long message is currently <strong>1206 characters</strong>
                                (approximately 8 SMS parts)</li>
                            <li><strong>You will be billed per individual message part</strong></li>
                        </ul>
                    </div>

                    <h6 class="fw-bold mt-4 mb-3">Character Count Examples:</h6>
                    <table class="table table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Character Range</th>
                                <th>SMS Parts</th>
                                <th>Cost Multiplier</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1 - 160</td>
                                <td>1</td>
                                <td>1x</td>
                            </tr>
                            <tr>
                                <td>161 - 306</td>
                                <td>2</td>
                                <td>2x</td>
                            </tr>
                            <tr>
                                <td>307 - 459</td>
                                <td>3</td>
                                <td>3x</td>
                            </tr>
                            <tr>
                                <td>460 - 612</td>
                                <td>4</td>
                                <td>4x</td>
                            </tr>
                            <tr>
                                <td>613 - 765</td>
                                <td>5</td>
                                <td>5x</td>
                            </tr>
                            <tr>
                                <td>766 - 918</td>
                                <td>6</td>
                                <td>6x</td>
                            </tr>
                            <tr>
                                <td>919 - 1071</td>
                                <td>7</td>
                                <td>7x</td>
                            </tr>
                            <tr>
                                <td>1072 - 1206</td>
                                <td>8</td>
                                <td>8x</td>
                            </tr>
                        </tbody>
                    </table>
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

    <!-- Multiple Numbers Modal -->
    <div class="modal fade" id="ToModel" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="material-icons-outlined me-2">groups</i>
                        Multiple Mobile Numbers
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <h6 class="fw-bold mb-3">Entering Multiple Numbers</h6>
                    <p>If you need to send to a large batch of mobile numbers, you may use the recipients box to supply the
                        mobile numbers. The numbers must be entered separated by commas or line breaks.</p>

                    <div class="alert alert-info">
                        <strong>Number Format Examples:</strong><br>
                        <code>07790111111,447790111112,497990111113</code><br>
                        Or on separate lines:<br>
                        <code>07790111111<br>447790111112<br>497990111113</code>
                    </div>

                    <h6 class="fw-bold mt-4 mb-3">Network IDs for Premium Rate Messages:</h6>
                    <div class="row g-2">
                        <div class="col-6"><span class="badge bg-primary">O2 - 10</span></div>
                        <div class="col-6"><span class="badge bg-success">Vodafone - 15</span></div>
                        <div class="col-6"><span class="badge bg-warning">3 - 20</span></div>
                        <div class="col-6"><span class="badge bg-danger">T-Mobile - 30</span></div>
                        <div class="col-6"><span class="badge bg-info">Orange - 33</span></div>
                    </div>

                    <div class="alert alert-warning mt-3">
                        <strong>Premium Rate Example:</strong><br>
                        <code>447790111111;30,447790111112;33</code>
                    </div>
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
        function formatTwoDecimal(value) {
            if (value === null || value === undefined) return '0.00';

            // remove currency symbol if present
            value = value.toString().replace(/[^0-9.-]/g, '');

            return Number(value).toFixed(2);
        }

        function formatFourDecimal(value) {
            if (value === null || value === undefined) return '0.0000';

            value = value.toString().replace(/[^0-9.-]/g, '');
            return Number(value).toFixed(4);
        }



        document.addEventListener('DOMContentLoaded', function() {
            // Auto-hide flash messages
            setTimeout(function() {
                const flashMessages = document.querySelectorAll('#flash-message, #flash-error-message');
                flashMessages.forEach(msg => {
                    if (msg) {
                        msg.style.opacity = '0';
                        msg.style.transform = 'translateY(-20px)';
                        setTimeout(() => msg.style.display = 'none', 300);
                    }
                });
            }, 4000);

            // Enhanced character counter with SMS parts calculation
            document.getElementById('messageContent').addEventListener('input', function() {
                const textLength = this.value.length;
                let smsParts = 1;
                let partInfo = '';

                if (textLength <= 160) {
                    smsParts = 1;
                    partInfo = ` / 1 SMS`;
                } else {
                    smsParts = Math.ceil(textLength / 153);
                    partInfo = ` / ${smsParts} SMS parts`;

                    // Add clickable link for multi-part messages
                    if (smsParts > 1) {
                        partInfo +=
                            ` <a href="#" onclick="event.preventDefault(); $('#LongMessagesModal').modal('show');" style="color: #ea6118; text-decoration: underline; font-size: 0.85rem;">(?)</a>`;
                    }
                }

                // Update counter with color coding
                const counterElement = document.getElementById('txtCounter');
                let colorClass = '#64748b'; // Default gray

                if (textLength > 1206) {
                    colorClass = '#dc2626'; // Red for exceeding max
                    partInfo +=
                        ' <span style="color: #dc2626; font-weight: bold;">⚠ Exceeds maximum (1206 chars)</span>';
                } else if (textLength > 918) {
                    colorClass = '#ea6118'; // Orange for high usage
                } else if (textLength > 459) {
                    colorClass = '#f59e0b'; // Yellow for medium usage
                }

                counterElement.innerHTML =
                    `<span style="color: ${colorClass}; font-weight: 600;">${textLength} characters${partInfo}</span>`;
            });

            // Trigger the input event initially to show counter
            document.getElementById('messageContent').dispatchEvent(new Event('input'));

            // Load contacts with pagination
            const selListType = document.getElementById('selListType');
            const selList = document.getElementById('selList');
            const userBigId = document.getElementById('bigid').value;
            const searchBox = document.getElementById('searchBox');
            const btnPrevPage = document.getElementById('btnPrevPage');
            const btnNextPage = document.getElementById('btnNextPage');
            const btnResetPagination = document.getElementById('btnResetPagination');
            const paginationInfo = document.getElementById('paginationInfo');

            // Pagination state
            let currentPage = 1;
            let totalPages = 1;
            let perPage = 50;
            let searchTimeout = null;

            function fetchAndPopulateContacts(listType, userBigId, page = 1, search = '') {
                selList.innerHTML = '<option>Loading...</option>';
                currentPage = page;

                fetch('/get-available-contacts', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            user_bigid: userBigId,
                            list_type: listType,
                            page: page,
                            per_page: perPage,
                            search: search
                        })
                    })
                    .then(response => response.json())
                    .then(response => {
                        selList.innerHTML = '';

                        if (response.error) {
                            selList.innerHTML = '<option>Error loading contacts</option>';
                            updatePaginationUI(1, 1, false, false);
                            return;
                        }

                        const data = response.data || response;
                        const pagination = response.pagination || { current_page: 1, total_pages: 1, has_prev: false, has_next: false, total: 0 };

                        if (!data || data.length === 0) {
                            selList.innerHTML = '<option>No contacts available</option>';
                            updatePaginationUI(1, 1, false, false, 0);
                            return;
                        }

                        data.forEach(contact => {
                            const option = document.createElement('option');
                            // Handle groups (array of msisdn) vs favourites (single msisdn)
                            if (Array.isArray(contact.msisdn)) {
                                option.value = contact.msisdn.join(',');
                            } else {
                                option.value = contact.msisdn;
                            }
                            option.textContent = contact.name || `Contact`;
                            selList.appendChild(option);
                        });

                        // Update pagination UI
                        totalPages = pagination.total_pages || 1;
                        updatePaginationUI(pagination.current_page, pagination.total_pages, pagination.has_prev, pagination.has_next, pagination.total);
                    })
                    .catch(error => {
                        console.error('Error fetching contacts:', error);
                        selList.innerHTML = '<option>Error loading contacts</option>';
                        updatePaginationUI(1, 1, false, false);
                    });
            }

            function updatePaginationUI(currentPage, totalPages, hasPrev, hasNext, total = 0) {
                btnPrevPage.disabled = !hasPrev;
                btnNextPage.disabled = !hasNext;
                paginationInfo.textContent = `Page ${currentPage} of ${totalPages} (${total} total)`;
            }

            // Initial load
            fetchAndPopulateContacts('favourites', userBigId, 1, '');

            // Handle list type change
            selListType.addEventListener('change', function() {
                currentPage = 1;
                searchBox.value = '';
                fetchAndPopulateContacts(this.value, userBigId, 1, '');
            });

            // Handle Previous button
            btnPrevPage.addEventListener('click', function() {
                if (currentPage > 1) {
                    fetchAndPopulateContacts(selListType.value, userBigId, currentPage - 1, searchBox.value);
                }
            });

            // Handle Next button
            btnNextPage.addEventListener('click', function() {
                if (currentPage < totalPages) {
                    fetchAndPopulateContacts(selListType.value, userBigId, currentPage + 1, searchBox.value);
                }
            });

            // Handle Reset button
            btnResetPagination.addEventListener('click', function() {
                currentPage = 1;
                searchBox.value = '';
                fetchAndPopulateContacts(selListType.value, userBigId, 1, '');
            });

            // Handle contact selection
            const btnAddThese = document.getElementById("btnAddThese");
            selList.addEventListener("change", function() {
                btnAddThese.disabled = selList.selectedOptions.length === 0;
            });

            // Search Box with debounce - searches server-side
            searchBox.addEventListener("keyup", function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    currentPage = 1;
                    fetchAndPopulateContacts(selListType.value, userBigId, 1, this.value);
                }, 300);
            });


            // Smooth animations
            const cards = document.querySelectorAll('.section-card, .sendsms-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';

                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // OLD SYSTEM GSM validation (cp2_sendsms.inc charPositionOrMinusOne + sms_constants.inc).
        // The valid set comes from the server constant so client & server always agree.
        const VALID_GSM_CHARACTER_SET = @json(\App\Helpers\GsmCharacterConverter::VALID_GSM_CHARACTER_SET);

        // Return the 1-based position of the first non-GSM character, or -1 if all valid.
        // Newline (10) and carriage return (13) are always allowed.
        function charPositionOrMinusOne(text) {
            for (let i = 0; i < text.length; i++) {
                if (VALID_GSM_CHARACTER_SET.indexOf(text.charAt(i)) === -1) {
                    const code = text.charCodeAt(i);
                    if (code !== 10 && code !== 13) {
                        return i + 1;
                    }
                }
            }
            return -1;
        }

        function submitForm(actionType) {
            const form = document.getElementById('smsForm');
            const sendTypeInput = document.getElementById('send_type');

            // GSM character validation — matches OLD control panel (reject before sending).
            const content = (document.getElementById('messageContent') || {}).value || '';
            const invalidChar = charPositionOrMinusOne(content);
            if (invalidChar !== -1) {
                alert("The text for the message contains an invalid character: " + content.charAt(invalidChar - 1) + " at position " + invalidChar + ".\n\nThis is usually a Microsoft Word character or an Apple apostrophe, and will cause the message to fail if sent to the network.\n\nTry deleting the character now, and re-typing it.");
                return;
            }

            if (actionType === 'send_now') {
                sendTypeInput.value = 'send_now';
            } else if (actionType === 'send_later') {
                sendTypeInput.value = 'send_later';
            }

            // Always submit to the same route
            form.action = "{{ route('send.sms.client') }}";
            form.submit();
        }

        function addThese() {
            const txtTo = document.getElementById("txtTo");
            const selList = document.getElementById("selList");
            const selectedOptions = Array.from(selList.selectedOptions).map(option => option.value);

            if (selectedOptions.length > 0) {
                txtTo.value += (txtTo.value ? ", " : "") + selectedOptions.join(", ");
            }
        }

        function calculateCost(event) {
            event.preventDefault();

            const numbers = document.getElementById("txtTo").value.trim();
            const messageContent = document.getElementById("messageContent").value.trim();
            const calculateDiv = document.getElementById('calculate-message');

            // Get sender ID
            let senderId = '';
            const rdoDefault = document.getElementById('rdoSenderDefault');
            const rdoSpecific = document.getElementById('rdoSenderSpecific');
            if (rdoDefault && rdoDefault.checked) {
                senderId = document.getElementById('txtSenderDefault')?.value || '';
            } else if (rdoSpecific && rdoSpecific.checked) {
                senderId = document.getElementById('txtSenderSpecific')?.value || '';
            }

            if (!numbers) {
                alert("You must enter phone numbers in the To: box");
                return;
            }

            if (!messageContent) {
                alert("Please enter a message to calculate cost");
                return;
            }

            // Loading state
            calculateDiv.style.display = 'block';
            calculateDiv.className = 'calculate-message';
            calculateDiv.style.background = 'linear-gradient(135deg, #334155, #1e293b)';
            calculateDiv.innerHTML =
                `<i class="material-icons-outlined">hourglass_empty</i> Calculating cost...`;

            fetch('{{ route('calculate.sms.cost') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        txtTo: numbers,
                        messageContent: messageContent,
                        senderId: senderId
                    })
                })
                .then(res => res.json())
                .then(data => {

                    if (!data.success) {
                        throw new Error(data.error || 'Failed to calculate cost');
                    }

                    let message = `
            <button onclick="document.getElementById('calculate-message').style.display='none'"
                style="float:right;background:none;border:none;color:white;font-size:24px;cursor:pointer">
                &times;
            </button>
            <div style="padding:10px;clear:both">
        `;

                    /* MESSAGE INFO */
                    message += `
            <h5 style="color:white;margin-bottom:12px">
                <i class="material-icons-outlined">message</i> Message Details
            </h5>
            <p><strong>Character Count:</strong> ${data.message_info.length}</p>
            <p><strong>SMS Parts:</strong> ${data.message_info.sms_parts}</p>
        `;

                    /* RECIPIENT INFO */
                    message += `
            <hr style="border-color:rgba(255,255,255,0.3)">
            <h5 style="color:white">
                <i class="material-icons-outlined">people</i> Recipients
            </h5>
            <p><strong>Valid Numbers:</strong> ${data.recipients.total}</p>
        `;

                    if (data.recipients.invalid > 0) {
                        message += `
                <p style="color:#fbbf24">
                    <strong>Invalid Numbers:</strong> ${data.recipients.invalid}
                </p>
                <p style="color:#fbbf24;font-size:13px">
                    ${data.recipients.invalid_numbers.join(', ')}
                </p>
            `;
                    }

                    /* PROVIDER INFO */
                    if (data.provider) {
                        message += `
                <hr style="border-color:rgba(255,255,255,0.3)">
                <h5 style="color:white">
                    <i class="material-icons-outlined">router</i> Route Provider
                </h5>
                <p><strong>Provider:</strong> ${data.provider}</p>
            `;
                        if (data.sender_id) {
                            message += `<p><strong>Sender ID:</strong> ${data.sender_id}</p>`;
                        }
                    }

                    /* COST BREAKDOWN - OLD SYSTEM FORMAT (pence) */
                    if (data.cost_breakdown && data.cost_breakdown.length > 0) {
                        message += `
                <hr style="border-color:rgba(255,255,255,0.3)">
                <h5 style="color:white">
                    <i class="material-icons-outlined">currency_pound</i> Cost Breakdown (${data.provider || 'Route'} based estimate)
                </h5>
            `;

                        data.cost_breakdown.forEach(country => {
                            // Convert pounds to pence for display (OLD SYSTEM format)
                            const rateInPence = (parseFloat(country.rate_per_sms) * 100).toFixed(2);
                            const totalInPence = (parseFloat(country.total_cost) * 100).toFixed(2);
                            message += `
                    <p>
                        <strong>${country.country} (+${country.dialcode})</strong> :
                        ${country.count} ×
                        ${rateInPence}p ×
                        ${data.message_info.sms_parts} SMS =
                        <strong>${totalInPence}p</strong>
                    </p>
                `;
                        });
                    }

                    /* TOTAL COST - OLD SYSTEM FORMAT (pence) */
                    const totalInPence = (parseFloat(data.total_cost.amount) * 100).toFixed(2);
                    message += `
            <hr style="border-color:rgba(255,255,255,0.3)">
            <h4 style="color:white">
                <strong>Total Cost : ${totalInPence}p</strong>
            </h4>
            <p><strong>Wallet Balance :</strong> £ ${formatTwoDecimal(data.wallet.balance)}</p>
        `;

                    if (!data.wallet.sufficient_funds) {
                        calculateDiv.style.background = 'linear-gradient(135deg, #dc2626, #b91c1c)';
                        message += `
                <p style="color:#fde68a">
                    <strong>⚠ Insufficient funds!</strong>
                </p>
            `;
                    } else {
                        calculateDiv.style.background = 'linear-gradient(135deg, #0891b2, #0e7490)';
                        message += `
                <p style="color:#86efac">
                    ✓ You have sufficient funds to send this message.
                </p>
            `;
                    }

                    message += `</div>`;
                    calculateDiv.innerHTML = message;

                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                })
                .catch(err => {
                    calculateDiv.style.background = 'linear-gradient(135deg, #dc2626, #b91c1c)';
                    calculateDiv.innerHTML =
                        `<i class="material-icons-outlined">error</i> ${err.message}`;
                });
        }


        // function calculateCost(event) {
        //     event.preventDefault();

        //     const numbers = document.getElementById("txtTo").value.trim();
        //     const messageContent = document.getElementById("messageContent").value.trim();

        //     if (numbers === "") {
        //         alert("You must enter phone numbers in the To: box");
        //         return;
        //     }

        //     if (messageContent === "") {
        //         alert("Please enter a message to calculate cost");
        //         return;
        //     }

        //     // Show loading state
        //     const calculateDiv = document.getElementById('calculate-message');
        //     calculateDiv.innerHTML = `<i class="material-icons-outlined">hourglass_empty</i> Calculating cost...`;
        //     calculateDiv.style.display = 'block';
        //     calculateDiv.className = 'calculate-message';

        //     // Make API call to calculate cost
        //     fetch('{{ route('calculate.sms.cost') }}', {
        //             method: 'POST',
        //             headers: {
        //                 'Content-Type': 'application/json',
        //                 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        //             },
        //             body: JSON.stringify({
        //                 txtTo: numbers,
        //                 messageContent: messageContent
        //             })
        //         })
        //         .then(response => response.json())
        //         .then(data => {
        //             if (data.success) {
        //                 // Build the cost breakdown message with close button
        //                 let message =
        //                     '<button onclick="document.getElementById(\'calculate-message\').style.display=\'none\'" style="float: right; background: transparent; border: none; color: white; font-size: 24px; cursor: pointer; padding: 0; margin: -5px -5px 0 0;">&times;</button>';
        //                 message += '<div style="padding: 10px; clear: both;">';

        //                 // Message info
        //                 message +=
        //                     `<h5 style="color: white; margin-bottom: 15px;"><i class="material-icons-outlined">message</i> Message Details</h5>`;
        //                 message += `<p><strong>Character Count:</strong> ${data.message_info.length} characters</p>`;

        //                 // Make multi-messages clickable if it's a multi-part message
        //                 if (data.message_info.sms_parts > 1) {
        //                     message += `<p><strong>SMS Parts:</strong> ${data.message_info.sms_parts} `;
        //                     message +=
        //                         `(<a href="#" onclick="event.preventDefault(); $('#LongMessagesModal').modal('show');" style="color: #fbbf24; text-decoration: underline; cursor: pointer;">multi-messages</a> are 153 characters each)</p>`;
        //                 } else {
        //                     message +=
        //                         `<p><strong>SMS Parts:</strong> ${data.message_info.sms_parts} (single message up to 160 characters)</p>`;
        //                 }

        //                 // Recipients info
        //                 message += `<hr style="border-color: rgba(255,255,255,0.3); margin: 15px 0;">`;
        //                 message +=
        //                     `<h5 style="color: white; margin-bottom: 15px;"><i class="material-icons-outlined">people</i> Recipients</h5>`;
        //                 message += `<p><strong>Valid Numbers:</strong> ${data.recipients.total}</p>`;

        //                 if (data.recipients.invalid > 0) {
        //                     message +=
        //                         `<p style="color: #fbbf24;"><strong>Invalid Numbers:</strong> ${data.recipients.invalid}</p>`;
        //                     if (data.recipients.invalid_numbers.length > 0) {
        //                         message +=
        //                             `<p style="color: #fbbf24; font-size: 0.9em;">Invalid: ${data.recipients.invalid_numbers.join(', ')}</p>`;
        //                     }
        //                 }

        //                 // Cost breakdown by country
        //                 if (data.cost_breakdown && data.cost_breakdown.length > 0) {
        //                     message += `<hr style="border-color: rgba(255,255,255,0.3); margin: 15px 0;">`;
        //                     message +=
        //                         `<h5 style="color: white; margin-bottom: 15px;"><i class="material-icons-outlined" style="font-style: normal;">£</i> Cost Breakdown</h5>`;

        //                     // data.cost_breakdown.forEach(country => {
        //                     //     // Ensure numeric values are parsed as numbers
        //                     //     const rate = parseFloat(country.rate_per_sms);
        //                     //     const totalCost = parseFloat(country.total_cost);

        //                     //     message += `<p><strong>${country.country} (+${country.dialcode}):</strong> `;
        //                     //     message += `${country.count} number${country.count > 1 ? 's' : ''} × `;
        //                     //     message += `£ ${rate.toFixed(4)} × ${data.message_info.sms_parts} SMS = `;
        //                     //     message += `<strong>£ ${totalCost.toFixed(4)}</strong></p>`;
        //                     // });

        //                     data.cost_breakdown.forEach(country => {
        //                         const rate = parseFloat(country.rate_per_sms);
        //                         const totalCost = parseFloat(country.total_cost);

        //                         message += `<p><strong>${country.country} (+${country.dialcode}):</strong> `;
        //                         message += `${country.count} number${country.count > 1 ? 's' : ''} × `;
        //                         message +=
        //                             `£ ${formatTwoDecimal(rate)} × ${data.message_info.sms_parts} SMS = `;
        //                         message += `<strong>£ ${formatTwoDecimal(totalCost)}</strong></p>`;
        //                     });

        //                 }

        //                 // Total cost
        //                 message += `<hr style="border-color: rgba(255,255,255,0.3); margin: 15px 0;">`;
        //                 message +=
        //                     `<h4 style="color: white;">
    //                         <strong>Total Cost : £ ${formatTwoDecimal(data.total_cost.formatted)}</strong>
    //                      </h4>`;

        //                 // message +=
        //                 //     `<h4 style="color: white;"><strong>Total Cost : ${data.total_cost.formatted}</strong></h4>`;

        //                 // Wallet balance check
        //                 message += `<p><strong>Wallet Balance :</strong> ${data.wallet.formatted_balance}</p>`;

        //                 if (!data.wallet.sufficient_funds) {
        //                     calculateDiv.style.background = 'linear-gradient(135deg, #dc2626, #b91c1c)';
        //                     message +=
        //                         `<p style="color: #fbbf24;"><strong>⚠️ Insufficient funds!</strong> You need ${data.wallet.formatted_shortage} more.</p>`;
        //                 } else {
        //                     calculateDiv.style.background = 'linear-gradient(135deg, #0891b2, #0e7490)';
        //                     message +=
        //                         `<p style="color: #86efac;">✓ You have sufficient funds to send this message.</p>`;
        //                 }

        //                 message += '</div>';

        //                 calculateDiv.innerHTML = message;
        //                 calculateDiv.style.display = 'block';

        //                 // Scroll to top
        //                 window.scrollTo({
        //                     top: 0,
        //                     behavior: 'smooth'
        //                 });

        //                 // Don't auto-hide - let user dismiss or it stays visible
        //             } else {
        //                 // Show error
        //                 calculateDiv.style.background = 'linear-gradient(135deg, #dc2626, #b91c1c)';
        //                 calculateDiv.innerHTML =
        //                     `<i class="material-icons-outlined">error</i> Error: ${data.error || 'Failed to calculate cost'}`;
        //                 calculateDiv.style.display = 'block';

        //                 setTimeout(() => {
        //                     calculateDiv.style.display = 'none';
        //                 }, 5000);
        //             }
        //         })
        //         .catch(error => {
        //             console.error('Error:', error);
        //             calculateDiv.style.background = 'linear-gradient(135deg, #dc2626, #b91c1c)';
        //             calculateDiv.innerHTML =
        //                 `<i class="material-icons-outlined">error</i> Error calculating cost. Please try again.`;
        //             calculateDiv.style.display = 'block';

        //             setTimeout(() => {
        //                 calculateDiv.style.display = 'none';
        //             }, 5000);
        //         });
        // }

        function payType() {
            // Payment type functionality if needed
        }

        console.log('Send SMS page loaded successfully!');
    </script>
@endpush
