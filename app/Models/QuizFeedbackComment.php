<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class QuizFeedbackComment extends Model {
    protected $table = 'quiz_feedback_comments';
    public $timestamps = false;
    protected $fillable = ['id','feedback_id','reply','added_by','added_on','status'];
}
