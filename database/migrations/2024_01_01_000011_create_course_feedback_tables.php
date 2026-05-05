<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * lms_course_feedback_questions        → course_feedback_questions
 * lms_course_feedback_questions_answer → course_feedback_answers
 * lms_feedback                         → course_feedback_ratings
 * lms_feedback_comment                 → course_feedback_comments
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_feedback_questions', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('course_id');
            $table->text('question_text');
            $table->tinyInteger('status')->default(1)->comment('0: Disabled, 1: Active');
            $table->unsignedBigInteger('added_by')->default(0);
            $table->dateTime('added_on')->nullable();
            $table->unsignedBigInteger('updated_by')->default(0);
            $table->dateTime('updated_on')->nullable();

            $table->index('course_id');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
        });

        Schema::create('course_feedback_answers', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('feedback_question_id');
            $table->tinyInteger('rating');
            $table->dateTime('added_on')->nullable();

            $table->index('course_id');
            $table->index('user_id');
            $table->index('feedback_question_id');
        });

        Schema::create('course_feedback_ratings', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('user_id');
            $table->float('star')->default(0);
            $table->text('comment');
            $table->timestamp('added_on')->nullable()->default(null);
            $table->tinyInteger('status')->default(1)->comment('1: Active, 0: Disabled');
            $table->unsignedBigInteger('updated_by')->default(0);
            $table->timestamp('updated_on')->nullable()->default(null);

            $table->index('course_id');
            $table->index('user_id');
        });

        Schema::create('course_feedback_comments', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('feedback_id')->comment('references course_feedback_ratings.id');
            $table->text('reply');
            $table->unsignedBigInteger('added_by')->default(0);
            $table->timestamp('added_on')->nullable()->default(null);
            $table->tinyInteger('status')->default(1)->comment('0: Disabled, 1: Active');

            $table->index('feedback_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_feedback_comments');
        Schema::dropIfExists('course_feedback_ratings');
        Schema::dropIfExists('course_feedback_answers');
        Schema::dropIfExists('course_feedback_questions');
    }
};
