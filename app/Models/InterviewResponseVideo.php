<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class InterviewResponseVideo extends Model {
    protected $table = 'interview_response_videos';
    public $timestamps = false;
    protected $fillable = ['id','response_id','src','added_on'];
}
