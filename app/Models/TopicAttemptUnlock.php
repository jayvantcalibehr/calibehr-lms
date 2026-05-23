<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TopicAttemptUnlock extends Model
{
    public $timestamps = false;

    protected $table = 'topic_attempt_unlocks';

    protected $fillable = [
        'topic_id', 'course_id', 'chapter_id',
        'user_id', 'unlocked_by', 'used',
        'unlocked_on', 'used_on',
    ];
}
