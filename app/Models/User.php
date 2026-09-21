<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasName
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($user) {
            $user->subscriptions()->delete();
            $user->notifications()->delete();
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->hasRole('super_admin') || $this->can('access_admin_panel') || $this->role === 'admin';
    }

    public function getFilamentName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'password',
        'role',
        'city',
        'country',
        'employer',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    protected $appends = [
        'onboarding_completed',
        'onboarding_status',
    ];

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'user_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'user_id');
    }

    public function onboardingSession()
    {
        return $this->hasOne(OnboardingSession::class, 'user_id');
    }

    public function getOnboardingCompletedAttribute(): bool
    {
        return $this->onboardingSession()->whereIn('status', ['completed', 'validated'])->exists();
    }

    public function getOnboardingStatusAttribute(): ?string
    {
        return $this->onboardingSession ? $this->onboardingSession->status : null;
    }
}
