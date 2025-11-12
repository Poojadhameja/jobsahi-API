<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('skill_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('test_id');
            $table->text('question_text');
            $table->string('option_a');
            $table->string('option_b');
            $table->string('option_c');
            $table->string('option_d');
            $table->enum('correct_option', ['A', 'B', 'C', 'D']);
            $table->timestamp('created_at')->useCurrent();

            // Foreign key relation
            $table->foreign('test_id')->references('id')->on('skill_tests')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('skill_questions');
    }
};
