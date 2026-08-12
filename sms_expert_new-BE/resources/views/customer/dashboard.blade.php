@extends('layouts.app')
@section('title', 'SMS Expert Dashboards')

@section('content')
<div class="main-content">
    <!-- Page Header with Date Range Filter -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="display-6 fw-bold mb-1" style="color: #293b50;">Welcome Back, {{ ucfirst($user_contactname) }}!</h1>
            <p class="text-muted mb-0">Monitor your SMS campaigns and performance analytics</p>
        </div>
        <div class="d-flex align-items-center gap-3">
            <div class="modern-card p-3">
                <form method="GET" action="{{ route('dashboard') }}" class="d-flex align-items-center gap-2">
                    <label class="fw-bold text-muted small">Date Range:</label>
                    <input type="date" name="start_date" value="{{ $startDate }}" class="form-control form-control-sm" style="width: 150px;">
                    <span class="text-muted">to</span>
                    <input type="date" name="end_date" value="{{ $endDate }}" class="form-control form-control-sm" style="width: 150px;">
                    <button type="submit" class="btn btn-modern-primary btn-sm">
                        <i class="material-icons-outlined me-1" style="font-size: 16px;">refresh</i>
                        Update
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Key Statistics Cards -->
    <div class="row g-4 mb-4">
        <!-- Wallet Balance -->
        <div class="col-xl-3 col-lg-6">
            <div class="stats-card slide-up" style="animation-delay: 0.1s">
                <div class="d-flex justify-content-between align-items-start">
                    <div class="flex-grow-1">
                        <div class="stats-icon" style="background: linear-gradient(135deg, #ea6118, #293b50);">
                            <i class="material-icons-outlined text-white">account_balance_wallet</i>
                        </div>
                        <h3 class="mb-1 fw-bold">£{{ number_format($remaining_wallet ?? 0, 2) }}</h3>
                        <p class="text-muted mb-0">Available Balance</p>
                        <small class="text-success">
                            <i class="material-icons-outlined" style="font-size: 14px;">trending_up</i>
                            Ready for {{ number_format(($remaining_wallet ?? 0) * 10) }} SMS
                        </small>
                    </div>
                    <a href="{{ route('buysms') }}" class="btn btn-modern-primary btn-sm">
                        <i class="material-icons-outlined" style="font-size: 16px;">add</i>
                    </a>
                </div>
            </div>
        </div>

        <!-- Total SMS Sent -->
        <div class="col-xl-3 col-lg-6">
            <div class="stats-card slide-up" style="animation-delay: 0.2s">
                <div class="stats-icon gradient-card-success">
                    <i class="material-icons-outlined">send</i>
                </div>
                <h3 class="mb-1 fw-bold">{{ number_format($smsStats['total_sent']) }}</h3>
                <p class="text-muted mb-0">Total SMS Sent</p>
                <small class="text-info">
                    <i class="material-icons-outlined" style="font-size: 14px;">today</i>
                    {{ number_format($smsStats['today_sent']) }} sent today
                </small>
            </div>
        </div>

        <!-- Delivered SMS -->
        <div class="col-xl-3 col-lg-6">
            <div class="stats-card slide-up" style="animation-delay: 0.3s">
                <div class="stats-icon gradient-card-success">
                    <i class="material-icons-outlined">check_circle</i>
                </div>
                <h3 class="mb-1 fw-bold">{{ number_format($smsStats['delivered']) }}</h3>
                <p class="text-muted mb-0">SMS Delivered</p>
                <small class="text-success">
                    <i class="material-icons-outlined" style="font-size: 14px;">trending_up</i>
                    {{ $smsStats['delivery_rate'] }}% delivery rate
                </small>
            </div>
        </div>

        <!-- Pending SMS -->
        <div class="col-xl-3 col-lg-6">
            <div class="stats-card slide-up" style="animation-delay: 0.4s">
                <div class="stats-icon gradient-card-warning">
                    <i class="material-icons-outlined">schedule</i>
                </div>
                <h3 class="mb-1 fw-bold">{{ number_format($smsStats['pending']) }}</h3>
                <p class="text-muted mb-0">Pending SMS</p>
                <small class="text-warning">
                    <i class="material-icons-outlined" style="font-size: 14px;">hourglass_empty</i>
                    In queue for delivery
                </small>
            </div>
        </div>
    </div>

    <!-- Financial Overview -->
    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-lg-6">
            <div class="stats-card slide-up" style="animation-delay: 0.5s">
                <div class="stats-icon" style="background: linear-gradient(135deg, #ea6118, #d1520e);">
                    <i class="material-icons-outlined text-white">payments</i>
                </div>
                <h3 class="mb-1 fw-bold">£{{ number_format($smsStats['total_spent'], 2) }}</h3>
                <p class="text-muted mb-0">Total Spent</p>
                <small class="text-primary">
                    £{{ number_format($smsStats['avg_cost_per_sms'], 3) }} per SMS
                </small>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6">
            <div class="stats-card slide-up" style="animation-delay: 0.6s">
                <div class="stats-icon gradient-card-success">
                    <i class="material-icons-outlined">schedule</i>
                </div>
                <h3 class="mb-1 fw-bold">{{ Carbon\Carbon::parse($startDate)->format('M j') }} - {{ Carbon\Carbon::parse($endDate)->format('M j') }}</h3>
                <p class="text-muted mb-0">Date Range</p>
                <small class="text-info">
                    {{ Carbon\Carbon::parse($startDate)->diffInDays(Carbon\Carbon::parse($endDate)) + 1 }} days selected
                </small>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6">
            <div class="stats-card slide-up" style="animation-delay: 0.7s">
                <div class="stats-icon gradient-card-info">
                    <i class="material-icons-outlined">speed</i>
                </div>
                <h3 class="mb-1 fw-bold">{{ $smsStats['total_sent'] > 0 ? number_format($smsStats['total_sent'] / max(1, Carbon\Carbon::parse($startDate)->diffInDays(Carbon\Carbon::parse($endDate)) + 1), 1) : 0 }}</h3>
                <p class="text-muted mb-0">Daily Average</p>
                <small class="text-success">SMS per day</small>
            </div>
        </div>

        <div class="col-xl-3 col-lg-6">
            <div class="stats-card slide-up" style="animation-delay: 0.8s">
                <div class="stats-icon gradient-card-warning">
                    <i class="material-icons-outlined">sms</i>
                </div>
                <h3 class="mb-1 fw-bold">{{ number_format(($remaining_wallet ?? 0) / max(0.01, $smsStats['avg_cost_per_sms'])) }}</h3>
                <p class="text-muted mb-0">Remaining Credits</p>
                <small class="text-warning">
                    Based on current rates
                </small>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row g-4 mb-4">
        <!-- Daily Trends Chart -->
        <div class="col-lg-8">
            <div class="modern-card p-4 slide-up" style="animation-delay: 0.9s">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h5 class="mb-0 fw-bold" style="color: #293b50;">
                        <i class="material-icons-outlined me-2">timeline</i>
                        Daily SMS Activity
                    </h5>
                    <div class="d-flex align-items-center gap-2">
                        <span class="badge" style="background: #ea6118; color: white;">SMS Sent</span>
                        <span class="badge" style="background: #293b50; color: white;">Delivered</span>
                    </div>
                </div>
                <div id="dailyTrendsChart" style="height: 350px;"></div>
            </div>
        </div>

        <!-- Delivery Status Pie Chart -->
        <div class="col-lg-4">
            <div class="modern-card p-4 slide-up" style="animation-delay: 1.0s">
                <h5 class="mb-4 fw-bold" style="color: #293b50;">
                    <i class="material-icons-outlined me-2">pie_chart</i>
                    Delivery Status
                </h5>
                <div id="deliveryStatusChart" style="height: 300px;"></div>
                
                <!-- Status Legend -->
                <div class="mt-3">
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="fw-bold" style="color: #10b981;">{{ number_format($smsStats['delivered']) }}</div>
                            <small class="text-muted">Delivered</small>
                        </div>
                        <div class="col-4">
                            <div class="fw-bold" style="color: #f59e0b;">{{ number_format($smsStats['pending']) }}</div>
                            <small class="text-muted">Pending</small>
                        </div>
                        <div class="col-4">
                            <div class="fw-bold" style="color: #ef4444;">{{ number_format($smsStats['failed']) }}</div>
                            <small class="text-muted">Failed</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Monthly Trends and Quick Actions -->
    <div class="row g-4 mb-4">
        <!-- Monthly Overview Chart -->
        <div class="col-lg-8">
            <div class="modern-card p-4 slide-up" style="animation-delay: 1.1s">
                <h5 class="mb-4 fw-bold" style="color: #293b50;">
                    <i class="material-icons-outlined me-2">bar_chart</i>
                    Monthly Overview ({{ Carbon\Carbon::now()->year }})
                </h5>
                <div id="monthlyTrendsChart" style="height: 300px;"></div>
            </div>
        </div>

        <!-- Quick Actions & Today's Summary -->
        <div class="col-lg-4">
            <!-- Today's Summary -->
            <div class="modern-card p-4 mb-4 slide-up" style="animation-delay: 1.2s">
                <h6 class="mb-3 fw-bold" style="color: #293b50;">
                    <i class="material-icons-outlined me-2">today</i>
                    Today's Activity
                </h6>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted">SMS Sent</span>
                    <span class="fw-bold">{{ number_format($smsStats['today_sent']) }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="text-muted">Delivered</span>
                    <span class="fw-bold text-success">{{ number_format($smsStats['today_delivered']) }}</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <span class="text-muted">Amount Spent</span>
                    <span class="fw-bold">£{{ number_format($smsStats['today_spent'], 2) }}</span>
                </div>
                <div class="progress mb-2" style="height: 8px;">
                    <div class="progress-bar" style="background: linear-gradient(135deg, #ea6118, #293b50); width: {{ $smsStats['today_sent'] > 0 ? ($smsStats['today_delivered'] / $smsStats['today_sent']) * 100 : 0 }}%"></div>
                </div>
                <small class="text-muted">Today's delivery rate: {{ $smsStats['today_sent'] > 0 ? number_format(($smsStats['today_delivered'] / $smsStats['today_sent']) * 100, 1) : 0 }}%</small>
            </div>

            <!-- Quick Actions -->
            <div class="modern-card p-4 slide-up" style="animation-delay: 1.3s">
                <h6 class="mb-3 fw-bold" style="color: #293b50;">
                    <i class="material-icons-outlined me-2">flash_on</i>
                    Quick Actions
                </h6>
                <div class="d-grid gap-2">
                    <a href="{{ route('sendsms') }}" class="btn btn-modern-primary">
                        <i class="material-icons-outlined me-2">send</i>
                        Send New SMS
                    </a>
                    <a href="{{ route('sentsms') }}" class="btn btn-outline-modern">
                        <i class="material-icons-outlined me-2">history</i>
                        View SMS History
                    </a>
                    <a href="{{ route('numbers.index') }}" class="btn btn-outline-modern">
                        <i class="material-icons-outlined me-2">contacts</i>
                        Manage Contacts
                    </a>
                    <a href="{{ route('buysms') }}" class="btn btn-outline-modern">
                        <i class="material-icons-outlined me-2">account_balance_wallet</i>
                        Top Up Balance
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Insights -->
    <div class="row g-4">
        <div class="col-12">
            <div class="modern-card p-4 slide-up" style="animation-delay: 1.4s">
                <h5 class="mb-4 fw-bold" style="color: #293b50;">
                    <i class="material-icons-outlined me-2">insights</i>
                    Performance Insights
                </h5>
                <div class="row">
                    <div class="col-md-3 text-center mb-3">
                        <div class="fw-bold fs-4" style="color: #ea6118;">{{ $smsStats['delivery_rate'] }}%</div>
                        <small class="text-muted">Delivery Success Rate</small>
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar" style="background: #ea6118; width: {{ $smsStats['delivery_rate'] }}%"></div>
                        </div>
                    </div>
                    <div class="col-md-3 text-center mb-3">
                        <div class="fw-bold fs-4" style="color: #293b50;">£{{ number_format($smsStats['avg_cost_per_sms'], 2) }}</div>
                        <small class="text-muted">Average Cost per SMS</small>
                    </div>
                    <div class="col-md-3 text-center mb-3">
                        <div class="fw-bold fs-4" style="color: #10b981;">{{ $smsStats['total_sent'] > 0 ? number_format($smsStats['total_sent'] / max(1, Carbon\Carbon::parse($startDate)->diffInDays(Carbon\Carbon::parse($endDate)) + 1), 1) : 0 }}</div>
                        <small class="text-muted">Daily Average SMS</small>
                    </div>
                    <div class="col-md-3 text-center mb-3">
                        <div class="fw-bold fs-4" style="color: #f59e0b;">{{ number_format($smsStats['failed']) }}</div>
                        <small class="text-muted">Failed Messages</small>
                        <div class="progress mt-2" style="height: 4px;">
                            <div class="progress-bar bg-warning" style="width: {{ $smsStats['total_sent'] > 0 ? ($smsStats['failed'] / $smsStats['total_sent']) * 100 : 0 }}%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('js')
<script src="{{ asset('assets/plugins/apexchart/apexcharts.min.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar color theme
    const primaryColor = '#ea6118';
    const secondaryColor = '#293b50';
    const successColor = '#10b981';
    const warningColor = '#f59e0b';
    const dangerColor = '#ef4444';

    // Daily Trends Chart
    const dailyData = @json($dailyTrends);
    const dailyDates = dailyData.map(item => item.date);
    const dailySent = dailyData.map(item => parseInt(item.total_sent));
    const dailyDelivered = dailyData.map(item => parseInt(item.delivered));

    const dailyTrendsOptions = {
        series: [
            { name: 'SMS Sent', data: dailySent },
            { name: 'Delivered', data: dailyDelivered }
        ],
        chart: {
            type: 'area',
            height: 350,
            toolbar: { show: false },
            foreColor: '#64748b'
        },
        colors: [primaryColor, secondaryColor],
        fill: {
            type: 'gradient',
            gradient: {
                shade: 'light',
                type: 'vertical',
                shadeIntensity: 0.1,
                opacityFrom: 0.8,
                opacityTo: 0.1
            }
        },
        stroke: { curve: 'smooth', width: 3 },
        xaxis: { 
            categories: dailyDates,
            labels: {
                formatter: function(value) {
                    return new Date(value).toLocaleDateString('en-GB', { 
                        day: '2-digit', 
                        month: 'short' 
                    });
                }
            }
        },
        yaxis: { 
            title: { text: 'Number of SMS' },
            labels: {
                formatter: function(value) {
                    return Math.round(value).toLocaleString();
                }
            }
        },
        tooltip: {
            theme: 'light',
            x: {
                formatter: function(value) {
                    return new Date(dailyDates[value - 1]).toLocaleDateString('en-GB');
                }
            }
        },
        legend: { position: 'top' },
        grid: {
            borderColor: '#e2e8f0',
            strokeDashArray: 3
        }
    };
    new ApexCharts(document.querySelector("#dailyTrendsChart"), dailyTrendsOptions).render();

    // Delivery Status Pie Chart
    const deliveryStatusOptions = {
        series: [{{ $smsStats['delivered'] }}, {{ $smsStats['pending'] }}, {{ $smsStats['failed'] }}],
        chart: {
            type: 'donut',
            height: 300
        },
        labels: ['Delivered', 'Pending', 'Failed'],
        colors: [successColor, warningColor, dangerColor],
        stroke: { width: 0 },
        plotOptions: {
            pie: {
                donut: {
                    size: '70%',
                    labels: {
                        show: true,
                        total: {
                            show: true,
                            label: 'Total SMS',
                            formatter: function () {
                                return {{ $smsStats['total_sent'] }};
                            }
                        }
                    }
                }
            }
        },
        legend: { show: false },
        tooltip: {
            y: {
                formatter: function(value) {
                    return value.toLocaleString() + ' SMS';
                }
            }
        }
    };
    new ApexCharts(document.querySelector("#deliveryStatusChart"), deliveryStatusOptions).render();

    // Monthly Trends Chart
    const monthlyData = @json($monthlyTrends);
    const monthlyMonths = monthlyData.map(item => item.month);
    const monthlySent = monthlyData.map(item => parseInt(item.total_sent));
    const monthlyDelivered = monthlyData.map(item => parseInt(item.delivered));

    const monthlyTrendsOptions = {
        series: [
            { name: 'SMS Sent', data: monthlySent },
            { name: 'Delivered', data: monthlyDelivered }
        ],
        chart: {
            type: 'bar',
            height: 300,
            toolbar: { show: false },
            foreColor: '#64748b'
        },
        colors: [primaryColor, secondaryColor],
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '60%',
                borderRadius: 4
            }
        },
        xaxis: { categories: monthlyMonths },
        yaxis: { 
            title: { text: 'Number of SMS' },
            labels: {
                formatter: function(value) {
                    return Math.round(value).toLocaleString();
                }
            }
        },
        legend: { position: 'top' },
        grid: {
            borderColor: '#e2e8f0',
            strokeDashArray: 3
        },
        tooltip: {
            theme: 'light',
            y: {
                formatter: function(value) {
                    return value.toLocaleString() + ' SMS';
                }
            }
        }
    };
    new ApexCharts(document.querySelector("#monthlyTrendsChart"), monthlyTrendsOptions).render();

    // Auto-refresh data every 5 minutes
    setInterval(function() {
        if (document.visibilityState === 'visible') {
            location.reload();
        }
    }, 300000); // 5 minutes
});
</script>
@endpush
@endsection