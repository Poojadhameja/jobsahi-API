<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('skill_tests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->string('test_platform', 50);
            $table->string('test_name');
            $table->integer('score')->default(0);
            $table->integer('max_score')->default(100);
            $table->dateTime('completed_at')->nullable();
            $table->boolean('badge_awarded')->default(false);
            $table->boolean('passed')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('modified_at')->useCurrent()->useCurrentOnUpdate();
            $table->dateTime('deleted_at')->nullable();
            $table->enum('admin_action', ['pending', 'approved', 'rejected'])->default('pending');

            // Foreign key relation
            $table->foreign('student_id')->references('id')->on('student_profiles')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('skill_tests');
    }
};
