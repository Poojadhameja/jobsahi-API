<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('users', function (Blueprint $table) {
            $table->unsignedBigInteger('id', true); // ✅ Explicit UNSIGNED
            $table->string('user_name');
            $table->string('email')->unique();
            $table->string('password');
            $table->enum('role', ['student','recruiter','institute','admin'])->default('student');
            $table->string('phone_number')->unique();
            $table->boolean('is_verified')->default(true);
            $table->enum('status', ['active','inactive'])->default('active');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('users');
    }
};
