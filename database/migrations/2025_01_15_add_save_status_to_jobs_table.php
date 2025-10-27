<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->boolean('save_status')->default(0)->after('status');
            $table->unsignedBigInteger('saved_by_student_id')->nullable()->after('save_status');
            
            // Add index for better performance
            $table->index(['save_status', 'saved_by_student_id'], 'idx_save_status_student');
        });
    }

    public function down()
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->dropIndex('idx_save_status_student');
            $table->dropColumn(['save_status', 'saved_by_student_id']);
        });
    }
};
