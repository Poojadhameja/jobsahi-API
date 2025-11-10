<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('institute_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('institute_name', 255)->nullable();
            $table->string('institute_type', 100)->nullable();
            $table->string('website', 255)->nullable();
            $table->text('description')->nullable();
            $table->string('address', 255)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('contact_person', 255)->nullable();
            $table->string('contact_designation', 100)->nullable();
            $table->string('accreditation', 255)->nullable();
            $table->integer('established_year')->nullable();
            $table->string('location', 255)->nullable();
            $table->text('courses_offered')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('modified_at')->useCurrent()->useCurrentOnUpdate();
            $table->timestamp('deleted_at')->nullable();
            $table->enum('admin_action', ['pending', 'approved', 'rejected'])->default('approved');

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('institute_profiles');
    }
};
