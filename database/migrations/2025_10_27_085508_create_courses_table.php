<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
       Schema::create('courses', function (Blueprint $table) {
    $table->id();
    $table->foreignId('institute_id')->constrained('institute_profiles')->onDelete('cascade');
    $table->foreignId('category_id')->nullable()->constrained('course_category')->onDelete('set null');
    $table->string('title');
    $table->text('description')->nullable();
    $table->string('duration')->nullable();
    $table->decimal('fee',10,2)->default(0);
    $table->string('instructor_name')->nullable();
    $table->string('mode')->nullable();
    $table->boolean('certification_allowed')->default(true);
    $table->timestamps();
});

    }

    public function down(): void {
        Schema::dropIfExists('courses');
    }
};
