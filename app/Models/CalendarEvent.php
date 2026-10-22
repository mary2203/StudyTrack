<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CalendarEvent extends Model
{
    use HasFactory;

    protected $table = 'calendar_events';

    protected $fillable = [
        'user_id',
        'course_id',
        'activity_id',
        'title',
        'description',
        'event_datetime',
        'calculated_weight',
        'priority_level_id',
    ];

    protected $casts = [
        'event_datetime'    => 'datetime',
        'calculated_weight' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }

    public function priorityRule()
    {
        return $this->belongsTo(PriorityRule::class, 'priority_level_id');
    }
}