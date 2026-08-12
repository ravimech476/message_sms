<!-- Notification Bell Icon with Dropdown -->
<li class="nav-item dropdown" id="notificationDropdown">
    <a class="nav-link text-white position-relative d-flex align-items-center justify-content-center"
        href="javascript:;" data-bs-toggle="dropdown" aria-expanded="false" id="notificationBell">
        <i class="material-icons-outlined">notifications</i>
        <span class="position-absolute badge rounded-pill bg-danger notification-badge" id="notificationCount" style="display: none;">
            0
            <span class="visually-hidden">unread notifications</span>
        </span>
    </a>
    <ul class="dropdown-menu dropdown-menu-end notification-dropdown" style="min-width: 380px; max-width: 400px;">
        <li class="dropdown-header d-flex justify-content-between align-items-center px-3 py-2">
            <span class="fw-bold">Notifications</span>
            <small class="text-muted" id="notificationNewCount">0 new</small>
        </li>
        <li><hr class="dropdown-divider m-0"></li>
        <li id="notificationList" style="max-height: 350px; overflow-y: auto;">
            <div class="text-center py-4" id="notificationLoading">
                <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                <p class="small text-muted mb-0 mt-2">Loading...</p>
            </div>
            <div class="text-center py-4" id="notificationEmpty" style="display: none;">
                <i class="material-icons-outlined text-muted" style="font-size: 48px;">notifications_off</i>
                <p class="small text-muted mb-0 mt-2">No notifications</p>
            </div>
        </li>
        <li><hr class="dropdown-divider m-0"></li>
        <li>
            <a class="dropdown-item text-center py-2" href="javascript:;" id="loadMoreNotifications" style="display: none;">
                <small class="text-primary">Load More</small>
            </a>
        </li>
    </ul>
</li>

<!-- Notification Acknowledgment Popup Modal -->
<div class="modal fade" id="notificationAckModal" tabindex="-1" aria-labelledby="notificationAckModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false" style="z-index: 99999;">
    <div class="modal-dialog modal-dialog-centered" style="z-index: 100000;">
        <div class="modal-content" style="border-radius: 16px; border: none; overflow: hidden; position: relative; z-index: 100001;">
            <div class="modal-header" id="ackModalHeader" style="background: linear-gradient(135deg, #293b50, #1f2c3d); color: white; border: none;">
                <div class="d-flex align-items-center">
                    <span class="notification-icon-modal me-2" id="ackModalIcon">
                        <i class="material-icons-outlined">info</i>
                    </span>
                    <h5 class="modal-title mb-0 text-white" id="ackModalTitle">Notification</h5>
                </div>
            </div>
            <div class="modal-body text-center py-4" style="min-height: 150px;">
                <div id="ackModalContent">
                    <p class="mb-3" id="ackModalMessage" style="font-size: 1.1rem; color: #293b50;"></p>
                    <small class="text-muted" id="ackModalTime"></small>
                </div>
            </div>
            <div class="modal-footer justify-content-center border-0 pb-4" style="position: relative; z-index: 100002;">
                <button type="button" class="btn btn-acknowledge" id="ackModalBtn" style="background: linear-gradient(135deg, #ea6118, #d1520e); color: white; padding: 0.75rem 2rem; border-radius: 12px; font-weight: 600; min-width: 150px; position: relative; z-index: 100003; pointer-events: auto; cursor: pointer;">
                    <i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">check_circle</i>
                    I Understand
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    /* Notification Dropdown Styles */
    .notification-dropdown {
        border: none;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        border-radius: 12px !important;
        padding: 0;
    }

    /* Modal z-index fix to ensure it's above sidebar */
    #notificationAckModal {
        z-index: 99999 !important;
    }
    
    #notificationAckModal .modal-dialog {
        z-index: 100000 !important;
    }
    
    #notificationAckModal .modal-content {
        z-index: 100001 !important;
        position: relative;
    }
    
    #notificationAckModal .modal-footer {
        z-index: 100002 !important;
        position: relative;
    }
    
    #notificationAckModal .btn-acknowledge {
        z-index: 100003 !important;
        position: relative;
        pointer-events: auto !important;
        cursor: pointer !important;
    }
    
    /* Ensure modal backdrop is visible but below modal */
    .modal-backdrop.show {
        z-index: 99998 !important;
        opacity: 0.6 !important;
    }

    .notification-item {
        padding: 0.75rem 1rem;
        border-bottom: 1px solid #f0f0f0;
        transition: all 0.2s ease;
        cursor: pointer;
        width: 100%;
        box-sizing: border-box;
    }

    .notification-item:hover {
        background: #f8f9fa;
    }

    .notification-item.unread {
        background: rgba(234, 97, 24, 0.05);
        border-left: 3px solid #ea6118;
    }

    .notification-item .notification-icon {
        width: 40px;
        height: 40px;
        min-width: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .notification-item .notification-icon.type-info { background: rgba(13, 110, 253, 0.1); color: #0d6efd; }
    .notification-item .notification-icon.type-warning { background: rgba(255, 193, 7, 0.1); color: #ffc107; }
    .notification-item .notification-icon.type-success { background: rgba(25, 135, 84, 0.1); color: #198754; }
    .notification-item .notification-icon.type-danger { background: rgba(220, 53, 69, 0.1); color: #dc3545; }
    .notification-item .notification-icon.type-announcement { background: rgba(13, 202, 240, 0.1); color: #0dcaf0; }

    .notification-item .notification-content {
        flex: 1;
        min-width: 0;
        max-width: calc(100% - 55px);
        overflow: hidden;
    }

    .notification-item .notification-title {
        font-weight: 600;
        font-size: 0.9rem;
        color: #293b50;
        margin-bottom: 0.25rem;
        word-wrap: break-word;
        overflow-wrap: break-word;
        white-space: normal;
        line-height: 1.3;
    }

    .notification-item .notification-text {
        font-size: 0.8rem;
        color: #6c757d;
        margin-bottom: 0.25rem;
        word-wrap: break-word;
        overflow-wrap: break-word;
        white-space: normal;
        line-height: 1.4;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    .notification-item .notification-time {
        font-size: 0.75rem;
        color: #adb5bd;
    }

    .notification-item .ack-badge {
        font-size: 0.7rem;
        padding: 0.2rem 0.5rem;
        border-radius: 10px;
        background: #ffc107;
        color: #000;
        flex-shrink: 0;
        white-space: nowrap;
        margin-left: 0.5rem;
    }

    /* Notification header row - fixed alignment for long titles */
    .notification-item .notification-header-row {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        gap: 0.25rem;
        margin-bottom: 0.25rem;
    }

    .notification-item .notification-header-row .notification-title {
        flex: 1;
        min-width: 0;
    }

    .notification-item .d-flex.justify-content-between {
        flex-wrap: wrap;
        gap: 0.25rem;
    }

    /* Acknowledgment Modal Styles */
    .notification-icon-modal {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    #ackModalMessage {
        word-wrap: break-word;
        overflow-wrap: break-word;
        white-space: normal;
        max-width: 100%;
        line-height: 1.5;
    }

    #ackModalTitle {
        word-wrap: break-word;
        overflow-wrap: break-word;
        white-space: normal;
    }

    .btn-acknowledge:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(234, 97, 24, 0.4);
    }

    /* Pulsing animation for pending acknowledgments */
    .has-pending-ack {
        animation: pulse-notification 2s infinite;
    }

    @keyframes pulse-notification {
        0% { box-shadow: 0 0 0 0 rgba(234, 97, 24, 0.4); }
        70% { box-shadow: 0 0 0 10px rgba(234, 97, 24, 0); }
        100% { box-shadow: 0 0 0 0 rgba(234, 97, 24, 0); }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const notificationBell = document.getElementById('notificationBell');
    const notificationCount = document.getElementById('notificationCount');
    const notificationList = document.getElementById('notificationList');
    const notificationLoading = document.getElementById('notificationLoading');
    const notificationEmpty = document.getElementById('notificationEmpty');
    const notificationNewCount = document.getElementById('notificationNewCount');
    const loadMoreBtn = document.getElementById('loadMoreNotifications');
    
    let currentPage = 1;
    let hasMore = false;
    let pendingAcknowledgements = [];

    // Fetch unread count
    function fetchUnreadCount() {
        fetch('/api/notifications/unread-count')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateBadge(data.count);
                }
            })
            .catch(error => console.error('Error fetching unread count:', error));
    }

    // Update badge
    function updateBadge(count) {
        if (count > 0) {
            notificationCount.textContent = count > 99 ? '99+' : count;
            notificationCount.style.display = 'flex';
            notificationNewCount.textContent = count + ' new';
        } else {
            notificationCount.style.display = 'none';
            notificationNewCount.textContent = '0 new';
        }
    }

    // Fetch notifications
    function fetchNotifications(page = 1) {
        if (page === 1) {
            notificationLoading.style.display = 'block';
            notificationEmpty.style.display = 'none';
            // Clear existing notifications except loading
            Array.from(notificationList.children).forEach(child => {
                if (child !== notificationLoading && child !== notificationEmpty) {
                    child.remove();
                }
            });
        }

        fetch(`/api/notifications?page=${page}&per_page=10`)
            .then(response => response.json())
            .then(data => {
                notificationLoading.style.display = 'none';
                
                if (data.success && data.notifications.length > 0) {
                    notificationEmpty.style.display = 'none';
                    
                    data.notifications.forEach(notification => {
                        const item = createNotificationItem(notification);
                        notificationList.insertBefore(item, notificationLoading);
                    });

                    hasMore = data.pagination.current_page < data.pagination.last_page;
                    loadMoreBtn.style.display = hasMore ? 'block' : 'none';
                    currentPage = data.pagination.current_page;
                } else if (page === 1) {
                    notificationEmpty.style.display = 'block';
                }
            })
            .catch(error => {
                console.error('Error fetching notifications:', error);
                notificationLoading.style.display = 'none';
            });
    }

    // Create notification item
    function createNotificationItem(notification) {
        const item = document.createElement('div');
        item.className = `notification-item d-flex align-items-start ${!notification.is_read ? 'unread' : ''}`;
        item.onclick = () => markAsRead(notification.id, item);
        
        const ackBadge = notification.requires_acknowledgment && !notification.is_acknowledged 
            ? '<span class="ack-badge">Action Required</span>' 
            : '';
        
        item.innerHTML = `
            <div class="notification-icon type-${notification.type} me-3">
                <i class="material-icons-outlined">${notification.type_icon}</i>
            </div>
            <div class="notification-content">
                <div class="notification-header-row">
                    <div class="notification-title">${notification.title}</div>
                    ${ackBadge}
                </div>
                <div class="notification-text">${notification.message}</div>
                <div class="notification-time">${notification.created_at}</div>
            </div>
        `;
        
        return item;
    }

    // Mark as read
    function markAsRead(id, element) {
        fetch(`/api/notifications/${id}/read`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || csrfToken
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                element.classList.remove('unread');
                fetchUnreadCount();
            }
        })
        .catch(error => console.error('Error marking as read:', error));
    }

    // Check for pending acknowledgments
    function checkPendingAcknowledgements() {
        fetch('/api/notifications/pending-acknowledgement')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.has_pending && data.notifications.length > 0) {
                    pendingAcknowledgements = data.notifications;
                    showAcknowledgementPopup(pendingAcknowledgements[0]);
                }
            })
            .catch(error => console.error('Error checking pending acknowledgements:', error));
    }

    // Show acknowledgment popup
    function showAcknowledgementPopup(notification) {
        const modal = document.getElementById('notificationAckModal');
        const modalTitle = document.getElementById('ackModalTitle');
        const modalMessage = document.getElementById('ackModalMessage');
        const modalTime = document.getElementById('ackModalTime');
        const modalIcon = document.getElementById('ackModalIcon');
        const modalBtn = document.getElementById('ackModalBtn');

        modalTitle.textContent = notification.title;
        modalMessage.textContent = notification.message;
        modalTime.textContent = notification.created_at_formatted;
        
        // Update icon based on type
        const iconMap = {
            'info': 'info',
            'warning': 'warning',
            'success': 'check_circle',
            'danger': 'error',
            'announcement': 'campaign'
        };
        modalIcon.innerHTML = `<i class="material-icons-outlined">${iconMap[notification.type] || 'info'}</i>`;

        // Set button action
        modalBtn.onclick = () => acknowledgeNotification(notification.id);

        // Show modal
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
        
        // Fix z-index after modal is shown
        setTimeout(() => {
            const backdrop = document.querySelector('.modal-backdrop');
            if (backdrop) {
                backdrop.style.zIndex = '99998';
            }
            modal.style.zIndex = '99999';
        }, 10);
    }

    // Acknowledge notification
    function acknowledgeNotification(id) {
        const btn = document.getElementById('ackModalBtn');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status"></span>Processing...';

        fetch(`/api/notifications/${id}/acknowledge`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || csrfToken
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Close modal
                const modal = bootstrap.Modal.getInstance(document.getElementById('notificationAckModal'));
                modal.hide();
                
                // Remove from pending list and show next if any
                pendingAcknowledgements = pendingAcknowledgements.filter(n => n.id !== id);
                if (pendingAcknowledgements.length > 0) {
                    setTimeout(() => {
                        showAcknowledgementPopup(pendingAcknowledgements[0]);
                    }, 500);
                }
                
                fetchUnreadCount();
                fetchNotifications();
            }
        })
        .catch(error => console.error('Error acknowledging notification:', error))
        .finally(() => {
            btn.disabled = false;
            btn.innerHTML = '<i class="material-icons-outlined me-1" style="font-size: 18px; vertical-align: middle;">check_circle</i> I Understand';
        });
    }

    // Load more
    loadMoreBtn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        if (hasMore) {
            fetchNotifications(currentPage + 1);
        }
    });

    // Load notifications when dropdown opens
    notificationBell.addEventListener('click', function() {
        currentPage = 1;
        fetchNotifications();
    });

    // Initial load
    fetchUnreadCount();
    
    // Check for pending acknowledgments on page load
    setTimeout(checkPendingAcknowledgements, 1000);

    // Refresh count every 30 seconds
    setInterval(fetchUnreadCount, 30000);
    
    // Check for pending acknowledgments every 60 seconds
    setInterval(checkPendingAcknowledgements, 60000);
    
    // Fix modal z-index when modal is shown
    const ackModal = document.getElementById('notificationAckModal');
    if (ackModal) {
        // Move modal to body to avoid z-index stacking context issues
        document.body.appendChild(ackModal);
        
        ackModal.addEventListener('shown.bs.modal', function () {
            // Ensure modal is above everything
            this.style.zIndex = '99999';
            const backdrop = document.querySelector('.modal-backdrop');
            if (backdrop) {
                backdrop.style.zIndex = '99998';
            }
            // Ensure body doesn't block clicks
            document.body.style.overflow = 'hidden';
        });
        
        ackModal.addEventListener('hidden.bs.modal', function () {
            document.body.style.overflow = '';
        });
    }
});
</script>
