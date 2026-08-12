@extends('layouts.app')
@section('title', 'Dashboard - SMS Expert')

@push('style')
<link href="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.css" rel="stylesheet">
<style>
    .dashboard-container {
        background: #f8fafc;
        min-height: 100vh;
        margin: -2rem;
        padding: 2rem;
    }

    .stats-card {
        background: white;
        border-radius: 15px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
        overflow: hidden;
        position: relative;
    }

    .stats-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: linear-gradient(90deg, #ea6118, #293b50);
    }

    .stats-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }

    .chart-card {
        background: white;
        border-radius: 15px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        min-height: 400px;
    }

    .metric-icon {
        width: 60px;
        height: 60px;
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 24px;
        margin-bottom: 1rem;
    }

    .metric-value {
        font-size: 2rem;
        font-weight: 700;
        color: #293b50;
        margin-bottom: 0.5rem;
    }

    .metric-label {
        color: #64748b;
        font-size: 0.9rem;
        font-weight: 500;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .trend-indicator {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-top: 0.5rem;
    }

    .trend-up {
        color: #16a34a;
    }

    .trend-down {
        color: #dc2626;
    }

    .quick-links-card {
        background: white;
        border-radius: 15px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        padding: 1.5rem;
    }

    .quick-links-table {
        width: 100%;
        margin-bottom: 0;
    }

    .quick-links-table th {
        background: #f8fafc;
        color: #293b50;
        font-weight: 600;
        padding: 1rem;
        border: none;
        border-bottom: 2px solid #e2e8f0;
        font-size: 0.9rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .quick-links-table td {
        padding: 1rem;
        border: none;
        border-bottom: 1px solid #f1f5f9;
        vertical-align: middle;
    }

    .quick-links-table tbody tr:hover {
        background: #f8fafc;
        transform: translateX(2px);
        transition: all 0.2s ease;
    }

    .quick-link-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        margin-right: 1rem;
    }

    .quick-link-btn {
        background: linear-gradient(135deg, #ea6118, #293b50);
        border: none;
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 10px;
        font-weight: 500;
        transition: all 0.3s ease;
        text-decoration: none;
        font-size: 0.85rem;
    }

    .quick-link-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(234, 97, 24, 0.3);
        color: white;
    }

    .date-filter {
        background: white;
        border-radius: 15px;
        border: 1px solid #e2e8f0;
        padding: 1rem;
        margin-bottom: 2rem;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .form-control, .form-select {
        border-radius: 10px;
        border: 1px solid #e2e8f0;
        background: white;
    }

    .form-control:focus, .form-select:focus {
        border-color: #ea6118;
        box-shadow: 0 0 0 0.2rem rgba(234, 97, 24, 0.25);
    }

    .recent-activity {
        background: white;
        border-radius: 15px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        max-height: 400px;
        overflow-y: auto;
    }

    .activity-item {
        padding: 1rem;
        border-bottom: 1px solid #f1f5f9;
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .activity-item:last-child {
        border-bottom: none;
    }

    .activity-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 16px;
    }

    .loading-spinner {
        display: inline-block;
        width: 20px;
        height: 20px;
        border: 3px solid #f3f3f3;
        border-top: 3px solid #ea6118;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    .gradient-primary { background: linear-gradient(135deg, #293b50, #1f2c3d); }
    .gradient-success { background: linear-gradient(135deg, #16a34a, #15803d); }
    .gradient-warning { background: linear-gradient(135deg, #f59e0b, #d97706); }
    .gradient-danger { background: linear-gradient(135deg, #dc2626, #b91c1c); }
    .gradient-info { background: linear-gradient(135deg, #0891b2, #0e7490); }
    .gradient-purple { background: linear-gradient(135deg, #7c3aed, #6d28d9); }
    .gradient-orange { background: linear-gradient(135deg, #ea6118, #d1520e); }

    .chart-container {
        position: relative;
        height: 350px;
        padding: 1rem;
    }

    .welcome-header {
        background: white;
        border-radius: 15px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        padding: 2rem;
        margin-bottom: 2rem;
        text-align: center;
    }

    .refresh-btn {
        position: absolute;
        top: 1rem;
        right: 1rem;
        background: rgba(234, 97, 24, 0.1);
        border: 1px solid rgba(234, 97, 24, 0.3);
        color: #ea6118;
        border-radius: 10px;
        width: 40px;
        height: 40px;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .refresh-btn:hover {
        background: #ea6118;
        color: white;
        transform: rotate(180deg);
    }

    .btn-primary {
        background: linear-gradient(135deg, #ea6118, #293b50);
        border: none;
        border-radius: 10px;
        padding: 0.6rem 1.5rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-primary:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(234, 97, 24, 0.3);
    }

    .page-title {
        color: #293b50;
        font-weight: 700;
        margin-bottom: 1rem;
    }

    .welcome-text {
        color: #64748b;
    }

    .card-title {
        color: #293b50;
        font-weight: 600;
        margin-bottom: 1rem;
    }

    /* Quick Actions Styling */
    .action-btn {
        background: linear-gradient(135deg, #ea6118, #d1520e);
        border: none;
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 15px;
        font-weight: 600;
        transition: all 0.3s ease;
        text-decoration: none;
        display: flex;
        align-items: center;
        gap: 0.5rem;
        margin-bottom: 1rem;
        width: 100%;
        justify-content: center;
    }

    .action-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 25px rgba(234, 97, 24, 0.4);
        color: white;
    }

    .quick-actions {
        background: white;
        border-radius: 15px;
        border: 1px solid #e2e8f0;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        padding: 1.5rem;
    }
</style>
@endpush

@section('content')
<div class="dashboard-container">
    @if(!empty($oldApiAlert))
    <!-- Old API usage alert (shown once per day for migrated customers still on the old API) -->
    <div class="alert alert-warning alert-dismissible fade show d-flex align-items-start" role="alert"
         style="border-left: 4px solid #ea6118; border-radius: 10px;">
        <i class="material-icons-outlined me-2" style="color:#ea6118;">warning</i>
        <div class="flex-grow-1">{{ $oldApiAlert }}</div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <!-- Welcome Header -->
    <div class="welcome-header">
        <h1 class="page-title display-6 mb-3">Welcome back, {{ ucfirst(urldecode($user_contactname)) }}! 🚀</h1>
        <p class="welcome-text lead mb-0">Here's your SMS Expert dashboard overview</p>
        <small class="text-muted">{{ now()->setTimezone('Europe/London')->format('l, F j, Y - H:i') }}</small>
    </div>

    <!-- Date Filter -->
    <div class="date-filter">
        <form id="dateFilterForm" class="row g-3 align-items-end">
            <div class="col-md-4">
                <label for="start_date" class="form-label">Start Date</label>
                {{-- Data is complete-days-only, so nothing exists past yesterday: cap both pickers
                     at $maxDate (yesterday) so no date beyond "Data up to" can be selected. --}}
                <input type="date" class="form-control" id="start_date" name="start_date" value="{{ $startDate }}" max="{{ $maxDate }}"
                    onchange="document.getElementById('end_date').min = this.value;">
            </div>
            <div class="col-md-4">
                <label for="end_date" class="form-label">End Date</label>
                {{-- min = current start so the picker can't choose an End before the Start; max = data
                     cutoff (yesterday). Server-side resolveDateRange() is the real guard (URL params
                     bypass pickers) — this is UX only. --}}
                <input type="date" class="form-control" id="end_date" name="end_date" value="{{ $endDate }}" min="{{ $startDate }}" max="{{ $maxDate }}"
                    onchange="document.getElementById('start_date').max = this.value;">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="material-icons-outlined">refresh</i> Update
                </button>
            </div>
            {{-- <div class="col-md-2">
                <select class="form-select" id="quickDateFilter">
                    <option value="">Quick Filters</option>
                    <option value="today">Today</option>
                    <option value="yesterday">Yesterday</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                    <option value="quarter">This Quarter</option>
                </select>
            </div> --}}
        </form>
    </div>

    {{-- These cards are served from the nightly customer_daily_stats rollup, which covers
         complete days only — so they reflect data up to yesterday, not today. --}}
    @if(!empty($smsStats['data_up_to']))
    <div class="mb-2">
        <span class="badge bg-light text-muted border">
            <i class="material-icons-outlined align-middle" style="font-size:14px;">history</i>
            Data up to {{ \Carbon\Carbon::parse($smsStats['data_up_to'])->format('d M Y') }}
        </span>
    </div>
    @endif

    <!-- Stats Cards -->
    <div class="row g-4 mb-4">
        <div class="col-lg-3 col-md-6">
            <div class="stats-card h-100 p-4 position-relative">
                {{-- <button class="refresh-btn" onclick="refreshStats()" title="Refresh Data">
                    <i class="material-icons-outlined">refresh</i>
                </button> --}}
                <div class="metric-icon gradient-primary">
                    <i class="material-icons-outlined">send</i>
                </div>
                <div class="metric-value" id="totalSent">{{ number_format($smsStats['total_sent']) }}</div>
                <div class="metric-label">Total Sent SMS</div>
                <div class="trend-indicator">
                    <i class="material-icons-outlined">event_available</i>
                    <span class="text-muted">in selected period</span>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="stats-card h-100 p-4">
                <div class="metric-icon gradient-success">
                    <i class="material-icons-outlined">check_circle</i>
                </div>
                <div class="metric-value" id="delivered">{{ number_format($smsStats['delivered']) }}</div>
                <div class="metric-label">Delivered SMS</div>
                <div class="trend-indicator">
                    <i class="material-icons-outlined trend-up">trending_up</i>
                    <span class="trend-up">{{ $smsStats['delivery_rate'] }}% delivery rate</span>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="stats-card h-100 p-4">
                <div class="metric-icon gradient-warning">
                    <i class="material-icons-outlined">hourglass_empty</i>
                </div>
                <div class="metric-value" id="pending">{{ number_format($smsStats['pending']) }}</div>
                <div class="metric-label">Pending SMS</div>
                <div class="trend-indicator">
                    <i class="material-icons-outlined">info</i>
                    <span class="text-muted">{{ number_format($smsStats['pending']) }} awaiting delivery</span>
                </div>
            </div>
        </div>

        <div class="col-lg-3 col-md-6">
            <div class="stats-card h-100 p-4">
                <div class="metric-icon gradient-orange">
                    <i class="material-icons-outlined">error</i>
                </div>
                <div class="metric-value" id="failed">{{ number_format($smsStats['blocklist']) }}</div>
                <div class="metric-label">Block List</div>
                <div class="trend-indicator">
                    @if($smsStats['total_sent'] > 0)
                        <i class="material-icons-outlined {{ $smsStats['blocklist_rate'] > 5 ? 'text-danger' : 'text-success' }}">{{ $smsStats['blocklist_rate'] > 5 ? 'warning' : 'check_circle' }}</i>
                        <span class="{{ $smsStats['blocklist_rate'] > 5 ? 'text-danger' : 'text-muted' }}">{{ $smsStats['blocklist_rate'] }}% blocked</span>
                    @else
                        <i class="material-icons-outlined">info</i>
                        <span class="text-muted">0% blocked</span>
                    @endif
                </div>
                {{-- <div class="metric-value">£{{ number_format($remaining_wallet, 2) }}</div>
                <div class="metric-label">Wallet Balance</div>
                <div class="trend-indicator">
                    <i class="material-icons-outlined">info</i>
                    <span class="text-muted">£{{ number_format($smsStats['today_spent'], 2) }} spent today</span>
                </div> --}}
            </div>
        </div>
    </div>

    <!-- Financial Stats -->
    <div class="row g-4 mb-4">
        <div class="col-lg-4 col-md-6">
            <div class="stats-card h-100 p-4">
                <div class="metric-icon gradient-danger">
                    <i class="material-icons-outlined">credit_card</i>
                </div>
                <div class="metric-value">£ {{ number_format($smsStats['total_spent'], 2) }}</div>
                <div class="metric-label">Total Spent Amount</div>
                <div class="trend-indicator">
                    <i class="material-icons-outlined">info</i>
                    <span class="text-muted">£ {{ number_format($smsStats['avg_cost_per_sms'], 2) }}/SMS avg</span>
                </div>
            </div>
        </div>

        <div class="col-lg-4 col-md-6">
            <div class="stats-card h-100 p-4">
                <div class="metric-icon gradient-purple">
                    <i class="material-icons-outlined">account_balance_wallet</i>
                </div>
                <div class="metric-value">£ {{ number_format($remaining_wallet, 2) }}</div>
                <div class="metric-label">Wallet Balance</div>
                <div class="trend-indicator">
                    <i class="material-icons-outlined">info</i>
                    <span class="text-muted">£ {{ number_format($smsStats['today_spent'], 2) }} spent today</span>
                </div>
                {{-- <div class="metric-value" id="failed">{{ number_format($smsStats['failed']) }}</div>
                <div class="metric-label">Failed SMS</div>
                <div class="trend-indicator">
                    <i class="material-icons-outlined">info</i>
                    <span class="text-muted">{{ $smsStats['total_sent'] > 0 ? round(($smsStats['failed'] / $smsStats['total_sent']) * 100, 2) : 0 }}% failure rate</span>
                </div> --}}
            </div>
        </div>

        <div class="col-lg-4 col-md-6">
            <div class="quick-links-card">
                <h5 class="card-title">Quick Links</h5>
                <table class="quick-links-table table table-borderless">
                    <thead>
                        <tr>
                            <th>Description</th>
                            <th>Action</th>
                            {{-- <th></th> --}}
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="quick-link-icon gradient-primary">
                                        <i class="material-icons-outlined">send</i>
                                    </div>
                                    <strong>Send SMS</strong>
                                </div>
                            </td>
                            {{-- <td><small class="text-muted">Send new message</small></td> --}}
                            <td>
                                <a href="{{ route('sendsms') }}" class="quick-link-btn">Go</a>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="quick-link-icon gradient-success">
                                        <i class="material-icons-outlined">shopping_cart</i>
                                    </div>
                                    <strong>Buy SMS</strong>
                                </div>
                            </td>
                            {{-- <td><small class="text-muted">Purchase SMS credits</small></td> --}}
                            <td>
                                <a href="{{ route('buysms') }}" class="quick-link-btn">Go</a>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="quick-link-icon gradient-info">
                                        <i class="material-icons-outlined">history</i>
                                    </div>
                                    <strong>Sent SMS History</strong>
                                </div>
                            </td>
                            {{-- <td><small class="text-muted">View sent messages</small></td> --}}
                            <td>
                                <a href="{{ route('sentsms') }}" class="quick-link-btn">Go</a>
                            </td>
                        </tr>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="quick-link-icon gradient-warning">
                                        <i class="material-icons-outlined">group</i>
                                    </div>
                                    <strong>Manage Groups</strong>
                                </div>
                            </td>
                            {{-- <td><small class="text-muted">Organize contacts</small></td> --}}
                            <td>
                                <a href="{{ route('groups.index') }}" class="quick-link-btn">Go</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- <!-- Charts Row -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="chart-card">
                <div class="chart-container">
                    <h5 class="card-title mb-3">SMS Trends Over Time</h5>
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="chart-card">
                <div class="chart-container">
                    <h5 class="card-title mb-3">Delivery Status</h5>
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Hourly Trends and Live Stats -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="chart-card">
                <div class="chart-container">
                    <h5 class="card-title mb-3">Last 24 Hours Activity</h5>
                    <canvas id="hourlyChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="stats-card h-100 p-4">
                <h5 class="card-title mb-3">Live Statistics</h5>
                <div id="liveStats">
                    <div class="mb-3">
                        <small class="text-muted">Last Hour</small>
                        <div class="d-flex justify-content-between">
                            <span>SMS Sent:</span>
                            <span class="fw-bold" id="hourSent">-</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Delivered:</span>
                            <span class="fw-bold text-success" id="hourDelivered">-</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Spent:</span>
                            <span class="fw-bold" id="hourSpent">-</span>
                        </div>
                    </div>
                    <hr>
                    <div>
                        <small class="text-muted">Today Total</small>
                        <div class="d-flex justify-content-between">
                            <span>SMS Sent:</span>
                            <span class="fw-bold" id="todaySent">-</span>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Delivery Rate:</span>
                            <span class="fw-bold text-info" id="todayDeliveryRate">-</span>
                        </div>
                    </div>
                    <div class="mt-3">
                        <small class="text-muted">Last updated: <span id="lastUpdated">-</span></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
                
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="chart-card">
                <div class="chart-container">
                    <h5 class="card-title mb-3">Monthly Overview ({{ date('Y') }})</h5>
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="quick-actions">
                <h5 class="card-title mb-3">Quick Actions</h5>
                <a href="{{ route('sendsms') }}" class="action-btn">
                    <i class="material-icons-outlined">send</i>
                    Send New SMS
                </a>
                <a href="{{ route('buysms') }}" class="action-btn">
                    <i class="material-icons-outlined">shopping_cart</i>
                    Buy SMS Credits
                </a>
                <a href="{{ route('sentsms') }}" class="action-btn">
                    <i class="material-icons-outlined">history</i>
                    View SMS History
                </a>
                <a href="{{ route('groups.index') }}" class="action-btn">
                    <i class="material-icons-outlined">group</i>
                    Manage Groups
                </a>
                <a href="{{ route('numbers.index') }}" class="action-btn">
                    <i class="material-icons-outlined">contacts</i>
                    Manage Numbers
                </a>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="row g-4">
        <div class="col-12">
            <div class="recent-activity">
                <div class="p-3 border-bottom">
                    <h5 class="card-title mb-0">Recent SMS Activity</h5>
                </div>
                <div id="recentActivity">
                    <div class="text-center p-4">
                        <div class="loading-spinner"></div>
                        <p class="mt-2">Loading recent activity...</p>
                    </div>
                </div>
            </div>
        </div>
    </div> --}}
</div>
@endsection

@push('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize charts with data
    const dailyTrends = @json($dailyTrends);
    const monthlyTrends = @json($monthlyTrends);
    const smsStats = @json($smsStats);

    // Trend Chart
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    const trendChart = new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: dailyTrends.map(item => new Date(item.date).toLocaleDateString()),
            datasets: [{
                label: 'SMS Sent',
                data: dailyTrends.map(item => item.total_sent),
                borderColor: '#ea6118',
                backgroundColor: 'rgba(234, 97, 24, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }, {
                label: 'Delivered',
                data: dailyTrends.map(item => item.delivered),
                borderColor: '#16a34a',
                backgroundColor: 'rgba(22, 163, 74, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    }
                }
            }
        }
    });

    // Status Chart (Doughnut)
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    const statusChart = new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Delivered', 'Pending', 'Failed'],
            datasets: [{
                data: [smsStats.delivered, smsStats.pending, smsStats.failed],
                backgroundColor: ['#16a34a', '#f59e0b', '#dc2626'],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                }
            }
        }
    });

    // Monthly Chart
    const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
    const monthlyChart = new Chart(monthlyCtx, {
        type: 'bar',
        data: {
            labels: monthlyTrends.map(item => item.month),
            datasets: [{
                label: 'SMS Sent',
                data: monthlyTrends.map(item => item.total_sent),
                backgroundColor: 'rgba(234, 97, 24, 0.8)',
                borderColor: '#ea6118',
                borderWidth: 1
            }, {
                label: 'Delivered',
                data: monthlyTrends.map(item => item.delivered),
                backgroundColor: 'rgba(22, 163, 74, 0.8)',
                borderColor: '#16a34a',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    }
                }
            }
        }
    });

    // Hourly Chart
    const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
    let hourlyChart = new Chart(hourlyCtx, {
        type: 'line',
        data: {
            labels: [],
            datasets: [{
                label: 'SMS Sent',
                data: [],
                borderColor: '#293b50',
                backgroundColor: 'rgba(41, 59, 80, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }, {
                label: 'Delivered',
                data: [],
                borderColor: '#16a34a',
                backgroundColor: 'rgba(22, 163, 74, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top'
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    }
                }
            }
        }
    });

    // Load hourly trends
    loadHourlyTrends();
    
    // Load live stats
    loadLiveStats();
    
    // Set up live stats auto-refresh
    setInterval(loadLiveStats, 60000); // Update every minute

    // Date filter functionality
    document.getElementById('dateFilterForm').addEventListener('submit', function(e) {
        e.preventDefault();
        updateDashboard();
    });

    document.getElementById('quickDateFilter').addEventListener('change', function() {
        const value = this.value;
        if (value) {
            setQuickDateFilter(value);
            updateDashboard();
        }
    });

    function setQuickDateFilter(period) {
        const now = new Date();
        let startDate, endDate;

        switch(period) {
            case 'today':
                startDate = endDate = now.toISOString().split('T')[0];
                break;
            case 'yesterday':
                const yesterday = new Date(now);
                yesterday.setDate(yesterday.getDate() - 1);
                startDate = endDate = yesterday.toISOString().split('T')[0];
                break;
            case 'week':
                const weekStart = new Date(now);
                weekStart.setDate(weekStart.getDate() - weekStart.getDay());
                startDate = weekStart.toISOString().split('T')[0];
                endDate = now.toISOString().split('T')[0];
                break;
            case 'month':
                startDate = new Date(now.getFullYear(), now.getMonth(), 1).toISOString().split('T')[0];
                endDate = now.toISOString().split('T')[0];
                break;
            case 'quarter':
                const quarterStart = new Date(now.getFullYear(), Math.floor(now.getMonth() / 3) * 3, 1);
                startDate = quarterStart.toISOString().split('T')[0];
                endDate = now.toISOString().split('T')[0];
                break;
        }

        document.getElementById('start_date').value = startDate;
        document.getElementById('end_date').value = endDate;
    }

    function updateDashboard() {
        // Data only exists up to yesterday ("Data up to <date>"). Cap the end date at $maxDate so
        // no query (even a hand-typed date) can ask for a day beyond the available data. ISO
        // yyyy-mm-dd strings compare chronologically, so plain string comparison is safe here.
        const maxDate = '{{ $maxDate }}';
        let startDate = document.getElementById('start_date').value;
        let endDate = document.getElementById('end_date').value;

        if (endDate && endDate > maxDate) {
            endDate = maxDate;
            document.getElementById('end_date').value = maxDate;
        }
        if (startDate && endDate && startDate > endDate) {
            // Reversed range -> SWAP (matches server-side resolveDateRange()), rather than collapsing
            // to a single day. Keeps both dates the user picked, just in valid start<=end order.
            [startDate, endDate] = [endDate, startDate];
            document.getElementById('start_date').value = startDate;
            document.getElementById('end_date').value = endDate;
        }

        // Show loading states
        showLoadingStates();

        // Fetch updated stats
        fetch(`{{ route('dashboard.stats') }}?start_date=${startDate}&end_date=${endDate}`)
            .then(response => response.json())
            .then(data => {
                updateStatsDisplay(data);
            })
            .catch(error => {
                console.error('Error updating stats:', error);
            });

        // Fetch updated charts
        fetch(`{{ route('dashboard.charts') }}?start_date=${startDate}&end_date=${endDate}`)
            .then(response => response.json())
            .then(data => {
                updateCharts(data);
            })
            .catch(error => {
                console.error('Error updating charts:', error);
            });
    }

    function showLoadingStates() {
        const stats = ['totalSent', 'delivered', 'pending', 'failed'];
        stats.forEach(stat => {
            const element = document.getElementById(stat);
            if (element) {
                element.innerHTML = '<div class="loading-spinner"></div>';
            }
        });
    }

    function updateStatsDisplay(data) {
        document.getElementById('totalSent').textContent = new Intl.NumberFormat().format(data.total_sent);
        document.getElementById('delivered').textContent = new Intl.NumberFormat().format(data.delivered);
        document.getElementById('pending').textContent = new Intl.NumberFormat().format(data.pending);
        document.getElementById('failed').textContent = new Intl.NumberFormat().format(data.failed);
    }

    function updateCharts(data) {
        // Update trend chart
        trendChart.data.labels = data.daily.map(item => new Date(item.date).toLocaleDateString());
        trendChart.data.datasets[0].data = data.daily.map(item => item.total_sent);
        trendChart.data.datasets[1].data = data.daily.map(item => item.delivered);
        trendChart.update();

        // Update status chart
        statusChart.data.datasets[0].data = [data.delivered, data.pending, data.failed];
        statusChart.update();

        // Update monthly chart
        monthlyChart.data.datasets[0].data = data.monthly.map(item => item.total_sent);
        monthlyChart.data.datasets[1].data = data.monthly.map(item => item.delivered);
        monthlyChart.update();
    }

    function loadHourlyTrends() {
        fetch('{{ route("api.dashboard.hourly-trends") }}')
            .then(response => response.json())
            .then(data => {
                hourlyChart.data.labels = data.map(item => item.hour);
                hourlyChart.data.datasets[0].data = data.map(item => item.sent);
                hourlyChart.data.datasets[1].data = data.map(item => item.delivered);
                hourlyChart.update();
            })
            .catch(error => {
                console.error('Error loading hourly trends:', error);
            });
    }

    function loadLiveStats() {
        fetch('{{ route("api.dashboard.live-stats") }}')
            .then(response => response.json())
            .then(data => {
                // Update hourly stats
                document.getElementById('hourSent').textContent = new Intl.NumberFormat().format(data.hourly.sent);
                document.getElementById('hourDelivered').textContent = new Intl.NumberFormat().format(data.hourly.delivered);
                document.getElementById('hourSpent').textContent = '£' + new Intl.NumberFormat().format(data.hourly.spent);
                
                // Update today stats
                document.getElementById('todaySent').textContent = new Intl.NumberFormat().format(data.today.sent);
                document.getElementById('todayDeliveryRate').textContent = data.today.delivery_rate + '%';
                
                // Update timestamp
                const timestamp = new Date(data.timestamp);
                document.getElementById('lastUpdated').textContent = timestamp.toLocaleTimeString();
            })
            .catch(error => {
                console.error('Error loading live stats:', error);
            });
    }

    function loadRecentActivity() {
        fetch('{{ route("api.dashboard.recent-activity") }}')
            .then(response => response.json())
            .then(activities => {
                let activityHtml = '';
                activities.forEach(activity => {
                    activityHtml += `
                        <div class="activity-item">
                            <div class="activity-icon ${activity.color}">
                                <i class="material-icons-outlined">${activity.icon}</i>
                            </div>
                            <div class="flex-grow-1">
                                <p class="mb-1">${activity.text}</p>
                                <small class="text-muted">${activity.time}</small>
                            </div>
                        </div>
                    `;
                });
                document.getElementById('recentActivity').innerHTML = activityHtml || '<div class="text-center p-4"><p class="text-muted">No recent activity</p></div>';
            })
            .catch(error => {
                console.error('Error loading recent activity:', error);
                document.getElementById('recentActivity').innerHTML = '<div class="text-center p-4"><p class="text-danger">Error loading activity</p></div>';
            });
    }

    // Load recent activity
    loadRecentActivity();

    // Auto-refresh functionality
    let autoRefreshInterval;
    function startAutoRefresh() {
        autoRefreshInterval = setInterval(() => {
            updateDashboard();
        }, 300000); // Refresh every 5 minutes
    }

    // Start auto-refresh
    startAutoRefresh();

    // Refresh stats function
    window.refreshStats = function() {
        updateDashboard();
    };

    console.log('Smart Dashboard initialized successfully!');
});
</script>
@endpush