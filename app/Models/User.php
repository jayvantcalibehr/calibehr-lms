<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * User Model — merged from cm_login (Mitra) + lms_login (LMS)
 *
 * Authentication: emp_code + emp_password (md5)
 * Primary key:    id  (preserves original Mitra IDs used throughout LMS as mitraID/addedBy etc.)
 */
class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table      = 'users';
    public    $timestamps = false;       // we manage timestamps manually (emp_add_date, updated_on)
    protected $primaryKey = 'id';
    public    $incrementing = false;     // IDs are pre-assigned from old DB
    protected $keyType    = 'integer';

    protected $fillable = [
        'id', 'on_roll',
        'emp_first_name', 'emp_middle_name', 'emp_last_name',
        'emp_username', 'emp_department',
        'emp_phone', 'emp_aadhar', 'emp_aadhar_status',
        'emp_dob', 'emp_pan', 'emp_photo',
        'emp_email', 'emp_code', 'emp_designation',
        'emp_doj', 'emp_location', 'emp_pfno', 'emp_uanno', 'emp_esicno',
        'emp_status_job', 'esic_tic_uploade',
        'emp_password', 'password_changed',
        'emp_type', 'emp_role', 'emp_rm', 'emp_rm_role',
        'emp_status', 'emp_active',
        'emp_client', 'emp_client_department',
        'mitra_status', 'mitra_active', 'last_visited_on',
        'emp_add_date', 'updated_on',
    ];

    protected $hidden = [
        'emp_password',
    ];

    protected $casts = [
        'on_roll'           => 'integer',
        'emp_aadhar_status' => 'integer',
        'password_changed'  => 'integer',
        'emp_department'    => 'integer',
        'emp_role'          => 'integer',
        'emp_rm'            => 'integer',
        'emp_rm_role'       => 'integer',
        'mitra_status'      => 'integer',
        'mitra_active'      => 'integer',
        'last_visited_on'   => 'datetime',
        'emp_add_date'      => 'datetime',
        'updated_on'        => 'datetime',
    ];

    // -------------------------------------------------------------------------
    // Authenticatable override — Laravel uses 'password' column by default
    // -------------------------------------------------------------------------
    public function getAuthPassword(): string
    {
        return $this->emp_password;
    }

    // -------------------------------------------------------------------------
    // Computed attributes
    // -------------------------------------------------------------------------
    public function getFullNameAttribute(): string
    {
        return trim($this->emp_first_name . ' ' . $this->emp_middle_name . ' ' . $this->emp_last_name);
    }

    public function getIsActiveAttribute(): bool
    {
        return $this->emp_status !== 'D';
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------
    public function roles()
    {
        return $this->hasMany(UserRole::class, 'user_id');
    }

    public function roleIds(): array
    {
        return $this->roles->pluck('role_id')->toArray();
    }

    public function hasRole(int $roleId): bool
    {
        return in_array($roleId, $this->roleIds());
    }

    public function isAdmin(): bool    { return $this->hasRole(1); }
    public function isTrainer(): bool  { return $this->hasRole(2); }
    public function isEmployee(): bool { return $this->hasRole(3); }

    public function courseLearners()
    {
        return $this->hasMany(CourseLearner::class, 'learner_id');
    }

    public function leaderboard()
    {
        return $this->hasOne(Leaderboard::class, 'user_id');
    }

    public function wishlist()
    {
        return $this->hasMany(Wishlist::class, 'user_id');
    }

    public function notificationTokens()
    {
        return $this->hasMany(PushNotificationToken::class, 'emp_id');
    }
}
