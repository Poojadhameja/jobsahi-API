<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('saved_jobs', function (Blueprint $table) {
            $table->id();
            
            // ✅ Foreign Keys
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('job_id');
            
            // ✅ Timestamps
            $table->dateTime('saved_at')->useCurrent();
            $table->dateTime('modified_at')->useCurrent()->useCurrentOnUpdate();
            
            // ✅ Foreign Key Constraints
            $table->foreign('student_id')
                ->references('id')
                ->on('student_profiles')
                ->onDelete('cascade');
                
            $table->foreign('job_id')
                ->references('id')
                ->on('jobs')
                ->onDelete('cascade');
            
            // ✅ Indexes
            $table->index('student_id');
            $table->index('job_id');
        });
    }

    public function down(): void {
        Schema::dropIfExists('saved_jobs');
    }
};

