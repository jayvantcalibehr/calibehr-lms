<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Candidate extends Model {
    protected $table = 'candidates';
    public $timestamps = false;
    protected $fillable = ['id','email','added_by','added_on'];
    public function invites() { return $this->hasMany(CandidateInvite::class, 'candidate_id'); }
}
