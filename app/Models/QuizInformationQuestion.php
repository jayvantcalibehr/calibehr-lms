<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class QuizInformationQuestion extends Model {
    protected $table = 'quiz_information_questions';
    public $timestamps = false;
    protected $fillable = ['id','quiz_id','question_order','question_text','question_type','added_by','added_on','updated_by','updated_on'];
}
