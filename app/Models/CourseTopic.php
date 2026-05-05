<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CourseTopic extends Model {
    protected $table = 'course_topics';
    public $timestamps = false;
    protected $fillable = [
        'id','course_id','chapter_id','name','type','description','information',
        'passing_percentage','number_of_attempt','duration','file_url','video_type',
        'status','added_by','added_on','updated_by','updated_on'
    ];
    // Type constants
    const TYPE_VIDEO    = 1;
    const TYPE_PDF      = 2;
    const TYPE_RESOURCE = 3;
    const TYPE_TEST     = 4;

    public function chapter()        { return $this->belongsTo(CourseChapter::class, 'chapter_id'); }
    public function questions()      { return $this->hasMany(TopicQuestion::class, 'topic_id')->orderBy('question_order'); }
    public function resourceLinks()  { return $this->hasMany(TopicResourceLink::class, 'topic_id')->where('status',1); }
    public function learnerStatus()  { return $this->hasMany(CourseLearnerTopicStatus::class, 'topic_id'); }
}
