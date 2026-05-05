<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * lms_course_chapters_topics_type_master → topic_type_master
 * lms_course_chapters_topics             → course_topics
 *
 * Topic types: 1=Video, 2=PDF, 3=Resource, 4=Test
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topic_type_master', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('name', 255);
            $table->string('icon', 255)->default('');
            $table->tinyInteger('status')->default(1)->comment('1: Active, 0: Disabled');
            $table->timestamp('added_on')->nullable()->default(null);
            $table->timestamp('updated_on')->nullable()->default(null);
        });

        Schema::create('course_topics', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('chapter_id');
            $table->text('name');
            $table->tinyInteger('type')->comment('1=Video, 2=PDF, 3=Resource, 4=Test');
            $table->text('description');
            $table->text('information');
            $table->tinyInteger('passing_percentage')->default(75);
            $table->integer('number_of_attempt')->default(0)->comment('0: Infinite');
            $table->integer('duration')->default(0)->comment('In minutes');
            $table->text('file_url')->nullable();
            $table->tinyInteger('video_type')->default(1);
            $table->tinyInteger('status')->default(1)->comment('1: Active, 0: Inactive');
            $table->unsignedInteger('added_by')->default(0);
            $table->timestamp('added_on')->nullable()->default(null);
            $table->unsignedBigInteger('updated_by')->default(0);
            $table->timestamp('updated_on')->nullable()->default(null);

            $table->index('course_id');
            $table->index('chapter_id');
            $table->index('status');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('chapter_id')->references('id')->on('course_chapters')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_topics');
        Schema::dropIfExists('topic_type_master');
    }
};
