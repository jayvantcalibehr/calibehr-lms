<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CourseLearnerTopicStatus extends Model {
    protected $table = 'course_learner_topic_status';
    public $timestamps = false;
    protected $fillable = ['id','course_id','topic_id','user_id','time_spent','started_on','completed','completed_on','user_marked'];
    protected $casts = ['started_on'=>'datetime','completed_on'=>'datetime'];
    public function topic() { return $this->belongsTo(CourseTopic::class, 'topic_id'); }
}
