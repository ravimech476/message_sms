@push('style')
<style>
    .status-card {
        border-radius: 12px;
        border: none;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .status-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(0,0,0,0.12);
    }
    
    .status-card .card-body {
        padding: 1.5rem;
    }
    
    .status-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
    }
    
    .status-value {
        font-size: 2rem;
        font-weight: 700;
        line-height: 1.2;
    }
    
    .status-label {
        color: #6c757d;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .traffic-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 16px;
        padding: 2rem;
    }
    
    .traffic-card .stat-item {
        text-align: center;
        padding: 1rem;
    }
    
    .traffic-card .stat-value {
        font-size: 2.5rem;
        font-weight: 700;
    }
    
    .traffic-card .stat-label {
        font-size: 0.9rem;
        opacity: 0.9;
    }
    
    .api-dashboard-card {
        border-radius: 12px;
        overflow: hidden;
    }
    
    .api-section {
        background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        color: white;
        padding: 1.5rem;
    }
    
    .dashboard-section {
        background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%);
        color: white;
        padding: 1.5rem;
    }
    
    .source-value {
        font-size: 2rem;
        font-weight: 700;
    }
    
    .source-label {
        font-size: 0.85rem;
        opacity: 0.9;
    }
    
    .top-customer-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 1rem;
        border-bottom: 1px solid #f0f0f0;
        transition: background 0.2s ease;
    }
    
    .top-customer-item:hover {
        background: #f8f9fa;
    }
    
    .top-customer-item:last-child {
        border-bottom: none;
    }
    
    .customer-rank {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 600;
        font-size: 0.8rem;
        margin-right: 12px;
    }
    
    .customer-name {
        flex: 1;
        font-weight: 500;
        color: #333;
    }
    
    .customer-count {
        font-weight: 600;
        color: #667eea;
    }
    
    .status-indicator {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 500;
    }
    
    .status-indicator.success {
        background: rgba(25, 135, 84, 0.1);
        color: #198754;
    }
    
    .status-indicator.warning {
        background: rgba(255, 193, 7, 0.1);
        color: #ffc107;
    }
    
    .status-indicator.danger {
        background: rgba(220, 53, 69, 0.1);
        color: #dc3545;
    }
    
    .pulse-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: currentColor;
        margin-right: 6px;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% { opacity: 1; transform: scale(1); }
        50% { opacity: 0.5; transform: scale(1.1); }
        100% { opacity: 1; transform: scale(1); }
    }
    
    .refresh-btn {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border: none;
        color: white;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-size: 0.85rem;
        cursor: pointer;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }
    
    .refresh-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }
    
    .refresh-btn:disabled {
        opacity: 0.7;
        cursor: not-allowed;
    }
    
    .refresh-btn .spinner-border {
        width: 14px;
        height: 14px;
        border-width: 2px;
    }

    .chart-container {
        position: relative;
        height: 250px;
    }
</style>
@endpush

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h5 class="mb-1">
            <i class="material-icons-outlined me-2" style="vertical-align: middle;">analytics</i>
            System Status & SMS Traffic
        </h5>
        <small class="text-muted">Real-time SMS traffic monitoring and statistics</small>
    </div>
    <div class="d-flex align-items-center gap-3">
        <div class="text-muted">
            <i class="material-icons-outlined me-1" style="vertical-align: middle; font-size: 16px;">schedule</i>
            Server Time: <strong id="serverTime">{{ $serverTime ?? now()->format('Y-m-d H:i:s') }}</strong>
        </div>
        <button type="button" class="refresh-btn" id="refreshStatsBtn" onclick="refreshStats()">
            <i class="material-icons-outlined me-1" style="vertical-align: middle; font-size: 16px;">refresh</i>
            Refresh
        </button>
    </div>
</div>

@if(isset($smsStatsError))
<div class="alert alert-warning">
    <i class="material-icons-outlined me-2" style="vertical-align: middle;">warning</i>
    {{ $smsStatsError }}
</div>
@endif

<!-- Real-time Traffic Stats -->
<div class="traffic-card mb-4">
    <div class="row">
        <div class="col-md-3">
            <div class="stat-item">
                <div class="stat-value" id="smsPerSecond">{{ number_format($smsStats['sms_per_second'] ?? 0) }}</div>
                <div class="stat-label">SMS / Second</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-item">
                <div class="stat-value" id="smsPerMinute">{{ number_format($smsStats['sms_per_minute'] ?? 0) }}</div>
                <div class="stat-label">SMS / Minute</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-item">
                <div class="stat-value" id="smsPerHour">{{ number_format($smsStats['sms_per_hour'] ?? 0) }}</div>
                <div class="stat-label">SMS / Hour</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-item">
                <div class="stat-value" id="smsToday">{{ number_format($smsStats['sms_today'] ?? 0) }}</div>
                <div class="stat-label">SMS Today</div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- API vs Dashboard -->
    <div class="col-md-6 mb-4">
        <div class="card api-dashboard-card h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="material-icons-outlined me-2" style="vertical-align: middle;">compare_arrows</i>
                    SMS by Source (Today)
                </h6>
            </div>
            <div class="card-body p-0">
                <div class="row g-0">
                    <div class="col-6">
                        <div class="api-section text-center">
                            <i class="material-icons-outlined mb-2" style="font-size: 32px;">api</i>
                            <div class="source-value" id="smsApi">{{ number_format($smsStats['sms_api'] ?? 0) }}</div>
                            <div class="source-label">API</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="dashboard-section text-center">
                            <i class="material-icons-outlined mb-2" style="font-size: 32px;">dashboard</i>
                            <div class="source-value" id="smsDashboard">{{ number_format($smsStats['sms_dashboard'] ?? 0) }}</div>
                            <div class="source-label">Dashboard/Web</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- SMS Status Breakdown -->
    <div class="col-md-6 mb-4">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="material-icons-outlined me-2" style="vertical-align: middle;">pie_chart</i>
                    SMS Status (Today)
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-4 text-center">
                        <div class="status-card border-0 bg-light p-3 rounded">
                            <div class="status-indicator success mb-2">
                                <span class="pulse-dot"></span> Success
                            </div>
                            <div class="h3 mb-0 text-success" id="smsSuccess">{{ number_format($smsStats['sms_success'] ?? 0) }}</div>
                        </div>
                    </div>
                    <div class="col-4 text-center">
                        <div class="status-card border-0 bg-light p-3 rounded">
                            <div class="status-indicator warning mb-2">
                                <span class="pulse-dot"></span> Pending
                            </div>
                            <div class="h3 mb-0 text-warning" id="smsPending">{{ number_format($smsStats['sms_pending'] ?? 0) }}</div>
                        </div>
                    </div>
                    <div class="col-4 text-center">
                        <div class="status-card border-0 bg-light p-3 rounded">
                            <div class="status-indicator danger mb-2">
                                <span class="pulse-dot"></span> Failed
                            </div>
                            <div class="h3 mb-0 text-danger" id="smsFailed">{{ number_format($smsStats['sms_failed'] ?? 0) }}</div>
                        </div>
                    </div>
                </div>
                
                <!-- Progress bars -->
                <div class="mt-4">
                    @php
                        $total = ($smsStats['sms_success'] ?? 0) + ($smsStats['sms_pending'] ?? 0) + ($smsStats['sms_failed'] ?? 0);
                        $successPercent = $total > 0 ? round(($smsStats['sms_success'] ?? 0) / $total * 100, 1) : 0;
                        $pendingPercent = $total > 0 ? round(($smsStats['sms_pending'] ?? 0) / $total * 100, 1) : 0;
                        $failedPercent = $total > 0 ? round(($smsStats['sms_failed'] ?? 0) / $total * 100, 1) : 0;
                    @endphp
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-success" id="successBar" role="progressbar" style="width: {{ $successPercent }}%"></div>
                        <div class="progress-bar bg-warning" id="pendingBar" role="progressbar" style="width: {{ $pendingPercent }}%"></div>
                        <div class="progress-bar bg-danger" id="failedBar" role="progressbar" style="width: {{ $failedPercent }}%"></div>
                    </div>
                    <div class="d-flex justify-content-between mt-2 small text-muted">
                        <span>Success: <span id="successPercent">{{ $successPercent }}</span>%</span>
                        <span>Pending: <span id="pendingPercent">{{ $pendingPercent }}</span>%</span>
                        <span>Failed: <span id="failedPercent">{{ $failedPercent }}</span>%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Hourly Breakdown Chart -->
    <div class="col-md-8 mb-4">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="material-icons-outlined me-2" style="vertical-align: middle;">show_chart</i>
                    Hourly SMS Traffic (Today)
                </h6>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="hourlyChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Top Customers -->
    <div class="col-md-4 mb-4">
        <div class="card h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="material-icons-outlined me-2" style="vertical-align: middle;">leaderboard</i>
                    Top 5 Customers (Today)
                </h6>
            </div>
            <div class="card-body p-0">
                <div id="topCustomersList">
                    @forelse($smsStats['top_customers'] ?? [] as $index => $customer)
                    <div class="top-customer-item">
                        <span class="customer-rank">{{ $index + 1 }}</span>
                        <span class="customer-name">{{ $customer['name'] }}</span>
                        <span class="customer-count">{{ number_format($customer['count']) }}</span>
                    </div>
                    @empty
                    <div class="text-center py-4 text-muted">
                        <i class="material-icons-outlined" style="font-size: 48px;">inbox</i>
                        <p class="mb-0 mt-2">No SMS sent today</p>
                    </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Additional Stats Cards -->
<div class="row">
    <div class="col-md-3 mb-4">
        <div class="card status-card h-100">
            <div class="card-body d-flex align-items-center">
                <div class="status-icon bg-primary bg-opacity-10 text-primary me-3">
                    <i class="material-icons-outlined">speed</i>
                </div>
                <div>
                    <div class="status-value text-primary" id="avgPerMinute">{{ $smsStats['avg_per_minute'] ?? 0 }}</div>
                    <div class="status-label">Avg SMS/Min (1hr)</div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card status-card h-100">
            <div class="card-body d-flex align-items-center">
                <div class="status-icon bg-success bg-opacity-10 text-success me-3">
                    <i class="material-icons-outlined">check_circle</i>
                </div>
                <div>
                    @php
                        $deliveryRate = $total > 0 ? round(($smsStats['sms_success'] ?? 0) / $total * 100, 1) : 0;
                    @endphp
                    <div class="status-value text-success" id="deliveryRate">{{ $deliveryRate }}%</div>
                    <div class="status-label">Delivery Rate</div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card status-card h-100">
            <div class="card-body d-flex align-items-center">
                <div class="status-icon bg-info bg-opacity-10 text-info me-3">
                    <i class="material-icons-outlined">groups</i>
                </div>
                <div>
                    <div class="status-value text-info" id="activeCustomers">{{ count($smsStats['top_customers'] ?? []) }}</div>
                    <div class="status-label">Active Customers</div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3 mb-4">
        <div class="card status-card h-100">
            <div class="card-body d-flex align-items-center">
                <div class="status-icon bg-warning bg-opacity-10 text-warning me-3">
                    <i class="material-icons-outlined">trending_up</i>
                </div>
                <div>
                    @php
                        $peakHour = !empty($smsStats['hourly_breakdown']) ? array_search(max($smsStats['hourly_breakdown']), $smsStats['hourly_breakdown']) : '--';
                    @endphp
                    <div class="status-value text-warning" id="peakHour">{{ $peakHour !== '--' ? $peakHour . ':00' : '--' }}</div>
                    <div class="status-label">Peak Hour</div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
let hourlyChart = null;

document.addEventListener('DOMContentLoaded', function() {
    initHourlyChart();
    
    // Auto-refresh every 30 seconds
    setInterval(refreshStats, 30000);
});

function initHourlyChart() {
    const ctx = document.getElementById('hourlyChart').getContext('2d');
    
    // Prepare hourly data
    const hourlyData = @json($smsStats['hourly_breakdown'] ?? []);
    const labels = [];
    const data = [];
    
    for (let i = 0; i < 24; i++) {
        const hour = i.toString().padStart(2, '0');
        labels.push(hour + ':00');
        data.push(hourlyData[hour] || 0);
    }
    
    hourlyChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'SMS Count',
                data: data,
                backgroundColor: 'rgba(102, 126, 234, 0.7)',
                borderColor: 'rgba(102, 126, 234, 1)',
                borderWidth: 1,
                borderRadius: 4,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                },
                x: {
                    ticks: {
                        maxRotation: 45,
                        minRotation: 45
                    }
                }
            }
        }
    });
}

function refreshStats() {
    const btn = document.getElementById('refreshStatsBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Refreshing...';
    
    fetch('{{ route("admin.settings.realtime-sms-stats") }}')
        .then(response => response.json())
        .then(data => {
            if (data.smsStats) {
                // Update values
                document.getElementById('smsPerSecond').textContent = numberFormat(data.smsStats.sms_per_second);
                document.getElementById('smsPerMinute').textContent = numberFormat(data.smsStats.sms_per_minute);
                document.getElementById('smsPerHour').textContent = numberFormat(data.smsStats.sms_per_hour);
                document.getElementById('smsToday').textContent = numberFormat(data.smsStats.sms_today);
                document.getElementById('smsApi').textContent = numberFormat(data.smsStats.sms_api);
                document.getElementById('smsDashboard').textContent = numberFormat(data.smsStats.sms_dashboard);
                document.getElementById('smsSuccess').textContent = numberFormat(data.smsStats.sms_success);
                document.getElementById('smsPending').textContent = numberFormat(data.smsStats.sms_pending);
                document.getElementById('smsFailed').textContent = numberFormat(data.smsStats.sms_failed);
                document.getElementById('avgPerMinute').textContent = data.smsStats.avg_per_minute;
                document.getElementById('serverTime').textContent = data.serverTime;
                
                // Update progress bars
                const total = data.smsStats.sms_success + data.smsStats.sms_pending + data.smsStats.sms_failed;
                if (total > 0) {
                    const successPercent = (data.smsStats.sms_success / total * 100).toFixed(1);
                    const pendingPercent = (data.smsStats.sms_pending / total * 100).toFixed(1);
                    const failedPercent = (data.smsStats.sms_failed / total * 100).toFixed(1);
                    
                    document.getElementById('successBar').style.width = successPercent + '%';
                    document.getElementById('pendingBar').style.width = pendingPercent + '%';
                    document.getElementById('failedBar').style.width = failedPercent + '%';
                    document.getElementById('successPercent').textContent = successPercent;
                    document.getElementById('pendingPercent').textContent = pendingPercent;
                    document.getElementById('failedPercent').textContent = failedPercent;
                    document.getElementById('deliveryRate').textContent = successPercent + '%';
                }
                
                // Update chart
                if (hourlyChart && data.smsStats.hourly_breakdown) {
                    const chartData = [];
                    for (let i = 0; i < 24; i++) {
                        const hour = i.toString().padStart(2, '0');
                        chartData.push(data.smsStats.hourly_breakdown[hour] || 0);
                    }
                    hourlyChart.data.datasets[0].data = chartData;
                    hourlyChart.update();
                }
                
                // Update top customers
                if (data.smsStats.top_customers) {
                    let html = '';
                    if (data.smsStats.top_customers.length > 0) {
                        data.smsStats.top_customers.forEach((customer, index) => {
                            html += `
                                <div class="top-customer-item">
                                    <span class="customer-rank">${index + 1}</span>
                                    <span class="customer-name">${customer.name}</span>
                                    <span class="customer-count">${numberFormat(customer.count)}</span>
                                </div>
                            `;
                        });
                    } else {
                        html = `
                            <div class="text-center py-4 text-muted">
                                <i class="material-icons-outlined" style="font-size: 48px;">inbox</i>
                                <p class="mb-0 mt-2">No SMS sent today</p>
                            </div>
                        `;
                    }
                    document.getElementById('topCustomersList').innerHTML = html;
                }
                
                // Find peak hour
                if (data.smsStats.hourly_breakdown) {
                    const breakdown = data.smsStats.hourly_breakdown;
                    let maxCount = 0;
                    let peakHour = '--';
                    Object.keys(breakdown).forEach(hour => {
                        if (breakdown[hour] > maxCount) {
                            maxCount = breakdown[hour];
                            peakHour = hour;
                        }
                    });
                    document.getElementById('peakHour').textContent = peakHour !== '--' ? peakHour + ':00' : '--';
                }
            }
        })
        .catch(error => {
            console.error('Error refreshing stats:', error);
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="material-icons-outlined me-1" style="vertical-align: middle; font-size: 16px;">refresh</i> Refresh';
        });
}

function numberFormat(num) {
    return new Intl.NumberFormat().format(num);
}
</script>
@endpush
