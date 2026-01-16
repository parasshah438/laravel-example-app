<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('user_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('activity_type'); // 'login', 'logout', 'session_timeout', etc.
            $table->string('logout_reason')->nullable(); // 'tab_close', 'browser_close', 'timeout', etc.
            $table->string('ip_address', 45); // IPv4 and IPv6 support
            $table->text('user_agent')->nullable();
            $table->string('session_id')->nullable();
            $table->timestamp('activity_at');
            $table->json('additional_data')->nullable(); // For extra context
            $table->timestamps();
            
            // Indexes for better performance
            $table->index(['user_id', 'activity_at']);
            $table->index(['activity_type', 'activity_at']);
            $table->index('ip_address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_activity_logs');
    }
};
