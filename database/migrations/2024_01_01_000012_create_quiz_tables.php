<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * lms_quiz                   → quizzes
 * lms_quiz_questions         → quiz_questions
 * lms_quiz_questions_options → quiz_question_options
 * lms_quiz_information       → quiz_information_questions
 * lms_quiz_information_answers → quiz_information_answers
 * lms_quiz_invites           → quiz_invites
 * lms_quiz_answers           → quiz_answers
 * lms_quiz_answers_list      → quiz_answer_items
 * lms_quiz_feedback          → quiz_feedback_ratings
 * lms_quiz_feedback_comment  → quiz_feedback_comments
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name', 255);
            $table->text('description');
            $table->tinyInteger('type');
            $table->integer('points')->default(1);
            $table->tinyInteger('passing_percentage')->default(75);
            $table->tinyInteger('number_of_attempt')->default(1)->comment('0: Infinite');
            $table->unsignedBigInteger('added_by')->default(0);
            $table->timestamp('added_on')->nullable()->default(null);
            $table->timestamp('updated_on')->nullable()->default(null);
            $table->unsignedBigInteger('updated_by')->default(0);
            $table->tinyInteger('visibility')->default(0)->comment('0: Public, 1: Private');
            $table->tinyInteger('time')->default(0);
            $table->tinyInteger('show_marks')->default(1)->comment('0: Hide, 1: Show');
            $table->tinyInteger('status')->default(1)->comment('0: Deleted, 1: Published');

            $table->index('status');
            $table->index('visibility');
        });

        Schema::create('quiz_questions', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('quiz_id');
            $table->integer('question_order')->default(0);
            $table->text('question_text');
            $table->tinyInteger('point')->default(1);
            $table->tinyInteger('question_type')->default(0)->comment('0: Single, 1: Multiple, 3: Info');
            $table->unsignedBigInteger('added_by')->default(0);
            $table->timestamp('added_on')->nullable()->default(null);
            $table->unsignedBigInteger('updated_by')->default(0);
            $table->timestamp('updated_on')->nullable()->default(null);

            $table->index('quiz_id');
            $table->foreign('quiz_id')->references('id')->on('quizzes')->onDelete('cascade');
        });

        Schema::create('quiz_question_options', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('question_id');
            $table->text('option_text');
            $table->tinyInteger('answer')->default(0)->comment('0: Wrong, 1: Correct');
            $table->integer('value')->default(0);

            $table->index('question_id');
            $table->foreign('question_id')->references('id')->on('quiz_questions')->onDelete('cascade');
        });

        Schema::create('quiz_information_questions', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('quiz_id');
            $table->integer('question_order')->default(0);
            $table->text('question_text');
            $table->tinyInteger('question_type')->default(0);
            $table->unsignedBigInteger('added_by')->default(0);
            $table->timestamp('added_on')->nullable()->default(null);
            $table->unsignedBigInteger('updated_by')->default(0);
            $table->timestamp('updated_on')->nullable()->default(null);

            $table->index('quiz_id');
            $table->foreign('quiz_id')->references('id')->on('quizzes')->onDelete('cascade');
        });

        Schema::create('quiz_information_answers', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('quiz_id');
            $table->unsignedBigInteger('information_id');
            $table->unsignedBigInteger('answer_id');
            $table->text('value');
            $table->unsignedBigInteger('added_by')->default(0);
            $table->timestamp('added_on')->nullable()->default(null);

            $table->index('quiz_id');
            $table->index('answer_id');
        });

        Schema::create('quiz_invites', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('quiz_id');
            $table->string('email', 255);
            $table->unsignedBigInteger('added_by')->default(0);
            $table->timestamp('added_on')->nullable()->default(null);
            $table->tinyInteger('sent_status')->default(0)->comment('0: Not sent, 1: Sent, 2: Error');
            $table->timestamp('sent_on')->nullable()->default(null);
            $table->tinyInteger('status')->default(0)->comment('0: Not completed, 1: Completed');
            $table->timestamp('completed_on')->nullable()->default(null);

            $table->index('quiz_id');
            $table->index('email');
            $table->foreign('quiz_id')->references('id')->on('quizzes')->onDelete('cascade');
        });

        Schema::create('quiz_answers', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('quiz_id');
            $table->unsignedBigInteger('invite_id');
            $table->integer('points')->default(0);
            $table->integer('total_points')->default(0);
            $table->integer('correct_answers')->default(0);
            $table->integer('total_questions')->default(0);
            $table->float('percentage')->default(0);
            $table->tinyInteger('pass')->default(0)->comment('0: Fail, 1: Pass');
            $table->timestamp('answered_on')->nullable()->default(null);
            $table->tinyInteger('time_up')->default(0)->comment('0: Not recorded, 1: Timed out, 2: In time');
            $table->tinyInteger('switch_tabs')->default(0)->comment('0: No issue, 1: Too many tab switches');
            $table->string('ip', 100)->default('');
            $table->text('user_agent')->nullable();
            $table->timestamp('added_on')->nullable()->default(null);

            $table->index('quiz_id');
            $table->index('invite_id');
        });

        Schema::create('quiz_answer_items', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('answer_id');
            $table->unsignedBigInteger('question_id');
            $table->unsignedBigInteger('option_id');
            $table->tinyInteger('result')->default(0)->comment('0: Wrong, 1: Correct');

            $table->index('answer_id');
            $table->foreign('answer_id')->references('id')->on('quiz_answers')->onDelete('cascade');
        });

        Schema::create('quiz_feedback_ratings', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('quiz_id');
            $table->unsignedBigInteger('user_id');
            $table->float('star')->default(0);
            $table->text('comment');
            $table->timestamp('added_on')->nullable()->default(null);
            $table->tinyInteger('status')->default(1)->comment('1: Active, 0: Disabled');
            $table->unsignedBigInteger('updated_by')->default(0);
            $table->timestamp('updated_on')->nullable()->default(null);

            $table->index('quiz_id');
            $table->index('user_id');
        });

        Schema::create('quiz_feedback_comments', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('feedback_id');
            $table->text('reply');
            $table->unsignedBigInteger('added_by')->default(0);
            $table->timestamp('added_on')->nullable()->default(null);
            $table->tinyInteger('status')->default(1);

            $table->index('feedback_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_feedback_comments');
        Schema::dropIfExists('quiz_feedback_ratings');
        Schema::dropIfExists('quiz_answer_items');
        Schema::dropIfExists('quiz_answers');
        Schema::dropIfExists('quiz_invites');
        Schema::dropIfExists('quiz_information_answers');
        Schema::dropIfExists('quiz_information_questions');
        Schema::dropIfExists('quiz_question_options');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('quizzes');
    }
};
