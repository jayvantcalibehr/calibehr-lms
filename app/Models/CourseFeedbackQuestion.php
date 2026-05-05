<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CourseFeedbackQuestion extends Model {
    protected $table = 'course_feedback_questions';
    public $timestamps = false;
    protected $fillable = ['course_id','question_text','status','added_by','added_on','updated_by','updated_on'];

    public function answers() { return $this->hasMany(CourseFeedbackAnswer::class, 'feedback_question_id'); }
}
