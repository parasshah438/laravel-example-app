<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Standard API routes with 'api' middleware (stateless)
// Session management routes are in web.php since they need web middleware

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');