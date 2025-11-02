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
            $table->enum('target_role', ['student', 'recruiter', 'institute', 'admin', 'all'])->default('all');
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('created_at')->useCurrent();
        });
    }

    public function down(): void {
        Schema::dropIfExists('system_alerts');
    }
};
