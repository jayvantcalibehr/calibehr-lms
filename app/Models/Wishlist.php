<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Wishlist extends Model {
    protected $table = 'wishlists';
    public $timestamps = false;
    protected $fillable = ['id','user_id','course_id','added_on'];
    public function course() { return $this->belongsTo(Course::class, 'course_id'); }
}
