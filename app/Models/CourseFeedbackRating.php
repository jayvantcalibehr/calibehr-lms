<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CourseFeedbackRating extends Model {
    protected $table = 'course_feedback_ratings';
    public $timestamps = false;
    protected $fillable = ['id','course_id','user_id','star','comment','added_on','status','updated_by','updated_on'];
    public function comments() { return $this->hasMany(CourseFeedbackComment::class, 'feedback_id'); }
}
