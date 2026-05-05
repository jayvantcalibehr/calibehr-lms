<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class QuizQuestion extends Model {
    protected $table = 'quiz_questions';
    public $timestamps = false;
    protected $fillable = ['id','quiz_id','question_order','question_text','point','question_type','added_by','added_on','updated_by','updated_on'];
    public function options() { return $this->hasMany(QuizQuestionOption::class, 'question_id'); }
}
