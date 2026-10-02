<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasName
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;
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

        static::forceDeleting(function ($user) {
            DB::transaction(function () use ($user) {
                // 1. Delete payment_proofs, payment_events, s3p_callback_inbox related to user's subscriptions
                $subscriptionIds = DB::table('subscriptions')->where('user_id', $user->id)->pluck('id');
                if ($subscriptionIds->isNotEmpty()) {
                    DB::table('payment_events')->whereIn('subscription_id', $subscriptionIds)->delete();
                    DB::table('s3p_callback_inbox')->whereIn('subscription_id', $subscriptionIds)->delete();
                    DB::table('payment_proofs')->whereIn('subscription_id', $subscriptionIds)->delete();
                    DB::table('subscriptions')->whereIn('id', $subscriptionIds)->delete();
                }

                // 2. Clear reviewer references on subscriptions
                DB::table('subscriptions')->where('compliance_reviewed_by_user_id', $user->id)->update(['compliance_reviewed_by_user_id' => null]);
                DB::table('subscriptions')->where('accounting_reviewed_by_user_id', $user->id)->update(['accounting_reviewed_by_user_id' => null]);

                // 3. Delete any remaining payment_proofs linked to this user
                DB::table('payment_proofs')->where('user_id', $user->id)->delete();
                DB::table('payment_proofs')->where('reviewed_by', $user->id)->update(['reviewed_by' => null]);

                // 4. Clear payment_events actor_id
                DB::table('payment_events')->where('actor_id', $user->id)->update(['actor_id' => null]);

                // 5. Delete onboarding_sessions and sub-relations
                $sessionIds = DB::table('onboarding_sessions')->where('user_id', $user->id)->pluck('id');
                if ($sessionIds->isNotEmpty()) {
                    $ivIds = DB::table('identity_verifications')->whereIn('onboarding_session_id', $sessionIds)->pluck('id');
                    if ($ivIds->isNotEmpty()) {
                        DB::table('identity_verification_events')->whereIn('identity_verification_id', $ivIds)->delete();
                        DB::table('identity_verifications')->whereIn('onboarding_session_id', $sessionIds)->delete();
                    }
                    DB::table('onboarding_events')->whereIn('onboarding_session_id', $sessionIds)->delete();
                    DB::table('onboarding_sessions')->whereIn('id', $sessionIds)->delete();
                }
                DB::table('onboarding_events')->where('actor_user_id', $user->id)->update(['actor_user_id' => null]);

                // 6. Delete notifications
                DB::table('notifications')->where('user_id', $user->id)->delete();

                // 7. Delete OTP codes
                if ($user->email) {
                    DB::table('otp_codes')->where('email', $user->email)->delete();
                }

                // 8. Delete personal access tokens
                DB::table('personal_access_tokens')
                    ->where('tokenable_id', $user->id)
                    ->where(function ($q) use ($user) {
                        $q->where('tokenable_type', get_class($user))
                            ->orWhere('tokenable_type', User::class);
                    })
                    ->delete();

                // 9. Delete sessions
                DB::table('sessions')->where('user_id', $user->id)->delete();

                // 10. Delete admin_access_events
                DB::table('admin_access_events')->where('user_id', $user->id)->orWhere('actor_id', $user->id)->delete();

                // 11. Detach roles and permissions
                if (method_exists($user, 'roles')) {
                    $user->roles()->detach();
                }
                if (method_exists($user, 'permissions')) {
                    $user->permissions()->detach();
                }
            });
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
        'categorie_client',
        'type_piece',
        'num_piece',
        'doc_piece_identite',
        'doc_piece_verso',
        'expiration_piece',
        'last_id_expiry_reminder_at',
        'dob',
        'last_birthday_wish_sent_at',
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
        'expiration_piece' => 'date:Y-m-d',
        'last_id_expiry_reminder_at' => 'datetime',
        'dob' => 'date:Y-m-d',
        'last_birthday_wish_sent_at' => 'datetime',
        'last_onboarding_reminder_at' => 'datetime',
        'password' => 'hashed',
    ];

    protected $appends = [
        'onboarding_completed',
        'onboarding_status',
        'needs_category',
        'effective_expiration_piece',
        'is_id_expired',
        'is_id_expiring_soon',
        'id_days_until_expiration',
        'effective_dob',
        'age',
        'is_birthday_today',
        'days_until_next_birthday',
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

    public function getNeedsCategoryAttribute(): bool
    {
        return empty($this->categorie_client);
    }
    public function getEffectiveExpirationPieceAttribute(): ?string
    {
        if (! empty($this->attributes['expiration_piece'])) {
            return substr((string) $this->attributes['expiration_piece'], 0, 10);
        }
        $payload = $this->onboardingSession?->payload ?? [];
        return ! empty($payload['expiration_piece']) ? substr((string) $payload['expiration_piece'], 0, 10) : null;
    }

    public function getIsIdExpiredAttribute(): bool
    {
        $expiry = $this->effective_expiration_piece;
        if (! $expiry) {
            return false;
        }
        return \Carbon\Carbon::parse($expiry)->endOfDay()->isPast();
    }

    public function getIsIdExpiringSoonAttribute(): bool
    {
        $expiry = $this->effective_expiration_piece;
        if (! $expiry) {
            return false;
        }
        $date = \Carbon\Carbon::parse($expiry)->endOfDay();
        return ! $date->isPast() && $date->lte(now()->addDays(30));
    }

    public function getIdDaysUntilExpirationAttribute(): ?int
    {
        $expiry = $this->effective_expiration_piece;
        if (! $expiry) {
            return null;
        }
        return (int) now()->diffInDays(\Carbon\Carbon::parse($expiry)->endOfDay(), false);
    }
    public function getEffectiveDobAttribute(): ?string
    {
        if (! empty($this->attributes['dob'])) {
            return substr((string) $this->attributes['dob'], 0, 10);
        }
        $payload = $this->onboardingSession?->payload ?? [];
        return ! empty($payload['dob']) ? substr((string) $payload['dob'], 0, 10) : null;
    }

    public function getAgeAttribute(): ?int
    {
        $dob = $this->effective_dob;
        if (! $dob) {
            return null;
        }
        return (int) \Carbon\Carbon::parse($dob)->age;
    }

    public function getIsBirthdayTodayAttribute(): bool
    {
        $dob = $this->effective_dob;
        if (! $dob) {
            return false;
        }
        $birth = \Carbon\Carbon::parse($dob);
        $today = now();
        return $birth->month === $today->month && $birth->day === $today->day;
    }

    public function getDaysUntilNextBirthdayAttribute(): ?int
    {
        $dob = $this->effective_dob;
        if (! $dob) {
            return null;
        }
        $today = now()->startOfDay();
        $birth = \Carbon\Carbon::parse($dob);
        $nextBirthday = \Carbon\Carbon::create($today->year, $birth->month, $birth->day)->startOfDay();

        if ($nextBirthday->lt($today)) {
            $nextBirthday->addYear();
        }

        return (int) $today->diffInDays($nextBirthday);
    }
}