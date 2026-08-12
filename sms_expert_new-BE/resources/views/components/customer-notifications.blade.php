{{-- Customer Notification System --}}
{{-- Include this component in your customer layouts/app.blade.php --}}

<style>
    /* Notification Bell Icon */
    .notification-bell {
        position: relative;
        cursor: pointer;
    }
    
    .notification-bell .badge {
        position: absolute;
        top: -5px;
        right: -5px;
        font-size: 10px;
        padding: 3px 6px;
        border-radius: 50%;
        background: #dc3545;
        color: white;
    }
    
    /* Notification Dropdown */
    .notification-dropdown {
        width: 350px;
        max-height: 400px;
        overflow-y: auto;
        padding: 0;
    }
    
    .notification-dropdown .dropdown-header {
        background: linear-gradient(135deg, #293b50, #1f2c3d);
        color: white;
        padding: 12px 15px;
        font-weight: bold;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .notification-item {
        padding: 12px 15px;
        border-bottom: 1px solid #eee;
        cursor: pointer;
        transition: background 0.2s;
    }
    
    .notification-item:hover {
        background: #f8f9fa;
    }
    
    .notification-item.unread {
        background: #fff8e6;
    }
    
    .notification-item .notification-title {
        font-weight: 600;
        margin-bottom: 5px;
    }
    
    .notification-item .notification-message {
        font-size: 13px;
        color: #666;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .notification-item .notification-time {
        font-size: 11px;
        color: #999;
        margin-top: 5px;
    }
    
    .notification-type-indicator {
        width: 4px;
        height: 100%;
        position: absolute;
        left: 0;
        top: 0;
    }
    
    .notification-type-info { background: #0dcaf0; }
    .notification-type-warning { background: #ffc107; }
    .notification-type-success { background: #198754; }
    .notification-type-danger { background: #dc3545; }
    
    /* Acknowledgement Popup */
    .notification-acknowledge-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.6);
        z-index: 99999;
        display: flex;
        justify-content: center;
        align-items: center;
        pointer-events: auto;
    }
    
    .notification-acknowledge-popup {
        background: white;
        border-radius: 12px;
        max-width: 500px;
        width: 90%;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
        animation: popupSlideIn 0.3s ease;
        position: relative;
        z-index: 100000;
        pointer-events: auto;
    }
    
    @keyframes popupSlideIn {
        from {
            opacity: 0;
            transform: translateY(-30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .notification-acknowledge-popup .popup-header {
        padding: 20px;
        border-bottom: 1px solid #eee;
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .notification-acknowledge-popup .popup-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        display: flex;
        justify-content: center;
        align-items: center;
        font-size: 24px;
    }
    
    .popup-icon.type-info { background: #e7f5ff; color: #0dcaf0; }
    .popup-icon.type-warning { background: #fff8e6; color: #ffc107; }
    .popup-icon.type-success { background: #e6f7ed; color: #198754; }
    .popup-icon.type-danger { background: #ffe6e6; color: #dc3545; }
    
    .notification-acknowledge-popup .popup-body {
        padding: 20px;
        max-height: 300px;
        overflow-y: auto;
    }
    
    .notification-acknowledge-popup .popup-footer {
        padding: 15px 20px;
        border-top: 1px solid #eee;
        text-align: center;
        position: relative;
        z-index: 100001;
    }
    
    .notification-acknowledge-popup .btn-acknowledge {
        background: linear-gradient(135deg, #ea6118, #d1520e);
        border: none;
        color: white;
        padding: 12px 40px;
        border-radius: 8px;
        font-weight: bold;
        cursor: pointer;
        transition: all 0.3s;
        position: relative;
        z-index: 100002;
        pointer-events: auto;
    }
    
    .notification-acknowledge-popup .btn-acknowledge:hover {
        transform: scale(1.05);
        box-shadow: 0 4px 15px rgba(234, 97, 24, 0.4);
    }
    
    /* Center Notification (non-acknowledgement) */
    .notification-center-popup {
        position: fixed;
        top: 20px;
        right: 20px;
        max-width: 400px;
        background: white;
        border-radius: 10px;
        box-shadow: 0 5px 25px rgba(0, 0, 0, 0.2);
        z-index: 9998;
        animation: slideInRight 0.3s ease;
    }
    
    @keyframes slideInRight {
        from {
            opacity: 0;
            transform: translateX(100px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    .notification-center-popup .popup-content {
        padding: 15px;
        position: relative;
    }
    
    .notification-center-popup .close-btn {
        position: absolute;
        top: 10px;
        right: 10px;
        background: none;
        border: none;
        font-size: 20px;
        cursor: pointer;
        color: #999;
    }
</style>

{{-- Notification Bell (add to your header/navbar) --}}
<div class="dropdown notification-bell" id="notificationBell">
    <a href="#" class="nav-link dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside">
        <i class="material-icons-outlined">notifications</i>
        <span class="badge" id="notificationCount" style="display: none;">0</span>
    </a>
    <div class="dropdown-menu dropdown-menu-end notification-dropdown">
        <div class="dropdown-header">
            <span>Notifications</span>
            <button class="btn btn-sm btn-link text-white" id="markAllRead" style="text-decoration: none;">
                Mark all read
            </button>
        </div>
        <div id="notificationList">
            <div class="text-center py-4 text-muted">
                <i class="material-icons-outlined" style="font-size: 40px;">notifications_none</i>
                <p>No notifications</p>
            </div>
        </div>
    </div>
</div>

{{-- Acknowledgement Popup Container --}}
<div id="acknowledgePopupContainer"></div>

{{-- Center Notification Container --}}
<div id="centerNotificationContainer"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Fetch notifications on page load
    fetchNotifications();
    
    // Check for notifications every 30 seconds
    setInterval(fetchNotifications, 30000);
    
    // Mark all as read
    document.getElementById('markAllRead')?.addEventListener('click', function(e) {
        e.preventDefault();
        markAllAsRead();
    });
});

function fetchNotifications() {
    fetch('/api/customer/notifications/unread')
        .then(response => response.json())
        .then(data => {
            updateNotificationBadge(data.count);
            updateNotificationList(data.notifications);
            
            // Show acknowledgement popup if needed
            if (data.acknowledge_popup) {
                showAcknowledgePopup(data.acknowledge_popup);
            }
        })
        .catch(error => console.error('Error fetching notifications:', error));
}

function updateNotificationBadge(count) {
    const badge = document.getElementById('notificationCount');
    if (count > 0) {
        badge.textContent = count > 99 ? '99+' : count;
        badge.style.display = 'block';
    } else {
        badge.style.display = 'none';
    }
}

function updateNotificationList(notifications) {
    const list = document.getElementById('notificationList');
    
    if (notifications.length === 0) {
        list.innerHTML = `
            <div class="text-center py-4 text-muted">
                <i class="material-icons-outlined" style="font-size: 40px;">notifications_none</i>
                <p>No new notifications</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    notifications.forEach(notification => {
        html += `
            <div class="notification-item ${notification.is_read ? '' : 'unread'}" 
                 onclick="viewNotification(${notification.id})" 
                 style="position: relative;">
                <div class="notification-type-indicator notification-type-${notification.type}"></div>
                <div style="padding-left: 10px;">
                    <div class="notification-title">${escapeHtml(notification.title)}</div>
                    <div class="notification-message">${escapeHtml(notification.message)}</div>
                    <div class="notification-time">${notification.created_at}</div>
                </div>
            </div>
        `;
    });
    
    list.innerHTML = html;
}

function showAcknowledgePopup(notification) {
    const container = document.getElementById('acknowledgePopupContainer');
    
    const iconMap = {
        'info': 'info',
        'warning': 'warning',
        'success': 'check_circle',
        'danger': 'error'
    };
    
    container.innerHTML = `
        <div class="notification-acknowledge-overlay">
            <div class="notification-acknowledge-popup">
                <div class="popup-header">
                    <div class="popup-icon type-${notification.type}">
                        <i class="material-icons-outlined">${iconMap[notification.type] || 'notifications'}</i>
                    </div>
                    <div>
                        <h5 style="margin: 0;">${escapeHtml(notification.title)}</h5>
                        <small style="color: #666;">Important Notification</small>
                    </div>
                </div>
                <div class="popup-body">
                    <p style="white-space: pre-line;">${escapeHtml(notification.message)}</p>
                </div>
                <div class="popup-footer">
                    <button class="btn-acknowledge" onclick="acknowledgeNotification(${notification.id})">
                        <i class="material-icons-outlined" style="vertical-align: middle;">check</i>
                        I Acknowledge
                    </button>
                </div>
            </div>
        </div>
    `;
}

function showCenterNotification(notification) {
    const container = document.getElementById('centerNotificationContainer');
    
    const iconMap = {
        'info': 'info',
        'warning': 'warning',
        'success': 'check_circle',
        'danger': 'error'
    };
    
    const popup = document.createElement('div');
    popup.className = 'notification-center-popup';
    popup.id = `centerNotification-${notification.id}`;
    popup.innerHTML = `
        <div class="popup-content" style="border-left: 4px solid ${getTypeColor(notification.type)};">
            <button class="close-btn" onclick="closeCenterNotification(${notification.id})">×</button>
            <div style="display: flex; align-items: flex-start; gap: 10px;">
                <i class="material-icons-outlined" style="color: ${getTypeColor(notification.type)}; font-size: 28px;">
                    ${iconMap[notification.type] || 'notifications'}
                </i>
                <div>
                    <h6 style="margin: 0 0 5px 0;">${escapeHtml(notification.title)}</h6>
                    <p style="margin: 0; font-size: 14px; color: #666;">${escapeHtml(notification.message)}</p>
                </div>
            </div>
        </div>
    `;
    
    container.appendChild(popup);
    
    // Auto-dismiss after 10 seconds
    setTimeout(() => {
        closeCenterNotification(notification.id);
    }, 10000);
}

function getTypeColor(type) {
    const colors = {
        'info': '#0dcaf0',
        'warning': '#ffc107',
        'success': '#198754',
        'danger': '#dc3545'
    };
    return colors[type] || '#6c757d';
}

function viewNotification(id) {
    fetch(`/api/customer/notifications/${id}/read`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        }
    })
    .then(() => fetchNotifications())
    .catch(error => console.error('Error:', error));
}

function acknowledgeNotification(id) {
    fetch(`/api/customer/notifications/${id}/acknowledge`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.getElementById('acknowledgePopupContainer').innerHTML = '';
            fetchNotifications();
        }
    })
    .catch(error => console.error('Error:', error));
}

function markAllAsRead() {
    fetch('/api/customer/notifications/mark-all-read', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
        }
    })
    .then(() => fetchNotifications())
    .catch(error => console.error('Error:', error));
}

function closeCenterNotification(id) {
    const popup = document.getElementById(`centerNotification-${id}`);
    if (popup) {
        popup.remove();
    }
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>
