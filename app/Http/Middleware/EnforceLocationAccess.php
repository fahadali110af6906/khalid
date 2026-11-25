<?php

namespace App\Http\Middleware;

use App\Models\Location;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceLocationAccess
{
    /**
     * Handle an incoming request and enforce strict location authorization.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        // Check if user account is deactivated
        if (!$user->is_active) {
            auth()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'This account has been deactivated. Please contact the administrator.',
            ]);
        }

        // Resolve requested location from route parameter or query string
        $requestedLocation = $request->route('location') 
            ?? $request->route('location_id') 
            ?? $request->query('location') 
            ?? $request->input('location_id');

        if ($requestedLocation !== null && $requestedLocation !== '') {
            // Consolidated 'all' view is strictly reserved for the owner
            if ($requestedLocation === 'all') {
                if (!$user->isOwner()) {
                    abort(403, 'Forbidden: Only the business owner can access consolidated cross-location data.');
                }
                return $next($request);
            }

            // Verify explicit location access
            if (!$user->hasLocationAccess($requestedLocation)) {
                abort(403, 'Forbidden: You do not have authorization to view or manage records for this location.');
            }
        }

        return $next($request);
    }
}
