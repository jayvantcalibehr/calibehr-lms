<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class QuizInvite extends Model {
    protected $table = 'quiz_invites';
    public $timestamps = false;
    protected $fillable = ['id','quiz_id','email','added_by','added_on','sent_status','sent_on','status','completed_on'];
    public function quiz()    { return $this->belongsTo(Quiz::class, 'quiz_id'); }
    public function answers() { return $this->hasMany(QuizAnswer::class, 'invite_id'); }
}
