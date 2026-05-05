<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class QuizAnswerItem extends Model {
    protected $table = 'quiz_answer_items';
    public $timestamps = false;
    protected $fillable = ['id','answer_id','question_id','option_id','result'];
}
