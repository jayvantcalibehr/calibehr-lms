<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * lms_course_learners        → course_learners
 * lms_course_learners_status → course_learner_topic_status
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_learners', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('learner_id')->comment('references users.id');
            $table->tinyInteger('status')->default(1);
            $table->unsignedBigInteger('added_by')->default(0);
            $table->timestamp('added_on')->nullable()->default(null);
            $table->timestamp('started_on')->nullable()->default(null);
            $table->tinyInteger('completed')->default(0);
            $table->timestamp('completed_on')->nullable()->default(null);

            $table->index('course_id');
            $table->index('learner_id');
            $table->index(['course_id', 'learner_id']);
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('learner_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('course_learner_topic_status', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('topic_id');
            $table->unsignedBigInteger('user_id')->comment('references users.id');
            $table->integer('time_spent')->default(0)->comment('In seconds');
            $table->timestamp('started_on')->nullable()->default(null);
            $table->tinyInteger('completed')->default(0)->comment('0: Not completed, 1: Complete');
            $table->timestamp('completed_on')->nullable()->default(null);
            $table->tinyInteger('user_marked')->default(0)->comment('1: Marked completed by user');

            $table->index('course_id');
            $table->index('topic_id');
            $table->index('user_id');
            $table->index(['course_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_learner_topic_status');
        Schema::dropIfExists('course_learners');
    }
};
