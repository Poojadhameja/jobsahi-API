<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('receiver_id')->nullable();
            $table->text('message')->nullable();
            $table->string('type', 50)->nullable();
            $table->boolean('is_read')->default(false);
            $table->enum('received_role', ['student', 'recruiter', 'institute', 'admin'])->nullable();
            $table->dateTime('created_at')->useCurrent();

            // Optional foreign key constraints
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('notifications');
    }
};
