<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('generated_by');
            $table->enum('report_type', [
                'job_summary', 
                'placement_funnel', 
                'revenue_report', 
                'course_performance'
            ])->nullable();
            $table->longText('filters_applied')->nullable();
            $table->text('download_url')->nullable();
            $table->timestamp('generated_at')->useCurrent();
            $table->enum('admin_action', ['pending', 'approved', 'rejected'])->default('pending');

            // optional foreign key
            $table->foreign('generated_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('reports');
    }
};
