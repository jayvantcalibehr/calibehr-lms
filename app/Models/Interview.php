<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Interview extends Model {
    protected $table = 'interviews';
    public $timestamps = false;
    protected $fillable = ['id','name','description','type','added_by','added_on','updated_on','updated_by','visibility','time','show_marks','status'];
    public function questions() { return $this->hasMany(InterviewQuestion::class, 'interview_id')->where('status',1)->orderBy('id'); }
    public function invites()   { return $this->hasMany(InterviewInvite::class, 'interview_id'); }
}
