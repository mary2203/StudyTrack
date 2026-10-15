<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    // Relaciones agregadas para StudyTrack
    public function semesters()
    {
        return $this->hasMany(AcademicSemester::class);
    }

    public function activities()
    {
        return $this->hasMany(Activity::class);
    }

    public function calendarEvents()
    {
        return $this->hasMany(CalendarEvent::class);
    }

    public function recommendations()
    {
        return $this->hasMany(SystemRecommendation::class);
    }

    // Helper: Sincroniza el calendario del usuario mediante el SP
    public function syncCalendar()
    {
        return \Illuminate\Support\Facades\DB::select('CALL sp_sync_calendar_priorities(?)', [$this->id]);
    }

    // Helper: Genera las recomendaciones de estudio mediante el SP
    public function generateRecommendations()
    {
        return \Illuminate\Support\Facades\DB::select('CALL sp_generate_study_recommendations(?)', [$this->id]);
    }
}
