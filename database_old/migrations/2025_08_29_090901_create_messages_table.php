<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sender_id');
            $table->enum('sender_role', ['student', 'recruiter', 'institute', 'admin']);
            $table->unsignedBigInteger('receiver_id');
            $table->enum('receiver_role', ['student', 'recruiter', 'institute', 'admin']);
            $table->text('message')->nullable();
            $table->string('attachment_url')->nullable();
            $table->enum('attachment_type', ['image', 'pdf', 'doc', 'other'])->nullable();
            $table->enum('type', ['text', 'file', 'system'])->default('text');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void {
        Schema::dropIfExists('messages');
    }
};
