<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class UserRole extends Model {
    protected $table = 'user_roles';
    public $timestamps = false;
    protected $fillable = ['user_id','role_id','added_by','added_on'];
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
    public function role() { return $this->belongsTo(RoleMaster::class, 'role_id'); }
}
