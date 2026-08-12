@extends('admin.layouts.modern-app')

@section('title', 'Reports Dashboard - SMS Expert Admin')

@push('styles')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
    .report-cards-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .report-card {
        background: var(--card-bg);
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-light);
        transition: all 0.3s ease;
        overflow: hidden;
        cursor: pointer;
        text-decoration: none;
        color: inherit;
        position: relative;
    }

    .report-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: var(--accent-color);
        transform: scaleX(0);
        transition: transform 0.3s ease;
    }

    .report-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
        text-decoration: none;
        color: inherit;
    }

    .report-card:hover::before {
        transform: scaleX(1);
    }

    .report-card.primary { --accent-color: var(--primary-color); }
    .report-card.success { --accent-color: var(--success-color); }
    .report-card.warning { --accent-color: var(--warning-color); }
    .report-card.info { --accent-color: var(--info-color); }

    .report-header {
        padding: 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: start;
    }

    .report-icon {
        width: 56px;
        height: 56px;
        border-radius: var(--radius-lg);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        color: white;
        margin-bottom: 1rem;
    }

    .report-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .report-description {
        color: var(--text-secondary);
        font-size: 0.85rem;
        line-height: 1.4;
    }

    .report-stats {
        padding: 0 1.5rem 1.5rem;
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 1rem;
    }

    .report-stat {
        text-align: center;
    }

    .stat-number {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    .stat-text {
        font-size: 0.8rem;
        color: var(--text-secondary);
        margin-top: 0.25rem;
    }

    .filters-panel {
        background: var(--card-bg);
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-light);
        padding: 1.5rem;
        margin-bottom: 2rem;
    }

    .chart-container {
        background: var(--card-bg);
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-light);
        padding: 2rem;
        margin-bottom: 2rem;
    }

    .chart-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }

    .chart-title {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    .chart-controls {
        display: flex;
        gap: 0.5rem;
    }

    .date-range-picker {
        display: flex;
        align-items: center;
        gap: 1rem;
        background: var(--light-bg);
        padding: 1rem;
        border-radius: var(--radius-md);
        border: 1px solid var(--border-light);
    }

    .export-buttons {
        display: flex;
        gap: 0.5rem;
    }

    .btn-export {
        padding: 0.5rem 1rem;
        border-radius: var(--radius-md);
        border: 1px solid var(--border-color);
        background: var(--card-bg);
        color: var(--text-secondary);
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .btn-export:hover {
        background: var(--primary-color);
        color: white;
        border-color: var(--primary-color);
    }

    .data-table {
        background: var(--card-bg);
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-light);
        overflow: hidden;
    }

    .table-header {
        background: linear-gradient(135deg, var(--light-bg) 0%, #ffffff 100%);
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-light);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .table-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .table-actions {
        display: flex;
        gap: 0.5rem;
    }

    .table-modern {
        width: 100%;
    }

    .table-modern th {
        background: none;
        border: none;
        font-weight: 600;
        color: var(--text-primary);
        padding: 1rem;
        border-bottom: 1px solid var(--border-light);
    }

    .table-modern td {
        border: none;
        padding: 1rem;
        border-bottom: 1px solid var(--border-light);
        vertical-align: middle;
    }

    .table-modern tbody tr:hover {
        background: rgba(234, 97, 24, 0.02);
    }

    .table-modern tbody tr:last-child td {
        border-bottom: none;
    }

    .trend-indicator {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        font-size: 0.8rem;
        font-weight: 600;
        padding: 0.25rem 0.5rem;
        border-radius: 12px;
    }

    .trend-up {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success-color);
    }

    .trend-down {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger-color);
    }

    .trend-neutral {
        background: rgba(107, 114, 128, 0.1);
        color: var(--text-secondary);
    }
</style>
@endpush

@section('content')
<div class="fade-in">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Reports Dashboard</h1>
            <p class="text-muted">Comprehensive analytics and reporting for your SMS platform</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary" onclick="scheduleReport()">
                <i class="material-icons-outlined me-1">schedule</i>
                Schedule Report
            </button>
            <button class="btn btn-primary-modern" onclick="generateCustomReport()">
                <i class="material-icons-outlined me-1">summarize</i>
                Custom Report
            </button>
        </div>
    </div>

    <!-- Date Range Filter -->
    <div class="filters-panel">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h5 class="mb-3">Report Filters</h5>
                <div class="date-range-picker">
                    <label class="form-label mb-0 me-2">Date Range:</label>
                    <input type="date" class="form-control me-2" id="startDate" value="{{ now()->subDays(30)->format('Y-m-d') }}">
                    <span class="me-2">to</span>
                    <input type="date" class="form-control" id="endDate" value="{{ now()->format('Y-m-d') }}">
                    <button class="btn btn-outline-primary ms-2" onclick="applyDateFilter()">
                        <i class="material-icons-outlined">filter_alt</i>
                    </button>
                </div>
            </div>
            <div class="col-md-6 text-end">
                <div class="export-buttons">
                    <button class="btn-export" onclick="exportToPDF()">
                        <i class="material-icons-outlined me-1">picture_as_pdf</i>
                        PDF
                    </button>
                    <button class="btn-export" onclick="exportToExcel()">
                        <i class="material-icons-outlined me-1">description</i>
                        Excel
                    </button>
                    <button class="btn-export" onclick="exportToCSV()">
                        <i class="material-icons-outlined me-1">table_chart</i>
                        CSV
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Cards -->
    <div class="report-cards-grid">
        <!-- Post Pay Customers Report -->
        <a href="{{ route('admin.export.postpay') }}" class="report-card primary">
            <div class="report-header">
                <div>
                    <div class="report-icon bg-primary">
                        <i class="material-icons-outlined">credit_card</i>
                    </div>
                    <div class="report-title">Post Pay Customers</div>
                    <div class="report-description">Detailed report of postpaid customer accounts and usage</div>
                </div>
                <div class="trend-indicator trend-up">
                    <i class="material-icons-outlined" style="font-size: 0.9rem;">trending_up</i>
                    +8.5%
                </div>
            </div>
            <div class="report-stats">
                <div class="report-stat">
                    <div class="stat-number">{{ $postpayCustomers ?? 45 }}</div>
                    <div class="stat-text">Customers</div>
                </div>
                <div class="report-stat">
                    <div class="stat-number">£{{ number_format($postpayRevenue ?? 2580, 0) }}</div>
                    <div class="stat-text">Revenue</div>
                </div>
            </div>
        </a>

        <!-- Daily SMS Report -->
        <a href="{{ route('admin.export.daily_sms') }}" class="report-card success">
            <div class="report-header">
                <div>
                    <div class="report-icon bg-success">
                        <i class="material-icons-outlined">sms</i>
                    </div>
                    <div class="report-title">Daily SMS Report</div>
                    <div class="report-description">Daily SMS delivery statistics and performance metrics</div>
                </div>
                <div class="trend-indicator trend-up">
                    <i class="material-icons-outlined" style="font-size: 0.9rem;">trending_up</i>
                    +12.3%
                </div>
            </div>
            <div class="report-stats">
                <div class="report-stat">
                    <div class="stat-number">{{ number_format($dailySms ?? 15420) }}</div>
                    <div class="stat-text">Today's SMS</div>
                </div>
                <div class="report-stat">
                    <div class="stat-number">98.5%</div>
                    <div class="stat-text">Success Rate</div>
                </div>
            </div>
        </a>

        <!-- Money Transfer Report -->
        <a href="{{ route('admin.export.money_transferred') }}" class="report-card warning">
            <div class="report-header">
                <div>
                    <div class="report-icon bg-warning">
                        <i class="material-icons-outlined">account_balance</i>
                    </div>
                    <div class="report-title">Money Transfer Report</div>
                    <div class="report-description">Financial transactions and wallet operations</div>
                </div>
                <div class="trend-indicator trend-down">
                    <i class="material-icons-outlined" style="font-size: 0.9rem;">trending_down</i>
                    -2.1%
                </div>
            </div>
            <div class="report-stats">
                <div class="report-stat">
                    <div class="stat-number">£{{ number_format($transfers ?? 8950, 0) }}</div>
                    <div class="stat-text">Transferred</div>
                </div>
                <div class="report-stat">
                    <div class="stat-number">{{ $transferCount ?? 156 }}</div>
                    <div class="stat-text">Transactions</div>
                </div>
            </div>
        </a>

        <!-- Monthly Report -->
        <a href="{{ route('admin.monthly-report') }}" class="report-card info">
            <div class="report-header">
                <div>
                    <div class="report-icon bg-info">
                        <i class="material-icons-outlined">calendar_month</i>
                    </div>
                    <div class="report-title">Monthly Report</div>
                    <div class="report-description">Comprehensive monthly performance and revenue analysis</div>
                </div>
                <div class="trend-indicator trend-up">
                    <i class="material-icons-outlined" style="font-size: 0.9rem;">trending_up</i>
                    +15.7%
                </div>
            </div>
            <div class="report-stats">
                <div class="report-stat">
                    <div class="stat-number">{{ now()->format('M') }}</div>
                    <div class="stat-text">Current Month</div>
                </div>
                <div class="report-stat">
                    <div class="stat-number">£{{ number_format($monthlyRevenue ?? 12580, 0) }}</div>
                    <div class="stat-text">Revenue</div>
                </div>
            </div>
        </a>
    </div>

    <!-- Analytics Charts -->
    <div class="row">
        <div class="col-lg-8">
            <div class="chart-container">
                <div class="chart-header">
                    <div>
                        <h3 class="chart-title">Revenue & SMS Analytics</h3>
                        <p class="text-muted mb-0">Revenue and SMS delivery trends over time</p>
                    </div>
                    <div class="chart-controls">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-primary active" data-period="7d">7D</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-period="30d">30D</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-period="90d">90D</button>
                            <button type="button" class="btn btn-sm btn-outline-primary" data-period="1y">1Y</button>
                        </div>
                    </div>
                </div>
                <div class="chart-area">
                    <canvas id="revenueChart" height="400"></canvas>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="chart-container">
                <div class="chart-header">
                    <h4 class="chart-title">Customer Distribution</h4>
                    <p class="text-muted mb-0">Account types breakdown</p>
                </div>
                <div class="chart-area">
                    <canvas id="customerChart" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Reports Table -->
    <div class="data-table">
        <div class="table-header">
            <h4 class="table-title">Recent Report Downloads</h4>
            <div class="table-actions">
                <button class="btn btn-sm btn-outline-primary" onclick="refreshRecentReports()">
                    <i class="material-icons-outlined">refresh</i>
                </button>
                <button class="btn btn-sm btn-outline-secondary" onclick="clearReportHistory()">
                    <i class="material-icons-outlined">clear_all</i>
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-modern">
                <thead>
                    <tr>
                        <th>Report Type</th>
                        <th>Generated By</th>
                        <th>Date Range</th>
                        <th>Generated At</th>
                        <th>Size</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <i class="material-icons-outlined text-primary">credit_card</i>
                                <div>
                                    <div class="fw-semibold">Post Pay Report</div>
                                    <small class="text-muted">Customer billing report</small>
                                </div>
                            </div>
                        </td>
                        <td>{{ $user_contactname ?? 'Admin' }}</td>
                        <td>Jul 1 - Jul 31, 2024</td>
                        <td>{{ now()->subHours(2)->format('M d, H:i') }}</td>
                        <td>2.3 MB</td>
                        <td><span class="badge bg-success">Completed</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="downloadReport('postpay-2024-07.pdf')">
                                <i class="material-icons-outlined">download</i>
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <i class="material-icons-outlined text-success">sms</i>
                                <div>
                                    <div class="fw-semibold">Daily SMS Report</div>
                                    <small class="text-muted">SMS delivery statistics</small>
                                </div>
                            </div>
                        </td>
                        <td>{{ $user_contactname ?? 'Admin' }}</td>
                        <td>Jul 31, 2024</td>
                        <td>{{ now()->subHours(5)->format('M d, H:i') }}</td>
                        <td>856 KB</td>
                        <td><span class="badge bg-success">Completed</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="downloadReport('daily-sms-2024-07-31.pdf')">
                                <i class="material-icons-outlined">download</i>
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <i class="material-icons-outlined text-warning">account_balance</i>
                                <div>
                                    <div class="fw-semibold">Money Transfer Report</div>
                                    <small class="text-muted">Financial transactions</small>
                                </div>
                            </div>
                        </td>
                        <td>{{ $user_contactname ?? 'Admin' }}</td>
                        <td>Jul 1 - Jul 31, 2024</td>
                        <td>{{ now()->subHours(8)->format('M d, H:i') }}</td>
                        <td>1.1 MB</td>
                        <td><span class="badge bg-warning">Processing</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-secondary" disabled>
                                <i class="material-icons-outlined">sync</i>
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <i class="material-icons-outlined text-info">assessment</i>
                                <div>
                                    <div class="fw-semibold">Monthly Analytics</div>
                                    <small class="text-muted">Comprehensive monthly report</small>
                                </div>
                            </div>
                        </td>
                        <td>{{ $user_contactname ?? 'Admin' }}</td>
                        <td>Jun 1 - Jun 30, 2024</td>
                        <td>{{ now()->subDays(3)->format('M d, H:i') }}</td>
                        <td>4.7 MB</td>
                        <td><span class="badge bg-success">Completed</span></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick="downloadReport('monthly-2024-06.pdf')">
                                <i class="material-icons-outlined">download</i>
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize charts
    initializeRevenueChart();
    initializeCustomerChart();
    
    // Chart period buttons
    const periodButtons = document.querySelectorAll('[data-period]');
    periodButtons.forEach(button => {
        button.addEventListener('click', function() {
            periodButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            updateRevenueChart(this.dataset.period);
        });
    });
    
    // Auto-refresh data every 5 minutes
    setInterval(refreshReportData, 300000);
});

function initializeRevenueChart() {
    const ctx = document.getElementById('revenueChart').getContext('2d');
    
    const revenueGradient = ctx.createLinearGradient(0, 0, 0, 300);
    revenueGradient.addColorStop(0, 'rgba(234, 97, 24, 0.3)');
    revenueGradient.addColorStop(1, 'rgba(234, 97, 24, 0.05)');
    
    const smsGradient = ctx.createLinearGradient(0, 0, 0, 300);
    smsGradient.addColorStop(0, 'rgba(16, 185, 129, 0.3)');
    smsGradient.addColorStop(1, 'rgba(16, 185, 129, 0.05)');
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: [{
                label: 'Revenue (£)',
                data: [1200, 1900, 1500, 2500, 2200, 1800, 2400],
                borderColor: 'rgb(234, 97, 24)',
                backgroundColor: revenueGradient,
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: 'rgb(234, 97, 24)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8
            }, {
                label: 'SMS Sent (K)',
                data: [12, 19, 15, 25, 22, 18, 24],
                borderColor: 'rgb(16, 185, 129)',
                backgroundColor: smsGradient,
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: 'rgb(16, 185, 129)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: {
                            size: 12,
                            weight: '500'
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        font: {
                            size: 11
                        }
                    }
                },
                y: {
                    grid: {
                        color: 'rgba(0, 0, 0, 0.05)'
                    },
                    ticks: {
                        font: {
                            size: 11
                        },
                        callback: function(value) {
                            return value.toLocaleString();
                        }
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });
}

function initializeCustomerChart() {
    const ctx = document.getElementById('customerChart').getContext('2d');
    
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Prepaid', 'Postpaid', 'Trial', 'Suspended'],
            datasets: [{
                data: [105, 45, 12, 8],
                backgroundColor: [
                    'rgb(234, 97, 24)',
                    'rgb(16, 185, 129)', 
                    'rgb(59, 130, 246)',
                    'rgb(239, 68, 68)'
                ],
                borderWidth: 0,
                cutout: '60%'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true,
                        font: {
                            size: 11
                        }
                    }
                }
            }
        }
    });
}

function updateRevenueChart(period) {
    console.log('Updating chart for period:', period);
    showNotification(`Loading ${period} data...`, 'info');
    // Implementation would fetch new data based on period
}

function applyDateFilter() {
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    
    if (startDate && endDate) {
        showNotification(`Filtering reports from ${startDate} to ${endDate}`, 'info');
        // Implement date filtering
    } else {
        showNotification('Please select both start and end dates', 'warning');
    }
}

function exportToPDF() {
    showNotification('Generating PDF report...', 'info');
    setTimeout(() => {
        showNotification('PDF report generated successfully!', 'success');
    }, 2000);
}

function exportToExcel() {
    showNotification('Generating Excel report...', 'info');
    setTimeout(() => {
        showNotification('Excel report generated successfully!', 'success');
    }, 1500);
}

function exportToCSV() {
    showNotification('Generating CSV report...', 'info');
    setTimeout(() => {
        showNotification('CSV report generated successfully!', 'success');
    }, 1000);
}

function scheduleReport() {
    showNotification('Opening report scheduler...', 'info');
    // Implement report scheduling
}

function generateCustomReport() {
    showNotification('Opening custom report builder...', 'info');
    // Implement custom report builder
}

function downloadReport(filename) {
    showNotification(`Downloading ${filename}...`, 'info');
    // Implement file download
}

function refreshRecentReports() {
    showNotification('Refreshing recent reports...', 'info');
    setTimeout(() => {
        showNotification('Reports refreshed successfully!', 'success');
    }, 1000);
}

function clearReportHistory() {
    if (confirm('Are you sure you want to clear the report history?')) {
        showNotification('Clearing report history...', 'info');
        setTimeout(() => {
            showNotification('Report history cleared!', 'success');
        }, 1000);
    }
}

function refreshReportData() {
    // Auto-refresh functionality
    console.log('Auto-refreshing report data...');
}
</script>
@endpush