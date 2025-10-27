<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('otp_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('otp_code', 10);
            $table->enum('purpose', ['signup', 'login', 'forgot_password', 'phone_verify'])->default('signup');
            $table->boolean('is_used')->default(false);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('expires_at');
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('otp_requests');
    }
};
