<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->string('title', 255);
            $table->text('description');
            $table->string('duration', 255);
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('tagged_skills', 255)->nullable();
            $table->integer('batch_limit')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->string('instructor_name', 100)->nullable();
            $table->enum('mode', ['online', 'offline', 'hybrid'])->default('offline');
            $table->boolean('certification_allowed')->default(0);
            $table->string('module_title', 255)->nullable();
            $table->text('module_description')->nullable();
            $table->text('media')->nullable();
            $table->decimal('fee', 8, 2);
            $table->enum('admin_action', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('institute_id')->references('id')->on('institute_profiles')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('course_category')->onDelete('set null');
        });
    }

    public function down(): void {
        Schema::dropIfExists('courses');
    }
};
