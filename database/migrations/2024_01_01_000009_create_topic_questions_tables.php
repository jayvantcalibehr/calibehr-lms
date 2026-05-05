<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * lms_course_chapters_topics_questions             → topic_questions
 * lms_course_chapters_topics_questions_options     → topic_question_options
 * lms_course_chapters_topics_questions_answers     → topic_question_answers
 * lms_course_chapters_topics_questions_answers_list → topic_question_answers_list
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topic_questions', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('chapter_id');
            $table->unsignedBigInteger('topic_id');
            $table->integer('question_order')->default(0);
            $table->text('question_text');
            $table->tinyInteger('point')->default(1);
            $table->tinyInteger('question_type')->default(0)->comment('0: Single, 1: Multiple, 3: Info');
            $table->unsignedBigInteger('added_by')->default(0);
            $table->timestamp('added_on')->nullable()->default(null);
            $table->unsignedBigInteger('updated_by')->default(0);
            $table->timestamp('updated_on')->nullable()->default(null);

            $table->index('topic_id');
            $table->index('chapter_id');
            $table->foreign('topic_id')->references('id')->on('course_topics')->onDelete('cascade');
        });

        Schema::create('topic_question_options', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('question_id');
            $table->text('option_text');
            $table->tinyInteger('answer')->default(0)->comment('0: Wrong, 1: Correct');
            $table->integer('value')->default(0);

            $table->index('question_id');
            $table->foreign('question_id')->references('id')->on('topic_questions')->onDelete('cascade');
        });

        Schema::create('topic_question_answers', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('chapter_id');
            $table->unsignedBigInteger('topic_id');
            $table->integer('points')->default(0);
            $table->integer('total_points')->default(0);
            $table->integer('correct_answers')->default(0);
            $table->integer('total_questions')->default(0);
            $table->float('percentage')->default(0);
            $table->unsignedBigInteger('answered_by');
            $table->timestamp('answered_on')->nullable()->default(null);

            $table->index('topic_id');
            $table->index('answered_by');
        });

        Schema::create('topic_question_answers_list', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('answer_id');
            $table->unsignedBigInteger('question_id');
            $table->unsignedBigInteger('option_id');
            $table->tinyInteger('result')->default(0)->comment('0: Wrong, 1: Correct');

            $table->index('answer_id');
            $table->foreign('answer_id')->references('id')->on('topic_question_answers')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_question_answers_list');
        Schema::dropIfExists('topic_question_answers');
        Schema::dropIfExists('topic_question_options');
        Schema::dropIfExists('topic_questions');
    }
};
