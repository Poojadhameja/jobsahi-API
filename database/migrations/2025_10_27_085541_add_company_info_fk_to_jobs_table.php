<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('jobs', function (Blueprint $table) {
            if (!Schema::hasColumn('jobs', 'company_info_id')) {
                $table->unsignedBigInteger('company_info_id')->nullable();
            }

            $table->foreign('company_info_id')
                ->references('id')
                ->on('recruiter_company_info')
                ->nullOnDelete();
        });
    }

    public function down(): void {
        Schema::table('jobs', function (Blueprint $table) {
            $table->dropForeign(['company_info_id']);
        });
    }
};
