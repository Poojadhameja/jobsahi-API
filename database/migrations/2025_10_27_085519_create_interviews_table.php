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
      Schema::create('interviews', function (Blueprint $table) {
    $table->id();
    $table->foreignId('application_id')->constrained('applications')->onDelete('cascade');
    $table->dateTime('scheduled_at')->nullable();
    $table->string('mode')->nullable();
    $table->string('status')->default('pending');
    $table->timestamps();
});

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interviews');
    }
};
