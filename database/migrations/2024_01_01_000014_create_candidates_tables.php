<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * lms_candidate         → candidates
 * lms_candidate_invites → candidate_invites
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('candidates', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('email', 255);
            $table->unsignedBigInteger('added_by')->default(0);
            $table->timestamp('added_on')->nullable()->default(null);

            $table->index('email');
        });

        Schema::create('candidate_invites', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('candidate_id');
            $table->unsignedBigInteger('invite_id');
            $table->integer('type')->comment('1: Quiz, 2: Interview');
            $table->unsignedBigInteger('added_by')->default(0);
            $table->timestamp('added_on')->nullable()->default(null);

            $table->index('candidate_id');
            $table->index('invite_id');
            $table->foreign('candidate_id')->references('id')->on('candidates')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidate_invites');
        Schema::dropIfExists('candidates');
    }
};
