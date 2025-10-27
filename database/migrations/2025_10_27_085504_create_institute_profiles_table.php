<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('institute_profiles', function (Blueprint $table) {
            $table->unsignedBigInteger('id', true); // ✅ Unsigned ID
            $table->unsignedBigInteger('user_id')->nullable(); // ✅ Match type with users.id
            $table->string('institute_name');
            $table->string('registration_no')->nullable();
            $table->string('institute_type')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->timestamps();

            // ✅ Add FK after table creation (safe method)
            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('institute_profiles');
    }
};
