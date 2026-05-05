<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * lms_course → courses
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('name', 255);
            $table->text('description');
            $table->text('what_will_you_learn');
            $table->text('pre_requisites');
            $table->text('trainer_details');
            $table->text('image_url');
            $table->unsignedBigInteger('category_id')->default(0);
            $table->tinyInteger('type')->comment('references course_master.id');
            $table->integer('points')->default(1);
            $table->unsignedBigInteger('added_by')->default(0);
            $table->timestamp('added_on')->nullable()->default(null);
            $table->timestamp('updated_on')->nullable()->default(null);
            $table->unsignedBigInteger('updated_by')->default(0);
            $table->tinyInteger('visibility')->default(0)->comment('0: Public, 1: Private');
            $table->tinyInteger('status')->default(1)->comment('0: Deleted, 1: Drafted, 2: Published');
            $table->tinyInteger('featured')->default(0)->comment('0: Not featured, 1: Featured');
            $table->unsignedBigInteger('featured_by')->default(0);
            $table->timestamp('featured_on')->nullable()->default(null);

            $table->index('category_id');
            $table->index('status');
            $table->index('featured');
            $table->index('added_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
