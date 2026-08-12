@extends('layouts.app')
@section('title', 'SMS Details - SMS Expert')

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

    .sms-details-container {
        background: #f8fafc;
        min-height: 100vh;
        margin: -2rem;
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

    .details-card {
        background: white;
        border-radius: 15px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        position: relative;
    }

    .details-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, #ea6118, #293b50);
    }

    .details-header {
        background: linear-gradient(135deg, #f8fafc, #e2e8f0);
        padding: 1.5rem 2rem;
        border-bottom: 2px solid #e2e8f0;
    }

    .details-title {
        color: #293b50;
        font-weight: 700;
        font-size: 1.4rem;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .details-body {
        padding: 2rem;
    }

    .detail-row {
        display: flex;
        align-items: flex-start;
        padding: 1rem 0;
        border-bottom: 1px solid #f1f5f9;
    }

    .detail-row:last-child {
        border-bottom: none;
    }

    .detail-icon {
        width: 40px;
        height: 40px;
        background: linear-gradient(135deg, rgba(234, 97, 24, 0.1), rgba(41, 59, 80, 0.1));
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 1rem;
        flex-shrink: 0;
    }

    .detail-icon i {
        color: #ea6118;
        font-size: 1.2rem;
    }

    .detail-content {
        flex: 1;
    }

    .detail-label {
        color: #64748b;
        font-size: 0.85rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 0.25rem;
    }

    .detail-value {
        color: #293b50;
        font-size: 1rem;
        font-weight: 500;
    }

    .message-box {
        background: linear-gradient(135deg, #f8fafc, #e2e8f0);
        border-radius: 12px;
        padding: 1.5rem;
        border-left: 4px solid #ea6118;
        margin: 1rem 0;
    }

    .message-text {
        color: #293b50;
        font-size: 1.1rem;
        line-height: 1.6;
        word-break: break-word;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border-radius: 25px;
        font-weight: 600;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .status-delivered {
        background: linear-gradient(135deg, #16a34a, #15803d);
        color: white;
    }

    .status-pending {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        color: white;
    }

    .status-failed {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
        color: white;
    }

    .status-progress {
        background: linear-gradient(135deg, #0891b2, #0e7490);
        color: white;
    }

    .cost-highlight {
        background: linear-gradient(135deg, rgba(234, 97, 24, 0.1), rgba(41, 59, 80, 0.05));
        border-radius: 8px;
        padding: 0.5rem 1rem;
        display: inline-block;
        font-weight: 700;
        color: #ea6118;
        font-size: 1.1rem;
    }

    .btn-back {
        background: linear-gradient(135deg, #64748b, #475569);
        border: none;
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 10px;
        font-weight: 600;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
    }

    .btn-back:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(100, 116, 139, 0.4);
        color: white;
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
    }

    .info-section {
        background: #f8fafc;
        border-radius: 12px;
        padding: 1.5rem;
        border: 1px solid #e2e8f0;
    }

    .info-section-title {
        color: #293b50;
        font-weight: 700;
        font-size: 1rem;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding-bottom: 0.75rem;
        border-bottom: 2px solid #e2e8f0;
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

    .alert-danger {
        background: linear-gradient(135deg, #dc2626, #b91c1c);
        color: white;
    }

    @media (max-width: 768px) {
        .info-grid {
            grid-template-columns: 1fr;
        }

        .detail-row {
            flex-direction: column;
        }

        .detail-icon {
            margin-bottom: 0.5rem;
            margin-right: 0;
        }
    }
</style>
@endpush

@section('content')
<div class="sms-details-container">
    @php
        // Build back URL with preserved filters
        $filterParams = request()->except(['tableName', 'id', '_token']);
        $backUrl = route('sentsms');
        // If we have filter params, we need to POST them - store in session or use JS
    @endphp

    <!-- Breadcrumb -->
    <div class="breadcrumb-container d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center">
            <div class="breadcrumb-title pe-3">
                <i class="material-icons-outlined" style="color: #ea6118;">info</i>
                SMS Details
            </div>&nbsp;
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="{{ route('dashboard') }}">Dashboard</a>
                    </li>
                    <li class="breadcrumb-item">
                        <a href="javascript:void(0)" onclick="goBackWithFilters()">Sent SMS</a>
                    </li>
                    <li class="breadcrumb-item active">Message Details</li>
                </ol>
            </nav>
        </div>
        <button type="button" onclick="goBackWithFilters()" class="btn btn-outline-secondary back-btn">
            <i class="material-icons-outlined me-1">arrow_back</i> Back to Sent SMS
        </button>
    </div>

    <!-- Hidden form for POST back with filters -->
    <form id="backForm" action="{{ route('sentsms') }}" method="POST" style="display: none;">
        @csrf
        @foreach($filterParams as $key => $value)
            @if(is_array($value))
                @foreach($value as $v)
                    <input type="hidden" name="{{ $key }}[]" value="{{ $v }}">
                @endforeach
            @else
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endif
        @endforeach
    </form>

    @if(isset($error))
        <div class="alert alert-danger">
            <i class="material-icons-outlined">error</i>
            {{ $error }}
        </div>
    @else
        <!-- Main Details Card -->
        <div class="details-card">
            <div class="details-header">
                <h4 class="details-title">
                    <i class="material-icons-outlined">sms</i>
                    Sent SMS Details
                </h4>
            </div>

            <div class="details-body">
                <!-- Info Grid -->
                <div class="info-grid">
                    <!-- Timing Information -->
                    <div class="info-section">
                        <div class="info-section-title">
                            <i class="material-icons-outlined">schedule</i>
                            Timing Information
                        </div>

                        <div class="detail-row">
                            <div class="detail-icon">
                                <i class="material-icons-outlined">schedule</i>
                            </div>
                            <div class="detail-content">
                                <div class="detail-label">Date Submitted</div>
                                <div class="detail-value">{{ $smsDetails['Date_Submitted'] ?? 'N/A' }}</div>
                            </div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-icon">
                                <i class="material-icons-outlined">send</i>
                            </div>
                            <div class="detail-content">
                                <div class="detail-label">Send at Time</div>
                                <div class="detail-value">{{ $smsDetails['Send_at_time'] ?? 'N/A' }}</div>
                            </div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-icon">
                                <i class="material-icons-outlined">done_all</i>
                            </div>
                            <div class="detail-content">
                                <div class="detail-label">Date Finalised</div>
                                <div class="detail-value">{{ $smsDetails['Date_Finalised'] ?? ($smsDetails['Delivery_Time'] ?? 'N/A') }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Sender & Recipient -->
                    <div class="info-section">
                        <div class="info-section-title">
                            <i class="material-icons-outlined">people</i>
                            Sender & Recipient
                        </div>

                        <div class="detail-row">
                            <div class="detail-icon">
                                <i class="material-icons-outlined">person</i>
                            </div>
                            <div class="detail-content">
                                <div class="detail-label">Sender</div>
                                <div class="detail-value">{{ $smsDetails['Sender'] ?? 'N/A' }}</div>
                            </div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-icon">
                                <i class="material-icons-outlined">phone</i>
                            </div>
                            <div class="detail-content">
                                <div class="detail-label">Sent To</div>
                                <div class="detail-value">{{ $smsDetails['Sent_To'] ?? 'N/A' }}</div>
                            </div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-icon">
                                <i class="material-icons-outlined">source</i>
                            </div>
                            <div class="detail-content">
                                <div class="detail-label">Sent By</div>
                                <div class="detail-value">{{ $smsDetails['Sent_By'] ?? 'N/A' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Message Content -->
                <div class="mt-4">
                    <div class="detail-row">
                        <div class="detail-icon">
                            <i class="material-icons-outlined">message</i>
                        </div>
                        <div class="detail-content">
                            <div class="detail-label">Message Content</div>
                            <div class="message-box">
                                <div class="message-text">{{ $smsDetails['Message'] ?? 'N/A' }}</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cost & Status -->
                <div class="info-grid mt-4">
                    <!-- Cost Information -->
                    <div class="info-section">
                        <div class="info-section-title">
                            <i class="material-icons-outlined">payments</i>
                            Cost Information
                        </div>

                        <div class="detail-row">
                            <div class="detail-icon">
                                <i class="material-icons-outlined">paid</i>
                            </div>
                            <div class="detail-content">
                                <div class="detail-label">Recipient Cost</div>
                                <div class="detail-value">
                                    @php
                                        $recipientCost = $smsDetails['Recipient_Cost'] ?? 'N/A';
                                        if ($recipientCost !== 'n/a' && $recipientCost !== 'N/A') {
                                            $costStr = str_replace(['£', ' '], '', $recipientCost);
                                            $costInPounds = floatval($costStr);
                                            $recipientCost = number_format($costInPounds * 100, 2) . 'p';
                                        }
                                    @endphp
                                    <span class="cost-highlight">{{ $recipientCost }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="detail-row">
                            <div class="detail-icon">
                                <i class="material-icons-outlined">account_balance_wallet</i>
                            </div>
                            <div class="detail-content">
                                <div class="detail-label">Cost to You</div>
                                <div class="detail-value">
                                    @php
                                        $costToYou = $smsDetails['Cost_to_You'] ?? 'N/A';
                                        if ($costToYou !== 'N/A' && str_contains($costToYou, '£')) {
                                            $costStr = str_replace(['£', ' '], '', $costToYou);
                                            $costInPounds = floatval($costStr);
                                            $costToYou = number_format($costInPounds * 100, 2) . 'p';
                                        }
                                    @endphp
                                    <span class="cost-highlight">{{ $costToYou }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status Information -->
                    <div class="info-section">
                        <div class="info-section-title">
                            <i class="material-icons-outlined">check_circle</i>
                            Status Information
                        </div>

                        <div class="detail-row">
                            <div class="detail-icon">
                                <i class="material-icons-outlined">local_shipping</i>
                            </div>
                            <div class="detail-content">
                                <div class="detail-label">Delivery Status</div>
                                <div class="detail-value">
                                    @php
                                        $deliveryStatus = $smsDetails['Delivery_Status'] ?? 'Pending';
                                        $statusClass = 'status-progress';
                                        $statusIcon = 'hourglass_empty';

                                        if (stripos($deliveryStatus, 'delivered') !== false) {
                                            $statusClass = 'status-delivered';
                                            $statusIcon = 'check_circle';
                                        } elseif (stripos($deliveryStatus, 'failed') !== false || stripos($deliveryStatus, 'error') !== false || stripos($deliveryStatus, 'rejected') !== false) {
                                            $statusClass = 'status-failed';
                                            $statusIcon = 'cancel';
                                        } elseif (stripos($deliveryStatus, 'pending') !== false) {
                                            $statusClass = 'status-pending';
                                            $statusIcon = 'schedule';
                                        }
                                    @endphp
                                    <span class="status-badge {{ $statusClass }}">
                                        <i class="material-icons-outlined">{{ $statusIcon }}</i>
                                        {{ $deliveryStatus }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        @if (!empty($smsDetails['Upstream_Reason']))
                        <div class="detail-row">
                            <div class="detail-icon">
                                <i class="material-icons-outlined">report</i>
                            </div>
                            <div class="detail-content">
                                <div class="detail-label">Reason</div>
                                <div class="detail-value" style="font-style: italic;">'{{ $smsDetails['Upstream_Reason'] }}'</div>
                            </div>
                        </div>
                        @endif

                        <div class="detail-row">
                            <div class="detail-icon">
                                <i class="material-icons-outlined">info</i>
                            </div>
                            <div class="detail-content">
                                <div class="detail-label">Message Status</div>
                                <div class="detail-value">{{ $smsDetails['Message_Status'] ?? 'Message sent successfully' }}</div>
                            </div>
                        </div>

                        @if (!empty($smsDetails['User_Defined']))
                        <div class="detail-row">
                            <div class="detail-icon">
                                <i class="material-icons-outlined">edit_note</i>
                            </div>
                            <div class="detail-content">
                                <div class="detail-label">User defined log info</div>
                                <div class="detail-value" style="word-break: break-all;">{{ $smsDetails['User_Defined'] }}</div>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="mt-4 pt-3 border-top d-flex gap-3">
                    <button type="button" onclick="goBackWithFilters()" class="btn-back">
                        <i class="material-icons-outlined">arrow_back</i>
                        Back to Sent SMS
                    </button>
                </div>
            </div>
        </div>

        {{-- Separate Debug card — only rendered when ?debug=true is in the URL.
             Shows the source table / id / migration status, and (when
             ?show_debug_columns=id,name,... is passed) the value of each requested
             smsg_log column. --}}
        @if (!empty($debug))
        <div class="details-card" style="margin-top:20px; border:1px solid #f0c987;">
            <div class="details-header" style="background:#fff7e6;">
                <h4 class="details-title" style="color:#b45309;">
                    <i class="material-icons-outlined">bug_report</i>
                    Debug Information
                </h4>
            </div>
            <div class="details-body">
                <div class="info-section">
                    <div class="info-section-title">
                        <i class="material-icons-outlined">storage</i>
                        Record Source
                    </div>
                    @foreach ($debug['meta'] as $k => $v)
                    <div class="detail-row">
                        <div class="detail-content">
                            <div class="detail-label">{{ $k }}</div>
                            <div class="detail-value" style="font-family:Consolas,Menlo,monospace; word-break:break-all;">{{ $v }}</div>
                        </div>
                    </div>
                    @endforeach
                </div>

                @if (!empty($debug['columns']))
                <div class="info-section" style="margin-top:14px;">
                    <div class="info-section-title">
                        <i class="material-icons-outlined">view_column</i>
                        Requested Columns
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-bordered mb-0" style="font-size:12.5px;">
                            <thead class="table-light"><tr><th style="white-space:nowrap;">Column</th><th>Value</th></tr></thead>
                            <tbody>
                                @foreach ($debug['columns'] as $col => $val)
                                <tr>
                                    <td style="white-space:nowrap; font-family:Consolas,Menlo,monospace;">{{ $col }}</td>
                                    <td style="font-family:Consolas,Menlo,monospace; word-break:break-all;">{{ $val }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif
    @endif
</div>
@endsection

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Smooth card animation
    const card = document.querySelector('.details-card');
    if (card) {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';

        setTimeout(() => {
            card.style.transition = 'all 0.5s ease';
            card.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        }, 100);
    }

    console.log('SMS Details page loaded successfully!');
});

// Go back to sent SMS list with filters preserved
function goBackWithFilters() {
    const backForm = document.getElementById('backForm');
    if (backForm && backForm.querySelectorAll('input[type="hidden"]:not([name="_token"])').length > 0) {
        // We have filter params, submit the form
        backForm.submit();
    } else {
        // No filters, just go back
        window.location.href = '{{ route("sentsms") }}';
    }
}
</script>
@endpush
