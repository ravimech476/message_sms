@extends('layouts.app')
@section('title', 'Keyword Configuration - SMS Expert')

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

        .module-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 1.5rem;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .module-card:hover {
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .module-header {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .module-checkbox {
            width: 22px;
            height: 22px;
            cursor: pointer;
            accent-color: #16a34a;
        }

        .module-checkbox:disabled {
            cursor: not-allowed;
            opacity: 0.5;
        }

        .module-title-wrapper {
            flex: 1;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .module-title {
            color: #293b50;
            font-weight: 700;
            font-size: 1rem;
            margin: 0;
        }

        .module-status-text {
            font-size: 0.75rem;
            color: #ea6118;
            font-style: italic;
        }

        .module-content {
            padding: 1.5rem;
        }

        .module-description {
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .module-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        .configure-button {
            background: linear-gradient(135deg, #ea6118, #293b50);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .configure-button:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(234, 97, 24, 0.4);
            color: white;
        }

        .configure-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .module-card.module-enabled {
            border-left: 4px solid #16a34a;
        }

        .module-card.module-disabled {
            border-left: 4px solid #94a3b8;
            opacity: 0.7;
        }

        .module-card.module-disabled .configure-button {
            opacity: 0.5;
            pointer-events: none;
        }

        .module-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 22px;
        }

        .icon-auto-responder { background: linear-gradient(135deg, #16a34a, #15803d); }
        .icon-forwarder { background: linear-gradient(135deg, #0891b2, #0e7490); }
        .icon-sms-forwarder { background: linear-gradient(135deg, #ea6118, #d1520e); }
        .icon-business-card { background: linear-gradient(135deg, #7c3aed, #6d28d9); }
        .icon-subscription { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .icon-wap-push { background: linear-gradient(135deg, #06b6d4, #0891b2); }
        .icon-voting { background: linear-gradient(135deg, #dc2626, #b91c1c); }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-enabled {
            background: #dcfce7;
            color: #16a34a;
        }

        .status-disabled {
            background: #f1f5f9;
            color: #64748b;
        }

        .icon-primary {
            color: #ea6118;
            font-size: 1.2rem;
        }

        .overview-card {
            background: linear-gradient(135deg, rgba(234, 97, 24, 0.1), rgba(41, 59, 80, 0.1));
            border: 2px solid #ea6118;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .overview-card h5 {
            color: #293b50;
            font-weight: 700;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .overview-card p {
            color: #64748b;
            margin-bottom: 0;
            line-height: 1.6;
        }

        .modules-grid {
            display: grid;
            gap: 1rem;
        }

        /* Subkeyword Management Styles */
        .subkeyword-section {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .subkeyword-header {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            padding: 1.5rem;
            color: white;
        }

        .subkeyword-header h5 {
            margin: 0;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .subkeyword-content {
            padding: 1.5rem;
        }

        .subkeyword-table {
            width: 100%;
            border-collapse: collapse;
        }

        .subkeyword-table th {
            background: #f8fafc;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #293b50;
            border-bottom: 2px solid #e2e8f0;
        }

        .subkeyword-table td {
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }

        .subkeyword-table tr:hover {
            background: #f8fafc;
        }

        .btn-delete-subkey {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
            border: none;
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-delete-subkey:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
        }

        .btn-configure-subkey {
            background: linear-gradient(135deg, #ea6118, #d1520e);
            border: none;
            color: white;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .btn-configure-subkey:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(234, 97, 24, 0.3);
            color: white;
        }

        .add-subkeyword-form {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            padding: 1.5rem;
            border-radius: 12px;
            margin-top: 1.5rem;
        }

        .add-subkeyword-form h6 {
            color: #293b50;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .add-subkeyword-form .form-control {
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            padding: 0.75rem 1rem;
        }

        .add-subkeyword-form .form-control:focus {
            border-color: #ea6118;
            box-shadow: 0 0 0 3px rgba(234, 97, 24, 0.1);
        }

        .btn-add-subkey {
            background: linear-gradient(135deg, #16a34a, #15803d);
            border: none;
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-add-subkey:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(22, 163, 74, 0.3);
        }

        .no-subkeywords {
            text-align: center;
            padding: 2rem;
            color: #64748b;
        }

        .no-subkeywords i {
            font-size: 3rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }

        .no-modules-message {
            text-align: center;
            padding: 3rem;
            color: #64748b;
        }

        .no-modules-message i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }

        .no-modules-message h5 {
            color: #293b50;
            margin-bottom: 0.5rem;
        }

        .keyword-info-card {
            background: linear-gradient(135deg, #293b50, #1e293b);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            color: white;
        }

        .keyword-info-card h4 {
            margin: 0 0 0.5rem 0;
            font-weight: 700;
            color: white !important;
        }

        .keyword-info-card .keyword-details {
            display: flex;
            gap: 2rem;
            flex-wrap: wrap;
        }

        .keyword-info-card .detail-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .keyword-info-card .detail-label {
            color: rgba(255,255,255,0.7);
            font-size: 0.85rem;
        }

        .keyword-info-card .detail-value {
            font-weight: 600;
            color: white;
        }

        .configuring-badge {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-left: 1rem;
            font-size: 0.9rem;
        }

        .config-selector {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .config-selector h6 {
            color: #293b50;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .config-selector select {
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            font-size: 1rem;
        }

        .config-selector select:focus {
            border-color: #ea6118;
            outline: none;
            box-shadow: 0 0 0 3px rgba(234, 97, 24, 0.1);
        }
    </style>
@endpush

@php
    $itaggId = $itaggId ?? request()->route('itaggId');
    $keyword = $keyword ?? request()->route('keyword') ?? request()->route('subkeyword');
    $enabledModules = $enabledModules ?? [];
    $showSubkeywordManagement = $showSubkeywordManagement ?? false;
    $maxSubkeywords = $maxSubkeywords ?? 0;
    $isStarKeyword = $isStarKeyword ?? false;
    $isSubkeyword = $isSubkeyword ?? false;
    $subkeyword = $subkeyword ?? '';
    
    $configTarget = $isSubkeyword ? "subkeyword \"$subkeyword\"" : "main keyword \"{$itaggData->keyword}\"";
@endphp

@section('content')
    <div class="config-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb-container d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">
                    <i class="material-icons-outlined icon-primary">settings</i>
                    Keyword Configuration
                </div>
                &nbsp;
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="{{ route('keywords') }}">Keywords</a></li>
                        <li class="breadcrumb-item active">Configuration</li>
                    </ol>
                </nav>
            </div>
            <button id="backButton" class="btn btn-outline-secondary back-btn">
                <i class="material-icons-outlined me-1">arrow_back</i> Back
            </button>
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

        <!-- Keyword Info Card -->
        @if(isset($itaggData))
        <div class="keyword-info-card">
            <h4>
                Configuring "{{ $itaggData->keyword }}" on {{ $itaggData->shortcode }}
                @if($isSubkeyword)
                    <span class="configuring-badge">
                        <i class="material-icons-outlined" style="font-size: 18px;">label</i>
                        Subkeyword: {{ $subkeyword }}
                    </span>
                @endif
            </h4>
            <div class="keyword-details">
                <div class="detail-item">
                    <span class="detail-label">Shortcode:</span>
                    <span class="detail-value">{{ $itaggData->shortcode ?? 'N/A' }}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Type:</span>
                    <span class="detail-value">{{ $itaggData->description ?? 'N/A' }}</span>
                </div>
                <div class="detail-item">
                    <span class="detail-label">Expiry:</span>
                    <span class="detail-value">{{ $itaggData->expiry ?? 'N/A' }}</span>
                </div>
            </div>
        </div>
        @endif

        <!-- Subkeyword Management Section -->
        @if($showSubkeywordManagement)
        <div class="subkeyword-section">
            <div class="subkeyword-header">
                <h5>
                    <i class="material-icons-outlined">label</i>
                    Subkeyword Management
                </h5>
            </div>
            <div class="subkeyword-content">
                <!-- Configuration Selector -->
                <div class="config-selector">
                    <h6><i class="material-icons-outlined me-2" style="font-size: 18px; vertical-align: middle;">tune</i>Configure:</h6>
                    <select id="config-select" onchange="configSelect(this)">
                        <option value="" {{ !$isSubkeyword ? 'selected' : '' }}>the main keyword "{{ $itaggData->keyword }}"</option>
                    </select>
                </div>

                <!-- Existing Subkeywords Table -->
                <div id="subkeywords-list">
                    <table class="subkeyword-table" id="subkeyword-table">
                        <thead>
                            <tr>
                                <th>Subkeyword</th>
                                <th>Response Route</th>
                                <th>Forwarding Email</th>
                                <th>Sender ID</th>
                                <th style="width: 120px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="subkeyword-tbody">
                            <tr>
                                <td colspan="5" class="no-subkeywords">
                                    <i class="material-icons-outlined d-block">hourglass_empty</i>
                                    Loading subkeywords...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Add New Subkeyword Form -->
                <div class="add-subkeyword-form">
                    <h6>
                        <i class="material-icons-outlined">add_circle</i>
                        Add a new subkeyword
                    </h6>
                    <form id="add-subkeyword-form" class="row g-3">
                        @csrf
                        <input type="hidden" name="itagg_id" value="{{ $itaggId }}">
                        <div class="col-md-8">
                            <input type="text" class="form-control" id="new_subkeyword" name="subkeyword" 
                                   placeholder="Enter subkeyword name (e.g., INFO, HELP, JOIN)" required
                                   pattern="^[A-Za-z0-9\-]+$"
                                   title="Subkeyword must contain only letters, numbers, and hyphens (cannot start with -)">
                            <small class="text-muted">Subkeyword must be 1-16 characters: A-Z, a-z, 0-9, and - (cannot start with -)</small>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-add-subkey w-100">
                                <i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">add</i>
                                Add Subkeyword
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endif

        <!-- Available Modules Section -->
        <div class="overview-card">
            <h5>
                <i class="material-icons-outlined">info</i>
                @if($isStarKeyword)
                    Dedicated Number Configuration
                @else
                    Configuration options for {{ $configTarget }}
                @endif
            </h5>
            <p>
                @if($isStarKeyword)
                    Configure the email/URL forwarding for your dedicated number.
                @else
                    Use the checkboxes to enable/disable modules, then click Configure to set up each module.
                @endif
            </p>
        </div>

        <!-- Main Content - Modules -->
        <div class="config-card">
            <div class="section-header">
                <h5 class="section-title">
                    <i class="material-icons-outlined">tune</i>
                    Modules to handle inbound SMS
                    @if($isSubkeyword)
                        <span class="badge bg-purple ms-2" style="background: linear-gradient(135deg, #8b5cf6, #7c3aed);">for subkeyword "{{ $subkeyword }}"</span>
                    @endif
                </h5>
            </div>

            <div class="section-content">
                <div class="modules-grid">
                    @php $hasAnyModule = false; @endphp

                    <!-- SMS Auto-Responder Module -->
                    @if(!empty($enabledModules['smsResponder']))
                        @php $hasAnyModule = true; @endphp
                        <div class="module-card" id="module-card-smsResponder" data-module="smsResponder">
                            <div class="module-header">
                                <input type="checkbox" class="module-checkbox" id="chk-smsResponder" 
                                       data-module="smsResponder" onchange="toggleModule(this)">
                                <div class="module-icon icon-auto-responder">
                                    <i class="material-icons-outlined">auto_awesome</i>
                                </div>
                                <div class="module-title-wrapper">
                                    <span class="module-title">SMS Auto-Responder</span>
                                    <span class="module-status-text" id="status-smsResponder"></span>
                                </div>
                                <span class="status-badge status-disabled" id="badge-smsResponder">OFF</span>
                            </div>
                            <div class="module-content">
                                <p class="module-description">
                                    Allows you to send an automatic SMS response to your users.
                                </p>
                                <div class="module-actions">
                                    <a href="{{ route('keywords.sms-responder', [$itaggId, $keyword]) }}" 
                                       class="configure-button" id="btn-smsResponder" disabled>
                                        <i class="material-icons-outlined">settings</i>
                                        Configure
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- SMS to Email Forwarder Module -->
                    @if(!empty($enabledModules['Forwarder']) || !empty($enabledModules['EmailForwarder']))
                        @php $hasAnyModule = true; @endphp
                        <div class="module-card" id="module-card-Forwarder" data-module="Forwarder">
                            <div class="module-header">
                                <input type="checkbox" class="module-checkbox" id="chk-Forwarder" 
                                       data-module="Forwarder" onchange="toggleModule(this)">
                                <div class="module-icon icon-forwarder">
                                    <i class="material-icons-outlined">forward_to_inbox</i>
                                </div>
                                <div class="module-title-wrapper">
                                    <span class="module-title">SMS to Email Forwarder (can also forward to API/URL)</span>
                                    <span class="module-status-text" id="status-Forwarder"></span>
                                </div>
                                <span class="status-badge status-disabled" id="badge-Forwarder">OFF</span>
                            </div>
                            <div class="module-content">
                                <p class="module-description">
                                    Allows you to specify an email address that you can have the incoming SMS Keyword requests
                                    forwarded to. Developers can also forward them to an API.
                                </p>
                                <div class="module-actions">
                                    <a href="{{ route('keywords.email-forwarder', [$itaggId, $keyword]) }}" 
                                       class="configure-button" id="btn-Forwarder" disabled>
                                        <i class="material-icons-outlined">settings</i>
                                        Configure
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- SMS Forwarder Module — HIDDEN per request (remove `false &&` below to show again) -->
                    @if(false && !empty($enabledModules['SMSForwarder']))
                        @php $hasAnyModule = true; @endphp
                        <div class="module-card" id="module-card-SMSForwarder" data-module="SMSForwarder">
                            <div class="module-header">
                                <input type="checkbox" class="module-checkbox" id="chk-SMSForwarder" 
                                       data-module="SMSForwarder" onchange="toggleModule(this)">
                                <div class="module-icon icon-sms-forwarder">
                                    <i class="material-icons-outlined">sms</i>
                                </div>
                                <div class="module-title-wrapper">
                                    <span class="module-title">SMS Forwarder</span>
                                    <span class="module-status-text" id="status-SMSForwarder"></span>
                                </div>
                                <span class="status-badge status-disabled" id="badge-SMSForwarder">OFF</span>
                            </div>
                            <div class="module-content">
                                <p class="module-description">
                                    Allows you to specify a mobile number that you can have the incoming iTAGG requests
                                    forwarded to.
                                </p>
                                <div class="module-actions">
                                    <a href="{{ route('keywords.sms-forwarder', [$itaggId, $keyword]) }}" 
                                       class="configure-button" id="btn-SMSForwarder" disabled>
                                        <i class="material-icons-outlined">settings</i>
                                        Configure
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Business Card Module — HIDDEN per request (remove `false &&` below to show again) -->
                    @if(false && !empty($enabledModules['BusinessCard']))
                        @php $hasAnyModule = true; @endphp
                        <div class="module-card" id="module-card-BusinessCard" data-module="BusinessCard">
                            <div class="module-header">
                                <input type="checkbox" class="module-checkbox" id="chk-BusinessCard" 
                                       data-module="BusinessCard" onchange="toggleModule(this)">
                                <div class="module-icon icon-business-card">
                                    <i class="material-icons-outlined">badge</i>
                                </div>
                                <div class="module-title-wrapper">
                                    <span class="module-title">Business Card</span>
                                    <span class="module-status-text" id="status-BusinessCard"></span>
                                </div>
                                <span class="status-badge status-disabled" id="badge-BusinessCard">OFF</span>
                            </div>
                            <div class="module-content">
                                <p class="module-description">
                                    Allows you to send a Business Card (vCard) to your users.
                                </p>
                                <div class="module-actions">
                                    <a href="{{ route('keywords.business-card', [$itaggId, $keyword]) }}" 
                                       class="configure-button" id="btn-BusinessCard" disabled>
                                        <i class="material-icons-outlined">settings</i>
                                        Configure
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Subscription Module — HIDDEN per request (remove `false &&` below to show again) -->
                    @if(false && !empty($enabledModules['Subscription']))
                        @php $hasAnyModule = true; @endphp
                        <div class="module-card" id="module-card-Subscription" data-module="Subscription">
                            <div class="module-header">
                                <input type="checkbox" class="module-checkbox" id="chk-Subscription" 
                                       data-module="Subscription" onchange="toggleModule(this)">
                                <div class="module-icon icon-subscription">
                                    <i class="material-icons-outlined">subscriptions</i>
                                </div>
                                <div class="module-title-wrapper">
                                    <span class="module-title">Subscription</span>
                                    <span class="module-status-text" id="status-Subscription"></span>
                                </div>
                                <span class="status-badge status-disabled" id="badge-Subscription">OFF</span>
                            </div>
                            <div class="module-content">
                                <p class="module-description">
                                    Allows you to run a Subscription service. Users can subscribe/unsubscribe using START and STOP.
                                </p>
                                <div class="module-actions">
                                    <a href="{{ route('keywords.subscription', [$itaggId, $keyword]) }}" 
                                       class="configure-button" id="btn-Subscription" disabled>
                                        <i class="material-icons-outlined">settings</i>
                                        Configure
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- WAP Push Responder Module — HIDDEN per request (remove `false &&` below to show again) -->
                    @if(false && !empty($enabledModules['WAPPushResponder']))
                        @php $hasAnyModule = true; @endphp
                        <div class="module-card" id="module-card-WAPPushResponder" data-module="WAPPushResponder">
                            <div class="module-header">
                                <input type="checkbox" class="module-checkbox" id="chk-WAPPushResponder" 
                                       data-module="WAPPushResponder" onchange="toggleModule(this)">
                                <div class="module-icon icon-wap-push">
                                    <i class="material-icons-outlined">wifi</i>
                                </div>
                                <div class="module-title-wrapper">
                                    <span class="module-title">WAP Push Responder</span>
                                    <span class="module-status-text" id="status-WAPPushResponder"></span>
                                </div>
                                <span class="status-badge status-disabled" id="badge-WAPPushResponder">OFF</span>
                            </div>
                            <div class="module-content">
                                <p class="module-description">
                                    Allows you to return a 'WAP Push' message which contains a URL (hyperlink) to the user.
                                </p>
                                <div class="module-actions">
                                    <a href="{{ route('keywords.WAPpushresponder', [$itaggId, $keyword]) }}" 
                                       class="configure-button" id="btn-WAPPushResponder" disabled>
                                        <i class="material-icons-outlined">settings</i>
                                        Configure
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Voting Module — HIDDEN per request (remove `false &&` below to show again) -->
                    @if(false && !empty($enabledModules['Voting']))
                        @php $hasAnyModule = true; @endphp
                        <div class="module-card" id="module-card-Voting" data-module="Voting">
                            <div class="module-header">
                                <input type="checkbox" class="module-checkbox" id="chk-Voting" 
                                       data-module="Voting" onchange="toggleModule(this)">
                                <div class="module-icon icon-voting">
                                    <i class="material-icons-outlined">how_to_vote</i>
                                </div>
                                <div class="module-title-wrapper">
                                    <span class="module-title">Voting</span>
                                    <span class="module-status-text" id="status-Voting"></span>
                                </div>
                                <span class="status-badge status-disabled" id="badge-Voting">OFF</span>
                            </div>
                            <div class="module-content">
                                <p class="module-description">
                                    Allows you to offer a live SMS Voting system for your users.
                                </p>
                                <div class="module-actions">
                                    <a href="{{ route('keywords.voting', [$itaggId, $keyword]) }}" 
                                       class="configure-button" id="btn-Voting" disabled>
                                        <i class="material-icons-outlined">settings</i>
                                        Configure
                                    </a>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if(!$hasAnyModule)
                        <div class="no-modules-message">
                            <i class="material-icons-outlined d-block">block</i>
                            <h5>No Modules Available</h5>
                            <p>There are no modules configured for this keyword. Please contact support to enable modules.</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

@push('js')
    <script>
        const itaggId = '{{ $itaggId }}';
        const mainKeyword = '{{ $itaggData->keyword ?? "" }}';
        const currentSubkeyword = '{{ $subkeyword }}';
        const maxSubkeywords = {{ $maxSubkeywords }};
        const showSubkeywordManagement = {{ $showSubkeywordManagement ? 'true' : 'false' }};
        // csrfToken is already defined in the layout
        
        // Determine which keyword to use for module status lookup
        const configKeyword = currentSubkeyword || mainKeyword;
        
        let currentSubkeywordCount = 0;

        document.addEventListener('DOMContentLoaded', function() {
            // Load subkeywords on page load
            if (showSubkeywordManagement) {
                loadSubkeywords();
            }

            // Load module status on page load
            loadModuleStatus();

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

            // Add subkeyword form submission
            const addForm = document.getElementById('add-subkeyword-form');
            if (addForm) {
                addForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    addSubkeyword();
                });
            }

            // Back button
            document.getElementById('backButton').addEventListener('click', function() {
                window.history.back();
            });
        });

        // Load module configuration status
        function loadModuleStatus() {
            fetch(`/keyword/module/status?itagg_id=${itaggId}&keyword=${encodeURIComponent(configKeyword)}&subkeyword=${encodeURIComponent(currentSubkeyword)}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.moduleStatus) {
                        Object.keys(data.moduleStatus).forEach(moduleName => {
                            const isEnabled = data.moduleStatus[moduleName];
                            updateModuleUI(moduleName, isEnabled);
                        });
                    }
                })
                .catch(error => {
                    console.error('Error loading module status:', error);
                });
        }

        // Update module UI based on status
        function updateModuleUI(moduleName, isEnabled) {
            const checkbox = document.getElementById(`chk-${moduleName}`);
            const badge = document.getElementById(`badge-${moduleName}`);
            const btn = document.getElementById(`btn-${moduleName}`);
            const card = document.getElementById(`module-card-${moduleName}`);
            const statusText = document.getElementById(`status-${moduleName}`);

            if (checkbox) checkbox.checked = isEnabled;
            
            if (badge) {
                badge.textContent = isEnabled ? 'ON' : 'OFF';
                badge.className = isEnabled ? 'status-badge status-enabled' : 'status-badge status-disabled';
            }
            
            if (btn) {
                if (isEnabled) {
                    btn.removeAttribute('disabled');
                    btn.style.pointerEvents = 'auto';
                    btn.style.opacity = '1';
                } else {
                    btn.setAttribute('disabled', 'disabled');
                    btn.style.pointerEvents = 'none';
                    btn.style.opacity = '0.5';
                }
            }
            
            if (card) {
                card.classList.remove('module-enabled', 'module-disabled');
                card.classList.add(isEnabled ? 'module-enabled' : 'module-disabled');
            }

            if (statusText) {
                statusText.textContent = '';
            }
        }

        // Toggle module on/off
        function toggleModule(checkbox) {
            const moduleName = checkbox.dataset.module;
            const isChecked = checkbox.checked;
            const action = isChecked ? 'switchon' : 'switchoff';
            const statusText = document.getElementById(`status-${moduleName}`);

            // Show confirmation for certain modules when switching off
            if (!isChecked) {
                let confirmMsg = '';
                if (moduleName === 'Voting') {
                    confirmMsg = "Switching off the Voting module will delete all of its campaign statistics. Are you sure you want to continue?";
                } else if (moduleName === 'Subscription') {
                    confirmMsg = "Switching off the Subscription module will delete its associated Subscription Group from your Phone Groups. Are you sure you want to continue?";
                }
                
                if (confirmMsg && !confirm(confirmMsg)) {
                    checkbox.checked = true;
                    return;
                }
            }

            // Show configuring status
            if (statusText) {
                statusText.textContent = `(Configuring ${isChecked ? 'on' : 'off'} - please wait...)`;
            }

            // Disable checkbox while processing
            checkbox.disabled = true;

            const formData = new FormData();
            formData.append('itagg_id', itaggId);
            formData.append('module', moduleName);
            formData.append('action', action);
            formData.append('keyword', configKeyword);
            formData.append('subkeyword', currentSubkeyword);
            formData.append('_token', csrfToken);

            fetch('/keyword/module/toggle', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                checkbox.disabled = false;
                
                if (data.success) {
                    updateModuleUI(moduleName, data.status === 'on');
                    showAlert('success', data.message);
                } else {
                    // Revert checkbox
                    checkbox.checked = !isChecked;
                    showAlert('danger', data.message || 'Failed to toggle module');
                }
            })
            .catch(error => {
                checkbox.disabled = false;
                checkbox.checked = !isChecked;
                console.error('Error toggling module:', error);
                showAlert('danger', 'An error occurred while toggling the module');
                if (statusText) statusText.textContent = '';
            });
        }

        // Configuration selector change
        function configSelect(select) {
            const value = select.value;
            if (value === '') {
                window.location.href = `/keyword-config/${itaggId}/${encodeURIComponent(mainKeyword)}`;
            } else {
                window.location.href = `/keyword-config/${itaggId}/${encodeURIComponent(value)}`;
            }
        }

        // Load all subkeywords
        function loadSubkeywords() {
            fetch(`/keyword/subkeywords/${itaggId}`)
                .then(response => response.json())
                .then(data => {
                    const tbody = document.getElementById('subkeyword-tbody');
                    const configSelect = document.getElementById('config-select');
                    
                    if (!tbody) return;
                    
                    if (data.success && data.subkeywords && data.subkeywords.length > 0) {
                        currentSubkeywordCount = data.subkeywords.length;
                        
                        let html = '';
                        data.subkeywords.forEach(subkey => {
                            html += `
                                <tr data-subkey="${subkey.keyword}">
                                    <td><strong style="color: #293b50;">${subkey.keyword}</strong></td>
                                    <td>${subkey.response_smsshortcodes_id || '-'}</td>
                                    <td>${subkey.forwarding_email || '-'}</td>
                                    <td>${subkey.response_sender_id || '-'}</td>
                                    <td>
                                        <div class="d-flex gap-2">
                                            <a href="/keyword-config/${itaggId}/${encodeURIComponent(subkey.keyword)}" 
                                               class="btn-configure-subkey" title="Configure">
                                                <i class="material-icons-outlined" style="font-size: 18px;">settings</i>
                                            </a>
                                            <button type="button" class="btn-delete-subkey" 
                                                    onclick="deleteSubkeyword('${subkey.keyword}')" title="Delete">
                                                <i class="material-icons-outlined" style="font-size: 18px;">delete</i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            `;
                        });
                        tbody.innerHTML = html;

                        if (configSelect) {
                            const firstOption = configSelect.options[0];
                            configSelect.innerHTML = '';
                            configSelect.appendChild(firstOption);
                            
                            data.subkeywords.forEach(subkey => {
                                const option = document.createElement('option');
                                option.value = subkey.keyword;
                                option.textContent = `the subkeyword "${subkey.keyword}"`;
                                if (currentSubkeyword === subkey.keyword) {
                                    option.selected = true;
                                }
                                configSelect.appendChild(option);
                            });
                        }
                    } else {
                        currentSubkeywordCount = 0;
                        tbody.innerHTML = `
                            <tr>
                                <td colspan="5" class="no-subkeywords">
                                    <i class="material-icons-outlined d-block">label_off</i>
                                    <p>No subkeywords found. Add your first subkeyword above.</p>
                                </td>
                            </tr>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error loading subkeywords:', error);
                });
        }

        function subkeyFormatCheck(sk) {
            if (sk.length < 1 || sk.length > 16) return false;
            if (sk.startsWith('-')) return false;
            if (!/^[A-Za-z0-9\-]+$/.test(sk)) return false;
            if (sk.toUpperCase() === 'START' || sk.toUpperCase() === 'STOP') return false;
            return true;
        }

        function addSubkeyword() {
            const subkeywordInput = document.getElementById('new_subkeyword');
            const subkeyword = subkeywordInput.value.trim().toUpperCase();

            if (!subkeyword) {
                showAlert('danger', 'Please enter a subkeyword name');
                return;
            }

            if (!subkeyFormatCheck(subkeyword)) {
                showAlert('danger', 'Invalid subkeyword format. Must be 1-16 characters: A-Z, 0-9, - (cannot start with -)');
                return;
            }

            if (maxSubkeywords > 0 && currentSubkeywordCount >= maxSubkeywords) {
                showAlert('danger', `Maximum number of subkeywords (${maxSubkeywords}) reached.`);
                return;
            }

            const formData = new FormData();
            formData.append('itagg_id', itaggId);
            formData.append('subkeyword', subkeyword);
            formData.append('_token', csrfToken);

            fetch('/keyword/subkeyword/add', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    subkeywordInput.value = '';
                    loadSubkeywords();
                    showAlert('success', data.message || 'Subkeyword added successfully!');
                } else {
                    showAlert('danger', data.message || 'Failed to add subkeyword');
                }
            })
            .catch(error => {
                console.error('Error adding subkeyword:', error);
                showAlert('danger', 'An error occurred while adding the subkeyword');
            });
        }

        function deleteSubkeyword(subkeyword) {
            if (!confirm(`Are you sure you want to delete the subkeyword "${subkeyword}"?`)) {
                return;
            }

            const formData = new FormData();
            formData.append('itagg_id', itaggId);
            formData.append('subkeyword', subkeyword);
            formData.append('_token', csrfToken);

            fetch('/keyword/subkeyword/delete', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    loadSubkeywords();
                    showAlert('success', data.message || 'Subkeyword deleted successfully!');
                    
                    if (currentSubkeyword === subkeyword) {
                        window.location.href = `/keyword-config/${itaggId}/${encodeURIComponent(mainKeyword)}`;
                    }
                } else {
                    showAlert('danger', data.message || 'Failed to delete subkeyword');
                }
            })
            .catch(error => {
                console.error('Error deleting subkeyword:', error);
                showAlert('danger', 'An error occurred while deleting the subkeyword');
            });
        }

        function showAlert(type, message) {
            const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
            const iconName = type === 'success' ? 'check_circle' : 'error';
            
            const alertHtml = `
                <div class="alert ${alertClass}" id="dynamic-alert" style="opacity: 0; transform: translateY(-20px); transition: all 0.3s ease;">
                    <i class="material-icons-outlined">${iconName}</i>
                    ${message}
                </div>
            `;
            
            const existingAlert = document.getElementById('dynamic-alert');
            if (existingAlert) existingAlert.remove();
            
            const breadcrumb = document.querySelector('.breadcrumb-container');
            breadcrumb.insertAdjacentHTML('afterend', alertHtml);
            
            setTimeout(() => {
                const newAlert = document.getElementById('dynamic-alert');
                if (newAlert) {
                    newAlert.style.opacity = '1';
                    newAlert.style.transform = 'translateY(0)';
                }
            }, 10);
            
            setTimeout(() => {
                const alert = document.getElementById('dynamic-alert');
                if (alert) {
                    alert.style.opacity = '0';
                    alert.style.transform = 'translateY(-20px)';
                    setTimeout(() => alert.remove(), 300);
                }
            }, 4000);
        }
    </script>
@endpush
