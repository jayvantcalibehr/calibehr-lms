<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class InterviewResponse extends Model {
    protected $table = 'interview_responses';
    public $timestamps = false;
    protected $fillable = ['id','interview_id','invite_id','question_id','started_on','submitted','submitted_on'];
    public function videos() { return $this->hasMany(InterviewResponseVideo::class, 'response_id'); }
}
