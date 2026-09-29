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
    use HasApiTokens, HasFactory, Notifiable;
    use HasRoles { hasPermissionTo as private hasPermissionWithoutDepartment; }

    public function adminDepartment()
    {
        return $this->belongsTo(AdminDepartment::class, 'admin_department_id');
    }

    public function hasPermissionTo($permission, $guardName = null): bool
    {
        $granted = $this->hasPermissionWithoutDepartment($permission, $guardName);
        if (! $granted || ! $this->admin_department_id || $this->hasRole('super_admin')) return $granted;
        // Read current department configuration: cached role permissions cannot bypass a revocation.
        $department = $this->adminDepartment()->first();
        $resolved = $this->filterPermission($permission, $guardName);
        return $department && $department->is_active && in_array($resolved->name, $department->permissions ?? [], true);
    }

    public function isBackofficeAccount(): bool
    {
        return $this->role === 'admin' || $this->hasRole('super_admin')
            || $this->getAllPermissions()->contains('name', 'access_admin_panel');
    }


    protected static function boot()
    {
        parent::boot();
        static::saved(function ($user) {
            if ($user->wasChanged('admin_department_id') || ($user->wasRecentlyCreated && $user->admin_department_id)) {
                AdminAccessEvent::record('department_assigned', $user->admin_department_id ?? $user->getOriginal('admin_department_id'),
                    ['before' => $user->getOriginal('admin_department_id'), 'after' => $user->admin_department_id], $user->id);
            }
        });

        static::deleting(function ($user) {
            $user->subscriptions()->delete();
            $user->notifications()->delete();
        });
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! $this->hasRole('super_admin') && $this->admin_department_id) {
            return $this->can('access_admin_panel');
        }
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
        'type_piece',
        'num_piece',
        'doc_piece_identite',
        'doc_piece_verso',
        'last_onboarding_reminder_at',
    ];

    protected $hidden = [
        'password',
        'admin_department_id',
        'admin_department',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'last_onboarding_reminder_at' => 'datetime',
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
