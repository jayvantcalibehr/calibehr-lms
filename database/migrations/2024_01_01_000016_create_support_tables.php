<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * lms_wishlist            → wishlists
 * lms_notification        → push_notification_tokens
 * lms_ldap                → ldap_configs
 * lms_appversion          → app_versions
 * lms_logs_admin_course   → logs_admin_course
 * lms_logs_admin_feature  → logs_admin_feature
 * lms_logs_admin_interview → logs_admin_interview
 * lms_logs_admin_quiz     → logs_admin_quiz
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wishlists', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('course_id');
            $table->timestamp('added_on')->nullable()->default(null);

            $table->unique(['user_id', 'course_id']);
            $table->index('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
        });

        Schema::create('push_notification_tokens', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('emp_id')->default(0);
            $table->string('device_type', 100)->default('');
            $table->text('token');
            $table->dateTime('added_on')->nullable();
            $table->dateTime('updated_on')->nullable();

            $table->index('emp_id');
        });

        Schema::create('ldap_configs', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->string('comment', 100)->default('');
            $table->string('host', 50);
            $table->string('domain', 155);
            $table->string('port', 10)->default('389');
            $table->string('group', 30)->default('');
            $table->unsignedBigInteger('added_by')->default(0);
            $table->dateTime('added_on')->nullable();
            $table->unsignedBigInteger('updated_by')->default(0);
            $table->dateTime('updated_on')->nullable();
            $table->unsignedBigInteger('deleted_by')->default(0);
            $table->dateTime('deleted_on')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->tinyInteger('is_deleted')->default(0);
        });

        Schema::create('app_versions', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->integer('device');
            $table->string('app_version', 10);
            $table->timestamp('updated_date')->useCurrent();
        });

        // ---- Admin Audit Logs ----

        Schema::create('logs_admin_course', function (Blueprint $table) {
            $table->unsignedBigInteger('course_id');
            $table->string('action', 255);
            $table->unsignedBigInteger('done_by')->default(0);
            $table->string('ip', 100)->default('');
            $table->text('user_agent')->nullable();
            $table->timestamp('done_on')->nullable()->default(null);

            $table->index('course_id');
            $table->index('done_by');
        });

        Schema::create('logs_admin_feature', function (Blueprint $table) {
            $table->unsignedBigInteger('course_id');
            $table->string('action', 255);
            $table->unsignedBigInteger('done_by')->default(0);
            $table->string('ip', 100)->default('');
            $table->text('user_agent')->nullable();
            $table->timestamp('done_on')->nullable()->default(null);

            $table->index('course_id');
        });

        Schema::create('logs_admin_interview', function (Blueprint $table) {
            $table->unsignedBigInteger('interview_id');
            $table->string('action', 255);
            $table->unsignedBigInteger('done_by')->default(0);
            $table->string('ip', 100)->default('');
            $table->text('user_agent')->nullable();
            $table->timestamp('done_on')->nullable()->default(null);

            $table->index('interview_id');
        });

        Schema::create('logs_admin_quiz', function (Blueprint $table) {
            $table->unsignedBigInteger('quiz_id');
            $table->string('action', 255);
            $table->unsignedBigInteger('done_by')->default(0);
            $table->string('ip', 100)->default('');
            $table->text('user_agent')->nullable();
            $table->timestamp('done_on')->nullable()->default(null);

            $table->index('quiz_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logs_admin_quiz');
        Schema::dropIfExists('logs_admin_interview');
        Schema::dropIfExists('logs_admin_feature');
        Schema::dropIfExists('logs_admin_course');
        Schema::dropIfExists('app_versions');
        Schema::dropIfExists('ldap_configs');
        Schema::dropIfExists('push_notification_tokens');
        Schema::dropIfExists('wishlists');
    }
};
