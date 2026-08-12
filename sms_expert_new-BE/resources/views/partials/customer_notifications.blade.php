<!-- Customer Notification System -->
<style>
    /* Notification Bell Styles */
    .notification-bell {
        position: relative;
        cursor: pointer;
    }
    
    .notification-bell .badge-count {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #dc3545;
        color: white;
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 10px;
        min-width: 18px;
        text-align: center;
    }
    
    /* Notification Dropdown */
    .notification-dropdown-menu {
        width: 380px;
        max-height: 450px;
        overflow-y: auto;
        border: none;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        border-radius: 12px;
        padding: 0;
    }
    
    .notification-dropdown-header {
        background: linear-gradient(135deg, #293b50, #1f2c3d);
        color: white;
        padding: 15px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-radius: 12px 12px 0 0;
    }
    
    .notification-list {
        max-height: 350px;
        overflow-y: auto;
    }
    
    .notification-item {
        padding: 15px 20px;
        border-bottom: 1px solid #f1f1f1;
        cursor: pointer;
        transition: all 0.2s ease;
        width: 100%;
        box-sizing: border-box;
    }
    
    .notification-item:hover {
        background: #f8f9fa;
    }
    
    .notification-item.unread {
        background: #fff8f5;
        border-left: 3px solid #ea6118;
    }
    
    .notification-item .icon-wrapper {
        width: 40px;
        height: 40px;
        min-width: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    
    .notification-item .icon-wrapper.info { background: rgba(23, 162, 184, 0.15); color: #17a2b8; }
    .notification-item .icon-wrapper.warning { background: rgba(255, 193, 7, 0.15); color: #ffc107; }
    .notification-item .icon-wrapper.success { background: rgba(40, 167, 69, 0.15); color: #28a745; }
    .notification-item .icon-wrapper.danger { background: rgba(220, 53, 69, 0.15); color: #dc3545; }
    .notification-item .icon-wrapper.announcement { background: rgba(111, 66, 193, 0.15); color: #6f42c1; }
    
    .notification-item .content {
        flex: 1;
        margin-left: 12px;
        min-width: 0;
        max-width: calc(100% - 55px);
        overflow: hidden;
    }
    
    .notification-item .title {
        font-weight: 600;
        font-size: 14px;
        color: #293b50;
        margin-bottom: 4px;
        word-wrap: break-word;
        overflow-wrap: break-word;
        white-space: normal;
        line-height: 1.3;
    }
    
    .notification-item .message {
        font-size: 13px;
        color: #6c757d;
        word-wrap: break-word;
        overflow-wrap: break-word;
        white-space: normal;
        line-height: 1.4;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .notification-item .time {
        font-size: 11px;
        color: #adb5bd;
        margin-top: 5px;
    }
    
    .notification-empty {
        padding: 40px 20px;
        text-align: center;
        color: #6c757d;
    }
    
    .notification-empty i {
        font-size: 48px;
        color: #dee2e6;
        margin-bottom: 10px;
    }
    
    /* Acknowledgment Modal */
    .acknowledgment-modal .modal-content {
        border: none;
        border-radius: 16px;
        overflow: hidden;
    }
    
    .acknowledgment-modal .modal-header {
        background: linear-gradient(135deg, #293b50, #1f2c3d);
        color: white;
        border: none;
        padding: 20px 25px;
    }
    
    .acknowledgment-modal .modal-header .btn-close {
        display: none;
    }
    
    .acknowledgment-modal .modal-body {
        padding: 30px;
    }
    
    .acknowledgment-modal .notification-type-icon {
        width: 70px;
        height: 70px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 20px;
    }
    
    .acknowledgment-modal .notification-type-icon.info { background: rgba(23, 162, 184, 0.15); color: #17a2b8; }
    .acknowledgment-modal .notification-type-icon.warning { background: rgba(255, 193, 7, 0.15); color: #ffc107; }
    .acknowledgment-modal .notification-type-icon.success { background: rgba(40, 167, 69, 0.15); color: #28a745; }
    .acknowledgment-modal .notification-type-icon.danger { background: rgba(220, 53, 69, 0.15); color: #dc3545; }
    .acknowledgment-modal .notification-type-icon.announcement { background: rgba(111, 66, 193, 0.15); color: #6f42c1; }
    
    .acknowledgment-modal .notification-type-icon i {
        font-size: 36px;
    }
    
    .acknowledgment-modal .notification-title {
        font-size: 22px;
        font-weight: 600;
        color: #293b50;
        text-align: center;
        margin-bottom: 15px;
        word-wrap: break-word;
        overflow-wrap: break-word;
        white-space: normal;
    }
    
    .acknowledgment-modal .notification-message {
        font-size: 15px;
        color: #555;
        text-align: center;
        line-height: 1.6;
        margin-bottom: 25px;
        white-space: pre-line;
        word-wrap: break-word;
        overflow-wrap: break-word;
        max-width: 100%;
    }
    
    .acknowledgment-modal .modal-footer {
        border: none;
        padding: 0 30px 30px;
        justify-content: center;
    }
    
    .acknowledgment-modal .btn-acknowledge {
        background: linear-gradient(135deg, #ea6118, #d1520e);
        border: none;
        color: white;
        padding: 12px 40px;
        border-radius: 8px;
        font-weight: 600;
        font-size: 16px;
        transition: all 0.3s ease;
    }
    
    .acknowledgment-modal .btn-acknowledge:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 20px rgba(234, 97, 24, 0.4);
    }
    
    /* Center Notification (non-acknowledgment) */
    .center-notification-modal .modal-dialog {
        max-width: 500px;
    }
    
    .center-notification-modal .modal-content {
        border: none;
        border-radius: 16px;
        overflow: hidden;
    }
    
    .center-notification-modal .modal-header {
        border: none;
        padding: 20px 25px 10px;
    }
    
    .center-notification-modal .modal-body {
        padding: 10px 30px 30px;
        text-align: center;
    }
    
    /* Spin animation for loading */
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
    .spin { animation: spin 1s linear infinite; }
</style>

<!-- Acknowledgment Modal -->
<div class="modal fade acknowledgment-modal" id="acknowledgmentModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="material-icons-outlined me-2">campaign</i>
                    Important Notice
                </h5>
            </div>
            <div class="modal-body">
                <div class="notification-type-icon" id="ackNotificationIcon">
                    <i class="material-icons-outlined">info</i>
                </div>
                <h4 class="notification-title" id="ackNotificationTitle">Notification Title</h4>
                <div class="notification-message" id="ackNotificationMessage">Notification message will appear here.</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-acknowledge" id="btnAcknowledge">
                    <i class="material-icons-outlined me-2">check_circle</i>
                    I Acknowledge
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Center Notification Modal (non-acknowledgment) -->
<div class="modal fade center-notification-modal" id="centerNotificationModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="notification-type-icon mb-3" id="centerNotificationIcon">
                    <i class="material-icons-outlined">info</i>
                </div>
                <h4 class="notification-title" id="centerNotificationTitle">Notification</h4>
                <div class="notification-message" id="centerNotificationMessage">Message here.</div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let currentAckNotificationId = null;
    let pendingAcknowledgments = [];
    let allNotifications = [];

    // Initialize notification system
    initNotifications();

    function initNotifications() {
        // Load notifications
        loadNotifications();
        
        // Check for pending acknowledgments
        checkPendingAcknowledgments();
        
        // Poll for new notifications every 30 seconds
        setInterval(loadNotifications, 30000);
        
        // Poll for pending acknowledgments every 60 seconds
        setInterval(checkPendingAcknowledgments, 60000);
    }

    function loadNotifications() {
        fetch('{{ route("customer.notifications") }}')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    allNotifications = data.notifications;
                    updateNotificationUI(data.notifications);
                }
            })
            .catch(error => console.error('Error loading notifications:', error));
    }

    function updateNotificationUI(notifications) {
        const listContainer = document.getElementById('notificationList');
        const countBadge = document.getElementById('notificationCount');
        const newCountText = document.getElementById('notificationNewCount');
        
        if (!listContainer || !countBadge) return;
        
        const unreadCount = notifications.filter(n => !n.is_read).length;
        
        // Update badge
        if (unreadCount > 0) {
            countBadge.textContent = unreadCount > 9 ? '9+' : unreadCount;
            countBadge.style.display = 'block';
        } else {
            countBadge.style.display = 'none';
        }
        
        if (newCountText) {
            newCountText.textContent = `${unreadCount} new`;
        }
        
        // Update list
        if (notifications.length === 0) {
            listContainer.innerHTML = `
                <div class="notification-empty">
                    <i class="material-icons-outlined">notifications_none</i>
                    <p>No notifications yet</p>
                </div>
            `;
            return;
        }
        
        let html = '';
        notifications.forEach(n => {
            const icon = getTypeIcon(n.type);
            const timeAgo = getTimeAgo(n.created_at);
            
            html += `
                <div class="notification-item d-flex align-items-start ${!n.is_read ? 'unread' : ''}" 
                     data-id="${n.id}" 
                     data-notification-id="${n.notification_id}"
                     onclick="showNotificationDetail(${n.id}, ${n.requires_acknowledgment}, ${n.is_acknowledged})">
                    <div class="icon-wrapper ${n.type}">
                        <i class="material-icons-outlined">${icon}</i>
                    </div>
                    <div class="content">
                        <div class="title">${escapeHtml(n.title)}</div>
                        <div class="message">${escapeHtml(n.message)}</div>
                        <div class="time">${timeAgo}</div>
                    </div>
                </div>
            `;
        });
        
        listContainer.innerHTML = html;
    }

    function checkPendingAcknowledgments() {
        fetch('{{ route("customer.notifications.pending") }}')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.has_pending) {
                    pendingAcknowledgments = data.notifications;
                    showNextAcknowledgment();
                }
            })
            .catch(error => console.error('Error checking pending acknowledgments:', error));
    }

    function showNextAcknowledgment() {
        if (pendingAcknowledgments.length === 0) return;
        
        const notification = pendingAcknowledgments[0];
        currentAckNotificationId = notification.id;
        
        // Update modal content
        document.getElementById('ackNotificationTitle').textContent = notification.title;
        document.getElementById('ackNotificationMessage').textContent = notification.message;
        
        // Update icon
        const iconContainer = document.getElementById('ackNotificationIcon');
        iconContainer.className = `notification-type-icon ${notification.type}`;
        iconContainer.innerHTML = `<i class="material-icons-outlined">${getTypeIcon(notification.type)}</i>`;
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('acknowledgmentModal'));
        modal.show();
    }

    // Acknowledge button click
    document.getElementById('btnAcknowledge')?.addEventListener('click', function() {
        if (!currentAckNotificationId) return;
        
        const btn = this;
        btn.disabled = true;
        btn.innerHTML = '<i class="material-icons-outlined me-2 spin">refresh</i> Processing...';
        
        fetch(`/api/notifications/${currentAckNotificationId}/acknowledge`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('acknowledgmentModal'));
                modal.hide();
                
                // Remove from pending list
                pendingAcknowledgments = pendingAcknowledgments.filter(n => n.id !== currentAckNotificationId);
                currentAckNotificationId = null;
                
                // Show next if any
                setTimeout(() => {
                    if (pendingAcknowledgments.length > 0) {
                        showNextAcknowledgment();
                    }
                }, 500);
                
                // Refresh notifications
                loadNotifications();
            }
        })
        .catch(error => {
            console.error('Error acknowledging notification:', error);
            alert('Failed to acknowledge notification. Please try again.');
        })
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="material-icons-outlined me-2">check_circle</i> I Acknowledge';
        });
    });

    // Show notification detail
    window.showNotificationDetail = function(id, requiresAck, isAcked) {
        const notification = allNotifications.find(n => n.id === id);
        if (!notification) return;
        
        // Mark as read
        if (!notification.is_read) {
            fetch(`/api/notifications/${id}/read`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                }
            }).then(() => loadNotifications());
        }
        
        if (requiresAck && !isAcked) {
            // Show acknowledgment modal
            currentAckNotificationId = id;
            document.getElementById('ackNotificationTitle').textContent = notification.title;
            document.getElementById('ackNotificationMessage').textContent = notification.message;
            
            const iconContainer = document.getElementById('ackNotificationIcon');
            iconContainer.className = `notification-type-icon ${notification.type}`;
            iconContainer.innerHTML = `<i class="material-icons-outlined">${getTypeIcon(notification.type)}</i>`;
            
            const modal = new bootstrap.Modal(document.getElementById('acknowledgmentModal'));
            modal.show();
        } else {
            // Show center notification modal
            document.getElementById('centerNotificationTitle').textContent = notification.title;
            document.getElementById('centerNotificationMessage').textContent = notification.message;
            
            const iconContainer = document.getElementById('centerNotificationIcon');
            iconContainer.className = `notification-type-icon ${notification.type}`;
            iconContainer.innerHTML = `<i class="material-icons-outlined">${getTypeIcon(notification.type)}</i>`;
            
            const modal = new bootstrap.Modal(document.getElementById('centerNotificationModal'));
            modal.show();
        }
    };

    // Helper functions
    function getTypeIcon(type) {
        const icons = {
            'info': 'info',
            'warning': 'warning',
            'success': 'check_circle',
            'danger': 'error',
            'announcement': 'campaign'
        };
        return icons[type] || 'notifications';
    }

    function getTimeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diff = Math.floor((now - date) / 1000);
        
        if (diff < 60) return 'Just now';
        if (diff < 3600) return `${Math.floor(diff / 60)} min ago`;
        if (diff < 86400) return `${Math.floor(diff / 3600)} hours ago`;
        if (diff < 604800) return `${Math.floor(diff / 86400)} days ago`;
        return date.toLocaleDateString();
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>
