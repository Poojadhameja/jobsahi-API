<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->decimal('amount', 10, 2)->nullable();
            $table->enum('method', ['card', 'UPI', 'wallet'])->nullable();
            $table->enum('purpose', ['plan', 'highlight', 'resume_boost'])->nullable();
            $table->enum('status', ['success', 'failed'])->nullable();
            $table->timestamp('timestamp')->useCurrent();
            
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('transactions');
    }
};