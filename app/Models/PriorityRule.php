<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PriorityRule extends Model
{
    use HasFactory;

    protected $table = 'priority_rules';
    public $timestamps = false; // Esta tabla no usa timestamps

    protected $fillable = [
        'code',
        'name',
        'color_hex',
        'badge_label',
        'min_score_threshold',
        'max_score_threshold',
        'weight_multiplier',
        'description',
    ];

    public function courses()
    {
        return $this->hasMany(Course::class, 'priority_level_id');
    }

    public function activities()
    {
        return $this->hasMany(Activity::class, 'priority_level_id');
    }
}