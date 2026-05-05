<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * lms_course_chapters_topics_resource_link → topic_resource_links
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topic_resource_links', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('chapter_id');
            $table->unsignedBigInteger('topic_id');
            $table->string('name', 255);
            $table->text('description');
            $table->text('link');
            $table->tinyInteger('status')->default(1);
            $table->timestamp('updated_on')->nullable()->default(null);
            $table->unsignedBigInteger('added_by')->default(0);
            $table->timestamp('added_on')->nullable()->default(null);

            $table->index('topic_id');
            $table->foreign('topic_id')->references('id')->on('course_topics')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_resource_links');
    }
};
