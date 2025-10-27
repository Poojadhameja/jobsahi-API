<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('faculty_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->string('name', 100)->nullable();
            $table->string('email', 100)->unique()->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('password', 255)->nullable();
            $table->enum('role', ['faculty', 'admin'])->default('faculty');
            $table->enum('admin_action', ['pending', 'approved', 'rejected'])->default('pending');

            $table->foreign('institute_id')->references('id')->on('institute_profiles')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('faculty_users');
    }
};
