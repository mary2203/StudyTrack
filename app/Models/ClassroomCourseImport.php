<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClassroomCourseImport extends Model
{
    protected $fillable = [
        'user_id',
        'google_course_id',
        'name',
        'code',
        'section',
        'course_state',
        'status',
        'course_id',
    ];
}
