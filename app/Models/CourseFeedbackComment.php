<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CourseFeedbackComment extends Model {
    protected $table = 'course_feedback_comments';
    public $timestamps = false;
    protected $fillable = ['id','feedback_id','reply','added_by','added_on','status'];
}
