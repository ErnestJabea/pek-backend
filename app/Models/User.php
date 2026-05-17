<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

use Filament\Models\Contracts\HasName;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements HasName, FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

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
        return $this->hasRole('super_admin') || $this->role === 'admin';
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

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }
}
