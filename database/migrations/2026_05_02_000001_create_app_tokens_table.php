<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores FCM push notification tokens per (user, device) pair.
 * Mobile app calls /Webservice/addAppToken on login to register.
 *
 * Old project equivalent: app_tokens table managed by Processing/addAppToken.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_tokens', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id');
            $table->text('token');
            $table->string('device', 10)->default('1')->comment('1: Android, 2: iOS');
            $table->timestamp('updated_on')->nullable()->default(null);
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['user_id', 'device']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_tokens');
    }
};
