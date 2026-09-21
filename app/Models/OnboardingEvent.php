<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OnboardingEvent extends Model
{
    protected $fillable = [
        'onboarding_session_id',
        'actor_user_id',
        'event_type',
        'from_status',
        'to_status',
        'reason',
    ];

    public function onboardingSession()
    {
        return $this->belongsTo(OnboardingSession::class);
    }
}
