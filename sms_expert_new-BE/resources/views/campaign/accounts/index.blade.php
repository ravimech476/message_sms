@extends('campaign.layouts.app')

@section('title', 'View Accounts - Campaign Manager')

@push('style')
<style>
    .dashboard-container {
        background: #f8fafc;
        margin: -2rem;
        padding: 2rem;
    }

    .page-header {
        background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
        border-radius: 15px;
        box-shadow: 0 4px 15px rgba(124, 58, 237, 0.3);
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

    .data-card {
        background: white;
        border-radius: 15px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        margin-bottom: 1.5rem;
    }

    .data-card .card-header {
        background: #f8fafc;
        border-bottom: 1px solid #e2e8f0;
        padding: 1rem 1.5rem;
    }

    .data-card .card-header h5 {
        color: #293b50;
        font-weight: 600;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .table-custom {
        margin-bottom: 0;
    }

    .table-custom thead th {
        background: #f8fafc;
        color: #293b50;
        font-weight: 600;
        padding: 1rem;
        border: none;
        border-bottom: 2px solid #e2e8f0;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .table-custom tbody td {
        padding: 1rem;
        border: none;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }

    .table-custom tbody tr:hover {
        background: #f8fafc;
    }

    .master-row {
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.05), rgba(109, 40, 217, 0.05)) !important;
    }

    .master-row:hover {
        background: linear-gradient(135deg, rgba(124, 58, 237, 0.1), rgba(109, 40, 217, 0.1)) !important;
    }

    .badge-master {
        background: linear-gradient(135deg, #7c3aed, #6d28d9);
        color: white;
        font-weight: 500;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.7rem;
        margin-left: 0.5rem;
    }

    .wallet-badge {
        padding: 0.4rem 0.8rem;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.85rem;
    }

    .wallet-positive {
        background: linear-gradient(135deg, rgba(22, 163, 74, 0.1), rgba(21, 128, 61, 0.1));
        color: #16a34a;
        border: 1px solid rgba(22, 163, 74, 0.2);
    }

    .wallet-negative {
        background: linear-gradient(135deg, rgba(220, 38, 38, 0.1), rgba(185, 28, 28, 0.1));
        color: #dc2626;
        border: 1px solid rgba(220, 38, 38, 0.2);
    }

    .username-badge {
        background: #f1f5f9;
        padding: 0.25rem 0.5rem;
        border-radius: 5px;
        font-family: monospace;
        font-size: 0.85rem;
        color: #64748b;
    }

    .copy-btn {
        background: transparent;
        border: none;
        color: #7c3aed;
        padding: 0.25rem;
        border-radius: 5px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .copy-btn:hover {
        background: rgba(124, 58, 237, 0.1);
        color: #6d28d9;
    }

    .transfer-card {
        background: white;
        border-radius: 15px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }

    .transfer-card .card-header {
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(217, 119, 6, 0.1));
        border-bottom: 1px solid rgba(245, 158, 11, 0.2);
        padding: 1rem 1.5rem;
    }

    .transfer-card .card-header h5 {
        color: #92400e;
        font-weight: 600;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .transfer-card .card-body {
        padding: 1.5rem;
    }

    .form-label {
        color: #293b50;
        font-weight: 600;
        margin-bottom: 0.5rem;
        font-size: 0.9rem;
    }

    .form-control, .form-select {
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        padding: 0.75rem 1rem;
        transition: all 0.3s ease;
    }

    .form-control:focus, .form-select:focus {
        border-color: #f59e0b;
        box-shadow: 0 0 0 0.2rem rgba(245, 158, 11, 0.15);
    }

    .btn-transfer {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        border: none;
        color: white;
        padding: 0.75rem 1.5rem;
        border-radius: 10px;
        font-weight: 600;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
    }

    .btn-transfer:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(245, 158, 11, 0.4);
        color: white;
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

    /* Toast notification */
    .toast-notification {
        position: fixed;
        bottom: 20px;
        right: 20px;
        background: linear-gradient(135deg, #16a34a, #15803d);
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 10px;
        box-shadow: 0 4px 15px rgba(22, 163, 74, 0.4);
        display: none;
        z-index: 9999;
        animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
</style>
@endpush

@section('content')
<div class="dashboard-container">
    <!-- Page Header -->
    <div class="page-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h4><i class="material-icons-outlined align-middle me-2">people</i>View Accounts</h4>
                <p>Manage your master and sub-accounts, transfer funds between them</p>
            </div>
            @php
                $userInfo = Session::get('user_info');
                $canAddSubAccount = false;
                if (isset($userInfo['bigid'])) {
                    $currentUser = \App\Models\User::where('bigid', $userInfo['bigid'])->first();
                    if ($currentUser && $currentUser->masteruname == ($userInfo['username'] ?? '') && 
                        strpos($currentUser->dashboardaccess ?? '', 'a') !== false) {
                        $canAddSubAccount = true;
                    }
                }
            @endphp
            @if($canAddSubAccount)
            <a href="{{ route('campaign.accounts.add') }}" class="btn" style="background: white; color: #7c3aed; font-weight: 600; border-radius: 10px; padding: 0.5rem 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i class="material-icons-outlined">person_add</i>
                Add Sub-Account
            </a>
            @endif
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

    <!-- Accounts Table -->
    <div class="data-card">
        <div class="card-header">
            <h5>
                <i class="material-icons-outlined">account_circle</i>
                Account List
            </h5>
        </div>
        <div class="table-responsive">
            <table class="table table-custom">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Contact Name</th>
                        <th>Email</th>
                        <th>Business Name</th>
                        <th>Daily Limit</th>
                        <th>Wallet Balance</th>
                        <th>Keywords</th>
                        <th>Username</th>
                    </tr>
                </thead>
                <tbody>
                    @php $counter = 1; @endphp
                    @foreach($accounts as $account)
                    <tr class="{{ $account->mastersub == 0 ? 'master-row' : '' }}">
                        <td>
                            <strong>{{ $counter++ }}</strong>
                        </td>
                        <td>
                            <strong>{{ urldecode($account->contactname) }}</strong>
                            @if($account->mastersub == 0)
                                <span class="badge-master">Master</span>
                            @endif
                        </td>
                        <td>{{ urldecode($account->contactemail) }}</td>
                        <td>
                            <strong>{{ urldecode($account->busname) }}</strong>
                        </td>
                        <td>{{ number_format($account->bulk_throughput) }}</td>
                        <td>
                            <span class="wallet-badge {{ $account->thewallet > 0 ? 'wallet-positive' : 'wallet-negative' }}">
                                £{{ number_format($account->thewallet, 2) }}
                            </span>
                        </td>
                        <td>{{ number_format($account->numkeywords ?? 0) }}</td>
                        <td>
                            <span class="username-badge">{{ $account->uname }}</span>
                            <button type="button" class="copy-btn" 
                                    onclick="copyToClipboard('{{ $account->uname }}')" title="Copy username">
                                <i class="material-icons-outlined" style="font-size: 16px;">content_copy</i>
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Transfer Funds Section -->
    <div class="transfer-card">
        <div class="card-header">
            <h5>
                <i class="material-icons-outlined" style="color: #d97706;">swap_horiz</i>
                Transfer Wallet Funds Between Accounts
            </h5>
        </div>
        <div class="card-body">
            <form action="{{ route('campaign.transfer.wallet') }}" method="POST" id="transferForm">
                @csrf
                <div class="row g-4 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label">From Account</label>
                        <select name="xferfrom" class="form-select" required>
                            <option value="">Source Account</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->uname }}">
                                    {{ $account->uname }} - {{ urldecode($account->busname) }} (£{{ number_format($account->thewallet, 2) }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">To Account</label>
                        <select name="xferto" class="form-select" required>
                            <option value="">Destination Account</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->uname }}">
                                    {{ $account->uname }} - {{ urldecode($account->busname) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Amount (£)</label>
                        <input type="number" name="xferamount" class="form-control" 
                               step="0.01" min="0.01" placeholder="Enter amount" required>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn-transfer w-100">
                            <i class="material-icons-outlined">send</i>
                            Transfer Funds
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Toast Notification -->
<div class="toast-notification" id="toastNotification">
    <i class="material-icons-outlined align-middle me-2">check_circle</i>
    <span id="toastMessage">Username copied!</span>
</div>
@endsection

@push('js')
<script>
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(function() {
        showToast('Username copied: ' + text);
    }).catch(function(err) {
        console.error('Could not copy text: ', err);
    });
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

document.getElementById('transferForm').addEventListener('submit', function(e) {
    if (!confirm('Are you sure you want to transfer these funds?')) {
        e.preventDefault();
    }
});
</script>
@endpush
