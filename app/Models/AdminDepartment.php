<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AdminDepartment extends Model
{
    protected $fillable = ['name', 'description', 'permissions', 'is_active'];
    protected $casts = ['permissions' => 'array', 'is_active' => 'boolean'];
    public function users() { return $this->hasMany(User::class, 'admin_department_id'); }
    protected static function booted(): void
    {
        static::created(fn ($department) => AdminAccessEvent::record('department_created', $department->id, $department->only(['name', 'permissions', 'is_active'])));
        static::updated(function ($department) {
            $changes = [];
            foreach (['name', 'description', 'permissions', 'is_active'] as $key) {
                if ($department->wasChanged($key)) $changes[$key] = ['before' => $department->getOriginal($key), 'after' => $department->$key];
            }
            if ($changes) AdminAccessEvent::record('department_updated', $department->id, $changes);
        });
    }
}
