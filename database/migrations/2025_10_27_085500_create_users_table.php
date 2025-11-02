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
            $table->string('password');
            $table->enum('role', ['student','recruiter','institute','admin'])->default('student');
            $table->string('phone_number');
            $table->boolean('is_verified')->default(1);
            $table->enum('status', ['active','inactive'])->default('active');
            $table->timestamp('last_activity')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('users');
    }
};
