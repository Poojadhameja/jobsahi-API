<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('recruiter_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('recruiter_profiles', 'gst_pan')) {
                $table->string('gst_pan', 50)->nullable()->after('company_name');
            }
        });
    }

    public function down(): void {
        Schema::table('recruiter_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('recruiter_profiles', 'gst_pan')) {
                $table->dropColumn('gst_pan');
            }
        });
    }
};

