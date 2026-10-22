<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassroomConnection extends Model
{
    protected $fillable = [
        'user_id',
        'google_email',
        'refresh_token',
        'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'refresh_token' => 'encrypted',
            'last_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
