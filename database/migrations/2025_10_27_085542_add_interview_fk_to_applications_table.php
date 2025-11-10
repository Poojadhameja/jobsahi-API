<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('applications', function (Blueprint $table) {
            if (!Schema::hasColumn('applications', 'interview_id')) {
                $table->unsignedBigInteger('interview_id')->nullable()->after('job_id');
            }

            // ✅ safe foreign key addition (after interviews table exists)
            $table->foreign('interview_id')
                ->references('id')
                ->on('interviews')
                ->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropForeign(['interview_id']);
        });
    }
};
