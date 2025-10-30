<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('interview_panel', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('interview_id');
            $table->string('panelist_name', 100)->nullable();
            $table->text('feedback')->nullable();
            $table->integer('rating')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->enum('admin_action', ['pending', 'approved', 'rejected'])->default('pending');

            $table->foreign('interview_id')->references('id')->on('interviews')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('interview_panel');
    }
};
