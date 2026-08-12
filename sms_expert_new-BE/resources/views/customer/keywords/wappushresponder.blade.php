@extends('layouts.app')
@section('title', 'WAP Push Responder - SMS Expert')

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

        .config-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .section-card {
            background: white;
            border-radius: 15px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
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
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }

        .module-header {
            background: linear-gradient(135deg, #f8fafc, #e2e8f0);
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .module-toggle {
            position: relative;
            width: 60px;
            height: 30px;
        }

        .module-toggle input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .module-toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #cbd5e1;
            transition: 0.4s;
            border-radius: 30px;
        }

        .module-toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: 0.4s;
            border-radius: 50%;
        }

        .module-toggle input:checked+.module-toggle-slider {
            background: linear-gradient(135deg, #ea6118, #d1520e);
        }

        .module-toggle input:checked+.module-toggle-slider:before {
            transform: translateX(30px);
        }

        .module-title {
            color: #293b50;
            font-weight: 700;
            font-size: 1.1rem;
            margin: 0;
            flex: 1;
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

        .configure-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(234, 97, 24, 0.4);
            color: white;
        }

        .configure-button:disabled,
        .configure-button.disabled {
            background: #cbd5e1;
            color: #94a3b8;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .module-enabled {
            border-left: 4px solid #16a34a;
        }

        .module-disabled {
            border-left: 4px solid #cbd5e1;
        }

        .module-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            margin-right: 1rem;
        }

        .icon-auto-responder {
            background: linear-gradient(135deg, #16a34a, #15803d);
        }

        .icon-forwarder {
            background: linear-gradient(135deg, #0891b2, #0e7490);
        }

        .icon-sms-forwarder {
            background: linear-gradient(135deg, #ea6118, #d1520e);
        }

        .icon-business-card {
            background: linear-gradient(135deg, #7c3aed, #6d28d9);
        }

        .icon-subscription {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .icon-wap-push {
            background: linear-gradient(135deg, #06b6d4, #0891b2);
        }

        .icon-voting {
            background: linear-gradient(135deg, #dc2626, #b91c1c);
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-enabled {
            background: linear-gradient(135deg, #16a34a, #15803d);
            color: white;
        }

        .status-disabled {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
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
            gap: 1.5rem;
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
    </style>
@endpush

@section('content')

    <div class="config-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb-container d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center">
                <div class="breadcrumb-title pe-3">
                    <i class="material-icons-outlined icon-primary">reply</i>
                    WAP Push Responder - {{ $itaggData->keyword ?? 'N/A' }}
                </div>
                &nbsp;
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        {{-- <li class="breadcrumb-item">
                        <i class="material-icons-outlined">home</i>
                    </li> --}}
                        <li class="breadcrumb-item">
                            <a href="{{ route('dashboard') }}">Dashboard</a>
                        </li>
                        <li class="breadcrumb-item">
                            <a href="{{ route('keywords') }}">Keywords</a>
                        </li>
                        <li class="breadcrumb-item active">Configuration</li>
                        <li class="breadcrumb-item active">WAP Push Responder</li>
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

        <!-- Overview Card -->
        {{-- <div class="overview-card">
            <h5>
                <i class="material-icons-outlined">info</i>
                Inbound SMS Handling Modules
            </h5>
            <p>
                Configure how your keyword responds to inbound SMS messages. You can enable multiple modules to create
                comprehensive automated responses, forwarding, and interactive services for your customers.
            </p>
        </div> --}}

        <!-- Main Content -->
        <div class="config-card">
            {{-- <div class="section-header">
                <h5 class="section-title">
                    <i class="material-icons-outlined">tune</i>
                    Available Modules
                </h5>
            </div> --}}

            <div class="section-content">

                <form method="POST" action="{{ route('keywords.WAPpushresponder.save') }}">
                    @csrf
                    <input type="hidden" name="requestaction" value="statesave">
                    <input type="hidden" name="itagg_id" value="{{ $itaggData->id ?? '' }}">
                    <input type="hidden" name="smsshortcode" value="{{ $itaggData->shortcode_number ?? '' }}">
                    <input type="hidden" name="itagg" value="{{ $itaggData->keyword ?? '' }}">
                    <input type="hidden" name="subkeyword" value="">
                    <input type="hidden" name="module" value="Subscription">
                    <input type="hidden" name="big_id" value="{{ $itaggData->users_bigid ?? '' }}">
                    <input type="hidden" name="smsshortcodes_id" value="{{ $itaggData->smsshortcodes_id ?? '' }}"> 
                    <p>Send the following WAP Push URL back</p>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Title</label>
                        <i class="material-icons-outlined info-icon" data-bs-toggle="modal"
                            data-bs-target="#TitleModal">help</i>
                        <input type="text" name="title" class="form-control" maxlength="90" value="{{ $Wappushresponder->title ?? ''}}" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">URL</label>
                        <i class="material-icons-outlined info-icon" data-bs-toggle="modal"
                            data-bs-target="#URLModal">help</i>
                        <input type="text" name="url" class="form-control" maxlength="2000"
                            value="{{ $Wappushresponder->url ?? ''}}" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Response Type</label>
                        <select name="responseroute" class="form-select">
                            <option value="NULL" selected>At cost to my Wallet</option>
                        </select>
                    </div>

                    <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="material-icons-outlined" style="vertical-align: middle; font-size: 1.2rem;">save</i>
                            Save Settings
                        </button>
                        <a href="{{ route('keywords.config', ['itaggId' => $itaggData->id, 'keyword' => $itaggData->keyword]) }}"
                            class="btn btn-secondary">
                            <i class="material-icons-outlined" style="vertical-align: middle; font-size: 1.2rem;">close</i>
                            Cancel
                        </a>
                    </div>
                </form>

                <hr class="my-4">

                <p>
                    To test how your phone handles WAP Push Service Indicator message,
                    send a text consisting of <b>itagg wap</b> to <b>{{ $itaggData->shortcode_number ?? '' }}</b>.
                </p>

                <p>
                    This should return a message to your handset containing a link to a test WAP page (demo costs standard
                    rate).
                </p>

                <p>
                    <a href="#" class="fw-bold" data-bs-toggle="modal" data-bs-target="#KnownIssuesModal">
                        Click here to see any known issues
                    </a>

                </p>

            </div>


        </div>
    </div>
    <div class="modal fade" id="TitleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="material-icons-outlined me-2">help_outline</i>
                        Information
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <p>WAP Push</p>

                    <p>
                        A WAP Push message is a specially formatted SMS message that the recipient uses to
                        connect to the internet.
                    </p>

                    <p>
                        These messages are used to allow people to connect to the internet to view a WAP page,
                        download a Java game or ringtone or an image etc. The URL in the message can point to
                        any type of file on the internet that the phone can handle. The Title that you are
                        asked to set is for the benefit of the person receiving the message - it describes what
                        the link is (eg. 'Space Combat' or 'daily pic - Wednesday')
                    </p>

                    <p>
                        WAP Push messages are fairly standard and work on most phones, but please bear in mind
                        that they are not supported by 100% of phones. Also the firmware is often updated by
                        manufacturers to fix problems.
                    </p>

                    <p>Currently we are aware of the following phones having experienced WAP Push message problems:</p>

                    <ul>
                        <li>Motorola v525</li>
                        <li>Motorola E-365</li>
                    </ul>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>

            </div>
        </div>
    </div>

    <!-- URL Help Modal -->
    <div class="modal fade" id="URLModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="material-icons-outlined me-2">help_outline</i>
                        Information – URL Format
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <h6><strong>URL format</strong></h6>
                    <p>This must start with the protocol (eg. http:// or https:// ).</p>

                    <p><strong>A valid URL here would be</strong></p>
                    <pre class="bg-light p-2 rounded">
                        http://www.itagg.com/mobile.php
                        https://secure.mypaymentsite.com/wapServlet
                        http://www.things.co.uk/mob.php?sender=itagg
                    </pre>

                    <p><strong>An Invalid URL would be</strong></p>
                    <pre class="bg-light p-2 rounded">
                        www.mysite.com
                        mysite.com
                    </pre>

                    <p>
                        Avoid using spaces in your URL if at all possible, as problems have been encountered.
                        If in doubt, test on phones on as many networks as possible.
                    </p>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>

            </div>
        </div>
    </div>

    <div class="modal fade" id="KnownIssuesModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="material-icons-outlined me-2">help_outline</i>
                        WAP Push Messages Information
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <h5>WAP Push messages</h5>

                    <p>WAP push messages generally come in to a different 'inbox' than normal SMS messages.
                        If you hear the phone receive a message but cannot find it, look somewhere in the 'Services' menu
                        area,
                        which contains items concerning WAP connections. For example the P800 stores incoming WAP Push
                        messages
                        in Internet->Push messages.</p>

                    <p><strong>User feedback - these phones have been user tested:</strong>
                        <br><br>
                        Nokia 7650, 3650, 7210, 7250, 7250i, 6310i, 6230, 6230i<br>
                        Sony Ericsson T610, Z600, P800, P900 (look in 'internet' menu for these messages)<br>
                        Ericsson T68 (but has a problem if the link is to a .gif image)<br>
                        LG 7100 (once the user turns the feature on). By default these seem to reject WAP Push messages, and
                        users
                        need to turn this ON by changing the phone option in: Fun->Wap services->Push messages->receive:
                        select 'ON' and the phone will receive them.<br>
                        Sony Ericsson K700i - this phone sometimes defaults to not allow WAP Push messages, so you'll need
                        to check
                        that this option is set correctly.</p>

                    <p>We do not guarantee that these phones will work on all networks at all times, but user feedback
                        indicates
                        that they receive & handle the WAP Push messages correctly (most modern phones should be able to do
                        so).</p>

                    <p><strong>There are currently known issues with WAP Push messages on the following phones:</strong>
                        <br>  <br>
                        Motorola v525, Motorola E-365 - these two have documented problems with Vodafone Live.<br>
                        Nokia 3330 doesn't seem to receive them.<br>
                        Siemens C35 (4-5 years old) interprets them as text (incorrect).<br>
                        Motorola v545 - an iTAGG user had problems using the message to connect to the internet.</p>

                    <p>If a user fails to receive a WAP Push message for some reason, you can send a normal SMS text message
                        containing the required link, which the phone should be able to open. We recommend that you
                        advertise
                        availability for specific phones that you know how to support, rather than assuming that all phones
                        will
                        unconditionally handle these messages. We will do our best to provide you with information but
                        ultimately
                        these messages are an industry standard, and we provide a mechanism to send them.</p>

                    <p>Contact us if you are having problems with a phone or to expand this list with your own findings.</p>
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
        });
    </script>
@endpush
