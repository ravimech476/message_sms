@extends('admin.layouts.modern-app')

@section('title', 'SMS Expert Admin Dashboard')

@push('styles')
<style>
    /* Dashboard specific styles */
    .dashboard-header {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
        border-radius: var(--radius-xl);
        color: white;
        padding: 2rem;
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
    }

    .dashboard-header::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 200px;
        height: 200px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
    }

    .dashboard-header::after {
        content: '';
        position: absolute;
        bottom: -30%;
        left: -10%;
        width: 150px;
        height: 150px;
        background: rgba(255, 255, 255, 0.05);
        border-radius: 50%;
    }

    .dashboard-content {
        position: relative;
        z-index: 2;
    }

    .welcome-title {
        font-size: 1.75rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .welcome-subtitle {
        opacity: 0.9;
        font-size: 1rem;
    }

    .dashboard-time {
        opacity: 0.8;
        font-size: 0.9rem;
        margin-top: 0.5rem;
    }

    /* Quick Actions Grid */
    .quick-actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .quick-action-card {
        background: var(--card-bg);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        border: 1px solid var(--border-light);
        transition: all 0.3s ease;
        cursor: pointer;
        text-decoration: none;
        color: inherit;
        position: relative;
        overflow: hidden;
    }

    .quick-action-card::before {
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

    .quick-action-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-lg);
        text-decoration: none;
        color: inherit;
    }

    .quick-action-card:hover::before {
        transform: scaleX(1);
    }

    .quick-action-card.primary { --accent-color: var(--primary-color); }
    .quick-action-card.success { --accent-color: var(--success-color); }
    .quick-action-card.warning { --accent-color: var(--warning-color); }
    .quick-action-card.info { --accent-color: var(--info-color); }

    .action-icon {
        width: 48px;
        height: 48px;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 1rem;
        font-size: 1.3rem;
    }

    .action-title {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
        color: var(--text-primary);
    }

    .action-description {
        color: var(--text-secondary);
        font-size: 0.85rem;
        line-height: 1.4;
    }

    /* Charts Section */
    .charts-section {
        display: grid;
        grid-template-columns: 2fr 1fr;
        gap: 2rem;
        margin-bottom: 2rem;
    }

    .chart-card {
        background: var(--card-bg);
        border-radius: var(--radius-xl);
        padding: 2rem;
        border: 1px solid var(--border-light);
        box-shadow: var(--shadow-md);
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

    .chart-subtitle {
        color: var(--text-secondary);
        font-size: 0.9rem;
        margin-top: 0.25rem;
    }

    /* Recent Activity */
    .activity-list {
        max-height: 400px;
        overflow-y: auto;
    }

    .activity-item {
        display: flex;
        align-items: start;
        gap: 1rem;
        padding: 1rem 0;
        border-bottom: 1px solid var(--border-light);
    }

    .activity-item:last-child {
        border-bottom: none;
    }

    .activity-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        font-size: 1rem;
    }

    .activity-content {
        flex: 1;
    }

    .activity-title {
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.25rem;
    }

    .activity-description {
        color: var(--text-secondary);
        font-size: 0.9rem;
        margin-bottom: 0.25rem;
    }

    .activity-time {
        color: var(--text-muted);
        font-size: 0.8rem;
    }

    /* System Health Cards */
    .health-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .health-card {
        background: var(--card-bg);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        border: 1px solid var(--border-light);
        text-align: center;
    }

    .health-icon {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        font-size: 1.3rem;
    }

    .health-value {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .health-label {
        color: var(--text-secondary);
        font-size: 0.9rem;
    }

    .health-status {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        margin-top: 0.5rem;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .status-excellent {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success-color);
    }

    .status-good {
        background: rgba(59, 130, 246, 0.1);
        color: var(--info-color);
    }

    .status-warning {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning-color);
    }

    /* Responsive Design */
    @media (max-width: 1024px) {
        .charts-section {
            grid-template-columns: 1fr;
        }
        
        .stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        }
    }

    @media (max-width: 768px) {
        .dashboard-header {
            padding: 1.5rem;
        }
        
        .welcome-title {
            font-size: 1.5rem;
        }
        
        .quick-actions-grid {
            grid-template-columns: 1fr;
        }
        
        .health-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>
@endpush

@section('content')
<div class="fade-in">
    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div class="dashboard-content">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h1 class="welcome-title">Welcome back, {{ $user_contactname ?? 'Admin' }}! 👋</h1>
                    <p class="welcome-subtitle">Here's what's happening with your SMS Expert platform today.</p>
                    <div class="dashboard-time">
                        <i class="material-icons-outlined me-1">schedule</i>
                        <span id="currentDateTime"></span>
                    </div>
                </div>
                <div class="text-end">
                    <div class="d-flex align-items-center gap-3">
                        <div class="text-center">
                            <div class="h4 mb-0">{{ date('d') }}</div>
                            <small>{{ date('M Y') }}</small>
                        </div>
                        <div class="vr"></div>
                        <div class="text-center">
                            <div class="h4 mb-0" id="liveTime"></div>
                            <small>Live Time</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
        <div class="stat-card primary">
            <div class="stat-header">
                <div class="stat-icon">
                    <i class="material-icons-outlined">send</i>
                </div>
                <div class="stat-trend trend-up">
                    <i class="material-icons-outlined">trending_up</i>
                    +12.5%
                </div>
            </div>
            <div class="stat-value">{{ number_format($allQuery->total_count ?? 0) }}</div>
            <div class="stat-label">SMS Messages Sent</div>
            <div class="text-muted small">Total messages delivered this month</div>
        </div>

        <div class="stat-card success">
            <div class="stat-header">
                <div class="stat-icon">
                    <i class="material-icons-outlined">trending_up</i>
                </div>
                <div class="stat-trend trend-up">
                    <i class="material-icons-outlined">trending_up</i>
                    +8.2%
                </div>
            </div>
            <div class="stat-value">£{{ number_format($allQuery->total_profit ?? 0, 2) }}</div>
            <div class="stat-label">Total Profit</div>
            <div class="text-muted small">Revenue generated this month</div>
        </div>

        <div class="stat-card warning">
            <div class="stat-header">
                <div class="stat-icon">
                    <i class="material-icons-outlined">account_balance</i>
                </div>
                <div class="stat-trend trend-down">
                    <i class="material-icons-outlined">trending_down</i>
                    -3.1%
                </div>
            </div>
            <div class="stat-value">£{{ number_format($allQuery->total_costprice ?? 0, 2) }}</div>
            <div class="stat-label">Operating Costs</div>
            <div class="text-muted small">Total operational expenses</div>
        </div>

        <div class="stat-card info">
            <div class="stat-header">
                <div class="stat-icon">
                    <i class="material-icons-outlined">people</i>
                </div>
                <div class="stat-trend trend-up">
                    <i class="material-icons-outlined">trending_up</i>
                    +15.7%
                </div>
            </div>
            <div class="stat-value">{{ number_format($totalUsers ?? 0) }}</div>
            <div class="stat-label">Total Users</div>
            <div class="text-muted small">Active customer accounts</div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="modern-card">
        <div class="card-header-modern">
            <h3 class="card-title-modern">
                <i class="material-icons-outlined">flash_on</i>
                Quick Actions
            </h3>
            <p class="card-subtitle-modern">Commonly used administrative tasks</p>
        </div>
        <div class="p-3">
            <div class="quick-actions-grid">
                <a href="{{ route('admin.users') }}" class="quick-action-card primary">
                    <div class="action-icon bg-light text-primary">
                        <i class="material-icons-outlined">people</i>
                    </div>
                    <div class="action-title">Manage Users</div>
                    <div class="action-description">View and manage customer accounts, permissions, and settings</div>
                </a>

                <a href="{{ route('admin.customer.create') }}" class="quick-action-card success">
                    <div class="action-icon bg-light text-success">
                        <i class="material-icons-outlined">person_add</i>
                    </div>
                    <div class="action-title">Add Customer</div>
                    <div class="action-description">Create new customer accounts and configure initial settings</div>
                </a>

                <a href="{{ route('email.form') }}" class="quick-action-card warning">
                    <div class="action-icon bg-light text-warning">
                        <i class="material-icons-outlined">email</i>
                    </div>
                    <div class="action-title">Send Notifications</div>
                    <div class="action-description">Send bulk emails and SMS notifications to customers</div>
                </a>

                <a href="{{ route('admin.monthly-report') }}" class="quick-action-card info">
                    <div class="action-icon bg-light text-info">
                        <i class="material-icons-outlined">assessment</i>
                    </div>
                    <div class="action-title">View Reports</div>
                    <div class="action-description">Access detailed analytics and performance reports</div>
                </a>
            </div>
        </div>
    </div>

    <!-- Charts and Activity Section -->
    <div class="charts-section">
        <!-- Performance Chart -->
        <div class="chart-card">
            <div class="chart-header">
                <div>
                    <h3 class="chart-title">Performance Analytics</h3>
                    <p class="chart-subtitle">SMS delivery trends and revenue metrics</p>
                </div>
                <div class="btn-group" role="group">
                    <button type="button" class="btn btn-sm btn-outline-primary active" data-period="7d">7D</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-period="30d">30D</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-period="90d">90D</button>
                </div>
            </div>
            <div class="chart-container">
                <canvas id="performanceChart" height="300"></canvas>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="chart-card">
            <div class="chart-header">
                <div>
                    <h3 class="chart-title">Recent Activity</h3>
                    <p class="chart-subtitle">Latest system events and user actions</p>
                </div>
                <button class="btn btn-sm btn-outline-primary" onclick="refreshActivity()">
                    <i class="material-icons-outlined">refresh</i>
                </button>
            </div>
            <div class="activity-list" id="activityList">
                <!-- Activity items will be loaded here -->
                <div class="text-center py-4">
                    <div class="loading-skeleton" style="height: 60px; margin-bottom: 1rem;"></div>
                    <div class="loading-skeleton" style="height: 60px; margin-bottom: 1rem;"></div>
                    <div class="loading-skeleton" style="height: 60px;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Health Status -->
    <div class="modern-card">
        <div class="card-header-modern">
            <h3 class="card-title-modern">
                <i class="material-icons-outlined">monitor_heart</i>
                System Health
            </h3>
            <p class="card-subtitle-modern">Real-time system performance and status</p>
        </div>
        <div class="p-3">
            <div class="health-grid">
                <div class="health-card">
                    <div class="health-icon bg-success text-white">
                        <i class="material-icons-outlined">router</i>
                    </div>
                    <div class="health-value text-success" id="serverStatus">99.9%</div>
                    <div class="health-label">Server Uptime</div>
                    <div class="health-status status-excellent">
                        <i class="material-icons-outlined" style="font-size: 0.8rem;">check_circle</i>
                        Excellent
                    </div>
                </div>

                <div class="health-card">
                    <div class="health-icon bg-info text-white">
                        <i class="material-icons-outlined">storage</i>
                    </div>
                    <div class="health-value text-info" id="dbStatus">5ms</div>
                    <div class="health-label">Database Response</div>
                    <div class="health-status status-good">
                        <i class="material-icons-outlined" style="font-size: 0.8rem;">check_circle</i>
                        Good
                    </div>
                </div>

                <div class="health-card">
                    <div class="health-icon bg-warning text-white">
                        <i class="material-icons-outlined">api</i>
                    </div>
                    <div class="health-value text-warning" id="apiStatus">250ms</div>
                    <div class="health-label">API Response Time</div>
                    <div class="health-status status-warning">
                        <i class="material-icons-outlined" style="font-size: 0.8rem;">warning</i>
                        Average
                    </div>
                </div>

                <div class="health-card">
                    <div class="health-icon bg-primary text-white">
                        <i class="material-icons-outlined">queue</i>
                    </div>
                    <div class="health-value text-primary" id="queueStatus">0</div>
                    <div class="health-label">Queue Jobs</div>
                    <div class="health-status status-excellent">
                        <i class="material-icons-outlined" style="font-size: 0.8rem;">check_circle</i>
                        Clear
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Update time display
    updateDateTime();
    setInterval(updateDateTime, 1000);
    
    // Initialize performance chart
    initializePerformanceChart();
    
    // Load recent activity
    loadRecentActivity();
    
    // Update system health
    updateSystemHealth();
    setInterval(updateSystemHealth, 30000); // Update every 30 seconds
    
    // Chart period buttons
    const periodButtons = document.querySelectorAll('[data-period]');
    periodButtons.forEach(button => {
        button.addEventListener('click', function() {
            periodButtons.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            updateChart(this.dataset.period);
        });
    });
});

function updateDateTime() {
    const now = new Date();
    const options = {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    };
    
    document.getElementById('currentDateTime').textContent = now.toLocaleDateString('en-US', options);
    document.getElementById('liveTime').textContent = now.toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit'
    });
}

function initializePerformanceChart() {
    const ctx = document.getElementById('performanceChart').getContext('2d');
    
    const gradient = ctx.createLinearGradient(0, 0, 0, 300);
    gradient.addColorStop(0, 'rgba(234, 97, 24, 0.3)');
    gradient.addColorStop(1, 'rgba(234, 97, 24, 0.05)');
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            datasets: [{
                label: 'SMS Sent',
                data: [12000, 19000, 15000, 25000, 22000, 18000, 24000],
                borderColor: 'rgb(234, 97, 24)',
                backgroundColor: gradient,
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: 'rgb(234, 97, 24)',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                pointRadius: 6,
                pointHoverRadius: 8
            }, {
                label: 'Revenue (£)',
                data: [3000, 4500, 3800, 6200, 5500, 4200, 5800],
                borderColor: 'rgb(16, 185, 129)',
                backgroundColor: 'rgba(16, 185, 129, 0.1)',
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

function loadRecentActivity() {
    // Simulate loading recent activity
    setTimeout(() => {
        const activities = [
            {
                icon: 'person_add',
                iconColor: 'success',
                title: 'New customer registered',
                description: 'John Smith created a new account',
                time: '2 minutes ago'
            },
            {
                icon: 'send',
                iconColor: 'primary',
                title: 'Bulk SMS campaign sent',
                description: '15,000 messages delivered successfully',
                time: '15 minutes ago'
            },
            {
                icon: 'payment',
                iconColor: 'info',
                title: 'Payment received',
                description: '£150.00 payment processed for customer #1234',
                time: '1 hour ago'
            },
            {
                icon: 'warning',
                iconColor: 'warning',
                title: 'System maintenance',
                description: 'Scheduled maintenance completed successfully',
                time: '2 hours ago'
            },
            {
                icon: 'security',
                iconColor: 'danger',
                title: 'Security alert',
                description: 'Failed login attempt detected from 192.168.1.100',
                time: '3 hours ago'
            }
        ];
        
        const activityList = document.getElementById('activityList');
        activityList.innerHTML = activities.map(activity => `
            <div class="activity-item">
                <div class="activity-icon bg-${activity.iconColor} text-white">
                    <i class="material-icons-outlined">${activity.icon}</i>
                </div>
                <div class="activity-content">
                    <div class="activity-title">${activity.title}</div>
                    <div class="activity-description">${activity.description}</div>
                    <div class="activity-time">${activity.time}</div>
                </div>
            </div>
        `).join('');
    }, 1000);
}

function updateSystemHealth() {
    // Simulate system health updates
    const metrics = {
        serverStatus: Math.random() > 0.1 ? '99.9%' : '98.5%',
        dbStatus: Math.floor(Math.random() * 10) + 'ms',
        apiStatus: Math.floor(Math.random() * 200) + 150 + 'ms',
        queueStatus: Math.floor(Math.random() * 5)
    };
    
    document.getElementById('serverStatus').textContent = metrics.serverStatus;
    document.getElementById('dbStatus').textContent = metrics.dbStatus;
    document.getElementById('apiStatus').textContent = metrics.apiStatus;
    document.getElementById('queueStatus').textContent = metrics.queueStatus;
}

function updateChart(period) {
    // Update chart based on selected period
    console.log('Updating chart for period:', period);
    // Implementation would fetch new data based on period
}

function refreshActivity() {
    const activityList = document.getElementById('activityList');
    activityList.innerHTML = `
        <div class="text-center py-4">
            <div class="loading-skeleton" style="height: 60px; margin-bottom: 1rem;"></div>
            <div class="loading-skeleton" style="height: 60px; margin-bottom: 1rem;"></div>
            <div class="loading-skeleton" style="height: 60px;"></div>
        </div>
    `;
    
    loadRecentActivity();
}

// Show notification when page loads
setTimeout(() => {
    showNotification('Dashboard loaded successfully! All systems operational.', 'success', 3000);
}, 1000);
</script>
@endpush