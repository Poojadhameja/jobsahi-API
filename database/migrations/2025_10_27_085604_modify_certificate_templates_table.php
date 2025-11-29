<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void {
        Schema::table('certificate_templates', function (Blueprint $table) {
            // Drop old fields
            if (Schema::hasColumn('certificate_templates', 'logo_url')) {
                $table->dropColumn('logo_url');
            }
            if (Schema::hasColumn('certificate_templates', 'seal_url')) {
                $table->dropColumn('seal_url');
            }
            if (Schema::hasColumn('certificate_templates', 'signature_url')) {
                $table->dropColumn('signature_url');
            }
            if (Schema::hasColumn('certificate_templates', 'header_text')) {
                $table->dropColumn('header_text');
            }
            if (Schema::hasColumn('certificate_templates', 'footer_text')) {
                $table->dropColumn('footer_text');
            }
            if (Schema::hasColumn('certificate_templates', 'background_image_url')) {
                $table->dropColumn('background_image_url');
            }
        });

        Schema::table('certificate_templates', function (Blueprint $table) {
            // Add new fields
            if (!Schema::hasColumn('certificate_templates', 'logo')) {
                $table->string('logo', 255)->nullable()->after('template_name');
            }
            if (!Schema::hasColumn('certificate_templates', 'seal')) {
                $table->string('seal', 255)->nullable()->after('logo');
            }
            if (!Schema::hasColumn('certificate_templates', 'signature')) {
                $table->string('signature', 255)->nullable()->after('seal');
            }
            if (!Schema::hasColumn('certificate_templates', 'description')) {
                $table->text('description')->nullable()->after('signature');
            }
        });

        // Set default value for existing rows and make NOT NULL
        DB::statement("UPDATE certificate_templates SET description = '' WHERE description IS NULL");
        DB::statement("ALTER TABLE certificate_templates MODIFY description TEXT NOT NULL");
    }

    public function down(): void {
        // Make description nullable first before dropping
        DB::statement("ALTER TABLE certificate_templates MODIFY description TEXT NULL");
        
        Schema::table('certificate_templates', function (Blueprint $table) {
            // Reverse: Drop new fields
            if (Schema::hasColumn('certificate_templates', 'description')) {
                $table->dropColumn('description');
            }
            if (Schema::hasColumn('certificate_templates', 'signature')) {
                $table->dropColumn('signature');
            }
            if (Schema::hasColumn('certificate_templates', 'seal')) {
                $table->dropColumn('seal');
            }
            if (Schema::hasColumn('certificate_templates', 'logo')) {
                $table->dropColumn('logo');
            }
        });

        Schema::table('certificate_templates', function (Blueprint $table) {
            // Reverse: Add back old fields
            if (!Schema::hasColumn('certificate_templates', 'logo_url')) {
                $table->string('logo_url', 255)->nullable()->after('template_name');
            }
            if (!Schema::hasColumn('certificate_templates', 'seal_url')) {
                $table->string('seal_url', 255)->nullable()->after('logo_url');
            }
            if (!Schema::hasColumn('certificate_templates', 'signature_url')) {
                $table->string('signature_url', 255)->nullable()->after('seal_url');
            }
            if (!Schema::hasColumn('certificate_templates', 'header_text')) {
                $table->string('header_text', 255)->nullable()->after('signature_url');
            }
            if (!Schema::hasColumn('certificate_templates', 'footer_text')) {
                $table->string('footer_text', 255)->nullable()->after('header_text');
            }
            if (!Schema::hasColumn('certificate_templates', 'background_image_url')) {
                $table->string('background_image_url', 255)->nullable()->after('footer_text');
            }
        });
    }
};

