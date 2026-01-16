// Auto logout functionality for Laravel application

class AutoLogout {
    constructor(options = {}) {
        this.options = {
            heartbeatInterval: 60000, // 1 minute
            warningTime: 300000,      // 5 minutes before logout
            logoutTime: 1800000,      // 30 minutes of inactivity
            warningCallback: null,
            logoutCallback: null,
            debug: false,              // Enable debug logging
            ...options
        };
        
        this.lastActivity = Date.now();
        this.warningShown = false;
        this.heartbeatTimer = null;
        this.logoutTimer = null;
        this.lastNavigationAction = 0;
        
        this.init();
    }
    
    debug(message, data = null) {
        if (this.options.debug) {
            console.log(`[AutoLogout] ${message}`, data || '');
        }
    }
    
    init() {
        this.bindEvents();
        this.startHeartbeat();
        this.startLogoutTimer();
        this.handleBeforeUnload();
    }
    
    bindEvents() {
        // Track user activity
        const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart', 'click'];
        events.forEach(event => {
            document.addEventListener(event, () => this.resetActivity(), true);
        });
    }
    
    resetActivity() {
        this.lastActivity = Date.now();
        this.warningShown = false;
        this.startLogoutTimer();
    }
    
    startHeartbeat() {
        this.heartbeatTimer = setInterval(() => {
            this.sendHeartbeat();
        }, this.options.heartbeatInterval);
    }
    
    sendHeartbeat() {
        if (!document.hidden) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            
            fetch('/api/heartbeat', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    timestamp: Date.now()
                })
            }).catch(error => {
                console.warn('Heartbeat failed:', error);
                // If heartbeat fails, user might be logged out, redirect to login
                if (error.status === 401) {
                    window.location.href = '/login?reason=timeout';
                }
            });
        }
    }
    
    startLogoutTimer() {
        if (this.logoutTimer) {
            clearTimeout(this.logoutTimer);
        }
        
        // Warning timer
        const warningDelay = this.options.logoutTime - this.options.warningTime;
        setTimeout(() => {
            if (!this.warningShown && Date.now() - this.lastActivity >= warningDelay) {
                this.showWarning();
            }
        }, warningDelay);
        
        // Logout timer
        this.logoutTimer = setTimeout(() => {
            if (Date.now() - this.lastActivity >= this.options.logoutTime) {
                this.performLogout();
            }
        }, this.options.logoutTime);
    }
    
    showWarning() {
        this.warningShown = true;
        
        if (this.options.warningCallback) {
            this.options.warningCallback();
        } else {
            // Default warning modal
            if (confirm('Your session will expire in 5 minutes due to inactivity. Click OK to continue your session.')) {
                this.resetActivity();
            }
        }
    }
    
    performLogout() {
        if (this.options.logoutCallback) {
            this.options.logoutCallback();
        } else {
            // Send logout request
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            
            fetch('/logout', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken || '',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            }).then(() => {
                window.location.href = '/login?reason=timeout';
            }).catch(() => {
                window.location.href = '/login?reason=timeout';
            });
        }
    }
    
    handleBeforeUnload() {
        // Use localStorage to differentiate between page reload and tab close
        const RELOAD_KEY = 'page_reload_timestamp';
        const SESSION_KEY = 'user_session_active';
        
        // Mark session as active when page loads
        localStorage.setItem(SESSION_KEY, Date.now().toString());
        
        // Track intentional navigation more broadly
        let isIntentionalAction = false;
        const resetIntentionalAction = () => {
            setTimeout(() => { isIntentionalAction = false; }, 500);
        };
        
        // Track ALL user interactions that could cause page reload/navigation
        const actions = ['click', 'keydown', 'submit', 'contextmenu'];
        actions.forEach(action => {
            document.addEventListener(action, (event) => {
                // Any user interaction marks as intentional
                if (action === 'keydown') {
                    // F5, Ctrl+R, Ctrl+Shift+R, Alt+F4, etc.
                    const isRefresh = event.key === 'F5' || 
                                    (event.ctrlKey && (event.key === 'r' || event.key === 'R')) ||
                                    (event.ctrlKey && event.shiftKey && (event.key === 'r' || event.key === 'R'));
                    
                    if (isRefresh) {
                        localStorage.setItem(RELOAD_KEY, Date.now().toString());
                        isIntentionalAction = true;
                        resetIntentionalAction();
                    }
                } else {
                    // Any click, submit, or right-click is intentional
                    isIntentionalAction = true;
                    resetIntentionalAction();
                }
            });
        });
        
        // Handle beforeunload with localStorage-based detection
        window.addEventListener('beforeunload', (event) => {
            const now = Date.now();
            const lastReload = localStorage.getItem(RELOAD_KEY);
            const recentReload = lastReload && (now - parseInt(lastReload)) < 2000; // 2 seconds
            
            // Don't logout if:
            // 1. Recent intentional action
            // 2. Recent reload detected
            // 3. Page has been open for less than 3 seconds (initial load)
            const pageOpenTime = localStorage.getItem(SESSION_KEY);
            const recentPageLoad = pageOpenTime && (now - parseInt(pageOpenTime)) < 3000;
            
            this.debug('beforeunload triggered', {
                isIntentionalAction,
                recentReload,
                recentPageLoad,
                timeSinceLastReload: lastReload ? now - parseInt(lastReload) : 'N/A',
                timeSincePageLoad: pageOpenTime ? now - parseInt(pageOpenTime) : 'N/A'
            });
            
            if (!isIntentionalAction && !recentReload && !recentPageLoad) {
                // This is likely a genuine tab/browser close
                this.debug('Sending logout beacon - genuine close detected');
                
                const data = new URLSearchParams();
                data.append('reason', 'tab_or_browser_close');
                data.append('user_id', document.querySelector('meta[name="user-id"]')?.getAttribute('content') || '');
                
                navigator.sendBeacon('/api/logout-beacon', data);
                
                // Clear session marker since we're logging out
                localStorage.removeItem(SESSION_KEY);
            } else {
                // This is likely a page reload/navigation, update reload timestamp
                this.debug('Page reload/navigation detected - not logging out');
                localStorage.setItem(RELOAD_KEY, now.toString());
            }
        });
        
        // Clean up old localStorage entries on page load
        window.addEventListener('load', () => {
            const now = Date.now();
            const oldReload = localStorage.getItem(RELOAD_KEY);
            
            // Remove reload markers older than 5 minutes
            if (oldReload && (now - parseInt(oldReload)) > 300000) {
                localStorage.removeItem(RELOAD_KEY);
            }
        });
        
        // Handle visibility changes for additional context
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                // Page became visible - reset activity and update session marker
                this.resetActivity();
                localStorage.setItem(SESSION_KEY, Date.now().toString());
            }
        });
        
        // Also handle pagehide event as backup
        window.addEventListener('pagehide', (event) => {
            // If page is being cached (bfcache), don't logout
            if (event.persisted) {
                return;
            }
            
            // Additional check for non-reload scenarios
            const now = Date.now();
            const lastReload = localStorage.getItem(RELOAD_KEY);
            const recentReload = lastReload && (now - parseInt(lastReload)) < 1000;
            
            if (!isIntentionalAction && !recentReload) {
                const data = new URLSearchParams();
                data.append('reason', 'page_unload');
                data.append('user_id', document.querySelector('meta[name="user-id"]')?.getAttribute('content') || '');
                
                navigator.sendBeacon('/api/logout-beacon', data);
            }
        });
    }
    
    sendLogoutBeacon(reason = 'unknown') {
        const data = new URLSearchParams();
        data.append('reason', reason);
        data.append('user_id', document.querySelector('meta[name="user-id"]')?.getAttribute('content') || '');
        
        navigator.sendBeacon('/api/logout-beacon', data);
    }
    
    destroy() {
        if (this.heartbeatTimer) {
            clearInterval(this.heartbeatTimer);
        }
        if (this.logoutTimer) {
            clearTimeout(this.logoutTimer);
        }
    }
}

// Initialize auto logout when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize if user is authenticated
    if (document.querySelector('meta[name="user-authenticated"]')) {
        window.autoLogout = new AutoLogout({
            heartbeatInterval: 60000,  // 1 minute heartbeat
            warningTime: 300000,       // Show warning 5 minutes before logout
            logoutTime: 1800000,       // Logout after 30 minutes of inactivity
            debug: false,              // Disable debug logging for production
            
            warningCallback: function() {
                // Custom warning implementation
                const modal = document.createElement('div');
                modal.innerHTML = `
                    <div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center;">
                        <div style="background: white; padding: 20px; border-radius: 5px; text-align: center; max-width: 400px;">
                            <h3>Session Expiring</h3>
                            <p>Your session will expire in 5 minutes due to inactivity.</p>
                            <button onclick="this.closest('div').remove(); window.autoLogout.resetActivity();" style="background: #007cba; color: white; border: none; padding: 10px 20px; margin: 5px; border-radius: 3px; cursor: pointer;">Continue Session</button>
                            <button onclick="window.autoLogout.performLogout();" style="background: #dc3545; color: white; border: none; padding: 10px 20px; margin: 5px; border-radius: 3px; cursor: pointer;">Logout Now</button>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
            }
        });
    }
});