<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('user_name');
            $table->string('email');
            $table->string('password')->nullable(); // Nullable for OAuth users
            $table->string('google_id')->nullable(); // OAuth: Google user ID
            $table->string('linkedin_id')->nullable(); // OAuth: LinkedIn user ID
            $table->enum('auth_provider', ['email', 'google', 'linkedin'])->default('email'); // OAuth provider
            $table->enum('role', ['student','recruiter','institute','admin'])->default('student');
            $table->string('phone_number');
            $table->boolean('is_verified')->default(1);
            $table->enum('status', ['active','inactive'])->default('active');
            $table->timestamp('last_activity')->nullable();
            $table->timestamps();
            
            // Add indexes for OAuth fields
            $table->index('google_id');
            $table->index('linkedin_id');
            $table->index('auth_provider');
        });
    }

    public function down(): void {
        Schema::dropIfExists('users');
    }
};
