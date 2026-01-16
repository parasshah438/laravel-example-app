<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Models\UserActivityLog;

class TrackUserActivity
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        if (Auth::check()) {
            $userId = Auth::id();
            $sessionId = session()->getId();
            
            // Get existing activity data
            $existingActivity = Cache::get("user_activity_{$userId}");
            
            // Check session timeout only if we have existing activity data
            if ($existingActivity) {
                $this->checkSessionTimeout($userId, $existingActivity);
                $this->enforceSingleSession($userId, $sessionId, $existingActivity);
            }
            
            // Update activity after checks (this extends the session)
            Cache::put("user_activity_{$userId}", [
                'last_activity' => Carbon::now(),
                'session_id' => $sessionId,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ], now()->addMinutes(config('session.lifetime', 120)));
        }
        
        return $next($request);
    }
    
    /**
     * Check if session has timed out
     */
    private function checkSessionTimeout($userId, $activity)
    {
        if (!isset($activity['last_activity'])) {
            return; // No activity data, skip check
        }
        
        $lastActivity = Carbon::parse($activity['last_activity']);
        $sessionTimeout = config('session.lifetime', 120); // minutes
        $minutesSinceActivity = $lastActivity->diffInMinutes(now());
        
        // Add some buffer to prevent premature timeouts
        if ($minutesSinceActivity > ($sessionTimeout + 5)) {
            // Log the session timeout
            UserActivityLog::logSessionTimeout($userId, request(), [
                'minutes_since_activity' => $minutesSinceActivity,
                'timeout_threshold' => $sessionTimeout,
                'last_activity' => $lastActivity->toDateTimeString()
            ]);
            
            // Use info level for production, debug level for development
            \Log::channel(config('app.env') === 'production' ? 'single' : 'daily')->info("Session timeout for user {$userId}", [
                'last_activity' => $lastActivity->toDateTimeString(),
                'minutes_since_activity' => $minutesSinceActivity,
                'timeout_threshold' => $sessionTimeout,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent()
            ]);
            
            $this->performLogout($userId, 'Session expired due to inactivity');
            $this->redirectToLogin('timeout', 'Your session has expired due to inactivity.');
        }
    }
    
    /**
     * Enforce single session per user (optional - can be disabled)
     */
    private function enforceSingleSession($userId, $currentSessionId, $activity)
    {
        // Skip single session enforcement if disabled
        if (!config('app.enforce_single_session', false)) {
            return;
        }
        
        if (!isset($activity['session_id'])) {
            return; // No session data, skip check
        }
        
        $storedSessionId = $activity['session_id'];
        
        if ($storedSessionId !== $currentSessionId) {
            // Log the concurrent session logout
            UserActivityLog::create([
                'user_id' => $userId,
                'activity_type' => 'logout',
                'logout_reason' => 'concurrent_session',
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'session_id' => $currentSessionId,
                'activity_at' => now(),
                'additional_data' => [
                    'current_session' => substr($currentSessionId, 0, 10),
                    'stored_session' => substr($storedSessionId, 0, 10),
                    'forced_logout' => true
                ]
            ]);
            
            \Log::channel(config('app.env') === 'production' ? 'single' : 'daily')->info("Multiple sessions detected for user {$userId}", [
                'current_session' => substr($currentSessionId, 0, 10) . '...',
                'stored_session' => substr($storedSessionId, 0, 10) . '...',
                'ip_address' => request()->ip()
            ]);
            
            $this->performLogout($userId, 'Another session was started');
            $this->redirectToLogin('concurrent', 'Another session was started with your account.');
        }
    }
    
    /**
     * Perform logout and cleanup
     */
    private function performLogout($userId, $reason)
    {
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
        Cache::forget("user_activity_{$userId}");
    }
    
    /**
     * Redirect to login with reason
     */
    private function redirectToLogin($reason, $message)
    {
        if (request()->expectsJson()) {
            abort(401, $message);
        } else {
            session()->flash('message', $message);
            header('Location: ' . route('login', ['reason' => $reason]));
            exit();
        }
    }
}