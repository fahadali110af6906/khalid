<?php

namespace App\Services\Administration;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditService
{
    /**
     * Fields that must never be recorded in audit logs.
     */
    protected const REDACTED_FIELDS = [
        'password',
        'password_confirmation',
        'current_password',
        'remember_token',
        'token',
        'secret',
        'api_key',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'credit_card',
        'cvv',
    ];

    /**
     * Record an audit trail entry.
     */
    public function log(
        string $action,
        ?Model $model = null,
        string $description = '',
        ?string $descriptionUr = null,
        array $oldValues = [],
        array $newValues = [],
        ?User $actor = null,
        ?int $locationId = null
    ): AuditLog {
        $actor = $actor ?? Auth::user();
        $request = request();

        $cleanOld = $this->sanitizeValues($oldValues);
        $cleanNew = $this->sanitizeValues($newValues);

        return AuditLog::create([
            'user_id' => $actor?->id,
            'action' => strtolower(trim($action)),
            'auditable_type' => $model ? get_class($model) : null,
            'auditable_id' => $model?->getKey(),
            'location_id' => $locationId ?? ($model && isset($model->location_id) ? $model->location_id : null),
            'description' => $description,
            'description_ur' => $descriptionUr,
            'old_values' => !empty($cleanOld) ? $cleanOld : null,
            'new_values' => !empty($cleanNew) ? $cleanNew : null,
            'ip_address' => $request instanceof Request ? $request->ip() : null,
            'user_agent' => $request instanceof Request ? substr((string) $request->userAgent(), 0, 255) : null,
            'created_at' => now(),
        ]);
    }

    /**
     * Log authentication events.
     */
    public function logAuth(string $action, User $user, string $message): AuditLog
    {
        return $this->log(
            action: $action,
            model: $user,
            description: $message,
            actor: $user,
            locationId: $user->default_location_id
        );
    }

    /**
     * Sanitize values array to scrub sensitive data.
     */
    protected function sanitizeValues(array $values): array
    {
        $sanitized = [];

        foreach ($values as $key => $val) {
            $lowerKey = strtolower((string) $key);

            // Redact matching sensitive fields
            if (in_array($lowerKey, self::REDACTED_FIELDS, true) || str_contains($lowerKey, 'password') || str_contains($lowerKey, 'secret') || str_contains($lowerKey, 'token')) {
                $sanitized[$key] = '***REDACTED***';
            } elseif (is_array($val)) {
                $sanitized[$key] = $this->sanitizeValues($val);
            } else {
                $sanitized[$key] = $val;
            }
        }

        return $sanitized;
    }
}
