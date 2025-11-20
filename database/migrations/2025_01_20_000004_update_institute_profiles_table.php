<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::table('institute_profiles', function (Blueprint $table) {
            // Step A: Add registration_number field
            if (!Schema::hasColumn('institute_profiles', 'registration_number')) {
                $table->string('registration_number', 255)->nullable()->after('institute_name');
            }
        });

        Schema::table('institute_profiles', function (Blueprint $table) {
            // Step B: Drop removed fields
            if (Schema::hasColumn('institute_profiles', 'city')) {
                $table->dropColumn('city');
            }
            if (Schema::hasColumn('institute_profiles', 'state')) {
                $table->dropColumn('state');
            }
            if (Schema::hasColumn('institute_profiles', 'country')) {
                $table->dropColumn('country');
            }
            if (Schema::hasColumn('institute_profiles', 'location')) {
                $table->dropColumn('location');
            }
            if (Schema::hasColumn('institute_profiles', 'courses_offered')) {
                $table->dropColumn('courses_offered');
            }
        });

        // Step C: Modify institute_type from varchar to ENUM
        // Using raw SQL for ALTER MODIFY
        DB::statement("ALTER TABLE institute_profiles MODIFY institute_type ENUM('Private','Public','Government') DEFAULT 'Private'");
    }

    public function down(): void {
        // Reverse Step C: Change back to varchar
        DB::statement("ALTER TABLE institute_profiles MODIFY institute_type VARCHAR(100) DEFAULT NULL");

        Schema::table('institute_profiles', function (Blueprint $table) {
            // Reverse Step B: Add back removed fields
            if (!Schema::hasColumn('institute_profiles', 'courses_offered')) {
                $table->text('courses_offered')->nullable()->after('established_year');
            }
            if (!Schema::hasColumn('institute_profiles', 'location')) {
                $table->string('location', 255)->nullable()->after('established_year');
            }
            if (!Schema::hasColumn('institute_profiles', 'country')) {
                $table->string('country', 100)->nullable()->after('address');
            }
            if (!Schema::hasColumn('institute_profiles', 'state')) {
                $table->string('state', 100)->nullable()->after('city');
            }
            if (!Schema::hasColumn('institute_profiles', 'city')) {
                $table->string('city', 100)->nullable()->after('address');
            }
        });

        Schema::table('institute_profiles', function (Blueprint $table) {
            // Reverse Step A: Drop registration_number
            if (Schema::hasColumn('institute_profiles', 'registration_number')) {
                $table->dropColumn('registration_number');
            }
        });
    }
};

