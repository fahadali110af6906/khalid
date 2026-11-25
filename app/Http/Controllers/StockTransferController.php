<?php

namespace App\Http\Controllers;

use App\Exceptions\InsufficientStockException;
use App\Http\Requests\StoreStockTransferRequest;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Services\Inventory\InventoryService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockTransferController extends Controller
{
    public function __construct(
        protected InventoryService $inventoryService
    ) {}

    /**
     * Display a listing of inter-location stock transfers.
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        $query = StockTransfer::authorizedFor($user)
            ->with(['sourceLocation', 'destinationLocation', 'creator', 'dispatcher', 'receiver']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('source_location_id')) {
            $query->where('source_location_id', $request->source_location_id);
        }

        if ($request->filled('destination_location_id')) {
            $query->where('destination_location_id', $request->destination_location_id);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where('transfer_number', 'like', "%{$search}%");
        }

        $transfers = $query->latest('transfer_date')->latest('id')->paginate(15)->withQueryString();

        $authorizedLocations = $user->isOwner()
            ? Location::where('is_active', true)->orderBy('name')->get()
            : $user->locations;

        return view('inventory.transfers.index', compact('transfers', 'authorizedLocations'));
    }

    /**
     * Show the form for creating a new stock transfer.
     */
    public function create(Request $request): View
    {
        $user = $request->user();

        $sourceLocations = $user->isOwner()
            ? Location::where('is_active', true)->orderBy('name')->get()
            : $user->locations()->wherePivot('can_operate', true)->get();

        $allLocations = Location::where('is_active', true)->orderBy('name')->get();

        $products = Product::where('is_active', true)
            ->with(['variants' => fn ($q) => $q->where('is_active', true)])
            ->orderBy('name')
            ->get();

        return view('inventory.transfers.create', compact('sourceLocations', 'allLocations', 'products'));
    }

    /**
     * Store a newly created stock transfer (draft or immediate dispatch).
     */
    public function store(StoreStockTransferRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        try {
            $transfer = $this->inventoryService->createTransferDraft($validated, $user);

            if ($validated['action'] === 'dispatch_immediately') {
                $transfer = $this->inventoryService->dispatchTransfer($transfer, $user, $validated['dispatch_notes'] ?? null);

                return redirect()->route('inventory.transfers.show', $transfer)
                    ->with('success', "Stock Transfer {$transfer->transfer_number} dispatched. Stock deducted from source and marked in-transit.");
            }

            return redirect()->route('inventory.transfers.show', $transfer)
                ->with('success', "Stock Transfer draft {$transfer->transfer_number} saved.");
        } catch (InsufficientStockException $e) {
            return back()->withInput()->withErrors(['stock' => $e->getMessage()]);
        } catch (Exception $e) {
            return back()->withInput()->withErrors(['general' => $e->getMessage()]);
        }
    }

    /**
     * Display the specified stock transfer details and challan view.
     */
    public function show(Request $request, StockTransfer $transfer): View
    {
        $user = $request->user();

        if (!$user->isOwner() && !$user->hasLocationAccess($transfer->source_location_id) && !$user->hasLocationAccess($transfer->destination_location_id)) {
            abort(403, 'Forbidden: You do not have authorization to view this stock transfer.');
        }

        $transfer->load([
            'sourceLocation',
            'destinationLocation',
            'creator',
            'dispatcher',
            'receiver',
            'canceller',
            'items.productVariant.product',
        ]);

        return view('inventory.transfers.show', compact('transfer'));
    }

    /**
     * Dispatch a draft stock transfer into transit.
     */
    public function dispatch(Request $request, StockTransfer $transfer): RedirectResponse
    {
        $user = $request->user();

        try {
            $notes = $request->input('dispatch_notes');
            $this->inventoryService->dispatchTransfer($transfer, $user, $notes);

            return redirect()->route('inventory.transfers.show', $transfer)
                ->with('success', "Transfer {$transfer->transfer_number} dispatched successfully.");
        } catch (InsufficientStockException $e) {
            return back()->withErrors(['stock' => $e->getMessage()]);
        } catch (Exception $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }
    }

    /**
     * Confirm receipt of transfer at destination location.
     */
    public function receive(Request $request, StockTransfer $transfer): RedirectResponse
    {
        $user = $request->user();

        try {
            $notes = $request->input('receive_notes');
            $this->inventoryService->receiveTransfer($transfer, $user, $notes);

            return redirect()->route('inventory.transfers.show', $transfer)
                ->with('success', "Transfer {$transfer->transfer_number} received into destination stock.");
        } catch (Exception $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }
    }

    /**
     * Cancel an existing transfer.
     */
    public function cancel(Request $request, StockTransfer $transfer): RedirectResponse
    {
        $user = $request->user();
        $reason = $request->input('reason', 'Cancelled by operator');

        try {
            $this->inventoryService->cancelTransfer($transfer, $user, $reason);

            return redirect()->route('inventory.transfers.show', $transfer)
                ->with('success', "Transfer {$transfer->transfer_number} has been cancelled.");
        } catch (Exception $e) {
            return back()->withErrors(['general' => $e->getMessage()]);
        }
    }
}
