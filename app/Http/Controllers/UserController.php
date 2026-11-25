<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use App\Services\Administration\AuditService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class UserController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Display a listing of system users.
     */
    public function index(Request $request): View
    {
        $currentUser = $request->user();
        if (!$currentUser->isOwner()) {
            abort(403, 'Forbidden: Only business owners can manage users.');
        }

        $query = User::with(['roles', 'locations', 'defaultLocation']);

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role_id')) {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('roles.id', $request->role_id);
            });
        }

        if ($request->has('status') && $request->status !== '') {
            $query->where('is_active', (bool) $request->status);
        }

        $users = $query->orderBy('name')->paginate(15)->withQueryString();
        $roles = Role::orderBy('label')->get();

        return view('admin.users.index', compact('users', 'roles'));
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(Request $request): View
    {
        $currentUser = $request->user();
        if (!$currentUser->isOwner()) {
            abort(403, 'Forbidden: Only business owners can create users.');
        }

        $roles = Role::orderBy('label')->get();
        $locations = Location::where('is_active', true)->orderBy('name')->get();

        return view('admin.users.create', compact('roles', 'locations'));
    }

    /**
     * Store a newly created user in storage.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $currentUser = $request->user();

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'password' => Hash::make($validated['password']),
            'is_active' => $request->boolean('is_active', true),
            'default_location_id' => $validated['default_location_id'] ?? null,
        ]);

        if (!empty($validated['role_id'])) {
            $user->roles()->sync([$validated['role_id']]);
        }

        if (!empty($validated['locations'])) {
            $syncData = [];
            foreach ($validated['locations'] as $locId) {
                $syncData[$locId] = [
                    'is_primary' => ((int) $locId === (int) ($validated['default_location_id'] ?? 0)),
                    'can_view' => true,
                    'can_operate' => true,
                ];
            }
            $user->locations()->sync($syncData);
        }

        $this->auditService->log(
            action: 'user_create',
            model: $user,
            description: "Created user '{$user->name}' ({$user->email})",
            actor: $currentUser
        );

        return redirect()->route('admin.users.index')
            ->with('success', "User '{$user->name}' created successfully.");
    }

    /**
     * Show the form for editing an existing user.
     */
    public function edit(Request $request, User $user): View
    {
        $currentUser = $request->user();
        if (!$currentUser->isOwner()) {
            abort(403, 'Forbidden: Only business owners can edit users.');
        }

        $user->load(['roles', 'locations']);
        $roles = Role::orderBy('label')->get();
        $locations = Location::where('is_active', true)->orderBy('name')->get();

        return view('admin.users.edit', compact('user', 'roles', 'locations'));
    }

    /**
     * Update the specified user in storage.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();
        $currentUser = $request->user();

        $oldData = [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'is_active' => $user->is_active,
            'default_location_id' => $user->default_location_id,
        ];

        $userData = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'default_location_id' => $validated['default_location_id'] ?? null,
        ];

        // Never deactivate own account
        if ($user->id === $currentUser->id) {
            $userData['is_active'] = true;
        } else {
            $userData['is_active'] = $request->boolean('is_active', true);
        }

        if (!empty($validated['password'])) {
            $userData['password'] = Hash::make($validated['password']);
        }

        $user->update($userData);

        if (!empty($validated['role_id'])) {
            $user->roles()->sync([$validated['role_id']]);
        }

        if (isset($validated['locations'])) {
            $syncData = [];
            foreach ($validated['locations'] as $locId) {
                $syncData[$locId] = [
                    'is_primary' => ((int) $locId === (int) ($validated['default_location_id'] ?? 0)),
                    'can_view' => true,
                    'can_operate' => true,
                ];
            }
            $user->locations()->sync($syncData);
        }

        $this->auditService->log(
            action: 'user_update',
            model: $user,
            description: "Updated user '{$user->name}'",
            oldValues: $oldData,
            newValues: $userData,
            actor: $currentUser
        );

        return redirect()->route('admin.users.index')
            ->with('success', "User '{$user->name}' updated successfully.");
    }

    /**
     * Toggle the active status of a user (with self-deactivation protection).
     */
    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        $currentUser = $request->user();
        if (!$currentUser->isOwner()) {
            abort(403, 'Forbidden: Only business owners can modify user status.');
        }

        if ($user->id === $currentUser->id) {
            return redirect()->route('admin.users.index')
                ->with('error', 'Safety protection: You cannot deactivate your own active user account.');
        }

        $oldStatus = $user->is_active;
        $user->is_active = !$user->is_active;
        $user->save();

        $this->auditService->log(
            action: 'user_toggle_status',
            model: $user,
            description: "Changed user '{$user->name}' status to " . ($user->is_active ? 'Active' : 'Inactive'),
            oldValues: ['is_active' => $oldStatus],
            newValues: ['is_active' => $user->is_active],
            actor: $currentUser
        );

        $statusLabel = $user->is_active ? 'activated' : 'deactivated';

        return redirect()->route('admin.users.index')
            ->with('success', "User '{$user->name}' has been {$statusLabel}.");
    }
}
