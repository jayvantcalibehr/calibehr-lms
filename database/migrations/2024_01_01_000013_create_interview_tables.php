<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * lms_interview               → interviews
 * lms_interview_invite        → interview_invites
 * lms_interview_questions     → interview_questions
 * lms_interview_response      → interview_responses
 * lms_interview_response_video → interview_response_videos
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interviews', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name', 255);
            $table->text('description');
            $table->tinyInteger('type');
            $table->unsignedBigInteger('added_by')->default(0);
            $table->timestamp('added_on')->nullable()->default(null);
            $table->timestamp('updated_on')->nullable()->default(null);
            $table->unsignedBigInteger('updated_by')->default(0);
            $table->tinyInteger('visibility')->default(0)->comment('0: Public, 1: Private');
            $table->tinyInteger('time')->default(0);
            $table->tinyInteger('show_marks')->default(1);
            $table->tinyInteger('status')->default(1)->comment('0: Deleted, 1: Published');

            $table->index('status');
        });

        Schema::create('interview_invites', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('unique_id', 100);
            $table->unsignedBigInteger('interview_id');
            $table->string('email', 255);
            $table->tinyInteger('completed')->default(0)->comment('0: Not started, 1: Started, 2: Completed');
            $table->timestamp('complete_on')->nullable()->default(null);
            $table->timestamp('started_on')->nullable()->default(null);
            $table->timestamp('expire_on')->nullable()->default(null);
            $table->tinyInteger('invite_status')->default(0)->comment('0: Not sent, 1: Sent, 2: Error');
            $table->timestamp('invited_on')->nullable()->default(null);
            $table->unsignedBigInteger('added_by')->default(0);
            $table->timestamp('added_on')->nullable()->default(null);

            $table->unique('unique_id');
            $table->index('interview_id');
            $table->index('email');
            $table->foreign('interview_id')->references('id')->on('interviews')->onDelete('cascade');
        });

        Schema::create('interview_questions', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('interview_id');
            $table->text('question_text');
            $table->tinyInteger('question_time')->default(1)->comment('In minutes');
            $table->tinyInteger('question_view_time')->default(0)->comment('In seconds');
            $table->tinyInteger('status')->default(1)->comment('0: Disabled, 1: Active');
            $table->unsignedBigInteger('added_by')->default(0);
            $table->timestamp('added_on')->nullable()->default(null);
            $table->unsignedBigInteger('updated_by')->default(0);
            $table->timestamp('updated_on')->nullable()->default(null);

            $table->index('interview_id');
            $table->foreign('interview_id')->references('id')->on('interviews')->onDelete('cascade');
        });

        Schema::create('interview_responses', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('interview_id');
            $table->unsignedBigInteger('invite_id');
            $table->unsignedBigInteger('question_id');
            $table->timestamp('started_on')->nullable()->default(null);
            $table->tinyInteger('submitted')->default(0);
            $table->timestamp('submitted_on')->nullable()->default(null);

            $table->index('interview_id');
            $table->index('invite_id');
            $table->index('question_id');
        });

        Schema::create('interview_response_videos', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('response_id');
            $table->text('src');
            $table->timestamp('added_on')->nullable()->default(null);

            $table->index('response_id');
            $table->foreign('response_id')->references('id')->on('interview_responses')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_response_videos');
        Schema::dropIfExists('interview_responses');
        Schema::dropIfExists('interview_questions');
        Schema::dropIfExists('interview_invites');
        Schema::dropIfExists('interviews');
    }
};
