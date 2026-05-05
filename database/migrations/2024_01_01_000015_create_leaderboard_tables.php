<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * lms_leaderboard     → leaderboard
 * lms_leaderboard_log → leaderboard_log
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leaderboard', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('points')->default(0);
            $table->unsignedBigInteger('emp_client')->default(0);
            $table->unsignedBigInteger('emp_client_department')->default(0);
            $table->timestamp('added_on')->nullable()->default(null);
            $table->timestamp('updated_on')->nullable()->default(null);

            $table->index('user_id');
            $table->index('points');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::create('leaderboard_log', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->integer('points');
            $table->unsignedBigInteger('course_id');
            $table->timestamp('added_on')->nullable()->default(null);

            $table->index('user_id');
            $table->index('course_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leaderboard_log');
        Schema::dropIfExists('leaderboard');
    }
};
