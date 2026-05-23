<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('topic_attempt_unlocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('topic_id');
            $table->unsignedBigInteger('course_id');
            $table->unsignedBigInteger('chapter_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('unlocked_by');
            $table->tinyInteger('used')->default(0); // 0=pending, 1=used
            $table->timestamp('unlocked_on')->useCurrent();
            $table->timestamp('used_on')->nullable();
            $table->index(['topic_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('topic_attempt_unlocks');
    }
};
