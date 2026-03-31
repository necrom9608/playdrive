<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const TYPE_SINGLE = 'single';
    public const TYPE_RECURRING = 'recurring';

    protected $fillable = [
        'tenant_id',
        'title',
        'description',
        'status',
        'task_type',
        'recurrence_pattern',
        'due_date',
        'start_date',
        'end_date',
        'assigned_user_id',
        'scheduled_by',
        'completed_at',
        'completed_by',
        'cancelled_at',
        'cancelled_by',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_OPEN => 'Open',
            self::STATUS_COMPLETED => 'Afgewerkt',
            self::STATUS_CANCELLED => 'Geannuleerd',
        ];
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function scheduler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by');
    }
}
