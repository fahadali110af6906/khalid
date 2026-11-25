<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreJournalEntryRequest;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\Location;
use App\Services\Accounting\GeneralLedgerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class JournalEntryController extends Controller
{
    public function __construct(
        protected GeneralLedgerService $glService
    ) {}

    /**
     * Display a listing of Journal Entries (Journal Register).
     */
    public function index(Request $request): View
    {
        $user = $request->user();
        $search = $request->query('search');
        $status = $request->query('status', 'all');
        $sourceType = $request->query('source_type');
        $locationId = $request->query('location_id');
        $fromDate = $request->query('from_date');
        $toDate = $request->query('to_date');

        if ($locationId && $locationId !== 'all') {
            if (!$user->hasLocationAccess((int) $locationId)) {
                abort(403, 'Forbidden: You do not have authorization for this branch.');
            }
        } elseif ($locationId === 'all' && !$user->isOwner()) {
            abort(403, 'Forbidden: Only the owner can view cross-location journals.');
        }

        $query = JournalEntry::authorizedFor($user)
            ->with(['location', 'creator', 'lines.account'])
            ->when($search, function ($q) use ($search) {
                $q->where('entry_number', 'like', "%{$search}%")
                  ->orWhere('reference_number', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            })
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($sourceType, fn ($q) => $q->where('source_type', $sourceType))
            ->when($locationId && $locationId !== 'all', fn ($q) => $q->where('location_id', $locationId))
            ->when($fromDate, fn ($q) => $q->whereDate('entry_date', '>=', $fromDate))
            ->when($toDate, fn ($q) => $q->whereDate('entry_date', '<=', $toDate))
            ->latest('entry_date')
            ->latest('id');

        $entries = $query->paginate(25)->withQueryString();

        $authorizedLocations = $user->isOwner()
            ? Location::where('is_active', true)->orderBy('name')->get()
            : $user->locations()->wherePivot('can_view', true)->orderBy('name')->get();

        return view('accounting.journals.index', compact(
            'entries',
            'authorizedLocations',
            'search',
            'status',
            'sourceType',
            'locationId',
            'fromDate',
            'toDate'
        ));
    }

    /**
     * Show the form to create a manual Journal Voucher.
     */
    public function create(Request $request): View
    {
        $user = $request->user();

        $authorizedLocations = $user->isOwner()
            ? Location::where('is_active', true)->orderBy('name')->get()
            : $user->locations()->wherePivot('can_operate', true)->orderBy('name')->get();

        $postableAccounts = ChartOfAccount::postable()->orderBy('code')->get();

        return view('accounting.journals.create', compact('authorizedLocations', 'postableAccounts'));
    }

    /**
     * Store a manual Journal Voucher.
     */
    public function store(StoreJournalEntryRequest $request): RedirectResponse
    {
        $user = $request->user();

        try {
            $header = [
                'entry_date' => $request->validated('entry_date'),
                'location_id' => $request->validated('location_id'),
                'reference_number' => $request->validated('reference_number'),
                'description' => $request->validated('description'),
                'source_type' => 'manual',
                'idempotency_key' => $request->validated('idempotency_key'),
            ];

            $entry = $this->glService->recordJournalEntry($header, $request->validated('lines'), $user);

            return redirect()->route('accounting.journals.show', $entry)
                ->with('success', "Journal Voucher #{$entry->entry_number} posted successfully.");
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withInput()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Display a specific Journal Voucher.
     */
    public function show(Request $request, JournalEntry $journal): View
    {
        $user = $request->user();

        if ($journal->location_id && !$user->hasLocationAccess($journal->location_id)) {
            abort(403, 'Forbidden: You do not have authorization to view this branch voucher.');
        }

        $journal->load([
            'location',
            'creator',
            'reversedBy',
            'reversalOf',
            'lines.account',
            'lines.location',
        ]);

        return view('accounting.journals.show', compact('journal'));
    }

    /**
     * Reverse a posted Journal Voucher.
     */
    public function reverse(Request $request, JournalEntry $journal): RedirectResponse
    {
        $user = $request->user();

        if ($journal->location_id && !$user->hasLocationAccess($journal->location_id, true)) {
            abort(403, 'Forbidden: You do not have authorization to reverse vouchers for this branch.');
        }

        $request->validate([
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $reversal = $this->glService->reverseJournalEntry($journal, $user, $request->input('reason'));

            return redirect()->route('accounting.journals.show', $journal)
                ->with('success', "Journal Voucher #{$journal->entry_number} reversed successfully by Reversal Voucher #{$reversal->entry_number}.");
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }
    }
}
