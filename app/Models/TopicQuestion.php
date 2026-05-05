<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TopicQuestion extends Model {
    protected $table = 'topic_questions';
    public $timestamps = false;
    protected $fillable = ['id','course_id','chapter_id','topic_id','question_order','question_text','point','question_type','added_by','added_on','updated_by','updated_on'];
    public function options() { return $this->hasMany(TopicQuestionOption::class, 'question_id'); }
}
