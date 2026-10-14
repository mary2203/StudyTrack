<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Activity extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'activities';

    protected $fillable = [
        'course_id',
        'title',
        'description',
        'activity_type',
        'is_graded',
        'points_possible',
        'points_obtained',
        'due_datetime',
        'estimated_minutes',
        'status',
        'priority_level_id',
        'completed_at',
    ];

    protected $casts = [
        'is_graded'        => 'boolean',
        'points_possible'  => 'decimal:2',
        'points_obtained'  => 'decimal:2',
        'due_datetime'     => 'datetime',
        'completed_at'     => 'datetime',
        'estimated_minutes'=> 'integer',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function priorityRule()
    {
        return $this->belongsTo(PriorityRule::class, 'priority_level_id');
    }

    public function calendarEvents()
    {
        return $this->hasMany(CalendarEvent::class);
    }
}