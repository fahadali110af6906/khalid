<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'is_active',
        'default_location_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The roles assigned to the user.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')->withTimestamps();
    }

    /**
     * The locations the user has access to.
     */
    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'user_location_access')
            ->withPivot(['is_primary', 'can_view', 'can_operate'])
            ->withTimestamps();
    }

    /**
     * The user's default/home location.
     */
    public function defaultLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'default_location_id');
    }

    /**
     * Check if user has a specific role.
     */
    public function hasRole(string|array $roles): bool
    {
        if (is_string($roles)) {
            $roles = [$roles];
        }

        return $this->roles->pluck('name')->intersect($roles)->isNotEmpty();
    }

    /**
     * Assign a role to the user.
     */
    public function assignRole(Role|string $role): void
    {
        if (is_string($role)) {
            $role = Role::where('name', $role)->firstOrFail();
        }

        $this->roles()->syncWithoutDetaching([$role->id]);
        $this->load('roles');
    }

    /**
     * Check if user is an owner / super admin.
     */
    public function isOwner(): bool
    {
        return $this->hasRole(['owner', 'super_admin']);
    }

    /**
     * Check if user has permission to view/operate on a given location.
     */
    public function hasLocationAccess(int|string|Location $location, bool $requireOperate = false): bool
    {
        // Owner has global cross-location visibility
        if ($this->isOwner()) {
            return true;
        }

        $locationId = match (true) {
            $location instanceof Location => $location->id,
            is_numeric($location) => (int) $location,
            default => Location::where('code', $location)->value('id'),
        };

        if (!$locationId) {
            return false;
        }

        return \Illuminate\Support\Facades\DB::table('user_location_access')
            ->where('user_id', $this->id)
            ->where('location_id', $locationId)
            ->where('can_view', 1)
            ->when($requireOperate, fn ($query) => $query->where('can_operate', 1))
            ->exists();
    }

    /**
     * Get all authorized location IDs for data query scoping.
     */
    public function getAuthorizedLocationIds(): array
    {
        if ($this->isOwner()) {
            return Location::where('is_active', true)->pluck('id')->all();
        }

        return \Illuminate\Support\Facades\DB::table('user_location_access')
            ->where('user_id', $this->id)
            ->where('can_view', 1)
            ->pluck('location_id')
            ->all();
    }
}
