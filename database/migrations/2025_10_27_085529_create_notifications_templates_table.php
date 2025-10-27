<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('notifications_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->nullable();
            $table->enum('type', ['email', 'sms', 'push'])->nullable();
            $table->string('subject', 255)->nullable();
            $table->text('body')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->string('role', 50)->nullable();
        });
    }

    public function down(): void {
        Schema::dropIfExists('notifications_templates');
    }
};
