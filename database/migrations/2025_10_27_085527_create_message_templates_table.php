<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('short_description')->nullable();
            $table->enum('message_type', ['individual', 'bulk', 'group', 'announcement'])->default('individual');
            $table->enum('delivery_type', ['email', 'sms', 'push', 'all'])->default('all');
            $table->enum('role', ['admin', 'institute', 'student', 'recruiter'])->default('institute');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->enum('category', ['general', 'academic', 'notice', 'finance'])->default('general');
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void {
        Schema::dropIfExists('message_templates');
    }
};
