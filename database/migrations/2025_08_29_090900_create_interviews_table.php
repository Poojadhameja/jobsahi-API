<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('application_id');
            $table->dateTime('scheduled_at');
            $table->enum('mode', ['online', 'offline'])->default('online');
            $table->text('location');
            $table->enum('status', ['scheduled', 'completed', 'cancelled'])->default('scheduled');
            $table->text('feedback');
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('application_id')->references('id')->on('applications')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('interviews');
    }
};