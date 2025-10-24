<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('notifications_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->nullable();
            $table->enum('type', ['email', 'sms', 'push'])->nullable();
            $table->string('subject')->nullable();
            $table->text('body')->nullable();
            $table->dateTime('created_at')->useCurrent();
        });
    }

    public function down()
    {
        Schema::dropIfExists('notifications_templates');
    }
};