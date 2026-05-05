<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Quiz extends Model {
    protected $table = 'quizzes';
    public $timestamps = false;
    protected $fillable = ['id','name','description','type','points','passing_percentage','number_of_attempt','added_by','added_on','updated_on','updated_by','visibility','time','show_marks','status'];
    public function questions()      { return $this->hasMany(QuizQuestion::class, 'quiz_id')->orderBy('question_order'); }
    public function invites()        { return $this->hasMany(QuizInvite::class, 'quiz_id'); }
    public function infoQuestions()  { return $this->hasMany(QuizInformationQuestion::class, 'quiz_id')->orderBy('question_order'); }
    public function feedbackRatings(){ return $this->hasMany(QuizFeedbackRating::class, 'quiz_id'); }
    public function scopeActive($q)  { return $q->where('status','!=',0); }
}
