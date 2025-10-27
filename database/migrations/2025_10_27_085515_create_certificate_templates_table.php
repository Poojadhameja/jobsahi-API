<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('certificate_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id')->nullable();
            $table->string('template_name', 100)->nullable();
            $table->string('logo_url', 255)->nullable();
            $table->string('seal_url', 255)->nullable();
            $table->string('signature_url', 255)->nullable();
            $table->string('header_text', 255)->nullable();
            $table->string('footer_text', 255)->nullable();
            $table->string('background_image_url', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('modified_at')->useCurrent()->useCurrentOnUpdate();
            $table->dateTime('deleted_at')->nullable();
            $table->enum('admin_action', ['pending', 'approved', 'rejected'])->default('approved');

            $table->foreign('institute_id')->references('id')->on('institute_profiles')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('certificate_templates');
    }
};
