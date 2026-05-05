<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CandidateInvite extends Model {
    protected $table = 'candidate_invites';
    public $timestamps = false;
    protected $fillable = ['id','candidate_id','invite_id','type','added_by','added_on'];
    public function candidate() { return $this->belongsTo(Candidate::class, 'candidate_id'); }
}
