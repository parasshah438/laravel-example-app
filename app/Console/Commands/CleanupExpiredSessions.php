<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class CleanupExpiredSessions extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'sessions:cleanup
                            {--force : Force cleanup without confirmation}
                            {--dry-run : Show what would be cleaned without actually cleaning}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up expired user activity sessions from cache';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $sessionTimeout = config('session.lifetime'); // minutes
        
        if (!$force && !$dryRun) {
            if (!$this->confirm('This will clean up expired user activity sessions. Continue?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }
        
        $expiredSessions = [];
        $activeUsers = [];
        
        // Get all cached user activity keys
        // Note: This is a simplified example. In production, you might want to use Redis SCAN or similar
        $cacheKeys = $this->getCacheKeys('user_activity_*');
        
        foreach ($cacheKeys as $key) {
            $activity = Cache::get($key);
            
            if ($activity && isset($activity['last_activity'])) {
                $lastActivity = Carbon::parse($activity['last_activity']);
                $isExpired = $lastActivity->diffInMinutes(now()) > $sessionTimeout;
                
                if ($isExpired) {
                    $expiredSessions[] = $key;
                    
                    if (!$dryRun) {
                        Cache::forget($key);
                    }
                } else {
                    $activeUsers[] = $key;
                }
            } else {
                // Invalid data, clean it up
                $expiredSessions[] = $key;
                
                if (!$dryRun) {
                    Cache::forget($key);
                }
            }
        }
        
        if ($dryRun) {
            $this->info("DRY RUN - No changes made:");
            $this->info("Would clean up " . count($expiredSessions) . " expired sessions:");
            foreach ($expiredSessions as $session) {
                $this->line("  - {$session}");
            }
        } else {
            $this->info("Cleaned up " . count($expiredSessions) . " expired sessions.");
        }
        
        $this->info("Active sessions: " . count($activeUsers));
        
        return 0;
    }
    
    /**
     * Get cache keys matching a pattern
     * Note: This is a simplified implementation. For Redis, you'd use SCAN command.
     */
    private function getCacheKeys($pattern)
    {
        // This is a placeholder implementation
        // In a real application, you'd implement this based on your cache driver
        // For Redis: use SCAN command
        // For file/database cache: query the storage directly
        
        $keys = [];
        
        // For demonstration, we'll return an empty array
        // In production, implement based on your cache driver
        
        return $keys;
    }
}