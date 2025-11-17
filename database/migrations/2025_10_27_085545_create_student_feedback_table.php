<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('student_feedback', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->string('subject', 255)->nullable();
            $table->text('feedback');
            $table->enum('admin_action', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('student_id')
                ->references('id')
                ->on('student_profiles')
                ->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('student_feedback');
    }
};

