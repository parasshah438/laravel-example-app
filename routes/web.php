<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Auth::routes();

Route::get('/home', [App\Http\Controllers\HomeController::class, 'index'])->name('home');

// User activity logs (for viewing login/logout history)
Route::get('/my-activity', function () {
    if (!Auth::check()) {
        return redirect()->route('login');
    }
    
    $activities = \App\Models\UserActivityLog::getRecentActivities(Auth::id(), 50);
    
    return view('user-activity', compact('activities'));
})->middleware('auth')->name('user.activity');

// API routes for session management (need web middleware for CSRF and sessions)
Route::prefix('api')->middleware('web')->group(function () {
    Route::post('/heartbeat', [App\Http\Controllers\Api\SessionController::class, 'heartbeat']);
    Route::post('/logout-beacon', [App\Http\Controllers\Api\SessionController::class, 'logoutBeacon']);
    Route::get('/session-status', [App\Http\Controllers\Api\SessionController::class, 'status']);
    Route::post('/extend-session', [App\Http\Controllers\Api\SessionController::class, 'extend']);
});
