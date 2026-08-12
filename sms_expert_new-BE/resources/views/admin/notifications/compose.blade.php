@extends('admin.layouts.modern-app')

@section('title', 'Send Notifications - SMS Expert Admin')

@push('styles')
<style>
    .notification-composer {
        background: var(--card-bg);
        border-radius: var(--radius-xl);
        border: 1px solid var(--border-light);
        overflow: hidden;
    }

    .composer-header {
        background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
        color: white;
        padding: 2rem;
    }

    .composer-title {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }

    .composer-subtitle {
        opacity: 0.9;
        font-size: 1rem;
    }

    .composer-body {
        padding: 2rem;
    }

    .notification-type-selector {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 1rem;
        margin-bottom: 2rem;
    }

    .type-card {
        padding: 1.5rem;
        border: 2px solid var(--border-light);
        border-radius: var(--radius-lg);
        text-align: center;
        cursor: pointer;
        transition: all 0.3s ease;
        background: var(--card-bg);
    }

    .type-card:hover {
        border-color: var(--primary-color);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .type-card.active {
        border-color: var(--primary-color);
        background: rgba(234, 97, 24, 0.05);
    }

    .type-icon {
        width: 48px;
        height: 48px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1rem;
        font-size: 1.3rem;
        color: white;
    }

    .type-title {
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 0.5rem;
    }

    .type-description {
        color: var(--text-secondary);
        font-size: 0.85rem;
    }

    .recipient-selector {
        background: var(--light-bg);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        margin-bottom: 2rem;
        border: 1px solid var(--border-light);
    }

    .recipient-options {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 1rem;
        margin-top: 1rem;
    }

    .recipient-option {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 1rem;
        background: var(--card-bg);
        border-radius: var(--radius-md);
        border: 1px solid var(--border-light);
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .recipient-option:hover {
        background: rgba(234, 97, 24, 0.05);
        border-color: var(--primary-color);
    }

    .recipient-option.selected {
        background: rgba(234, 97, 24, 0.1);
        border-color: var(--primary-color);
    }

    .recipient-count {
        margin-left: auto;
        background: var(--primary-color);
        color: white;
        padding: 0.25rem 0.75rem;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .message-composer {
        background: var(--card-bg);
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-light);
        padding: 1.5rem;
        margin-bottom: 2rem;
    }

    .composer-tabs {
        display: flex;
        border-bottom: 1px solid var(--border-light);
        margin-bottom: 1.5rem;
    }

    .composer-tab {
        padding: 1rem 1.5rem;
        border: none;
        background: none;
        color: var(--text-secondary);
        font-weight: 500;
        border-bottom: 2px solid transparent;
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .composer-tab.active {
        color: var(--primary-color);
        border-bottom-color: var(--primary-color);
    }

    .message-editor {
        min-height: 200px;
        border: 1px solid var(--border-color);
        border-radius: var(--radius-md);
        padding: 1rem;
        font-family: inherit;
        resize: vertical;
        width: 100%;
    }

    .message-editor:focus {
        outline: none;
        border-color: var(--primary-color);
        box-shadow: 0 0 0 3px rgba(234, 97, 24, 0.1);
    }

    .character-counter {
        text-align: right;
        margin-top: 0.5rem;
        font-size: 0.85rem;
        color: var(--text-secondary);
    }

    .scheduling-options {
        background: var(--light-bg);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        border: 1px solid var(--border-light);
        margin-bottom: 2rem;
    }

    .preview-section {
        background: var(--card-bg);
        border-radius: var(--radius-lg);
        border: 1px solid var(--border-light);
        padding: 1.5rem;
        margin-bottom: 2rem;
    }

    .preview-phone {
        max-width: 300px;
        margin: 0 auto;
        background: #333;
        border-radius: 25px;
        padding: 2rem 1rem;
        color: white;
        position: relative;
    }

    .preview-screen {
        background: white;
        color: var(--text-primary);
        border-radius: 15px;
        padding: 1rem;
        min-height: 200px;
        overflow-y: auto;
    }

    .sms-bubble {
        background: var(--primary-color);
        color: white;
        padding: 0.75rem 1rem;
        border-radius: 18px 18px 4px 18px;
        margin-bottom: 0.5rem;
        max-width: 80%;
        margin-left: auto;
        word-wrap: break-word;
    }

    .email-preview {
        border: 1px solid var(--border-light);
        border-radius: var(--radius-md);
        padding: 1rem;
        background: white;
        min-height: 200px;
    }

    .email-header {
        border-bottom: 1px solid var(--border-light);
        padding-bottom: 0.75rem;
        margin-bottom: 1rem;
        font-size: 0.9rem;
    }

    .email-subject {
        font-weight: 600;
        font-size: 1.1rem;
        margin-bottom: 0.5rem;
    }

    .send-options {
        background: linear-gradient(135deg, var(--light-bg) 0%, #ffffff 100%);
        border-radius: var(--radius-lg);
        padding: 1.5rem;
        border: 1px solid var(--border-light);
    }

    .send-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .summary-item {
        text-align: center;
        padding: 1rem;
        background: var(--card-bg);
        border-radius: var(--radius-md);
        border: 1px solid var(--border-light);
    }

    .summary-value {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--text-primary);
    }

    .summary-label {
        font-size: 0.85rem;
        color: var(--text-secondary);
        margin-top: 0.25rem;
    }
</style>
@endpush

@section('content')
<div class="fade-in">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Send Notifications</h1>
            <p class="text-muted">Send email and SMS notifications to your customers</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-outline-primary" onclick="loadTemplate()">
                <i class="material-icons-outlined me-1">description</i>
                Load Template
            </button>
            <button class="btn btn-outline-secondary" onclick="previewNotification()">
                <i class="material-icons-outlined me-1">preview</i>
                Preview
            </button>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <!-- Notification Composer -->
            <div class="notification-composer">
                <div class="composer-header">
                    <h2 class="composer-title">Notification Composer</h2>
                    <p class="composer-subtitle">Create and send notifications to your customers via email and SMS</p>
                </div>
                
                <div class="composer-body">
                    <form id="notificationForm">
                        <!-- Notification Type Selection -->
                        <div class="mb-4">
                            <h5 class="mb-3">Choose Notification Type</h5>
                            <div class="notification-type-selector">
                                <div class="type-card active" data-type="email">
                                    <div class="type-icon bg-primary">
                                        <i class="material-icons-outlined">email</i>
                                    </div>
                                    <div class="type-title">Email Only</div>
                                    <div class="type-description">Send email notifications to selected recipients</div>
                                </div>
                                
                                <div class="type-card" data-type="sms">
                                    <div class="type-icon bg-success">
                                        <i class="material-icons-outlined">sms</i>
                                    </div>
                                    <div class="type-title">SMS Only</div>
                                    <div class="type-description">Send SMS notifications to mobile numbers</div>
                                </div>
                                
                                <div class="type-card" data-type="both">
                                    <div class="type-icon bg-info">
                                        <i class="material-icons-outlined">mark_email_unread</i>
                                    </div>
                                    <div class="type-title">Email & SMS</div>
                                    <div class="type-description">Send both email and SMS notifications</div>
                                </div>
                            </div>
                        </div>

                        <!-- Recipient Selection -->
                        <div class="recipient-selector">
                            <h5 class="mb-3">Select Recipients</h5>
                            <div class="recipient-options">
                                <div class="recipient-option" data-recipients="all">
                                    <i class="material-icons-outlined text-primary">people</i>
                                    <div>
                                        <div class="fw-semibold">All Customers</div>
                                        <small class="text-muted">Send to all registered customers</small>
                                    </div>
                                    <span class="recipient-count">{{ $totalCustomers ?? 150 }}</span>
                                </div>
                                
                                <div class="recipient-option" data-recipients="active">
                                    <i class="material-icons-outlined text-success">verified_user</i>
                                    <div>
                                        <div class="fw-semibold">Active Customers</div>
                                        <small class="text-muted">Send to active accounts only</small>
                                    </div>
                                    <span class="recipient-count">{{ $activeCustomers ?? 120 }}</span>
                                </div>
                                
                                <div class="recipient-option" data-recipients="postpaid">
                                    <i class="material-icons-outlined text-info">credit_card</i>
                                    <div>
                                        <div class="fw-semibold">Postpaid Customers</div>
                                        <small class="text-muted">Send to postpaid accounts</small>
                                    </div>
                                    <span class="recipient-count">{{ $postpaidCustomers ?? 45 }}</span>
                                </div>
                                
                                <div class="recipient-option" data-recipients="prepaid">
                                    <i class="material-icons-outlined text-warning">account_balance_wallet</i>
                                    <div>
                                        <div class="fw-semibold">Prepaid Customers</div>
                                        <small class="text-muted">Send to prepaid accounts</small>
                                    </div>
                                    <span class="recipient-count">{{ $prepaidCustomers ?? 105 }}</span>
                                </div>
                                
                                <div class="recipient-option" data-recipients="custom">
                                    <i class="material-icons-outlined text-secondary">list_alt</i>
                                    <div>
                                        <div class="fw-semibold">Custom List</div>
                                        <small class="text-muted">Upload or select specific recipients</small>
                                    </div>
                                    <span class="recipient-count">0</span>
                                </div>
                            </div>
                        </div>

                        <!-- Message Composer -->
                        <div class="message-composer">
                            <div class="composer-tabs">
                                <button type="button" class="composer-tab active" data-tab="email">
                                    <i class="material-icons-outlined me-2">email</i>
                                    Email Message
                                </button>
                                <button type="button" class="composer-tab" data-tab="sms">
                                    <i class="material-icons-outlined me-2">sms</i>
                                    SMS Message
                                </button>
                            </div>
                            
                            <!-- Email Tab -->
                            <div class="tab-content" id="emailTab">
                                <div class="form-group mb-3">
                                    <label class="form-label">Email Subject</label>
                                    <input type="text" class="form-control" id="emailSubject" placeholder="Enter email subject">
                                </div>
                                
                                <div class="form-group mb-3">
                                    <label class="form-label">Email Template</label>
                                    <select class="form-control" id="emailTemplate">
                                        <option value="">Select a template (optional)</option>
                                        <option value="welcome">Welcome Email</option>
                                        <option value="maintenance">System Maintenance</option>
                                        <option value="promotion">Promotional Offer</option>
                                        <option value="invoice">Invoice Reminder</option>
                                        <option value="security">Security Alert</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Email Message</label>
                                    <textarea class="message-editor" id="emailMessage" placeholder="Enter your email message here..."></textarea>
                                    <div class="character-counter">
                                        <span id="emailCharCount">0</span> characters
                                    </div>
                                </div>
                            </div>
                            
                            <!-- SMS Tab -->
                            <div class="tab-content d-none" id="smsTab">
                                <div class="form-group mb-3">
                                    <label class="form-label">SMS Message</label>
                                    <textarea class="message-editor" id="smsMessage" placeholder="Enter your SMS message here..." maxlength="160"></textarea>
                                    <div class="character-counter">
                                        <span id="smsCharCount">0</span> / 160 characters
                                        <span class="ms-2 badge bg-info" id="smsSegments">1 segment</span>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Sender ID</label>
                                    <input type="text" class="form-control" id="senderId" placeholder="SMS Expert" maxlength="11">
                                    <small class="text-muted">Maximum 11 characters for sender ID</small>
                                </div>
                            </div>
                        </div>

                        <!-- Scheduling Options -->
                        <div class="scheduling-options">
                            <h5 class="mb-3">Scheduling Options</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="radio" name="scheduling" id="sendNow" value="now" checked>
                                        <label class="form-check-label" for="sendNow">
                                            <i class="material-icons-outlined me-2">send</i>
                                            Send Immediately
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="scheduling" id="scheduleFor" value="scheduled">
                                        <label class="form-check-label" for="scheduleFor">
                                            <i class="material-icons-outlined me-2">schedule</i>
                                            Schedule for Later
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group" id="schedulingDateTime" style="display: none;">
                                        <label class="form-label">Schedule Date & Time</label>
                                        <input type="datetime-local" class="form-control" id="scheduleDateTime">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <!-- Preview Section -->
            <div class="preview-section">
                <h5 class="mb-3">Live Preview</h5>
                
                <!-- SMS Preview -->
                <div id="smsPreview" class="mb-4">
                    <h6 class="text-muted mb-2">SMS Preview</h6>
                    <div class="preview-phone">
                        <div class="preview-screen">
                            <div class="text-center mb-2">
                                <small class="text-muted">SMS Expert</small>
                            </div>
                            <div class="sms-bubble" id="smsPreviewContent">
                                Your SMS message will appear here...
                            </div>
                            <div class="text-center mt-2">
                                <small class="text-muted">{{ now()->format('H:i') }}</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Email Preview -->
                <div id="emailPreview">
                    <h6 class="text-muted mb-2">Email Preview</h6>
                    <div class="email-preview">
                        <div class="email-header">
                            <div><strong>From:</strong> SMS Expert &lt;noreply@smsexpert.com&gt;</div>
                            <div><strong>To:</strong> Selected Recipients</div>
                            <div><strong>Subject:</strong> <span id="emailSubjectPreview">Your email subject</span></div>
                        </div>
                        <div id="emailContentPreview">
                            Your email message will appear here...
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Send Options -->
            <div class="send-options">
                <h5 class="mb-3">Send Summary</h5>
                
                <div class="send-summary">
                    <div class="summary-item">
                        <div class="summary-value" id="recipientCount">0</div>
                        <div class="summary-label">Recipients</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-value" id="estimatedCost">£0.00</div>
                        <div class="summary-label">Estimated Cost</div>
                    </div>
                    <div class="summary-item">
                        <div class="summary-value" id="deliveryTime">Instant</div>
                        <div class="summary-label">Delivery</div>
                    </div>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="button" class="btn btn-primary-modern btn-lg" onclick="sendNotification()">
                        <i class="material-icons-outlined me-2">send</i>
                        Send Notification
                    </button>
                    <button type="button" class="btn btn-outline-primary" onclick="saveDraft()">
                        <i class="material-icons-outlined me-2">save</i>
                        Save as Draft
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Notification type selection
    const typeCards = document.querySelectorAll('.type-card');
    typeCards.forEach(card => {
        card.addEventListener('click', function() {
            typeCards.forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            updateUIForType(this.dataset.type);
        });
    });
    
    // Recipient selection
    const recipientOptions = document.querySelectorAll('.recipient-option');
    recipientOptions.forEach(option => {
        option.addEventListener('click', function() {
            recipientOptions.forEach(o => o.classList.remove('selected'));
            this.classList.add('selected');
            updateRecipientCount(this.dataset.recipients);
        });
    });
    
    // Tab switching
    const composerTabs = document.querySelectorAll('.composer-tab');
    composerTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            composerTabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');
            switchTab(this.dataset.tab);
        });
    });
    
    // Character counting
    document.getElementById('emailMessage').addEventListener('input', updateEmailPreview);
    document.getElementById('emailSubject').addEventListener('input', updateEmailPreview);
    document.getElementById('smsMessage').addEventListener('input', updateSmsPreview);
    
    // Scheduling options
    document.querySelectorAll('input[name="scheduling"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const dateTimeField = document.getElementById('schedulingDateTime');
            if (this.value === 'scheduled') {
                dateTimeField.style.display = 'block';
            } else {
                dateTimeField.style.display = 'none';
            }
            updateDeliveryTime();
        });
    });
    
    // Set default recipient
    document.querySelector('.recipient-option[data-recipients="all"]').click();
});

function updateUIForType(type) {
    const emailTab = document.querySelector('.composer-tab[data-tab="email"]');
    const smsTab = document.querySelector('.composer-tab[data-tab="sms"]');
    
    switch(type) {
        case 'email':
            emailTab.style.display = 'block';
            smsTab.style.display = 'none';
            emailTab.click();
            break;
        case 'sms':
            emailTab.style.display = 'none';
            smsTab.style.display = 'block';
            smsTab.click();
            break;
        case 'both':
            emailTab.style.display = 'block';
            smsTab.style.display = 'block';
            break;
    }
}

function switchTab(tab) {
    document.getElementById('emailTab').classList.toggle('d-none', tab !== 'email');
    document.getElementById('smsTab').classList.toggle('d-none', tab !== 'sms');
}

function updateRecipientCount(recipients) {
    const counts = {
        'all': {{ $totalCustomers ?? 150 }},
        'active': {{ $activeCustomers ?? 120 }},
        'postpaid': {{ $postpaidCustomers ?? 45 }},
        'prepaid': {{ $prepaidCustomers ?? 105 }},
        'custom': 0
    };
    
    const count = counts[recipients] || 0;
    document.getElementById('recipientCount').textContent = count.toLocaleString();
    
    // Update estimated cost (assuming £0.05 per SMS, £0.01 per email)
    const selectedType = document.querySelector('.type-card.active').dataset.type;
    let cost = 0;
    
    if (selectedType === 'sms' || selectedType === 'both') {
        cost += count * 0.05; // SMS cost
    }
    if (selectedType === 'email' || selectedType === 'both') {
        cost += count * 0.01; // Email cost
    }
    
    document.getElementById('estimatedCost').textContent = '£' + cost.toFixed(2);
}

function updateEmailPreview() {
    const subject = document.getElementById('emailSubject').value || 'Your email subject';
    const message = document.getElementById('emailMessage').value || 'Your email message will appear here...';
    
    document.getElementById('emailSubjectPreview').textContent = subject;
    document.getElementById('emailContentPreview').innerHTML = message.replace(/\n/g, '<br>');
    
    // Update character count
    document.getElementById('emailCharCount').textContent = message.length.toLocaleString();
}

function updateSmsPreview() {
    const message = document.getElementById('smsMessage').value || 'Your SMS message will appear here...';
    
    document.getElementById('smsPreviewContent').textContent = message;
    
    // Update character count and segments
    const charCount = message.length;
    const segments = Math.ceil(charCount / 160);
    
    document.getElementById('smsCharCount').textContent = charCount;
    document.getElementById('smsSegments').textContent = segments + ' segment' + (segments !== 1 ? 's' : '');
    
    // Update segments color
    const segmentsBadge = document.getElementById('smsSegments');
    if (segments === 1) {
        segmentsBadge.className = 'ms-2 badge bg-success';
    } else if (segments <= 3) {
        segmentsBadge.className = 'ms-2 badge bg-warning';
    } else {
        segmentsBadge.className = 'ms-2 badge bg-danger';
    }
}

function updateDeliveryTime() {
    const scheduling = document.querySelector('input[name="scheduling"]:checked').value;
    const deliveryElement = document.getElementById('deliveryTime');
    
    if (scheduling === 'now') {
        deliveryElement.textContent = 'Instant';
    } else {
        deliveryElement.textContent = 'Scheduled';
    }
}

function sendNotification() {
    const selectedType = document.querySelector('.type-card.active').dataset.type;
    const selectedRecipients = document.querySelector('.recipient-option.selected').dataset.recipients;
    const recipientCount = document.getElementById('recipientCount').textContent;
    
    if (confirm(`Send ${selectedType} notification to ${recipientCount} recipients?`)) {
        showNotification('Sending notifications...', 'info');
        
        // Simulate sending process
        setTimeout(() => {
            showNotification(`Notification sent successfully to ${recipientCount} recipients!`, 'success');
        }, 2000);
    }
}

function saveDraft() {
    showNotification('Draft saved successfully!', 'success');
}

function loadTemplate() {
    const templates = {
        'welcome': {
            subject: 'Welcome to SMS Expert!',
            email: 'Welcome to SMS Expert! We\'re excited to have you on board. Your account is now ready to use.',
            sms: 'Welcome to SMS Expert! Your account is now active. Start sending SMS today!'
        },
        'maintenance': {
            subject: 'Scheduled System Maintenance',
            email: 'We will be performing scheduled maintenance on {{ date }}. Services may be temporarily unavailable.',
            sms: 'SMS Expert maintenance scheduled for {{ date }}. Service may be briefly interrupted.'
        },
        'promotion': {
            subject: 'Special Offer - 20% Off SMS Credits',
            email: 'Limited time offer! Get 20% off all SMS credits. Use code SAVE20 at checkout.',
            sms: '20% OFF SMS credits! Use code SAVE20. Limited time offer. Order now!'
        }
    };
    
    const templateSelect = document.getElementById('emailTemplate');
    if (templateSelect.value && templates[templateSelect.value]) {
        const template = templates[templateSelect.value];
        document.getElementById('emailSubject').value = template.subject;
        document.getElementById('emailMessage').value = template.email;
        document.getElementById('smsMessage').value = template.sms;
        
        updateEmailPreview();
        updateSmsPreview();
        
        showNotification('Template loaded successfully!', 'success');
    }
}

function previewNotification() {
    // Implement preview in new window/modal
    showNotification('Opening preview...', 'info');
}
</script>
@endpush