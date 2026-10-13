<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Course extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'courses';

    protected $fillable = [
        'semester_id',
        'name',
        'code',
        'color_hex',
        'target_grade',
        'current_average',
        'priority_level_id',
    ];

    protected $casts = [
        'target_grade'    => 'decimal:2',
        'current_average' => 'decimal:2',
    ];

    // Relaciones
    public function semester()
    {
        return $this->belongsTo(AcademicSemester::class, 'semester_id');
    }

    public function priorityRule()
    {
        return $this->belongsTo(PriorityRule::class, 'priority_level_id');
    }

    public function activities()
    {
        return $this->hasMany(Activity::class);
    }

    public function calendarEvents()
    {
        return $this->hasMany(CalendarEvent::class);
    }

    // Helper: Invoca el SP para recalcular promedio y semáforo del curso
    public function recalculateAverage()
    {
        return DB::select('CALL sp_calculate_course_average(?)', [$this->id]);
    }
}