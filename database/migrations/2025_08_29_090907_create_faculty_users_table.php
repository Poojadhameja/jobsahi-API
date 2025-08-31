<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('faculty_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id');
            $table->string('name', 100)->nullable();
            $table->string('email', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('password')->nullable();
            $table->enum('role', ['faculty', 'admin'])->default('faculty');
            
            $table->unique('email');
            $table->foreign('institute_id')->references('id')->on('institute_profiles')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('faculty_users');
    }
};