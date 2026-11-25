<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() === 'ur' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invoice #{{ $invoice->invoice_number }} — Khalid Traders</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; font-size: 13px; color: #1e293b; line-height: 1.4; padding: 24px; background: #fff; }
        .invoice-box { max-width: 800px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #0f172a; padding-bottom: 16px; margin-bottom: 20px; }
        .title { font-size: 24px; font-weight: 800; color: #0f172a; text-transform: uppercase; letter-spacing: 0.5px; }
        .subtitle { font-size: 12px; color: #64748b; margin-top: 2px; }
        .invoice-meta { text-align: right; }
        .invoice-meta h2 { font-size: 18px; color: #4338ca; font-family: monospace; }
        .parties { display: flex; justify-content: space-between; margin-bottom: 24px; }
        .party-col { width: 48%; }
        .party-title { font-size: 11px; text-transform: uppercase; font-weight: 700; color: #94a3b8; margin-bottom: 6px; }
        .party-name { font-size: 16px; font-weight: 700; color: #0f172a; }
        .party-details { font-size: 12px; color: #475569; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th { background: #f8fafc; border-bottom: 2px solid #cbd5e1; padding: 8px 10px; font-size: 11px; text-transform: uppercase; font-weight: 700; text-align: left; }
        td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; font-size: 12px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-mono { font-family: monospace; }
        .totals-section { display: flex; justify-content: flex-end; margin-bottom: 30px; }
        .totals-table { width: 320px; }
        .totals-table td { padding: 6px 10px; border: none; }
        .totals-table tr.grand-total { font-size: 15px; font-weight: 800; border-top: 2px solid #0f172a; border-bottom: 2px solid #0f172a; }
        .signatures { display: flex; justify-content: space-between; margin-top: 50px; padding-top: 20px; }
        .sig-box { width: 200px; text-align: center; border-top: 1px dashed #94a3b8; padding-top: 8px; font-size: 11px; font-weight: 600; color: #64748b; }
        .print-btn-bar { margin-bottom: 20px; display: flex; justify-content: flex-end; gap: 10px; }
        .btn { padding: 8px 16px; background: #4f46e5; color: #fff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 12px; }
        @media print {
            .print-btn-bar { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
    <div class="invoice-box">
        <div class="print-btn-bar">
            <button onclick="window.print()" class="btn">Print Invoice</button>
            <a href="{{ route('sales.invoices.show', $invoice) }}" class="btn" style="background:#64748b;">Back</a>
        </div>

        <div class="header">
            <div>
                <div class="title">Khalid Traders</div>
                <div class="subtitle">Wholesale Footwear Manufacturers & Distributors</div>
                <div class="subtitle">Branch / Shop: {{ $invoice->location->name }} ({{ $invoice->location->code }})</div>
            </div>
            <div class="invoice-meta">
                <h2>{{ $invoice->invoice_number }}</h2>
                <div style="font-size: 12px; margin-top: 4px;">Date: <strong>{{ $invoice->invoice_date->format('Y-m-d') }}</strong></div>
                <div style="font-size: 11px; color: #64748b;">Status: {{ strtoupper($invoice->status) }} | {{ strtoupper($invoice->payment_status) }}</div>
            </div>
        </div>

        <div class="parties">
            <div class="party-col">
                <div class="party-title">Bill To (Party / Customer):</div>
                <div class="party-name">{{ $invoice->customer->business_name }}</div>
                <div class="party-details">
                    Party Code: <strong>{{ $invoice->customer->customer_code }}</strong><br>
                    Contact Person: {{ $invoice->customer->contact_person }}<br>
                    Phone: {{ $invoice->customer->phone }}<br>
                    @if ($invoice->customer->address)
                        Address: {{ $invoice->customer->address }}, {{ $invoice->customer->city }}
                    @endif
                </div>
            </div>
            <div class="party-col" style="text-align: right;">
                <div class="party-title">Delivery Details:</div>
                <div class="party-details">
                    Location: {{ $invoice->location->name }}<br>
                    Sales Representative: {{ $invoice->creator->name }}<br>
                    @if ($invoice->notes)
                        Notes / Bilty: <em>{{ $invoice->notes }}</em>
                    @endif
                </div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 30px;">#</th>
                    <th>Article / Description</th>
                    <th>Size / Color</th>
                    <th class="text-right">Cartons</th>
                    <th class="text-right">Pairs</th>
                    <th class="text-right">Rate (PKR)</th>
                    <th class="text-right">Amount (PKR)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->items as $idx => $item)
                    <tr>
                        <td class="text-center font-mono">{{ $idx + 1 }}</td>
                        <td>
                            <strong>{{ $item->variant->product->name }}</strong>
                            <div style="font-size: 10px; color: #64748b; font-family: monospace;">{{ $item->variant->sku }}</div>
                        </td>
                        <td>
                            Size: {{ $item->variant->size }} | Color: {{ $item->variant->color }}
                        </td>
                        <td class="text-right font-mono">{{ (float)$item->quantity_cartons }}</td>
                        <td class="text-right font-mono">{{ number_format($item->quantity_pairs, 0) }}</td>
                        <td class="text-right font-mono">{{ number_format($item->unit_price, 2) }}</td>
                        <td class="text-right font-mono" style="font-weight: 700;">{{ number_format($item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals-section">
            <table class="totals-table">
                <tr>
                    <td>Subtotal:</td>
                    <td class="text-right font-mono">{{ number_format($invoice->subtotal_amount, 2) }} PKR</td>
                </tr>
                @if ((float)$invoice->discount_amount > 0)
                    <tr style="color: #dc2626;">
                        <td>Discount:</td>
                        <td class="text-right font-mono">-{{ number_format($invoice->discount_amount, 2) }} PKR</td>
                    </tr>
                @endif
                @if ((float)$invoice->tax_amount > 0)
                    <tr>
                        <td>Tax:</td>
                        <td class="text-right font-mono">+{{ number_format($invoice->tax_amount, 2) }} PKR</td>
                    </tr>
                @endif
                <tr class="grand-total">
                    <td>Grand Total:</td>
                    <td class="text-right font-mono" style="color: #4338ca;">{{ number_format($invoice->total_amount, 2) }} PKR</td>
                </tr>
                <tr style="color: #16a34a; font-weight: 600;">
                    <td>Paid / Received:</td>
                    <td class="text-right font-mono">{{ number_format($invoice->paid_amount, 2) }} PKR</td>
                </tr>
                <tr style="font-weight: 700; color: {{ (float)$invoice->balance_amount > 0 ? '#d97706' : '#64748b' }};">
                    <td>Remaining Balance:</td>
                    <td class="text-right font-mono">{{ number_format($invoice->balance_amount, 2) }} PKR</td>
                </tr>
            </table>
        </div>

        <div class="signatures">
            <div class="sig-box">Prepared By (Counter)</div>
            <div class="sig-box">Receiver Signature (Party)</div>
            <div class="sig-box">Authorized Signature / Stamp</div>
        </div>
    </div>
</body>
</html>
