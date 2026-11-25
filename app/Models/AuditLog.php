<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

class AuditLog extends Model
{
    use HasFactory;

    public $timestamps = false; // Only created_at is managed

    protected $fillable = [
        'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'location_id',
        'description',
        'description_ur',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Boot model and strictly enforce immutability of audit records.
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new RuntimeException('Audit trail integrity violation: Audit log entries cannot be modified.');
        });

        static::deleting(function () {
            throw new RuntimeException('Audit trail integrity violation: Audit log entries cannot be deleted.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
