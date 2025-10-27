<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->boolean('is_featured')->default(0)->after('status');
            
            // Add index for better performance when filtering featured jobs
            $table->index('is_featured', 'idx_is_featured');
        });
    }

    public function down()
    {
        Schema::table('jobs', function (Blueprint $table) {
            $table->dropIndex('idx_is_featured');
            $table->dropColumn('is_featured');
        });
    }
};
