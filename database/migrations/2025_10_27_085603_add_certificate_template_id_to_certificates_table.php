<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('certificates', function (Blueprint $table) {
            if (!Schema::hasColumn('certificates', 'certificate_template_id')) {
                $table->unsignedBigInteger('certificate_template_id')->nullable()->after('id');
            }
        });

        // Add foreign key constraint separately
        Schema::table('certificates', function (Blueprint $table) {
            $table->foreign('certificate_template_id', 'fk_cert_template')
                ->references('id')
                ->on('certificate_templates')
                ->onDelete('set null')
                ->onUpdate('cascade');
        });
    }

    public function down(): void {
        Schema::table('certificates', function (Blueprint $table) {
            $table->dropForeign('fk_cert_template');
            if (Schema::hasColumn('certificates', 'certificate_template_id')) {
                $table->dropColumn('certificate_template_id');
            }
        });
    }
};

