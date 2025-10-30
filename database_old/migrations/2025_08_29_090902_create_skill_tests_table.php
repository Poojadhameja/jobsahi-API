<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('skill_tests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->enum('test_platform', ['Zoom', 'Mettl'])->nullable();
            $table->string('test_name')->nullable();
            $table->integer('score')->nullable();
            $table->integer('max_score')->nullable();
            $table->timestamp('completed_at')->useCurrent()->useCurrentOnUpdate();
            $table->boolean('badge_awarded')->default(false);
            $table->boolean('passed')->default(false);
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('student_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('skill_tests');
    }
};