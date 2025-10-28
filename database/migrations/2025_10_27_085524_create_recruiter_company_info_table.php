<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('recruiter_company_info', function (Blueprint $table) {
            $table->id();

            // ✅ Foreign Keys
            $table->unsignedBigInteger('job_id');
            $table->unsignedBigInteger('recruiter_id');

            // ✅ Columns
            $table->string('person_name'); // contact person full name
            $table->string('phone', 15);   // 10-digit mobile number
            $table->string('additional_contact')->nullable(); // email or alternate contact

            // ✅ Timestamps
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            // ✅ Foreign Key Constraints
            $table->foreign('recruiter_id')
                  ->references('id')
                  ->on('recruiter_profiles')
                  ->onDelete('cascade');

            $table->foreign('job_id')
                  ->references('id')
                  ->on('jobs')
                  ->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('recruiter_company_info');
    }
};
