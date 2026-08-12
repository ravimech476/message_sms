@extends('admin.layouts.modern-app')

@section('title', 'System Status - SMS Expert Admin')

@push('styles')
<style>
    .status-overview {
        background: linear-gradient(135deg, var(--success-color) 0%, #34d399 100%);
        border-radius: var(--radius-xl);
        color: white;
        padding: 2rem;
        margin-bottom: 2rem;
        position: relative;
        overflow: hidden;
    }

    .status-overview.warning {
        background: linear-gradient(135deg, var(--warning-color) 0%, #fbbf24 100%);
    }

    .status-overview.error {
        background: linear-gradient(135deg, var(--danger-color) 0%, #f87171 100%);
    }

    .status-overview::before {
        content: '';
        position: absolute;
        top: -50%;
        right: -20%;
        width: 200px;
        height: 200px;
        background: rgba(255, 255, 255, 0.1);
        border-radius: 50%;
    }

    .status-content {
        position: relative;
        z-index: 2;
    }

    .system-metrics {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .metric-card {
        background: var(--card-bg);
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-light);
        padding: 1.5rem;
        position: relative;
        overflow: hidden;
    }

    .metric-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: var(--metric-color);
    }

    .metric-card.excellent { --metric-color: var(--success-color); }
    .metric-card.good { --metric-color: var(--info-color); }
    .metric-card.warning { --metric-color: var(--warning-color); }
    .metric-card.critical { --metric-color: var(--danger-color); }

    .metric-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 1rem;
    }

    .metric-icon {
        width: 48px;
        height: 48px;
        border-radius: var(--radius-md);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 1.3rem;
    }

    .metric-status {
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .metric-value {
        font-size: 2rem;
        font-weight: 800;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .metric-label {
        color: var(--text-secondary);
        font-size: 0.9rem;
        margin-bottom: 1rem;
    }

    .metric-progress {
        width: 100%;
        height: 6px;
        background: var(--border-light);
        border-radius: 3px;
        overflow: hidden;
    }

    .progress-bar {
        height: 100%;
        border-radius: 3px;
        transition: width 0.3s ease;
    }

    .service-status-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .service-card {
        background: var(--card-bg);
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-light);
        padding: 1.5rem;
    }

    .service-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
    }

    .service-info h5 {
        margin: 0;
        color: var(--text-primary);
    }

    .service-description {
        color: var(--text-secondary);
        font-size: 0.85rem;
        margin-top: 0.25rem;
    }

    .service-status {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .service-metrics {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
        margin-top: 1rem;
    }

    .service-metric {
        text-align: center;
        padding: 0.75rem;
        background: var(--light-bg);
        border-radius: var(--radius-md);
    }

    .service-metric-value {
        font-size: 1.1rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    .service-metric-label {
        font-size: 0.8rem;
        color: var(--text-secondary);
        margin-top: 0.25rem;
    }

    .log-viewer {
        background: var(--card-bg);
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-light);
        overflow: hidden;
    }

    .log-header {
        background: linear-gradient(135deg, var(--light-bg) 0%, #ffffff 100%);
        padding: 1.5rem;
        border-bottom: 1px solid var(--border-light);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .log-content {
        max-height: 400px;
        overflow-y: auto;
        padding: 1rem;
        background: #1e1e1e;
        color: #e0e0e0;
        font-family: 'Courier New', monospace;
        font-size: 0.85rem;
    }

    .log-line {
        margin-bottom: 0.25rem;
        padding: 0.25rem 0.5rem;
        border-radius: 4px;
    }

    .log-line.info { background: rgba(59, 130, 246, 0.1); }
    .log-line.warning { background: rgba(245, 158, 11, 0.1); }
    .log-line.error { background: rgba(239, 68, 68, 0.1); }
    .log-line.success { background: rgba(16, 185, 129, 0.1); }

    .log-timestamp {
        color: #9ca3af;
        margin-right: 1rem;
    }

    .log-level {
        display: inline-block;
        width: 60px;
        text-align: center;
        margin-right: 1rem;
        font-weight: 600;
    }

    .log-level.info { color: #60a5fa; }
    .log-level.warning { color: #fbbf24; }
    .log-level.error { color: #f87171; }
    .log-level.success { color: #34d399; }

    .uptime-chart {
        background: var(--card-bg);
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-light);
        padding: 2rem;
    }

    .uptime-grid {
        display: grid;
        grid-template-columns: repeat(24, 1fr);
        gap: 2px;
        margin: 1rem 0;
    }

    .uptime-hour {
        height: 20px;
        border-radius: 2px;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .uptime-hour.up {
        background: var(--success-color);
    }

    .uptime-hour.down {
        background: var(--danger-color);
    }

    .uptime-hour.partial {
        background: var(--warning-color);
    }

    .uptime-hour:hover {
        transform: scale(1.2);
        z-index: 10;
        position: relative;
    }

    .uptime-legend {
        display: flex;
        justify-content: center;
        gap: 2rem;
        margin-top: 1rem;
        font-size: 0.85rem;
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .legend-color {
        width: 12px;
        height: 12px;
        border-radius: 2px;
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
</style>
@endpush

@section('content')
<div class="fade-in">
    <!-- Status Overview -->
    <div class="status-overview" id="statusOverview">
        <div class="status-content">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h1 class="h3 mb-2">
                        <i class="material-icons-outlined me-2">check_circle</i>
                        All Systems Operational
                    </h1>
                    <p class="mb-0 opacity-90">All services are running smoothly. Last checked: <span id="lastChecked">{{ now()->format('M d, Y H:i:s') }}</span></p>
                </div>
                <div class="text-end">
                    <div class="h2 mb-0" id="overallUptime">99.97%</div>
                    <small class="opacity-75">Overall Uptime</small>
                </div>
            </div>
        </div>
    </div>

    <!-- System Metrics -->
    <div class="system-metrics">
        <!-- Server Performance -->
        <div class="metric-card excellent">
            <div class="metric-header">
                <div class="metric-icon bg-success">
                    <i class="material-icons-outlined">dns</i>
                </div>
                <div class="metric-status status-excellent">
                    <i class="material-icons-outlined" style="font-size: 0.8rem;">check_circle</i>
                    Excellent
                </div>
            </div>
            <div class="metric-value" id="serverPerformance">98.5%</div>
            <div class="metric-label">Server Performance</div>
            <div class="metric-progress">
                <div class="progress-bar bg-success" style="width: 98.5%;"></div>
            </div>
        </div>

        <!-- Database Performance -->
        <div class="metric-card good">
            <div class="metric-header">
                <div class="metric-icon bg-info">
                    <i class="material-icons-outlined">storage</i>
                </div>
                <div class="metric-status status-good">
                    <i class="material-icons-outlined" style="font-size: 0.8rem;">check_circle</i>
                    Good
                </div>
            </div>
            <div class="metric-value" id="dbPerformance">5.2ms</div>
            <div class="metric-label">Database Response Time</div>
            <div class="metric-progress">
                <div class="progress-bar bg-info" style="width: 85%;"></div>
            </div>
        </div>

        <!-- Memory Usage -->
        <div class="metric-card warning">
            <div class="metric-header">
                <div class="metric-icon bg-warning">
                    <i class="material-icons-outlined">memory</i>
                </div>
                <div class="metric-status status-warning">
                    <i class="material-icons-outlined" style="font-size: 0.8rem;">warning</i>
                    Warning
                </div>
            </div>
            <div class="metric-value" id="memoryUsage">78%</div>
            <div class="metric-label">Memory Usage</div>
            <div class="metric-progress">
                <div class="progress-bar bg-warning" style="width: 78%;"></div>
            </div>
        </div>

        <!-- Queue Processing -->
        <div class="metric-card excellent">
            <div class="metric-header">
                <div class="metric-icon bg-primary">
                    <i class="material-icons-outlined">queue</i>
                </div>
                <div class="metric-status status-excellent">
                    <i class="material-icons-outlined" style="font-size: 0.8rem;">check_circle</i>
                    Clear
                </div>
            </div>
            <div class="metric-value" id="queueJobs">0</div>
            <div class="metric-label">Pending Queue Jobs</div>
            <div class="metric-progress">
                <div class="progress-bar bg-success" style="width: 100%;"></div>
            </div>
        </div>
    </div>

    <!-- Service Status -->
    <div class="row">
        <div class="col-lg-8">
            <div class="service-status-grid">
                <!-- SMPP Service -->
                <div class="service-card">
                    <div class="service-header">
                        <div class="service-info">
                            <h5>SMPP Gateway Service</h5>
                            <div class="service-description">SMS message routing and delivery</div>
                        </div>
                        <div class="service-status status-excellent">
                            <div class="status-indicator bg-success"></div>
                            Online
                        </div>
                    </div>
                    <div class="service-metrics">
                        <div class="service-metric">
                            <div class="service-metric-value">99.9%</div>
                            <div class="service-metric-label">Uptime</div>
                        </div>
                        <div class="service-metric">
                            <div class="service-metric-value">3</div>
                            <div class="service-metric-label">Connections</div>
                        </div>
                        <div class="service-metric">
                            <div class="service-metric-value">250ms</div>
                            <div class="service-metric-label">Response</div>
                        </div>
                    </div>
                </div>

                <!-- Email Service -->
                <div class="service-card">
                    <div class="service-header">
                        <div class="service-info">
                            <h5>Email Service</h5>
                            <div class="service-description">SMTP email delivery system</div>
                        </div>
                        <div class="service-status status-excellent">
                            <div class="status-indicator bg-success"></div>
                            Connected
                        </div>
                    </div>
                    <div class="service-metrics">
                        <div class="service-metric">
                            <div class="service-metric-value">100%</div>
                            <div class="service-metric-label">Uptime</div>
                        </div>
                        <div class="service-metric">
                            <div class="service-metric-value">45</div>
                            <div class="service-metric-label">Sent Today</div>
                        </div>
                        <div class="service-metric">
                            <div class="service-metric-value">1.2s</div>
                            <div class="service-metric-label">Avg Delivery</div>
                        </div>
                    </div>
                </div>

                <!-- API Service -->
                <div class="service-card">
                    <div class="service-header">
                        <div class="service-info">
                            <h5>API Service</h5>
                            <div class="service-description">REST API endpoints</div>
                        </div>
                        <div class="service-status status-good">
                            <div class="status-indicator bg-info"></div>
                            Active
                        </div>
                    </div>
                    <div class="service-metrics">
                        <div class="service-metric">
                            <div class="service-metric-value">99.5%</div>
                            <div class="service-metric-label">Uptime</div>
                        </div>
                        <div class="service-metric">
                            <div class="service-metric-value">1.2K</div>
                            <div class="service-metric-label">Requests/hr</div>
                        </div>
                        <div class="service-metric">
                            <div class="service-metric-value">180ms</div>
                            <div class="service-metric-label">Response</div>
                        </div>
                    </div>
                </div>

                <!-- Database Service -->
                <div class="service-card">
                    <div class="service-header">
                        <div class="service-info">
                            <h5>Database Service</h5>
                            <div class="service-description">MySQL database server</div>
                        </div>
                        <div class="service-status status-excellent">
                            <div class="status-indicator bg-success"></div>
                            Online
                        </div>
                    </div>
                    <div class="service-metrics">
                        <div class="service-metric">
                            <div class="service-metric-value">100%</div>
                            <div class="service-metric-label">Uptime</div>
                        </div>
                        <div class="service-metric">
                            <div class="service-metric-value">15</div>
                            <div class="service-metric-label">Connections</div>
                        </div>
                        <div class="service-metric">
                            <div class="service-metric-value">5ms</div>
                            <div class="service-metric-label">Query Time</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- 24-Hour Uptime Chart -->
            <div class="uptime-chart">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="mb-0">24-Hour Uptime</h5>
                    <span class="badge bg-success">99.97%</span>
                </div>
                <div class="uptime-grid" id="uptimeGrid">
                    <!-- Uptime blocks will be generated here -->
                </div>
                <div class="uptime-legend">
                    <div class="legend-item">
                        <div class="legend-color bg-success"></div>
                        <span>Up</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color bg-warning"></div>
                        <span>Partial</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-color bg-danger"></div>
                        <span>Down</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Logs -->
    <div class="log-viewer">
        <div class="log-header">
            <h4 class="mb-0">Real-time System Logs</h4>
            <div class="d-flex gap-2">
                <select class="form-select form-select-sm" id="logLevel">
                    <option value="all">All Levels</option>
                    <option value="info">Info</option>
                    <option value="warning">Warning</option>
                    <option value="error">Error</option>
                </select>
                <button class="btn btn-sm btn-outline-primary" onclick="clearLogs()">
                    <i class="material-icons-outlined">clear_all</i>
                </button>
                <button class="btn btn-sm btn-outline-secondary" onclick="pauseLogs()" id="pauseBtn">
                    <i class="material-icons-outlined">pause</i>
                </button>
            </div>
        </div>
        <div class="log-content" id="logContent">
            <!-- Logs will be populated here -->
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize uptime chart
    generateUptimeChart();
    
    // Initialize real-time logs
    initializeLogs();
    
    // Update metrics every 30 seconds
    updateSystemMetrics();
    setInterval(updateSystemMetrics, 30000);
    
    // Update logs every 5 seconds
    setInterval(function() {
        if (!document.getElementById('pauseBtn').classList.contains('paused')) {
            addNewLogEntry();
        }
    }, 5000);
    
    // Update last checked time every second
    setInterval(updateLastChecked, 1000);
});

function generateUptimeChart() {
    const uptimeGrid = document.getElementById('uptimeGrid');
    const currentHour = new Date().getHours();
    
    for (let i = 0; i < 24; i++) {
        const hourBlock = document.createElement('div');
        hourBlock.className = 'uptime-hour';
        
        // Simulate uptime data (mostly up, occasionally partial)
        const random = Math.random();
        if (random > 0.95) {
            hourBlock.classList.add('partial');
        } else {
            hourBlock.classList.add('up');
        }
        
        hourBlock.title = `Hour ${i}:00 - ${i + 1}:00`;
        hourBlock.addEventListener('mouseenter', function() {
            showHourDetails(i);
        });
        
        uptimeGrid.appendChild(hourBlock);
    }
}

function initializeLogs() {
    const logContent = document.getElementById('logContent');
    const initialLogs = [
        { time: '23:45:12', level: 'info', message: 'SMS gateway connection established successfully' },
        { time: '23:44:58', level: 'info', message: 'Database connection pool initialized' },
        { time: '23:44:45', level: 'success', message: 'System health check completed - all services operational' },
        { time: '23:44:30', level: 'info', message: 'SMTP service connected to mail.google.com:587' },
        { time: '23:44:15', level: 'warning', message: 'Memory usage reached 75% threshold' },
        { time: '23:44:02', level: 'info', message: 'API rate limiter reset for new hour' },
        { time: '23:43:48', level: 'info', message: 'Backup process completed successfully' },
        { time: '23:43:35', level: 'success', message: 'SMS delivery confirmation received for batch #1234' }
    ];
    
    initialLogs.forEach(log => {
        addLogEntry(log.time, log.level, log.message);
    });
}

function addLogEntry(timestamp, level, message) {
    const logContent = document.getElementById('logContent');
    const logLine = document.createElement('div');
    logLine.className = `log-line ${level}`;
    
    logLine.innerHTML = `
        <span class="log-timestamp">${timestamp}</span>
        <span class="log-level ${level}">${level.toUpperCase()}</span>
        <span class="log-message">${message}</span>
    `;
    
    logContent.insertBefore(logLine, logContent.firstChild);
    
    // Keep only last 50 log entries
    while (logContent.children.length > 50) {
        logContent.removeChild(logContent.lastChild);
    }
}

function addNewLogEntry() {
    const messages = [
        { level: 'info', message: 'Health check completed - all systems operational' },
        { level: 'info', message: 'SMS batch processed successfully' },
        { level: 'success', message: 'Email delivery confirmation received' },
        { level: 'info', message: 'Database optimization task completed' },
        { level: 'warning', message: 'High memory usage detected' },
        { level: 'info', message: 'User authentication successful' },
        { level: 'info', message: 'API request processed' },
        { level: 'success', message: 'Backup verification completed' }
    ];
    
    const randomMessage = messages[Math.floor(Math.random() * messages.length)];
    const currentTime = new Date().toLocaleTimeString('en-GB', { hour12: false });
    
    addLogEntry(currentTime, randomMessage.level, randomMessage.message);
}

function updateSystemMetrics() {
    // Simulate real-time metric updates
    const metrics = {
        serverPerformance: (Math.random() * 5 + 95).toFixed(1) + '%',
        dbPerformance: (Math.random() * 3 + 3).toFixed(1) + 'ms',
        memoryUsage: (Math.random() * 20 + 70).toFixed(0) + '%',
        queueJobs: Math.floor(Math.random() * 3)
    };
    
    document.getElementById('serverPerformance').textContent = metrics.serverPerformance;
    document.getElementById('dbPerformance').textContent = metrics.dbPerformance;
    document.getElementById('memoryUsage').textContent = metrics.memoryUsage;
    document.getElementById('queueJobs').textContent = metrics.queueJobs;
    
    // Update progress bars
    const serverProgress = parseFloat(metrics.serverPerformance);
    const memoryProgress = parseFloat(metrics.memoryUsage);
    
    document.querySelector('.metric-card.excellent .progress-bar').style.width = serverProgress + '%';
    document.querySelector('.metric-card.warning .progress-bar').style.width = memoryProgress + '%';
    
    // Update overall status based on metrics
    updateOverallStatus(serverProgress, memoryProgress);
}

function updateOverallStatus(serverPerf, memoryUsage) {
    const statusOverview = document.getElementById('statusOverview');
    const overallUptime = document.getElementById('overallUptime');
    
    if (serverPerf > 95 && memoryUsage < 80) {
        statusOverview.className = 'status-overview';
        statusOverview.querySelector('h1').innerHTML = '<i class="material-icons-outlined me-2">check_circle</i>All Systems Operational';
        overallUptime.textContent = '99.97%';
    } else if (serverPerf > 90 && memoryUsage < 90) {
        statusOverview.className = 'status-overview warning';
        statusOverview.querySelector('h1').innerHTML = '<i class="material-icons-outlined me-2">warning</i>Performance Warning';
        overallUptime.textContent = '98.5%';
    } else {
        statusOverview.className = 'status-overview error';
        statusOverview.querySelector('h1').innerHTML = '<i class="material-icons-outlined me-2">error</i>System Issues Detected';
        overallUptime.textContent = '95.2%';
    }
}

function updateLastChecked() {
    document.getElementById('lastChecked').textContent = new Date().toLocaleString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
}

function showHourDetails(hour) {
    const tooltip = `Hour ${hour}:00 - ${hour + 1}:00\nUptime: 100%\nIncidents: 0`;
    // You could implement a proper tooltip here
    console.log(tooltip);
}

function clearLogs() {
    if (confirm('Are you sure you want to clear the log display?')) {
        document.getElementById('logContent').innerHTML = '';
        showNotification('Log display cleared', 'info');
    }
}

function pauseLogs() {
    const pauseBtn = document.getElementById('pauseBtn');
    const isPaused = pauseBtn.classList.toggle('paused');
    
    if (isPaused) {
        pauseBtn.innerHTML = '<i class="material-icons-outlined">play_arrow</i>';
        pauseBtn.classList.add('btn-warning');
        pauseBtn.classList.remove('btn-outline-secondary');
        showNotification('Log updates paused', 'warning');
    } else {
        pauseBtn.innerHTML = '<i class="material-icons-outlined">pause</i>';
        pauseBtn.classList.remove('btn-warning');
        pauseBtn.classList.add('btn-outline-secondary');
        showNotification('Log updates resumed', 'info');
    }
}

// Filter logs by level
document.getElementById('logLevel').addEventListener('change', function() {
    const selectedLevel = this.value;
    const logLines = document.querySelectorAll('.log-line');
    
    logLines.forEach(line => {
        if (selectedLevel === 'all' || line.classList.contains(selectedLevel)) {
            line.style.display = 'block';
        } else {
            line.style.display = 'none';
        }
    });
});
</script>
@endpush