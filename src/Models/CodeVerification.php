<?php

namespace XWMS\Package\Models;

use Illuminate\Database\Eloquent\Model;

class CodeVerification extends Model
{
    protected $table = 'code_verifications';

    protected $fillable = [
        'user_id', 'ip', 'category', 'code', 'status', 'email',
        'attempt', 'last_attempt', 'completed_at', 'expires_at',
    ];

    protected $casts = [
        'last_attempt' => 'datetime',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Relatie met de gebruiker
     */
    public function user()
    {
        return $this->belongsTo( config('xwms.models.user', \App\Models\User::class));
    }

    /**
     * Check of de code verlopen is.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && now()->gt($this->expires_at);
    }

    /**
     * Markeer de code als gebruikt.
     */
    public function markAsUsed(): void
    {
        $this->status = 'used';
        $this->completed_at = now();
        $this->save();
    }

    /**
     * Verhoog het aantal pogingen.
     */
    public function incrementAttempt(): void
    {
        $this->attempt++;
        $this->save();
    }
}
