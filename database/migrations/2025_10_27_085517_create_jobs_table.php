<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recruiter_id')->constrained('recruiter_profiles')->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained('job_category')->onDelete('set null');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('skills_required')->nullable();
            $table->decimal('salary_min', 10, 2)->default(0);
            $table->decimal('salary_max', 10, 2)->default(0);
            $table->string('job_type')->default('full_time');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
