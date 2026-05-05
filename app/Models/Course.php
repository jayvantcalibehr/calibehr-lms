<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Course extends Model {
    protected $table = 'courses';
    public $timestamps = false;
    protected $fillable = [
        'id','name','description','what_will_you_learn','pre_requisites','trainer_details',
        'image_url','category_id','type','points','added_by','added_on','updated_on','updated_by',
        'visibility','status','featured','featured_by','featured_on'
    ];
    protected $casts = ['added_on'=>'datetime','updated_on'=>'datetime','featured_on'=>'datetime'];

    public function category()    { return $this->belongsTo(CategoryMaster::class, 'category_id'); }
    public function chapters()    { return $this->hasMany(CourseChapter::class, 'course_id')->where('status',1)->orderBy('id'); }
    public function learners()    { return $this->hasMany(CourseLearner::class, 'course_id'); }
    public function wishlistedBy(){ return $this->hasMany(Wishlist::class, 'course_id'); }
    public function feedbackRatings() { return $this->hasMany(CourseFeedbackRating::class, 'course_id'); }

    public function scopePublished($q) { return $q->where('status', 2); }
    public function scopeActive($q)    { return $q->where('status', '!=', 0); }
    public function scopeFeatured($q)  { return $q->where('featured', 1); }
}
