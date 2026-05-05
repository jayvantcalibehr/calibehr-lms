<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CourseChapter extends Model {
    protected $table = 'course_chapters';
    public $timestamps = false;
    protected $fillable = ['id','course_id','name','description','status','added_by','added_on','updated_by','updated_on'];

    public function course()  { return $this->belongsTo(Course::class, 'course_id'); }
    public function topics()  { return $this->hasMany(CourseTopic::class, 'chapter_id')->where('status',1)->orderBy('id'); }
}
