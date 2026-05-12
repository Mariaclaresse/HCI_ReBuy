// Notification Dropdown Functionality
document.addEventListener('DOMContentLoaded', function() {
    const notificationDropdown = document.querySelector('.notification-dropdown');
    const notificationMenu = document.getElementById('notification-menu');
    
    if (notificationDropdown && notificationMenu) {
        // Show dropdown on hover
        notificationDropdown.addEventListener('mouseenter', function() {
            notificationMenu.classList.add('show');
            
            // Close other dropdowns
            const userDropdown = document.querySelector('.user-dropdown');
            if (userDropdown) {
                userDropdown.classList.remove('active');
            }
        });
        
        // Hide dropdown when mouse leaves
        notificationDropdown.addEventListener('mouseleave', function(e) {
            // Give a small delay to allow moving to dropdown
            setTimeout(() => {
                if (!notificationDropdown.matches(':hover') && !notificationMenu.matches(':hover')) {
                    notificationMenu.classList.remove('show');
                }
            }, 100);
        });
        
        // Also hide when leaving the dropdown menu
        notificationMenu.addEventListener('mouseleave', function(e) {
            setTimeout(() => {
                if (!notificationDropdown.matches(':hover') && !notificationMenu.matches(':hover')) {
                    notificationMenu.classList.remove('show');
                }
            }, 100);
        });
        
        // Mark notification as read when clicked
        const notificationItems = document.querySelectorAll('.notification-item-dropdown');
        notificationItems.forEach(item => {
            item.addEventListener('click', function() {
                const notificationId = this.dataset.notificationId;
                if (notificationId) {
                    markNotificationAsRead(notificationId);
                }
            });
        });
    }
    
    // Auto-refresh notification count every 30 seconds
    setInterval(updateNotificationCount, 30000);
});

// Mark notification as read via AJAX
function markNotificationAsRead(notificationId) {
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'notification_ajax.php', true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.onload = function() {
        if (xhr.status === 200) {
            updateNotificationCount();
            // Remove unread class from the item
            const item = document.querySelector(`[data-notification-id="${notificationId}"]`);
            if (item) {
                item.classList.remove('unread');
            }
        }
    };
    xhr.send('action=mark_read&notification_id=' + notificationId);
}

// Update notification count
function updateNotificationCount() {
    const xhr = new XMLHttpRequest();
    xhr.open('GET', 'notification_ajax.php?action=get_count', true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            const response = JSON.parse(xhr.responseText);
            const countElement = document.querySelector('.notification-count');
            
            if (response.count > 0) {
                if (countElement) {
                    countElement.textContent = response.count;
                } else {
                    // Create count badge if it doesn't exist
                    const notificationBtn = document.getElementById('notification-btn');
                    if (notificationBtn) {
                        const badge = document.createElement('span');
                        badge.className = 'notification-count';
                        badge.textContent = response.count;
                        notificationBtn.appendChild(badge);
                    }
                }
            } else {
                // Remove count badge if count is 0
                if (countElement) {
                    countElement.remove();
                }
            }
        }
    };
    xhr.send();
}

// Real-time notification updates (optional - requires WebSocket or Server-Sent Events)
function setupRealtimeNotifications() {
    if (typeof EventSource !== 'undefined') {
        const eventSource = new EventSource('notification_stream.php');
        
        eventSource.onmessage = function(event) {
            const data = JSON.parse(event.data);
            
            if (data.type === 'new_notification') {
                // Show browser notification if permission granted
                if ('Notification' in window && Notification.permission === 'granted') {
                    showBrowserNotification(data.title, data.message);
                }
                
                // Update notification count
                updateNotificationCount();
                
                // Add new notification to dropdown (if it's open)
                const notificationMenu = document.getElementById('notification-menu');
                if (notificationMenu && notificationMenu.classList.contains('show')) {
                    addNotificationToDropdown(data);
                }
            }
        };
        
        eventSource.onerror = function() {
            console.log('Notification stream error');
        };
    }
}

// Show browser notification
function showBrowserNotification(title, message) {
    const notification = new Notification(title, {
        body: message,
        icon: '/favicon.ico',
        badge: '/favicon.ico'
    });
    
    notification.onclick = function() {
        window.focus();
        notification.close();
    };
    
    // Auto-close after 5 seconds
    setTimeout(function() {
        notification.close();
    }, 5000);
}

// Request notification permission
function requestNotificationPermission() {
    if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission().then(function(permission) {
            if (permission === 'granted') {
                console.log('Notification permission granted');
            }
        });
    }
}

// Add notification to dropdown
function addNotificationToDropdown(notification) {
    const list = document.querySelector('.notification-list-dropdown');
    if (!list) return;
    
    // Remove "no notifications" message if it exists
    const noNotifications = list.querySelector('.no-notifications');
    if (noNotifications) {
        noNotifications.remove();
    }
    
    // Create new notification element
    const item = document.createElement('div');
    item.className = 'notification-item-dropdown unread';
    item.dataset.notificationId = notification.id;
    
    item.innerHTML = `
        <div class="notif-icon">
            <i class="${getNotificationIcon(notification.type)}" style="color: ${getNotificationColor(notification.type)};"></i>
        </div>
        <div class="notif-content">
            <h5>${notification.title}</h5>
            <p>${notification.message.substring(0, 80)}...</p>
            <span class="notif-time">Just now</span>
        </div>
    `;
    
    // Add to the top of the list
    list.insertBefore(item, list.firstChild);
    
    // Limit to 5 items in dropdown
    const items = list.querySelectorAll('.notification-item-dropdown');
    if (items.length > 5) {
        items[items.length - 1].remove();
    }
}

// Helper functions (these should match the PHP functions)
function getNotificationIcon(type) {
    const icons = {
        'promo': 'fas fa-tag',
        'message': 'fas fa-envelope',
        'order': 'fas fa-shopping-cart',
        'system': 'fas fa-info-circle',
        'wishlist': 'fas fa-heart'
    };
    return icons[type] || 'fas fa-bell';
}

function getNotificationColor(type) {
    const colors = {
        'promo': '#ff6b6b',
        'message': '#4ecdc4',
        'order': '#45b7d1',
        'system': '#96ceb4',
        'wishlist': '#ff6b9d'
    };
    return colors[type] || '#96ceb4';
}

// Initialize real-time notifications and request permission
document.addEventListener('DOMContentLoaded', function() {
    requestNotificationPermission();
    setupRealtimeNotifications();
});
