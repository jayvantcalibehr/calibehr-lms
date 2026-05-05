<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TopicQuestionOption extends Model {
    protected $table = 'topic_question_options';
    public $timestamps = false;
    protected $fillable = ['id','question_id','option_text','answer','value'];
    public function question() { return $this->belongsTo(TopicQuestion::class, 'question_id'); }
}
