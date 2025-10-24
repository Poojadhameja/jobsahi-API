<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('certificate_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('institute_id')->nullable();
            $table->string('template_name', 100)->nullable();
            $table->string('logo_url')->nullable();
            $table->string('seal_url')->nullable();
            $table->string('signature_url')->nullable();
            $table->string('header_text')->nullable();
            $table->string('footer_text')->nullable();
            $table->string('background_image_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('institute_id')->references('id')->on('institute_profiles')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('certificate_templates');
    }
};