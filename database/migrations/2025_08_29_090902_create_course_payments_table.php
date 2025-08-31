<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('course_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('enrollment_id')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 10)->default('INR');
            $table->enum('status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->enum('method', ['card', 'UPI', 'wallet', 'netbanking', 'cash', 'other'])->nullable();
            $table->string('transaction_ref', 100)->nullable();
            $table->longText('gateway_response_json')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->foreign('student_id')->references('id')->on('student_profiles')->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('enrollment_id')->references('id')->on('student_course_enrollments')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('course_payments');
    }
};