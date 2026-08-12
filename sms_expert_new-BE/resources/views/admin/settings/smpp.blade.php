@extends('admin.layouts.modern-app')

@section('title', 'SMPP Configuration - SMS Expert Admin')

@push('styles')
<style>
    .connection-card {
        background: var(--card-bg);
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-light);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .connection-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 4px;
        background: var(--status-color);
    }

    .connection-card.online { --status-color: var(--success-color); }
    .connection-card.offline { --status-color: var(--danger-color); }
    .connection-card.warning { --status-color: var(--warning-color); }

    .connection-header {
        padding: 1.5rem;
        display: flex;
        justify-content: between;
        align-items: start;
    }

    .connection-info h5 {
        margin-bottom: 0.5rem;
        color: var(--text-primary);
    }

    .connection-details {
        color: var(--text-secondary);
        font-size: 0.9rem;
    }

    .connection-status {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .status-online {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success-color);
    }

    .status-offline {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger-color);
    }

    .status-warning {
        background: rgba(245, 158, 11, 0.1);
        color: var(--warning-color);
    }

    .connection-metrics {
        padding: 0 1.5rem 1.5rem;
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
    }

    .metric {
        text-align: center;
        padding: 1rem;
        background: var(--light-bg);
        border-radius: var(--radius-md);
    }

    .metric-value {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    .metric-label {
        font-size: 0.8rem;
        color: var(--text-secondary);
        margin-top: 0.25rem;
    }

    .config-form {
        background: var(--card-bg);
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-light);
        padding: 2rem;
    }

    .form-section {
        margin-bottom: 2rem;
        padding-bottom: 2rem;
        border-bottom: 1px solid var(--border-light);
    }

    .form-section:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }

    .section-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .form-group {
        margin-bottom: 1.5rem;
    }

    .form-label {
        font-weight: 500;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .form-control {
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        padding: 0.75rem 1rem;
        font-size: 0.9rem;
        transition: all 0.2s ease;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(234, 97, 24, 0.1);
    }

    .input-group-text {
        background: var(--light-bg);
        border: 1px solid var(--border-color);
        color: var(--text-secondary);
    }

    .test-connection-btn {
        background: var(--info-color);
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: var(--radius-md);
        font-size: 0.85rem;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .test-connection-btn:hover {
        background: #2563eb;
        transform: translateY(-1px);
    }

    .connection-test-result {
        padding: 1rem;
        border-radius: var(--radius-md);
        margin-top: 1rem;
        display: none;
    }

    .test-success {
        background: rgba(16, 185, 129, 0.1);
        color: var(--success-color);
        border: 1px solid rgba(16, 185, 129, 0.2);
    }

    .test-error {
        background: rgba(239, 68, 68, 0.1);
        color: var(--danger-color);
        border: 1px solid rgba(239, 68, 68, 0.2);
    }
</style>
@endpush

@section('content')
<div class="fade-in">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">SMPP Configuration</h1>
            <p class="text-muted">Manage SMPP gateway connections and routing settings</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-success" onclick="testAllConnections()">
                <i class="material-icons-outlined me-1">wifi_protected_setup</i>
                Test All Connections
            </button>
            <button class="btn btn-primary-modern" onclick="addNewConnection()">
                <i class="material-icons-outlined me-1">add</i>
                Add Connection
            </button>
        </div>
    </div>

    <!-- Active Connections Overview -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="connection-card online">
                <div class="connection-header">
                    <div class="connection-info">
                        <h5>Primary SMPP Gateway</h5>
                        <div class="connection-details">
                            <div>smpp.provider1.com:2775</div>
                            <div>Bind: TX/RX</div>
                        </div>
                    </div>
                    <div class="connection-status status-online">
                        <div class="status-indicator bg-success"></div>
                        Online
                    </div>
                </div>
                <div class="connection-metrics">
                    <div class="metric">
                        <div class="metric-value">15.2K</div>
                        <div class="metric-label">Messages Today</div>
                    </div>
                    <div class="metric">
                        <div class="metric-value">99.8%</div>
                        <div class="metric-label">Success Rate</div>
                    </div>
                    <div class="metric">
                        <div class="metric-value">250ms</div>
                        <div class="metric-label">Avg Response</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="connection-card online">
                <div class="connection-header">
                    <div class="connection-info">
                        <h5>Secondary SMPP Gateway</h5>
                        <div class="connection-details">
                            <div>smpp.provider2.com:2776</div>
                            <div>Bind: TX/RX</div>
                        </div>
                    </div>
                    <div class="connection-status status-online">
                        <div class="status-indicator bg-success"></div>
                        Online
                    </div>
                </div>
                <div class="connection-metrics">
                    <div class="metric">
                        <div class="metric-value">8.7K</div>
                        <div class="metric-label">Messages Today</div>
                    </div>
                    <div class="metric">
                        <div class="metric-value">98.5%</div>
                        <div class="metric-label">Success Rate</div>
                    </div>
                    <div class="metric">
                        <div class="metric-value">180ms</div>
                        <div class="metric-label">Avg Response</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="connection-card warning">
                <div class="connection-header">
                    <div class="connection-info">
                        <h5>Backup SMPP Gateway</h5>
                        <div class="connection-details">
                            <div>smpp.backup.com:2777</div>
                            <div>Bind: TX Only</div>
                        </div>
                    </div>
                    <div class="connection-status status-warning">
                        <div class="status-indicator bg-warning"></div>
                        Standby
                    </div>
                </div>
                <div class="connection-metrics">
                    <div class="metric">
                        <div class="metric-value">0</div>
                        <div class="metric-label">Messages Today</div>
                    </div>
                    <div class="metric">
                        <div class="metric-value">-</div>
                        <div class="metric-label">Success Rate</div>
                    </div>
                    <div class="metric">
                        <div class="metric-value">-</div>
                        <div class="metric-label">Avg Response</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Configuration Form -->
    <div class="config-form">
        <form id="smppConfigForm">
            <!-- Connection Settings -->
            <div class="form-section">
                <h4 class="section-title">
                    <i class="material-icons-outlined">settings_ethernet</i>
                    Connection Settings
                </h4>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Connection Name</label>
                            <input type="text" class="form-control" placeholder="Primary SMPP Gateway" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Priority</label>
                            <select class="form-control">
                                <option value="1">High (Primary)</option>
                                <option value="2">Medium (Secondary)</option>
                                <option value="3">Low (Backup)</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-8">
                        <div class="form-group">
                            <label class="form-label">SMPP Server Host</label>
                            <input type="text" class="form-control" placeholder="smpp.provider.com" required>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">Port</label>
                            <input type="number" class="form-control" placeholder="2775" value="2775" required>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Authentication -->
            <div class="form-section">
                <h4 class="section-title">
                    <i class="material-icons-outlined">lock</i>
                    Authentication
                </h4>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">System ID</label>
                            <input type="text" class="form-control" placeholder="your_system_id" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" placeholder="your_password" required>
                                <span class="input-group-text">
                                    <i class="material-icons-outlined cursor-pointer" onclick="togglePassword(this)">visibility</i>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">System Type</label>
                            <input type="text" class="form-control" placeholder="SMPP" value="SMPP">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">TON (Type of Number)</label>
                            <select class="form-control">
                                <option value="0">Unknown</option>
                                <option value="1" selected>International</option>
                                <option value="2">National</option>
                                <option value="3">Network Specific</option>
                                <option value="4">Subscriber</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Connection Parameters -->
            <div class="form-section">
                <h4 class="section-title">
                    <i class="material-icons-outlined">tune</i>
                    Connection Parameters
                </h4>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">Bind Type</label>
                            <select class="form-control">
                                <option value="transceiver" selected>Transceiver</option>
                                <option value="transmitter">Transmitter</option>
                                <option value="receiver">Receiver</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">Connection Timeout (sec)</label>
                            <input type="number" class="form-control" value="30" min="10" max="120">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">Enquire Link Interval (sec)</label>
                            <input type="number" class="form-control" value="30" min="10" max="300">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">Max Concurrent Connections</label>
                            <input type="number" class="form-control" value="5" min="1" max="50">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">Submit Rate (per second)</label>
                            <input type="number" class="form-control" value="100" min="1" max="1000">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label class="form-label">Auto Reconnect</label>
                            <select class="form-control">
                                <option value="1" selected>Enabled</option>
                                <option value="0">Disabled</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Routing Rules -->
            <div class="form-section">
                <h4 class="section-title">
                    <i class="material-icons-outlined">alt_route</i>
                    Routing Rules
                </h4>
                
                <div class="form-group">
                    <label class="form-label">Country Code Routing</label>
                    <textarea class="form-control" rows="4" placeholder="Enter country codes and routing rules (one per line)&#10;Example:&#10;+1,+44 -> Primary Gateway&#10;+91,+86 -> Secondary Gateway&#10;* -> Backup Gateway"></textarea>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Failover Strategy</label>
                            <select class="form-control">
                                <option value="round_robin" selected>Round Robin</option>
                                <option value="priority">Priority Based</option>
                                <option value="least_cost">Least Cost</option>
                                <option value="best_quality">Best Quality</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">Retry Attempts</label>
                            <input type="number" class="form-control" value="3" min="1" max="10">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Advanced Settings -->
            <div class="form-section">
                <h4 class="section-title">
                    <i class="material-icons-outlined">settings</i>
                    Advanced Settings
                </h4>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="enableDLR" checked>
                                <label class="form-check-label" for="enableDLR">
                                    Enable Delivery Receipts (DLR)
                                </label>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="enableLogging" checked>
                                <label class="form-check-label" for="enableLogging">
                                    Enable Connection Logging
                                </label>
                            </div>
                        </div>
                        <div class="form-group">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="enableValidation">
                                <label class="form-check-label" for="enableValidation">
                                    Enable Message Validation
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label class="form-label">DLR Webhook URL</label>
                            <input type="url" class="form-control" placeholder="https://yoursite.com/dlr-webhook">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Character Encoding</label>
                            <select class="form-control">
                                <option value="UTF-8" selected>UTF-8</option>
                                <option value="GSM7">GSM 7-bit</option>
                                <option value="UCS2">UCS2</option>
                                <option value="ISO-8859-1">ISO-8859-1</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <button type="button" class="test-connection-btn" onclick="testConfiguration()">
                        <i class="material-icons-outlined me-1">wifi_protected_setup</i>
                        Test Configuration
                    </button>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
                        <i class="material-icons-outlined me-1">refresh</i>
                        Reset
                    </button>
                    <button type="submit" class="btn btn-primary-modern">
                        <i class="material-icons-outlined me-1">save</i>
                        Save Configuration
                    </button>
                </div>
            </div>
            
            <!-- Test Result -->
            <div class="connection-test-result" id="testResult"></div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form submission
    document.getElementById('smppConfigForm').addEventListener('submit', function(e) {
        e.preventDefault();
        saveConfiguration();
    });
    
    // Update connection status every 30 seconds
    updateConnectionStatus();
    setInterval(updateConnectionStatus, 30000);
});

function updateConnectionStatus() {
    // Simulate real-time connection status updates
    const connections = document.querySelectorAll('.connection-card');
    connections.forEach((card, index) => {
        const isOnline = Math.random() > 0.1; // 90% chance online
        const statusElement = card.querySelector('.connection-status');
        const metricsElements = card.querySelectorAll('.metric-value');
        
        if (isOnline) {
            card.className = 'connection-card online';
            statusElement.className = 'connection-status status-online';
            statusElement.innerHTML = '<div class="status-indicator bg-success"></div>Online';
            
            // Update metrics with random values
            if (metricsElements.length >= 3) {
                metricsElements[0].textContent = (Math.floor(Math.random() * 5000) + 10000).toLocaleString();
                metricsElements[1].textContent = (Math.random() * 2 + 98).toFixed(1) + '%';
                metricsElements[2].textContent = Math.floor(Math.random() * 200 + 150) + 'ms';
            }
        } else if (index === 2) { // Keep backup as standby
            card.className = 'connection-card warning';
            statusElement.className = 'connection-status status-warning';
            statusElement.innerHTML = '<div class="status-indicator bg-warning"></div>Standby';
        }
    });
}

function testConfiguration() {
    const testButton = document.querySelector('.test-connection-btn');
    const resultDiv = document.getElementById('testResult');
    
    // Show loading state
    testButton.innerHTML = '<i class="material-icons-outlined me-1">sync</i>Testing...';
    testButton.disabled = true;
    
    // Simulate test
    setTimeout(() => {
        const isSuccess = Math.random() > 0.3; // 70% success rate
        
        resultDiv.style.display = 'block';
        if (isSuccess) {
            resultDiv.className = 'connection-test-result test-success';
            resultDiv.innerHTML = `
                <div class="d-flex align-items-center gap-2">
                    <i class="material-icons-outlined">check_circle</i>
                    <div>
                        <div class="fw-semibold">Connection Test Successful</div>
                        <div class="small">Successfully connected to SMPP gateway in 245ms</div>
                    </div>
                </div>
            `;
        } else {
            resultDiv.className = 'connection-test-result test-error';
            resultDiv.innerHTML = `
                <div class="d-flex align-items-center gap-2">
                    <i class="material-icons-outlined">error</i>
                    <div>
                        <div class="fw-semibold">Connection Test Failed</div>
                        <div class="small">Unable to connect: Connection timeout after 30 seconds</div>
                    </div>
                </div>
            `;
        }
        
        // Reset button
        testButton.innerHTML = '<i class="material-icons-outlined me-1">wifi_protected_setup</i>Test Configuration';
        testButton.disabled = false;
        
        // Hide result after 5 seconds
        setTimeout(() => {
            resultDiv.style.display = 'none';
        }, 5000);
    }, 2000);
}

function saveConfiguration() {
    showNotification('Saving SMPP configuration...', 'info');
    
    // Simulate save process
    setTimeout(() => {
        showNotification('SMPP configuration saved successfully!', 'success');
    }, 1500);
}

function testAllConnections() {
    showNotification('Testing all SMPP connections...', 'info');
    
    // Simulate testing all connections
    setTimeout(() => {
        showNotification('All connections tested successfully!', 'success');
    }, 3000);
}

function addNewConnection() {
    // Reset form for new connection
    document.getElementById('smppConfigForm').reset();
    showNotification('Form cleared for new connection', 'info');
}

function resetForm() {
    if (confirm('Are you sure you want to reset the form?')) {
        document.getElementById('smppConfigForm').reset();
        document.getElementById('testResult').style.display = 'none';
        showNotification('Form reset successfully', 'info');
    }
}

function togglePassword(icon) {
    const input = icon.closest('.input-group').querySelector('input');
    const type = input.getAttribute('type') === 'password' ? 'text' : 'password';
    input.setAttribute('type', type);
    icon.textContent = type === 'password' ? 'visibility' : 'visibility_off';
}
</script>
@endpush