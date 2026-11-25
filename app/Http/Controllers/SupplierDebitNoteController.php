<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierDebitNoteRequest;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierDebitNote;
use App\Services\Purchasing\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class SupplierDebitNoteController extends Controller
{
    public function __construct(
        protected PurchaseService $purchaseService
    ) {}

    /**
     * Display a listing of supplier debit notes.
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $type = $request->query('type', 'all');
        $locationId = $request->query('location_id');
        $supplierId = $request->query('supplier_id');
        $search = $request->query('search');

        if ($locationId && $locationId !== 'all') {
            if (!$user->hasLocationAccess($locationId)) {
                abort(403, 'Forbidden: You do not have authorization for this location.');
            }
        } elseif ($locationId === 'all' && !$user->isOwner()) {
            abort(403, 'Forbidden: Only the owner can view cross-location debit notes.');
        }

        $query = SupplierDebitNote::authorizedFor($user)
            ->with(['supplier', 'location', 'purchaseReturn', 'purchaseOrder', 'creator'])
            ->when($type !== 'all', fn ($q) => $q->where('debit_note_type', $type))
            ->when($locationId && $locationId !== 'all', fn ($q) => $q->where('location_id', $locationId))
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->when($search, function ($q) use ($search) {
                $q->where('debit_note_number', 'like', "%{$search}%")
                  ->orWhere('reference_number', 'like', "%{$search}%")
                  ->orWhere('reason', 'like', "%{$search}%")
                  ->orWhereHas('supplier', function ($sq) use ($search) {
                      $sq->where('business_name', 'like', "%{$search}%")
                         ->orWhere('supplier_code', 'like', "%{$search}%");
                  });
            })
            ->latest('note_date')
            ->latest('id');

        $debitNotes = $query->paginate(20)->withQueryString();

        $authorizedLocations = $user->isOwner()
            ? Location::where('is_active', true)->get()
            : $user->locations()->wherePivot('can_view', true)->get();

        $suppliers = Supplier::authorizedFor($user)->where('is_active', true)->orderBy('business_name')->get();

        return view('supplier_debit_notes.index', compact(
            'debitNotes',
            'authorizedLocations',
            'suppliers',
            'type',
            'locationId',
            'supplierId',
            'search'
        ));
    }

    /**
     * Show the form to record a financial-only debit note.
     */
    public function create(Request $request): View
    {
        $user = $request->user();
        $supplierId = $request->query('supplier_id');
        $selectedSupplier = $supplierId ? Supplier::find($supplierId) : null;

        if ($selectedSupplier && !$user->hasLocationAccess($selectedSupplier->primary_location_id)) {
            abort(403, 'Forbidden: You do not have authorization for this supplier.');
        }

        $authorizedLocations = $user->isOwner()
            ? Location::where('is_active', true)->get()
            : $user->locations()->wherePivot('can_operate', true)->get();

        $suppliers = Supplier::authorizedFor($user)->where('is_active', true)->orderBy('business_name')->get();

        return view('supplier_debit_notes.create', compact(
            'authorizedLocations',
            'suppliers',
            'selectedSupplier'
        ));
    }

    /**
     * Store a financial-only supplier debit note.
     */
    public function store(StoreSupplierDebitNoteRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();
        $supplier = Supplier::findOrFail($validated['supplier_id']);

        try {
            $debitNote = $this->purchaseService->recordFinancialDebitNote(
                $supplier,
                $validated,
                $user
            );

            return redirect()->route('supplier-debit-notes.show', $debitNote)
                ->with('success', "Debit Note #{$debitNote->debit_note_number} (PKR " . number_format($debitNote->amount, 2) . ") posted successfully to Supplier Khata.");
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withInput()->withErrors(['amount' => $e->getMessage()]);
        }
    }

    /**
     * Display a specific supplier debit note voucher.
     */
    public function show(Request $request, SupplierDebitNote $supplierDebitNote): View
    {
        $user = $request->user();

        if (!$user->hasLocationAccess($supplierDebitNote->location_id)) {
            abort(403, 'Forbidden: You do not have authorization to view this debit note.');
        }

        $supplierDebitNote->load([
            'supplier',
            'location',
            'purchaseReturn.goodsReceipt',
            'purchaseOrder',
            'creator',
            'postedBy',
        ]);

        return view('supplier_debit_notes.show', compact('supplierDebitNote'));
    }
}
