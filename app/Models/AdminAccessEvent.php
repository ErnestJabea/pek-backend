<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AdminAccessEvent extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $casts = ['changes' => 'array', 'created_at' => 'datetime'];
    public static function record(string $event, ?int $departmentId, array $changes, ?int $userId = null): void
    {
        static::create(['actor_id' => auth()->id(), 'user_id' => $userId, 'department_id' => $departmentId,
            'event' => $event, 'changes' => $changes, 'created_at' => now()]);
    }
}
