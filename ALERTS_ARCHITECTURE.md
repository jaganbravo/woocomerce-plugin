# Real-Time Alerts Architecture

## 🎯 How to Send Alerts from Browser

### The Challenge:
- Browser can't directly monitor server-side data
- Need to detect changes and notify browser
- Multiple approaches available

---

## 🔄 Architecture Options

### Option 1: Polling (Simplest) ⭐ RECOMMENDED FOR MVP

**How it works:**
- Browser checks server every X seconds
- Server checks for alert conditions
- Returns alerts if any found

**Implementation:**

**1. JavaScript (Browser-side):**
```javascript
// Check for alerts every 30 seconds
setInterval(function() {
    checkAlerts();
}, 30000); // 30 seconds

function checkAlerts() {
    jQuery.ajax({
        url: datavizAi.ajaxUrl,
        type: 'POST',
        data: {
            action: 'dataviz_ai_check_alerts',
            nonce: datavizAi.nonce
        },
        success: function(response) {
            if (response.success && response.data.alerts.length > 0) {
                displayAlerts(response.data.alerts);
            }
        }
    });
}

function displayAlerts(alerts) {
    alerts.forEach(function(alert) {
        // Show browser notification
        if (Notification.permission === 'granted') {
            new Notification(alert.title, {
                body: alert.message,
                icon: '/wp-content/plugins/dataviz-ai-woocommerce/admin/images/icon.png'
            });
        }
        
        // Show in-app notification
        showInAppNotification(alert);
    });
}
```

**2. PHP (Server-side):**
```php
// In class-dataviz-ai-ajax-handler.php
public function handle_check_alerts() {
    check_ajax_referer('dataviz_ai_admin', 'nonce');
    
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(array('message' => 'Unauthorized'), 403);
    }
    
    $alerts = $this->check_alert_conditions();
    
    wp_send_json_success(array(
        'alerts' => $alerts,
        'timestamp' => current_time('mysql')
    ));
}

protected function check_alert_conditions() {
    $alerts = array();
    
    // Check revenue drop
    $revenue_alert = $this->check_revenue_drop();
    if ($revenue_alert) {
        $alerts[] = $revenue_alert;
    }
    
    // Check low stock
    $stock_alert = $this->check_low_stock();
    if ($stock_alert) {
        $alerts[] = $stock_alert;
    }
    
    // Check cart abandonment
    $abandonment_alert = $this->check_cart_abandonment();
    if ($abandonment_alert) {
        $alerts[] = $abandonment_alert;
    }
    
    return $alerts;
}

protected function check_revenue_drop() {
    // Get today's revenue
    $today_revenue = $this->get_revenue_for_date(date('Y-m-d'));
    
    // Get yesterday's revenue
    $yesterday_revenue = $this->get_revenue_for_date(date('Y-m-d', strtotime('-1 day')));
    
    if ($yesterday_revenue > 0) {
        $drop_percentage = (($yesterday_revenue - $today_revenue) / $yesterday_revenue) * 100;
        
        // Alert if drop > 20%
        if ($drop_percentage > 20) {
            return array(
                'type' => 'revenue_drop',
                'title' => 'Revenue Alert',
                'message' => sprintf('Revenue dropped %.1f%% today ($%.2f vs $%.2f)', 
                    $drop_percentage, $today_revenue, $yesterday_revenue),
                'severity' => 'high',
                'action_url' => admin_url('admin.php?page=dataviz-ai&tab=revenue')
            );
        }
    }
    
    return null;
}
```

**Pros:**
- ✅ Simple to implement
- ✅ Works everywhere
- ✅ No special server setup
- ✅ Easy to debug

**Cons:**
- ❌ Not instant (30 second delay)
- ❌ Server load (checks every 30s)
- ❌ Battery drain on mobile

**Time:** 1-2 days
**Complexity:** Low

---

### Option 2: Server-Sent Events (SSE) ⭐ BEST FOR REAL-TIME

**How it works:**
- Server pushes alerts to browser
- Browser keeps connection open
- Instant notifications

**Implementation:**

**1. JavaScript (Browser-side):**
```javascript
// Connect to SSE endpoint
function connectAlerts() {
    const eventSource = new EventSource(
        datavizAi.ajaxUrl + '?action=dataviz_ai_alerts_stream&nonce=' + datavizAi.nonce
    );
    
    eventSource.onmessage = function(event) {
        const alert = JSON.parse(event.data);
        displayAlert(alert);
    };
    
    eventSource.onerror = function(event) {
        console.error('SSE connection error');
        // Reconnect after 5 seconds
        setTimeout(connectAlerts, 5000);
    };
}

// Start connection when page loads
jQuery(document).ready(function() {
    connectAlerts();
});
```

**2. PHP (Server-side):**
```php
// In class-dataviz-ai-ajax-handler.php
public function handle_alerts_stream() {
    // Set headers for SSE
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('Connection: keep-alive');
    
    // Check nonce
    check_ajax_referer('dataviz_ai_admin', 'nonce');
    
    // Keep connection alive
    while (true) {
        // Check for alerts
        $alerts = $this->check_alert_conditions();
        
        if (!empty($alerts)) {
            foreach ($alerts as $alert) {
                echo "data: " . json_encode($alert) . "\n\n";
                flush();
            }
        }
        
        // Send heartbeat every 30 seconds
        echo "data: " . json_encode(array('type' => 'heartbeat')) . "\n\n";
        flush();
        
        // Sleep for 30 seconds
        sleep(30);
        
        // Check if client disconnected
        if (connection_aborted()) {
            break;
        }
    }
}
```

**Pros:**
- ✅ Real-time (instant alerts)
- ✅ Efficient (server pushes)
- ✅ Low latency
- ✅ Works well for alerts

**Cons:**
- ❌ More complex
- ❌ Connection management needed
- ❌ Some hosting issues (timeouts)

**Time:** 2-3 days
**Complexity:** Medium

---

### Option 3: WebSockets (Most Complex)

**How it works:**
- Full-duplex connection
- Server and browser can send messages
- Most real-time option

**Implementation:**
- Requires WebSocket server (Ratchet, Socket.io)
- More complex setup
- Overkill for alerts

**Time:** 1-2 weeks
**Complexity:** High
**Recommendation:** Skip for MVP

---

### Option 4: WordPress Cron + Browser Polling (Hybrid)

**How it works:**
- WordPress cron checks conditions periodically
- Stores alerts in database/transients
- Browser polls for stored alerts

**Implementation:**

**1. WordPress Cron (Server-side):**
```php
// In class-dataviz-ai-loader.php
public function init() {
    // Schedule cron job
    if (!wp_next_scheduled('dataviz_ai_check_alerts')) {
        wp_schedule_event(time(), 'every_5_minutes', 'dataviz_ai_check_alerts');
    }
    
    add_action('dataviz_ai_check_alerts', array($this, 'check_alerts_cron'));
}

public function check_alerts_cron() {
    $alerts = $this->check_alert_conditions();
    
    if (!empty($alerts)) {
        // Store alerts in transient (5 minutes)
        set_transient('dataviz_ai_alerts_' . get_current_user_id(), $alerts, 300);
    }
}
```

**2. Browser Polling:**
```javascript
// Check every 30 seconds
setInterval(function() {
    jQuery.ajax({
        url: datavizAi.ajaxUrl,
        type: 'POST',
        data: {
            action: 'dataviz_ai_get_alerts',
            nonce: datavizAi.nonce
        },
        success: function(response) {
            if (response.success && response.data.alerts.length > 0) {
                displayAlerts(response.data.alerts);
            }
        }
    });
}, 30000);
```

**Pros:**
- ✅ Efficient (cron does heavy lifting)
- ✅ Reliable (WordPress cron)
- ✅ Scalable
- ✅ Works everywhere

**Cons:**
- ❌ Not instant (5 minute delay)
- ❌ Requires cron setup

**Time:** 2-3 days
**Complexity:** Medium

---

## 🎯 Recommended Approach: Hybrid (Cron + Polling)

### Best of Both Worlds:

**Architecture:**
```
WordPress Cron (every 5 min) → Check Conditions → Store Alerts → Browser Polls (every 30s) → Display
```

**Why This Works:**
1. ✅ **Efficient** - Cron does heavy checks
2. ✅ **Reliable** - WordPress cron is stable
3. ✅ **Scalable** - Works with many users
4. ✅ **Simple** - Easy to implement
5. ✅ **Flexible** - Can adjust timing

**Implementation:**

**1. Cron Job (Check conditions):**
```php
// In class-dataviz-ai-alerts.php (new file)
class Dataviz_AI_Alerts {
    
    public function check_alert_conditions() {
        $alerts = array();
        
        // Check revenue drop
        $revenue_alert = $this->check_revenue_drop();
        if ($revenue_alert) {
            $alerts[] = $revenue_alert;
        }
        
        // Check low stock
        $stock_alert = $this->check_low_stock();
        if ($stock_alert) {
            $alerts[] = $stock_alert;
        }
        
        // Check cart abandonment
        $abandonment_alert = $this->check_cart_abandonment();
        if ($abandonment_alert) {
            $alerts[] = $abandonment_alert;
        }
        
        // Store alerts for each admin user
        $admins = get_users(array('role' => 'administrator'));
        foreach ($admins as $admin) {
            if (!empty($alerts)) {
                set_transient(
                    'dataviz_ai_alerts_' . $admin->ID,
                    $alerts,
                    300 // 5 minutes
                );
            }
        }
    }
    
    protected function check_revenue_drop() {
        // Get today's revenue
        $today = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        $today_revenue = $this->get_revenue_for_date($today);
        $yesterday_revenue = $this->get_revenue_for_date($yesterday);
        
        if ($yesterday_revenue > 0) {
            $drop = (($yesterday_revenue - $today_revenue) / $yesterday_revenue) * 100;
            
            if ($drop > 20) {
                return array(
                    'type' => 'revenue_drop',
                    'title' => 'Revenue Alert',
                    'message' => sprintf('Revenue dropped %.1f%% today', $drop),
                    'severity' => 'high',
                    'timestamp' => current_time('mysql')
                );
            }
        }
        
        return null;
    }
    
    protected function check_low_stock() {
        $low_stock_products = $this->data_fetcher->get_low_stock_products(10);
        
        if (count($low_stock_products) >= 5) {
            return array(
                'type' => 'low_stock',
                'title' => 'Low Stock Alert',
                'message' => sprintf('%d products are low in stock', count($low_stock_products)),
                'severity' => 'medium',
                'count' => count($low_stock_products),
                'timestamp' => current_time('mysql')
            );
        }
        
        return null;
    }
}
```

**2. AJAX Endpoint (Get alerts):**
```php
// In class-dataviz-ai-ajax-handler.php
public function handle_get_alerts() {
    check_ajax_referer('dataviz_ai_admin', 'nonce');
    
    if (!current_user_can('manage_woocommerce')) {
        wp_send_json_error(array('message' => 'Unauthorized'), 403);
    }
    
    $user_id = get_current_user_id();
    $alerts = get_transient('dataviz_ai_alerts_' . $user_id);
    
    if ($alerts === false) {
        $alerts = array();
    }
    
    // Mark as read (optional)
    // delete_transient('dataviz_ai_alerts_' . $user_id);
    
    wp_send_json_success(array(
        'alerts' => $alerts,
        'count' => count($alerts)
    ));
}
```

**3. JavaScript (Browser polling):**
```javascript
// In admin/js/admin.js
(function($) {
    'use strict';
    
    var DatavizAlerts = {
        init: function() {
            // Request notification permission
            this.requestPermission();
            
            // Start polling
            this.startPolling();
        },
        
        requestPermission: function() {
            if ('Notification' in window && Notification.permission === 'default') {
                Notification.requestPermission();
            }
        },
        
        startPolling: function() {
            // Check immediately
            this.checkAlerts();
            
            // Then check every 30 seconds
            setInterval(function() {
                DatavizAlerts.checkAlerts();
            }, 30000);
        },
        
        checkAlerts: function() {
            $.ajax({
                url: datavizAi.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dataviz_ai_get_alerts',
                    nonce: datavizAi.nonce
                },
                success: function(response) {
                    if (response.success && response.data.alerts.length > 0) {
                        DatavizAlerts.displayAlerts(response.data.alerts);
                    }
                }
            });
        },
        
        displayAlerts: function(alerts) {
            alerts.forEach(function(alert) {
                // Browser notification
                if (Notification.permission === 'granted') {
                    new Notification(alert.title, {
                        body: alert.message,
                        icon: datavizAi.pluginUrl + '/admin/images/icon.png',
                        tag: alert.type + '_' + alert.timestamp // Prevent duplicates
                    });
                }
                
                // In-app notification
                DatavizAlerts.showInAppNotification(alert);
            });
        },
        
        showInAppNotification: function(alert) {
            // Create notification element
            var $notification = $('<div>')
                .addClass('dataviz-alert')
                .addClass('dataviz-alert-' + alert.severity)
                .html(
                    '<strong>' + alert.title + '</strong><br>' +
                    alert.message +
                    '<button class="dataviz-alert-close">&times;</button>'
                );
            
            // Add to page
            $('body').append($notification);
            
            // Auto-remove after 5 seconds
            setTimeout(function() {
                $notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
            
            // Close button
            $notification.find('.dataviz-alert-close').on('click', function() {
                $notification.fadeOut(function() {
                    $(this).remove();
                });
            });
        }
    };
    
    // Initialize on page load
    $(document).ready(function() {
        DatavizAlerts.init();
    });
    
})(jQuery);
```

**4. CSS (Styling):**
```css
/* In admin/css/admin.css */
.dataviz-alert {
    position: fixed;
    top: 20px;
    right: 20px;
    background: #fff;
    border-left: 4px solid #0073aa;
    padding: 15px 20px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
    z-index: 100000;
    max-width: 400px;
    animation: slideIn 0.3s ease-out;
}

.dataviz-alert-high {
    border-left-color: #dc3232;
}

.dataviz-alert-medium {
    border-left-color: #ffb900;
}

.dataviz-alert-low {
    border-left-color: #46b450;
}

.dataviz-alert-close {
    float: right;
    background: none;
    border: none;
    font-size: 20px;
    cursor: pointer;
    color: #666;
}

@keyframes slideIn {
    from {
        transform: translateX(100%);
    }
    to {
        transform: translateX(0);
    }
}
```

---

## 📋 Implementation Checklist

### Step 1: Create Alerts Class
- [ ] Create `class-dataviz-ai-alerts.php`
- [ ] Add alert condition checks
- [ ] Store alerts in transients

### Step 2: Setup Cron
- [ ] Register cron job
- [ ] Schedule every 5 minutes
- [ ] Test cron execution

### Step 3: AJAX Endpoint
- [ ] Add `handle_get_alerts()` method
- [ ] Return stored alerts
- [ ] Handle permissions

### Step 4: JavaScript
- [ ] Request notification permission
- [ ] Poll every 30 seconds
- [ ] Display browser notifications
- [ ] Display in-app notifications

### Step 5: CSS
- [ ] Style notification elements
- [ ] Add animations
- [ ] Make responsive

---

## ✅ Recommended: Hybrid Approach

**Architecture:**
- WordPress Cron (every 5 min) → Check conditions
- Store alerts in transients
- Browser polls (every 30s) → Get alerts
- Display notifications

**Why:**
- ✅ Efficient (cron does heavy work)
- ✅ Reliable (WordPress cron)
- ✅ Simple (easy to implement)
- ✅ Scalable (works with many users)
- ✅ Flexible (adjust timing easily)

**Time:** 2-3 days
**Complexity:** Medium

---

## 🎯 Quick Start

1. **Create alerts class** - Check conditions
2. **Setup cron** - Run every 5 minutes
3. **AJAX endpoint** - Return alerts
4. **JavaScript polling** - Check every 30 seconds
5. **Display notifications** - Browser + in-app

This gives you real-time alerts without complex infrastructure!

