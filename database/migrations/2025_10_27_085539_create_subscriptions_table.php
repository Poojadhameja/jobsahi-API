<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('plan_name');
            $table->enum('type', ['employer', 'institute', 'student'])->default('employer');
            $table->date('start_date');
            $table->date('expiry_date');
            $table->integer('credits_remaining')->default(0);
            $table->enum('admin_action', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();

            // Foreign key relation
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('subscriptions');
    }
};
