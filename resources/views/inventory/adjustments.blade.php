@extends('layouts.app')

@section('content')
<div class="space-y-8">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2.5">
                <h1 class="text-2xl font-bold tracking-tight text-slate-900">Inventory Adjustments</h1>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                    Audited Adjustments & Approvals
                </span>
            </div>
            <p class="text-sm text-slate-500 mt-1">Formal physical stock counts, reconciliations, and owner approvals. Silent balance edits are prohibited.</p>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('inventory.index') }}" class="px-3.5 py-2 rounded-lg bg-white border border-slate-300 text-slate-700 text-xs font-bold hover:bg-slate-50 transition">
                Back to Stock Ledger
            </a>
            <a href="{{ route('inventory.adjustments.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-xs transition">
                <i data-lucide="plus" class="w-4 h-4"></i>
                <span>New Stock Count / Adjustment</span>
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm font-medium flex items-center gap-2">
            <i data-lucide="check-circle" class="w-5 h-5 text-emerald-600"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    <!-- Adjustments Table -->
    <div class="bg-white rounded-2xl border border-slate-200/80 shadow-xs overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left rtl:text-right text-sm">
                <thead class="bg-slate-50/80 text-[11px] uppercase tracking-wider text-slate-500 font-bold border-b border-slate-100">
                    <tr>
                        <th class="py-3 px-6">Adjustment #</th>
                        <th class="py-3 px-6">Location</th>
                        <th class="py-3 px-6">Reason</th>
                        <th class="py-3 px-6">Created By</th>
                        <th class="py-3 px-6 text-center">Items Count</th>
                        <th class="py-3 px-6 text-center">Status</th>
                        <th class="py-3 px-6 text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 font-medium text-slate-700">
                    @forelse($adjustments as $adjustment)
                        <tr class="hover:bg-slate-50/60 transition">
                            <td class="py-4 px-6 font-mono font-bold text-indigo-600">
                                {{ $adjustment->adjustment_number }}
                            </td>
                            <td class="py-4 px-6 font-bold text-slate-900 text-xs">
                                {{ $adjustment->location->name }}
                            </td>
                            <td class="py-4 px-6 text-xs text-slate-700">
                                {{ $adjustment->reason }}
                            </td>
                            <td class="py-4 px-6 text-xs text-slate-500">
                                {{ $adjustment->creator->name ?? '—' }}
                                <span class="block text-[10px]">{{ $adjustment->created_at->format('d M Y, h:i A') }}</span>
                            </td>
                            <td class="py-4 px-6 text-center font-mono text-xs">
                                {{ $adjustment->items->count() }} Items
                            </td>
                            <td class="py-4 px-6 text-center">
                                @if($adjustment->status === 'approved')
                                    <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        Approved by {{ $adjustment->approver->name ?? 'Owner' }}
                                    </span>
                                @elseif($adjustment->status === 'draft')
                                    <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                        Pending Owner Approval
                                    </span>
                                @else
                                    <span class="px-2.5 py-1 rounded-full text-xs font-bold bg-rose-50 text-rose-700 border border-rose-200">
                                        Rejected
                                    </span>
                                @endif
                            </td>
                            <td class="py-4 px-6 text-center">
                                @if($adjustment->status === 'draft' && auth()->user()->isOwner())
                                    <form method="POST" action="{{ route('inventory.adjustments.approve', $adjustment) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="px-3 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-xs transition">
                                            Approve & Post
                                        </button>
                                    </form>
                                @else
                                    <span class="text-xs text-slate-400 font-mono">—</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 text-center text-slate-400">
                                <i data-lucide="scale" class="w-10 h-10 mx-auto text-slate-300 mb-2"></i>
                                <p class="text-sm font-semibold">No stock adjustments on record.</p>
                                <p class="text-xs text-slate-400 mt-1">Stock adjustments are generated during physical counts or damage write-offs.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="p-4 border-t border-slate-100">
            {{ $adjustments->links() }}
        </div>
    </div>
</div>
@endsection
