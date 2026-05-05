<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CourseFeedbackAnswer extends Model {
    protected $table = 'course_feedback_answers';
    public $timestamps = false;
    protected $fillable = ['course_id','user_id','feedback_question_id','rating','added_on'];

    public function question() { return $this->belongsTo(CourseFeedbackQuestion::class, 'feedback_question_id'); }
}
