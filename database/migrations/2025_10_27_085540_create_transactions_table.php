<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->decimal('amount', 10, 2)->default(0);
            $table->enum('method', ['UPI', 'card', 'wallet', 'cash'])->default('UPI');
            $table->string('purpose')->nullable(); // e.g. plan, resume_boost, highlight
            $table->enum('status', ['success', 'failed', 'pending'])->default('pending');
            $table->timestamp('timestamp')->useCurrent();
            $table->enum('admin_action', ['pending', 'approved', 'rejected'])->default('pending');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('transactions');
    }
};
