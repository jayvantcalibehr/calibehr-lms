<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * lms_course_master → course_master
 * Data: 1=Online Course, 2=Quiz, 3=Classroom
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_master', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('name', 255);
            $table->tinyInteger('status')->default(1)->comment('0: Disabled, 1: Active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_master');
    }
};
