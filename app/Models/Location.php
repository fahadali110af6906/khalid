<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'type',
        'city',
        'address',
        'phone',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Users who have this location as default.
     */
    public function defaultUsers(): HasMany
    {
        return $this->hasMany(User::class, 'default_location_id');
    }

    /**
     * Users explicitly assigned to this location.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_location_access')
            ->withPivot(['is_primary', 'can_view', 'can_operate'])
            ->withTimestamps();
    }

    public function productionOrders(): HasMany
    {
        return $this->hasMany(ProductionOrder::class, 'factory_location_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function isShop(): bool
    {
        return $this->type === 'shop';
    }

    public function isFactory(): bool
    {
        return $this->type === 'factory';
    }

    public function isWarehouse(): bool
    {
        return $this->type === 'warehouse';
    }

    public function isTransit(): bool
    {
        return $this->type === 'transit';
    }
}
