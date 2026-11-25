<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Location;
use App\Models\SalesOrder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SalesController extends Controller
{
    /**
     * Display the Phase 5A Sales module foundation page.
     * Location-aware query foundation, status filters, search, and empty state.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $status = $request->query('status', 'all');
        $locationId = $request->query('location_id');
        $search = $request->query('search');

        if ($locationId && $locationId !== 'all') {
            if (!$user->hasLocationAccess($locationId)) {
                abort(403, 'Forbidden: You do not have authorization to view sales for this location.');
            }
        } elseif ($locationId === 'all' && !$user->isOwner()) {
            abort(403, 'Forbidden: Only the owner can view consolidated cross-location sales.');
        }

        $query = SalesOrder::authorizedFor($user)
            ->with(['customer', 'location', 'creator'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($locationId && $locationId !== 'all', fn ($q) => $q->where('location_id', $locationId))
            ->when($search, function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('customer', function ($cq) use ($search) {
                      $cq->where('business_name', 'like', "%{$search}%")
                         ->orWhere('customer_code', 'like', "%{$search}%");
                  });
            })
            ->latest('order_date');

        $orders = $query->paginate(15)->withQueryString();

        $authorizedLocations = $user->isOwner()
            ? Location::where('is_active', true)->get()
            : $user->locations()->wherePivot('can_view', true)->get();

        return view('sales.index', compact('orders', 'authorizedLocations', 'status', 'locationId', 'search'));
    }
}
