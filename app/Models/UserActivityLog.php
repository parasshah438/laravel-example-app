<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivityLog extends Model
{
    protected $fillable = [
        'user_id',
        'activity_type',
        'logout_reason',
        'ip_address',
        'user_agent',
        'session_id',
        'activity_at',
        'additional_data'
    ];

    protected $casts = [
        'activity_at' => 'datetime',
        'additional_data' => 'array'
    ];

    /**
     * Get the user that owns the activity log
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log user login activity
     */
    public static function logLogin($user, $request, $additionalData = [])
    {
        return self::create([
            'user_id' => $user->id,
            'activity_type' => 'login',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'session_id' => session()->getId(),
            'activity_at' => now(),
            'additional_data' => $additionalData
        ]);
    }

    /**
     * Log user logout activity
     */
    public static function logLogout($user, $request, $reason = null, $additionalData = [])
    {
        return self::create([
            'user_id' => is_object($user) ? $user->id : $user,
            'activity_type' => 'logout',
            'logout_reason' => $reason,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'session_id' => session()->getId(),
            'activity_at' => now(),
            'additional_data' => $additionalData
        ]);
    }

    /**
     * Log session timeout
     */
    public static function logSessionTimeout($userId, $request, $additionalData = [])
    {
        return self::create([
            'user_id' => $userId,
            'activity_type' => 'session_timeout',
            'logout_reason' => 'inactivity_timeout',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'session_id' => session()->getId(),
            'activity_at' => now(),
            'additional_data' => $additionalData
        ]);
    }

    /**
     * Get recent activities for a user
     */
    public static function getRecentActivities($userId, $limit = 50)
    {
        return self::where('user_id', $userId)
            ->orderBy('activity_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get login/logout pairs for a user
     */
    public static function getSessionHistory($userId, $days = 30)
    {
        return self::where('user_id', $userId)
            ->where('activity_at', '>=', now()->subDays($days))
            ->orderBy('activity_at', 'desc')
            ->get()
            ->groupBy(function($item) {
                return $item->activity_at->format('Y-m-d');
            });
    }
}
