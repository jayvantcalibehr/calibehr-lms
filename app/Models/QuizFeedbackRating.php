<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class QuizFeedbackRating extends Model {
    protected $table = 'quiz_feedback_ratings';
    public $timestamps = false;
    protected $fillable = ['id','quiz_id','user_id','star','comment','added_on','status','updated_by','updated_on'];
    public function comments() { return $this->hasMany(QuizFeedbackComment::class, 'feedback_id'); }
}
