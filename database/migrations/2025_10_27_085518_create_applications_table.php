<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();

            // ✅ Foreign keys (safe ones only)
            $table->unsignedBigInteger('job_id');
            $table->unsignedBigInteger('interview_id')->nullable(); // FK added later via separate migration
            $table->unsignedBigInteger('student_id');

            // ✅ Fields
            $table->boolean('job_selected')->default(0);
            $table->enum('status', ['applied', 'shortlisted', 'rejected', 'selected'])->default('applied');
            $table->dateTime('applied_at');
            $table->string('resume_link', 255);
            $table->text('cover_letter');

            // ✅ System timestamps
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('modified_at')->useCurrent()->useCurrentOnUpdate();
            $table->dateTime('deleted_at')->nullable();
            $table->enum('admin_action', ['pending', 'approved', 'rejected'])->default('pending');

            // ✅ Safe Foreign Key Constraints
            $table->foreign('job_id')
                ->references('id')
                ->on('jobs')
                ->onDelete('cascade');

            $table->foreign('student_id')
                ->references('id')
                ->on('student_profiles')
                ->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['job_id']);
            $table->dropForeign(['student_id']);
        });

        Schema::dropIfExists('applications');
    }
};
