<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles_master', function (Blueprint $table) {
            $table->integer('id')->primary();
            $table->text('name');
            $table->tinyInteger('status')->default(1);
            $table->timestamp('added_on')->nullable()->default(null);
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('user_id');
            $table->integer('role_id');
            $table->unsignedBigInteger('added_by')->default(0);
            $table->timestamp('added_on')->nullable()->default(null);

            $table->index('user_id');
            $table->index('role_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('role_id')->references('id')->on('roles_master')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('roles_master');
    }
};
