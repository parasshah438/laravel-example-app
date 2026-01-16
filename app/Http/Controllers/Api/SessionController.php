<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use App\Models\UserActivityLog;

class SessionController extends Controller
{
    /**
     * Handle heartbeat requests to keep session alive
     */
    public function heartbeat(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['status' => 'unauthenticated'], 401);
        }
        
        $userId = Auth::id();
        $sessionId = session()->getId();
        
        // Update user activity
        Cache::put("user_activity_{$userId}", [
            'last_activity' => Carbon::now(),
            'session_id' => $sessionId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ], now()->addMinutes(config('session.lifetime')));
        
        return response()->json([
            'status' => 'success',
            'last_activity' => Carbon::now()->toISOString(),
            'session_expires_at' => now()->addMinutes(config('session.lifetime'))->toISOString()
        ]);
    }
    
    /**
     * Handle logout beacon requests (for browser close detection)
     * Note: This route is exempt from CSRF protection
     */
    public function logoutBeacon(Request $request)
    {
        $reason = $request->input('reason', 'unknown');
        $userId = $request->input('user_id');
        
        // Map beacon reasons to user-friendly logout reasons
        $logoutReasons = [
            'tab_or_browser_close' => 'tab_close',
            'browser_close' => 'browser_close',
            'page_hidden' => 'page_hidden',
            'page_unload' => 'page_unload',
            'unknown' => 'browser_action'
        ];
        
        $mappedReason = $logoutReasons[$reason] ?? $reason;
        
        // If user_id is provided and user is authenticated, verify they match
        if (Auth::check()) {
            $currentUserId = Auth::id();
            $user = Auth::user();
            
            // Calculate session duration
            $sessionDuration = $this->getSessionDuration($currentUserId);
            
            // Log the logout activity
            UserActivityLog::logLogout($user, $request, $mappedReason, [
                'logout_method' => 'beacon',
                'original_reason' => $reason,
                'session_duration_minutes' => $sessionDuration,
                'browser_action' => true
            ]);
            
            // Log with appropriate level based on environment
            \Log::channel(config('app.env') === 'production' ? 'single' : 'daily')->info("User {$currentUserId} logged out via beacon", [
                'reason' => $reason,
                'mapped_reason' => $mappedReason,
                'ip' => $request->ip(),
                'user_agent' => substr($request->userAgent(), 0, 100) . '...',
                'session_duration' => $sessionDuration
            ]);
            
            // Clear user activity cache
            Cache::forget("user_activity_{$currentUserId}");
            
            // Logout user
            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();
        } elseif ($userId) {
            // If not authenticated but user_id provided, still log the attempt
            UserActivityLog::create([
                'user_id' => $userId,
                'activity_type' => 'logout_attempt',
                'logout_reason' => $mappedReason,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'activity_at' => now(),
                'additional_data' => [
                    'logout_method' => 'beacon',
                    'original_reason' => $reason,
                    'authenticated' => false
                ]
            ]);
            
            // Just clear the cache
            Cache::forget("user_activity_{$userId}");
            \Log::channel(config('app.env') === 'production' ? 'single' : 'daily')->info("Cleared activity cache for user {$userId} via beacon", [
                'reason' => $reason,
                'ip' => $request->ip()
            ]);
        }
        
        return response()->json(['status' => 'success'], 200);
    }
    
    /**
     * Calculate session duration in minutes
     */
    private function getSessionDuration($userId)
    {
        $activity = Cache::get("user_activity_{$userId}");
        
        if ($activity && isset($activity['login_time'])) {
            $loginTime = Carbon::parse($activity['login_time']);
            return $loginTime->diffInMinutes(now());
        }
        
        return null;
    }
    
    /**
     * Get session status information
     */
    public function status(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['status' => 'unauthenticated'], 401);
        }
        
        $userId = Auth::id();
        $activity = Cache::get("user_activity_{$userId}");
        
        if (!$activity) {
            return response()->json(['status' => 'no_activity'], 404);
        }
        
        $lastActivity = Carbon::parse($activity['last_activity']);
        $sessionTimeout = config('session.lifetime'); // minutes
        $timeRemaining = $sessionTimeout - $lastActivity->diffInMinutes(now());
        
        return response()->json([
            'status' => 'authenticated',
            'last_activity' => $lastActivity->toISOString(),
            'time_remaining_minutes' => max(0, $timeRemaining),
            'session_expires_at' => $lastActivity->addMinutes($sessionTimeout)->toISOString()
        ]);
    }
    
    /**
     * Extend session manually
     */
    public function extend(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['status' => 'unauthenticated'], 401);
        }
        
        $userId = Auth::id();
        $sessionId = session()->getId();
        
        // Reset activity to now
        Cache::put("user_activity_{$userId}", [
            'last_activity' => Carbon::now(),
            'session_id' => $sessionId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ], now()->addMinutes(config('session.lifetime')));
        
        return response()->json([
            'status' => 'extended',
            'new_expiry' => now()->addMinutes(config('session.lifetime'))->toISOString()
        ]);
    }
}