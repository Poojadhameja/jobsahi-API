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
        Schema::create('faculty_users', function (Blueprint $table) {
    $table->id();
    $table->foreignId('institute_id')->constrained('institute_profiles')->onDelete('cascade');
    $table->string('faculty_name');
    $table->string('email')->nullable();
    $table->string('phone_number')->nullable();
    $table->string('qualification')->nullable();
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('faculty_users');
    }
};
