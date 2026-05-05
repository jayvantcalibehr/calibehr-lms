<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CourseLearner extends Model {
    protected $table = 'course_learners';
    public $timestamps = false;
    protected $fillable = ['id','course_id','learner_id','status','added_by','added_on','started_on','completed','completed_on'];
    protected $casts = ['started_on'=>'datetime','completed_on'=>'datetime','added_on'=>'datetime'];
    public function course()  { return $this->belongsTo(Course::class, 'course_id'); }
    public function learner() { return $this->belongsTo(User::class, 'learner_id'); }
    public function topicStatuses() { return $this->hasMany(CourseLearnerTopicStatus::class, 'user_id', 'learner_id')->where('course_id', $this->course_id); }
}
