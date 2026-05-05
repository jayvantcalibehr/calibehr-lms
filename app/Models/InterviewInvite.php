<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class InterviewInvite extends Model {
    protected $table = 'interview_invites';
    public $timestamps = false;
    protected $fillable = ['id','unique_id','interview_id','email','completed','complete_on','started_on','expire_on','invite_status','invited_on','added_by','added_on'];
    public function interview()  { return $this->belongsTo(Interview::class, 'interview_id'); }
    public function responses()  { return $this->hasMany(InterviewResponse::class, 'invite_id'); }
}
