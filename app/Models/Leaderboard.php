<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Leaderboard extends Model {
    protected $table = 'leaderboard';
    public $timestamps = false;
    protected $fillable = ['id','user_id','points','emp_client','emp_client_department','added_on','updated_on'];
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}
