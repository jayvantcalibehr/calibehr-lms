<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * lms_categories_master → categories_master
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories_master', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name', 255);
            $table->text('image_url')->nullable();
            $table->unsignedBigInteger('added_by')->default(0);
            $table->timestamp('added_on')->nullable()->default(null);
            $table->unsignedBigInteger('updated_by')->default(0);
            $table->timestamp('updated_on')->nullable()->default(null);
            $table->tinyInteger('status')->default(1)->comment('1: Active, 0: Deleted');

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories_master');
    }
};
