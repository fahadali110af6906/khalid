<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\File;

class DatabaseBackup extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'filename',
        'disk',
        'filepath',
        'size_bytes',
        'type',
        'driver',
        'status',
        'checksum_sha256',
        'error_message',
        'created_by',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleted(function (DatabaseBackup $backup) {
            if ($backup->filepath && File::exists($backup->filepath)) {
                File::delete($backup->filepath);
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get human-readable file size.
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->size_bytes;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        }

        return $bytes . ' B';
    }

    /**
     * Check if physical backup file actually exists on disk.
     */
    public function fileExists(): bool
    {
        return $this->filepath && File::exists($this->filepath);
    }
}
