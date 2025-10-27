<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('system_alerts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('message')->nullable();
            $table->enum('type', ['info', 'warning', 'error', 'success'])->default('info');
            $table->boolean('is_active')->default(true);
            $table->timestamp('scheduled_at')->nullable(); // for future alert scheduling
            $table->timestamp('expires_at')->nullable();   // alert expiry
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('system_alerts');
    }
};
