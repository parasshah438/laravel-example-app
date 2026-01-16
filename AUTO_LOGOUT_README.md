# Auto-Logout Feature for Laravel Application

This Laravel application has been configured with comprehensive auto-logout functionality that automatically logs out users in various scenarios.

## Features

### 1. Session Timeout
- Users are automatically logged out after a period of inactivity
- Default timeout: 30 minutes (configurable)
- Warning shown 5 minutes before logout
- Activity tracking keeps session alive during user interaction

### 2. Browser Close Detection
- Automatically logs out users when browser/tab is closed
- Uses browser `beforeunload` event and beacon API
- Handles page visibility changes (tab switching, minimizing)

### 3. Single Session Enforcement
- Only one active session per user allowed
- Logging in from another device/browser logs out previous sessions
- Prevents concurrent sessions for security

### 4. Real-time Activity Tracking
- Heartbeat system keeps track of user activity
- JavaScript events monitor user interactions
- Server-side middleware tracks and validates sessions

## Configuration

### Environment Variables

Add these to your `.env` file:

```dotenv
# Session configuration
SESSION_DRIVER=database
SESSION_LIFETIME=30
SESSION_EXPIRE_ON_CLOSE=true
SESSION_ENCRYPT=false

# Cache configuration (recommended for session tracking)
CACHE_STORE=database
```

### Session Timeout Settings

You can customize timeout settings in the JavaScript configuration:

```javascript
// In auto-logout.js, modify these values:
window.autoLogout = new AutoLogout({
    heartbeatInterval: 60000,  // Send heartbeat every 1 minute
    warningTime: 300000,       // Show warning 5 minutes before logout
    logoutTime: 1800000,       // Auto logout after 30 minutes of inactivity
});
```

## How It Works

### 1. Middleware (`TrackUserActivity`)
- Tracks user activity on each request
- Stores activity data in cache with session ID
- Enforces single session per user
- Checks for session timeouts

### 2. JavaScript (`auto-logout.js`)
- Monitors user interactions (mouse, keyboard, scroll, touch)
- Sends periodic heartbeat requests to keep session alive
- Detects browser close events and sends logout beacon
- Shows warning modals before automatic logout
- Handles page visibility changes

### 3. API Endpoints
- `POST /api/heartbeat` - Keep session alive
- `POST /api/logout-beacon` - Handle browser close logout
- `GET /api/session-status` - Check session status
- `POST /api/extend-session` - Manually extend session

### 4. Enhanced Login/Logout
- Login controller manages session data
- Displays appropriate messages based on logout reason
- Clears session data on logout

## Usage Examples

### Check Session Status (JavaScript)
```javascript
fetch('/api/session-status')
    .then(response => response.json())
    .then(data => {
        console.log('Time remaining:', data.time_remaining_minutes, 'minutes');
    });
```

### Extend Session Manually (JavaScript)
```javascript
fetch('/api/extend-session', {
    method: 'POST',
    headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
    }
});
```

### Custom Warning Handler
```javascript
window.autoLogout = new AutoLogout({
    warningCallback: function() {
        // Your custom warning implementation
        alert('Session expiring soon!');
    },
    logoutCallback: function() {
        // Your custom logout implementation
        window.location.href = '/login?reason=custom';
    }
});
```

## Installation Steps

1. **Copy the files** - All necessary files have been created
2. **Update your .env** - Add the session configuration variables
3. **Run migrations** - Make sure your database has the sessions table:
   ```bash
   php artisan session:table
   php artisan migrate
   ```
4. **Clear cache**:
   ```bash
   php artisan config:clear
   php artisan cache:clear
   ```

## Logout Scenarios

The system handles these logout scenarios:

1. **Inactivity Timeout** - User inactive for configured time
2. **Browser Close** - User closes browser/tab
3. **Concurrent Login** - User logs in from another device
4. **Manual Logout** - User clicks logout button
5. **Session Expiry** - Server-side session expires

## Security Features

- CSRF protection on all requests
- Session ID regeneration on login
- IP address and user agent tracking
- Secure session invalidation
- Cache-based activity tracking
- Beacon API for reliable browser close detection

## Troubleshooting

### Session not expiring properly
- Check if database sessions table exists
- Verify cache configuration
- Ensure JavaScript file is loaded

### Browser close detection not working
- Check if `navigator.sendBeacon` is supported
- Verify API endpoints are accessible
- Check browser console for errors

### Multiple sessions still allowed
- Verify middleware is applied to web routes
- Check cache store configuration
- Ensure user activity tracking is working

## Maintenance

### Clean up expired sessions
```bash
php artisan sessions:cleanup
```

### Monitor active sessions
Check the cache for `user_activity_*` keys to see active users.

## Customization

You can customize the behavior by:

1. Modifying timeout values in `.env`
2. Customizing JavaScript warnings and callbacks
3. Adding additional activity tracking events
4. Implementing custom session storage logic
5. Adding user activity logging for audit trails