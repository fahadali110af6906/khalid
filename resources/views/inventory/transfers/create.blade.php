@extends('layouts.app')

@section('title', __('erp.stock_transfers.new_transfer'))

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Breadcrumb / Back -->
    <div class="flex items-center justify-between">
        <a href="{{ route('inventory.transfers.index') }}" class="inline-flex items-center gap-2 text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Back to Stock Transfers</span>
        </a>
    </div>

    @if($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs">
            <ul class="list-disc pl-4 space-y-1">
                @foreach($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
            <h1 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <i data-lucide="arrow-left-right" class="w-5 h-5 text-indigo-600 dark:text-indigo-400"></i>
                <span>{{ __('erp.stock_transfers.new_transfer') }}</span>
            </h1>
            <p class="text-xs text-slate-500 mt-1">Inter-branch movement of footwear cartons and pairs between shops, central warehouse, or factory.</p>
        </div>

        <form action="{{ route('inventory.transfers.store') }}" method="POST" class="p-6 space-y-6">
            @csrf

            <!-- Header Info -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        Source Location (From) <span class="text-rose-500">*</span>
                    </label>
                    <select name="source_location_id" required class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white py-2 px-3">
                        <option value="">Select Source Location...</option>
                        @foreach($sourceLocations as $loc)
                            <option value="{{ $loc->id }}" {{ old('source_location_id') == $loc->id ? 'selected' : '' }}>
                                {{ $loc->name }} ({{ ucfirst($loc->type) }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        Destination Location (To) <span class="text-rose-500">*</span>
                    </label>
                    <select name="destination_location_id" required class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white py-2 px-3">
                        <option value="">Select Destination Location...</option>
                        @foreach($allLocations as $loc)
                            <option value="{{ $loc->id }}" {{ old('destination_location_id') == $loc->id ? 'selected' : '' }}>
                                {{ $loc->name }} ({{ ucfirst($loc->type) }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        Transfer Date <span class="text-rose-500">*</span>
                    </label>
                    <input type="date" name="transfer_date" value="{{ old('transfer_date', date('Y-m-d')) }}" required
                           class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white py-2 px-3">
                </div>
            </div>

            <!-- Notes -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                    Dispatch Notes / Driver / Vehicle #
                </label>
                <input type="text" name="dispatch_notes" value="{{ old('dispatch_notes') }}" placeholder="e.g. Transferred via Rickshaw #123 / Driver Aslam"
                       class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white py-2 px-3">
            </div>

            <!-- Transfer Items Table -->
            <div class="space-y-3 pt-4 border-t border-slate-200 dark:border-slate-700">
                <div class="flex items-center justify-between">
                    <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                        Transfer Footwear Articles & Variants
                    </h2>
                </div>

                <div class="overflow-x-auto border border-slate-200 dark:border-slate-700 rounded-xl">
                    <table class="w-full text-left text-xs" id="items-table">
                        <thead class="bg-slate-50 dark:bg-slate-700/60 text-slate-500 font-bold uppercase">
                            <tr>
                                <th class="py-2.5 px-3">Variant (Article / Size / Color)</th>
                                <th class="py-2.5 px-3 w-28">Cartons</th>
                                <th class="py-2.5 px-3 w-28">Pairs</th>
                                <th class="py-2.5 px-3">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                            @for($i = 0; $i < 5; $i++)
                                <tr>
                                    <td class="p-2">
                                        <select name="items[{{ $i }}][product_variant_id]" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white py-1.5 px-2">
                                            <option value="">Select Variant...</option>
                                            @foreach($products as $prod)
                                                <optgroup label="{{ $prod->article_code }} — {{ $prod->name }} (Pack: {{ $prod->carton_pack_qty }})">
                                                    @foreach($prod->variants as $var)
                                                        <option value="{{ $var->id }}" {{ old("items.{$i}.product_variant_id") == $var->id ? 'selected' : '' }}>
                                                            {{ $var->sku }} (Size: {{ $var->size }}, Color: {{ $var->color }})
                                                        </option>
                                                    @endforeach
                                                </optgroup>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td class="p-2">
                                        <input type="number" min="0" name="items[{{ $i }}][quantity_cartons]" value="{{ old("items.{$i}.quantity_cartons") }}" placeholder="0"
                                               class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white py-1.5 px-2 text-center font-mono">
                                    </td>
                                    <td class="p-2">
                                        <input type="number" min="0" step="1" name="items[{{ $i }}][quantity_pairs]" value="{{ old("items.{$i}.quantity_pairs") }}" placeholder="0"
                                               class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white py-1.5 px-2 text-center font-mono font-bold">
                                    </td>
                                    <td class="p-2">
                                        <input type="text" name="items[{{ $i }}][notes]" value="{{ old("items.{$i}.notes") }}" placeholder="Optional notes"
                                               class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white py-1.5 px-2">
                                    </td>
                                </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
                <p class="text-[11px] text-slate-400">Fill either Cartons or Pairs (or both). Leave unused rows blank.</p>
            </div>

            <!-- Submit Buttons -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-700">
                <a href="{{ route('inventory.transfers.index') }}" class="px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-xs font-semibold text-slate-600 dark:text-slate-300">
                    Cancel
                </a>
                <button type="submit" name="action" value="save_draft" class="px-5 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-800 dark:text-slate-200 text-xs font-semibold transition">
                    Save as Draft
                </button>
                <button type="submit" name="action" value="dispatch_immediately" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-md transition">
                    Dispatch Immediately
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
