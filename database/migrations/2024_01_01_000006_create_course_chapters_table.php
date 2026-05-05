<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * lms_course_chapters → course_chapters
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_chapters', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('course_id');
            $table->string('name', 255);
            $table->text('description');
            $table->tinyInteger('status')->default(1)->comment('1: Active, 0: Inactive');
            $table->unsignedBigInteger('added_by')->default(0);
            $table->timestamp('added_on')->nullable()->default(null);
            $table->unsignedBigInteger('updated_by')->default(0);
            $table->timestamp('updated_on')->nullable()->default(null);

            $table->index('course_id');
            $table->index('status');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_chapters');
    }
};
