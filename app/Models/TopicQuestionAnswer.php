<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TopicQuestionAnswer extends Model {
    protected $table = 'topic_question_answers';
    public $timestamps = false;
    protected $fillable = ['course_id','chapter_id','topic_id','points','total_points','correct_answers','total_questions','percentage','answered_by','answered_on'];

    public function items()  { return $this->hasMany(TopicQuestionAnswersList::class, 'answer_id'); }
    public function user()   { return $this->belongsTo(User::class, 'answered_by'); }
    public function topic()  { return $this->belongsTo(CourseTopic::class, 'topic_id'); }
}
