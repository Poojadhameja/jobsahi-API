<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('title', 100)->nullable();
            $table->enum('type', ['employer', 'institute'])->nullable();
            $table->integer('price')->nullable();
            $table->integer('duration_days')->nullable();
            $table->longText('features_json')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('modified_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void {
        Schema::dropIfExists('plans');
    }
};
