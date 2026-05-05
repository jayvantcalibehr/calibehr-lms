<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CategoryMaster extends Model {
    protected $table = 'categories_master';
    public $timestamps = false;
    protected $fillable = ['id','name','image_url','added_by','added_on','updated_by','updated_on','status'];
    public function courses() { return $this->hasMany(Course::class, 'category_id'); }
}
