<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add missing indexes on high-traffic columns.
 * Columns like topic_id, user_id, quiz_id were missing indexes
 * causing full table scans on large datasets.
 */
return new class extends Migration
{
    public function up(): void
    {
        // topic_question_answers — heavily queried for test results & attempt counts
        Schema::table('topic_question_answers', function (Blueprint $table) {
            if (!$this->indexExists('topic_question_answers', 'tqa_topic_user_idx')) {
                $table->index(['topic_id', 'answered_by'], 'tqa_topic_user_idx');
            }
            if (!$this->indexExists('topic_question_answers', 'tqa_course_idx')) {
                $table->index('course_id', 'tqa_course_idx');
            }
        });

        // quiz_answers — queried per invite and quiz
        Schema::table('quiz_answers', function (Blueprint $table) {
            if (!$this->indexExists('quiz_answers', 'qa_quiz_idx')) {
                $table->index('quiz_id', 'qa_quiz_idx');
            }
            if (!$this->indexExists('quiz_answers', 'qa_invite_idx')) {
                $table->index('invite_id', 'qa_invite_idx');
            }
        });

        // quiz_invites — queried by quiz_id and email
        Schema::table('quiz_invites', function (Blueprint $table) {
            if (!$this->indexExists('quiz_invites', 'qi_quiz_idx')) {
                $table->index('quiz_id', 'qi_quiz_idx');
            }
            if (!$this->indexExists('quiz_invites', 'qi_email_idx')) {
                $table->index('email', 'qi_email_idx');
            }
        });

        // leaderboard — always ordered by points
        Schema::table('leaderboard', function (Blueprint $table) {
            if (!$this->indexExists('leaderboard', 'lb_points_idx')) {
                $table->index('points', 'lb_points_idx');
            }
        });

        // topic_attempt_unlocks — queried per topic + user
        Schema::table('topic_attempt_unlocks', function (Blueprint $table) {
            if (!$this->indexExists('topic_attempt_unlocks', 'tau_topic_user_idx')) {
                $table->index(['topic_id', 'user_id'], 'tau_topic_user_idx');
            }
        });

        // interview_invites — queried by unique_id (UUID) for public route
        Schema::table('interview_invites', function (Blueprint $table) {
            if (!$this->indexExists('interview_invites', 'ii_unique_id_idx')) {
                $table->index('unique_id', 'ii_unique_id_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('topic_question_answers', function (Blueprint $table) {
            $table->dropIndex('tqa_topic_user_idx');
            $table->dropIndex('tqa_course_idx');
        });
        Schema::table('quiz_answers', function (Blueprint $table) {
            $table->dropIndex('qa_quiz_idx');
            $table->dropIndex('qa_invite_idx');
        });
        Schema::table('quiz_invites', function (Blueprint $table) {
            $table->dropIndex('qi_quiz_idx');
            $table->dropIndex('qi_email_idx');
        });
        Schema::table('leaderboard', function (Blueprint $table) {
            $table->dropIndex('lb_points_idx');
        });
        Schema::table('topic_attempt_unlocks', function (Blueprint $table) {
            $table->dropIndex('tau_topic_user_idx');
        });
        Schema::table('interview_invites', function (Blueprint $table) {
            $table->dropIndex('ii_unique_id_idx');
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        try {
            $indexes = \DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]);
            return count($indexes) > 0;
        } catch (\Throwable) {
            return false;
        }
    }
};
