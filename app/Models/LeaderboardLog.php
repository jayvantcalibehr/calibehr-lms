<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class LeaderboardLog extends Model {
    protected $table = 'leaderboard_log';
    public $timestamps = false;
    protected $fillable = ['user_id','points','course_id','added_on'];

    public function user()   { return $this->belongsTo(User::class, 'user_id'); }
    public function course() { return $this->belongsTo(Course::class, 'course_id'); }
}
