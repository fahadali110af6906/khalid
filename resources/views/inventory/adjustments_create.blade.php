@extends('layouts.app')

@section('title', 'New Stock Count & Inventory Adjustment')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Breadcrumb / Back -->
    <div class="flex items-center justify-between">
        <a href="{{ route('inventory.adjustments') }}" class="inline-flex items-center gap-2 text-sm text-slate-500 hover:text-slate-700 dark:hover:text-slate-300">
            <i data-lucide="arrow-left" class="w-4 h-4"></i>
            <span>Back to Adjustments Register</span>
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
                <i data-lucide="scale" class="w-5 h-5 text-indigo-600 dark:text-indigo-400"></i>
                <span>Physical Stock Count & Discrepancy Adjustment</span>
            </h1>
            <p class="text-xs text-slate-500 mt-1">Submit physical inventory audit adjustments. Drafts are safely queued for owner approval before ledger posting.</p>
        </div>

        <form action="{{ route('inventory.adjustments.store') }}" method="POST" class="p-6 space-y-6">
            @csrf

            <!-- Header Info -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        Location / Branch <span class="text-rose-500">*</span>
                    </label>
                    <select name="location_id" required class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white py-2 px-3">
                        <option value="">Select Location...</option>
                        @foreach($authorizedLocations as $loc)
                            <option value="{{ $loc->id }}" {{ old('location_id') == $loc->id ? 'selected' : '' }}>
                                {{ $loc->name }} ({{ ucfirst($loc->type) }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                        Reason for Adjustment <span class="text-rose-500">*</span>
                    </label>
                    <select name="reason" required class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white py-2 px-3">
                        <option value="Physical Stock Count Reconciliation">Physical Stock Count Reconciliation</option>
                        <option value="Damage / Defect Write-off">Damage / Defect Write-off</option>
                        <option value="Sample Dispatch">Sample Dispatch</option>
                        <option value="Audit Discrepancy Correction">Audit Discrepancy Correction</option>
                        <option value="Found Stock / Excess Inventory">Found Stock / Excess Inventory</option>
                    </select>
                </div>
            </div>

            <!-- Notes -->
            <div>
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5">
                    Audit Notes / Comments
                </label>
                <textarea name="notes" rows="2" placeholder="Describe the reason for variance or details of physical count audit..."
                          class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white py-2 px-3">{{ old('notes') }}</textarea>
            </div>

            <!-- Adjustment Items -->
            <div class="space-y-3 pt-4 border-t border-slate-200 dark:border-slate-700">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                    Adjustment Line Items (Positive = Increase, Negative = Decrease)
                </h2>

                <div class="overflow-x-auto border border-slate-200 dark:border-slate-700 rounded-xl">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 dark:bg-slate-700/60 text-slate-500 font-bold uppercase">
                            <tr>
                                <th class="py-2.5 px-3">Product Variant</th>
                                <th class="py-2.5 px-3 w-32">Delta Pairs (+ / -)</th>
                                <th class="py-2.5 px-3 w-28">Unit Cost (Rs.)</th>
                                <th class="py-2.5 px-3">Line Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                            @for($i = 0; $i < 5; $i++)
                                <tr>
                                    <td class="p-2">
                                        <select name="items[{{ $i }}][product_variant_id]" class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white py-1.5 px-2">
                                            <option value="">Select Variant...</option>
                                            @foreach($products as $prod)
                                                <optgroup label="{{ $prod->article_code }} — {{ $prod->name }}">
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
                                        <input type="number" step="1" name="items[{{ $i }}][quantity_delta_pairs]" value="{{ old("items.{$i}.quantity_delta_pairs") }}" placeholder="+10 or -5"
                                               class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white py-1.5 px-2 text-center font-mono font-bold">
                                    </td>
                                    <td class="p-2">
                                        <input type="number" min="0" step="0.01" name="items[{{ $i }}][unit_cost]" value="{{ old("items.{$i}.unit_cost", "0.00") }}" placeholder="0.00"
                                               class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white py-1.5 px-2 text-right font-mono">
                                    </td>
                                    <td class="p-2">
                                        <input type="text" name="items[{{ $i }}][notes]" value="{{ old("items.{$i}.notes") }}" placeholder="Optional line note"
                                               class="w-full text-xs rounded-lg border-slate-300 dark:border-slate-600 dark:bg-slate-700 dark:text-white py-1.5 px-2">
                                    </td>
                                </tr>
                            @endfor
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Submit -->
            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-700">
                <a href="{{ route('inventory.adjustments') }}" class="px-4 py-2 rounded-xl border border-slate-300 dark:border-slate-600 text-xs font-semibold text-slate-600 dark:text-slate-300">
                    Cancel
                </a>
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-md transition">
                    Submit for Owner Approval
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
