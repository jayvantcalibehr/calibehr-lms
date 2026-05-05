<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class QuizQuestionOption extends Model {
    protected $table = 'quiz_question_options';
    public $timestamps = false;
    protected $fillable = ['id','question_id','option_text','answer','value'];
}
