<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * MERGED TABLE: users
 *
 * Sources:
 *   - calibehr_mitra.cm_login  → employee master data + password
 *   - calibehr_lms.lms_login   → LMS-side mirror (mitraID, lastVisitedOn)
 *
 * Strategy:
 *   - Primary key = cm_login.id  (same numeric IDs used throughout LMS as mitraID/addedBy/updatedBy etc.)
 *   - Password migrated verbatim (md5 hashed) from cm_login.emp_password
 *   - Authentication: emp_code + password (md5)
 *   - lms_login.mitraID == users.id  → no ID remapping needed in any LMS table
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            // --- Identity (from cm_login) ---
            $table->unsignedBigInteger('id')->primary();  // preserve original IDs (used as mitraID everywhere)
            $table->tinyInteger('on_roll')->default(0)->comment('0: OffRoll, 1: OnRoll');
            $table->string('emp_first_name', 255);
            $table->string('emp_middle_name', 255)->default('');
            $table->string('emp_last_name', 255)->default('');
            $table->string('emp_username', 255)->default('');
            $table->unsignedInteger('emp_department')->default(0);
            $table->string('emp_phone', 100)->default('');
            $table->string('emp_aadhar', 100)->default('');
            $table->tinyInteger('emp_aadhar_status')->default(0)->comment('0: Empty/Rejected, 1: Pending, 2: Filled');
            $table->string('emp_dob', 100)->default('');
            $table->string('emp_pan', 100)->default('');
            $table->text('emp_photo')->nullable();
            $table->string('emp_email', 255)->default('');
            $table->string('emp_code', 50)->unique();
            $table->string('emp_designation', 255)->default('');
            $table->string('emp_doj', 100)->default('');
            $table->string('emp_location', 100)->default('');
            $table->string('emp_pfno', 100)->default('');
            $table->string('emp_uanno', 100)->default('');
            $table->string('emp_esicno', 100)->default('');
            $table->string('emp_status_job', 100)->default('');
            $table->string('esic_tic_uploade', 100)->default('');

            // --- Auth (from cm_login) ---
            $table->string('emp_password', 255)->comment('md5 hash from Mitra');
            $table->integer('password_changed')->default(0)->comment('0: not changed, 1: changed');
            $table->string('emp_type', 100)->default('A')->comment('A: Admin, E: Employee');
            $table->integer('emp_role')->default(0);
            $table->unsignedBigInteger('emp_rm')->default(0)->comment('Reporting Manager ID');
            $table->tinyInteger('emp_rm_role')->default(0);

            // --- Status fields (from cm_login) ---
            $table->string('emp_status', 10)->default('A')->comment('A: Active, D: Disabled');
            $table->string('emp_active', 10)->default('A')->comment('A: Active (employed), R: Resigned');
            $table->unsignedBigInteger('emp_client')->default(0);
            $table->unsignedBigInteger('emp_client_department')->default(0);

            // --- LMS-side fields (from lms_login) ---
            $table->tinyInteger('mitra_status')->default(0)->comment('0: disabled, 1: active in Mitra app');
            $table->tinyInteger('mitra_active')->default(0)->comment('0: disabled, 1: employed');
            $table->timestamp('last_visited_on')->nullable()->default(null);

            // --- Timestamps ---
            $table->timestamp('emp_add_date')->useCurrent();
            $table->timestamp('updated_on')->nullable()->default(null);

            // --- Indexes ---
            $table->index('emp_code');
            $table->index('emp_email');
            $table->index('emp_status');
            $table->index('emp_client');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
