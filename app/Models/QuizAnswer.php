<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class QuizAnswer extends Model {
    protected $table = 'quiz_answers';
    public $timestamps = false;
    protected $fillable = ['id','quiz_id','invite_id','points','total_points','correct_answers','total_questions','percentage','pass','answered_on','time_up','switch_tabs','ip','user_agent','added_on'];
    public function items() { return $this->hasMany(QuizAnswerItem::class, 'answer_id'); }
}
