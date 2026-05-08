<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

/**
 * CourseFeedback — alias model for course_feedback_ratings table.
 * Used by ReportController for Feedback Report (Excel/PDF/JSON).
 *
 * Maps to: course_feedback_ratings
 * Fields:  id, course_id, user_id, star (rating), comment, added_on, status
 */
class CourseFeedback extends Model
{
    protected $table      = 'course_feedback_ratings';
    public    $timestamps = false;

    protected $fillable = [
        'id', 'course_id', 'user_id', 'star', 'comment',
        'added_on', 'status', 'updated_by', 'updated_on'
    ];

    protected $appends = ['rating'];

    /** Rating accessor — maps 'rating' → 'star' column */
    public function getRatingAttribute(): mixed
    {
        return $this->attributes['star'] ?? null;
    }

    /** Learner who gave feedback */
    public function learner()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Course this feedback belongs to */
    public function course()
    {
        return $this->belongsTo(Course::class, 'course_id');
    }
}