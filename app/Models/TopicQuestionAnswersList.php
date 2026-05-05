<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TopicQuestionAnswersList extends Model {
    protected $table = 'topic_question_answers_list';
    public $timestamps = false;
    protected $fillable = ['answer_id','question_id','option_id','result'];

    public function answer()   { return $this->belongsTo(TopicQuestionAnswer::class, 'answer_id'); }
    public function question() { return $this->belongsTo(TopicQuestion::class, 'question_id'); }
    public function option()   { return $this->belongsTo(TopicQuestionOption::class, 'option_id'); }
}
