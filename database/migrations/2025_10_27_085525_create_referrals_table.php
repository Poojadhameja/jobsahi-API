<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('referrer_id'); // user who referred
            $table->string('referee_email'); // email of referred person
            $table->unsignedBigInteger('job_id')->nullable(); // optional job reference
            $table->enum('status', ['pending', 'joined', 'rejected'])->default('pending');
            $table->dateTime('created_at')->useCurrent();
            $table->enum('admin_action', ['pending', 'approved', 'rejected'])->default('pending');

            $table->foreign('referrer_id')->references('id')->on('student_profiles')->onDelete('cascade');
            $table->foreign('job_id')->references('id')->on('jobs')->onDelete('set null');
        });
    }

    public function down(): void {
        Schema::dropIfExists('referrals');
    }
};
