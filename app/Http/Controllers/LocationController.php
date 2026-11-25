<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Services\Administration\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Display a listing of system locations (Shops, Factories, Warehouses).
     */
    public function index(Request $request): View
    {
        $currentUser = $request->user();
        if (!$currentUser->isOwner()) {
            abort(403, 'Forbidden: Only business owners can manage locations.');
        }

        $query = Location::withCount(['users', 'defaultUsers']);

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type') && in_array($request->type, ['shop', 'factory', 'warehouse'])) {
            $query->where('type', $request->type);
        }

        if ($request->has('status') && $request->status !== '') {
            $query->where('is_active', (bool) $request->status);
        }

        $locations = $query->orderBy('type')->orderBy('id')->paginate(15)->withQueryString();

        $stats = [
            'total' => Location::count(),
            'shops' => Location::where('type', 'shop')->count(),
            'factories' => Location::where('type', 'factory')->count(),
            'warehouses' => Location::where('type', 'warehouse')->count(),
            'active' => Location::where('is_active', true)->count(),
        ];

        return view('admin.locations.index', compact('locations', 'stats'));
    }

    /**
     * Show the form for creating a new shop, factory, or warehouse.
     */
    public function create(Request $request): View
    {
        $currentUser = $request->user();
        if (!$currentUser->isOwner()) {
            abort(403, 'Forbidden: Only business owners can create locations.');
        }

        $type = $request->query('type', 'shop');
        if (!in_array($type, ['shop', 'factory', 'warehouse'])) {
            $type = 'shop';
        }

        // Generate suggested next code
        $prefix = match ($type) {
            'shop' => 'LOC-SH',
            'factory' => 'LOC-FAC',
            'warehouse' => 'LOC-WH',
            default => 'LOC-',
        };

        $existingCount = Location::where('type', $type)->count();
        $suggestedCode = sprintf('%s%02d', $prefix, $existingCount + 1);

        // Ensure suggested code is unique
        while (Location::where('code', $suggestedCode)->exists()) {
            $existingCount++;
            $suggestedCode = sprintf('%s%02d', $prefix, $existingCount + 1);
        }

        return view('admin.locations.create', compact('type', 'suggestedCode'));
    }

    /**
     * Store a newly created location.
     */
    public function store(Request $request): RedirectResponse
    {
        $currentUser = $request->user();
        if (!$currentUser->isOwner()) {
            abort(403, 'Forbidden: Only business owners can create locations.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', 'unique:locations,code'],
            'type' => ['required', 'in:shop,factory,warehouse'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $location = Location::create([
            'name' => $validated['name'],
            'code' => strtoupper(trim($validated['code'])),
            'type' => $validated['type'],
            'city' => $validated['city'] ?? null,
            'address' => $validated['address'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        // Auto-assign owner to the new location if owner has explicit location mappings
        if ($currentUser->locations()->exists()) {
            $currentUser->locations()->syncWithoutDetaching([
                $location->id => [
                    'is_primary' => false,
                    'can_view' => true,
                    'can_operate' => true,
                ],
            ]);
        }

        $this->auditService->log(
            action: 'location_create',
            model: $location,
            description: "Created new {$location->type} location '{$location->name}' ({$location->code}) in {$location->city}",
            actor: $currentUser
        );

        return redirect()->route('admin.locations.index')
            ->with('success', "Location '{$location->name}' ({$location->code}) created successfully.");
    }

    /**
     * Show the form for editing an existing location.
     */
    public function edit(Location $location, Request $request): View
    {
        $currentUser = $request->user();
        if (!$currentUser->isOwner()) {
            abort(403, 'Forbidden: Only business owners can edit locations.');
        }

        return view('admin.locations.edit', compact('location'));
    }

    /**
     * Update the specified location in storage.
     */
    public function update(Location $location, Request $request): RedirectResponse
    {
        $currentUser = $request->user();
        if (!$currentUser->isOwner()) {
            abort(403, 'Forbidden: Only business owners can update locations.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:20', Rule::unique('locations', 'code')->ignore($location->id)],
            'type' => ['required', 'in:shop,factory,warehouse,transit'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $oldValues = $location->toArray();

        $location->update([
            'name' => $validated['name'],
            'code' => strtoupper(trim($validated['code'])),
            'type' => $validated['type'],
            'city' => $validated['city'] ?? null,
            'address' => $validated['address'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        $this->auditService->log(
            action: 'location_update',
            model: $location,
            description: "Updated location '{$location->name}' ({$location->code})",
            actor: $currentUser,
            oldValues: $oldValues,
            newValues: $location->toArray()
        );

        return redirect()->route('admin.locations.index')
            ->with('success', "Location '{$location->name}' updated successfully.");
    }

    /**
     * Toggle location active status.
     */
    public function toggleActive(Location $location, Request $request): RedirectResponse
    {
        $currentUser = $request->user();
        if (!$currentUser->isOwner()) {
            abort(403, 'Forbidden: Only business owners can toggle location status.');
        }

        $oldStatus = $location->is_active;
        $location->is_active = !$oldStatus;
        $location->save();

        $statusLabel = $location->is_active ? 'activated' : 'deactivated';

        $this->auditService->log(
            action: 'location_toggle_status',
            model: $location,
            description: "Changed location '{$location->name}' ({$location->code}) status to {$statusLabel}",
            actor: $currentUser
        );

        return redirect()->route('admin.locations.index')
            ->with('success', "Location '{$location->name}' has been {$statusLabel}.");
    }
}
