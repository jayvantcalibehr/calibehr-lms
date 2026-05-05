<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class RoleMaster extends Model {
    protected $table = 'roles_master';
    public $timestamps = false;
    protected $fillable = ['id','name','status','added_on'];
}
