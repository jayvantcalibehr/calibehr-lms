<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class InterviewQuestion extends Model {
    protected $table = 'interview_questions';
    public $timestamps = false;
    protected $fillable = ['id','interview_id','question_text','question_time','question_view_time','status','added_by','added_on','updated_by','updated_on'];
    public function responses() { return $this->hasMany(InterviewResponse::class, 'question_id'); }
}
