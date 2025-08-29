<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up()
{
    Schema::create('users', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('email');
        $table->string('password');
        $table->enum('role', ['student', 'recruiter', 'institute', 'admin'])->default('student');
        $table->string('phone_number');
        $table->boolean('is_verified')->default(true)->comment('true, false');
        $table->enum('status', ['active', 'inactive']);
        $table->timestamps();
    });
}

   public function down()
{
    Schema::dropIfExists('users');
}
};