<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->text('action');
            $table->string('reference_table');
            $table->integer('reference_id');
            $table->dateTime('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('activity_logs');
    }
};