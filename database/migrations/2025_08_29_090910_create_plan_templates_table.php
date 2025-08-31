<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('plan_templates', function (Blueprint $table) {
            $table->id();
            $table->string('plan_name', 100)->nullable();
            $table->enum('type', ['employer', 'institute'])->nullable();
            $table->decimal('price', 10, 2)->nullable();
            $table->integer('duration_days')->nullable();
            $table->text('features')->nullable();
        });
    }

    public function down()
    {
        Schema::dropIfExists('plan_templates');
    }
};