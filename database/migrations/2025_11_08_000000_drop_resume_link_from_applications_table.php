<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('applications', function (Blueprint $table) {
            if (Schema::hasColumn('applications', 'resume_link')) {
                $table->dropColumn('resume_link');
            }
        });
    }

    public function down(): void {
        Schema::table('applications', function (Blueprint $table) {
            if (!Schema::hasColumn('applications', 'resume_link')) {
                $table->string('resume_link', 255)->nullable();
            }
        });
    }
};


