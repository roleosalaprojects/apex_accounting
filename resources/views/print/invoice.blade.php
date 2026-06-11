<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin: 0; }
        .muted { color: #555; }
        table { width: 100%; border-collapse: collapse; }
        .lines th, .lines td { border: 1px solid #999; padding: 4px 6px; text-align: left; }
        .lines th { background: #f0f0f0; }
        .right { text-align: right; }
        .totals td { padding: 2px 6px; }
        .exempt { font-weight: bold; color: #7a0000; }
        .sig { margin-top: 40px; width: 100%; }
        .sig td { padding-top: 24px; border-top: 1px solid #333; text-align: center; width: 33%; font-size: 10px; }
    </style>
</head>
<body>
    <h1>{{ $company->name }}</h1>
    <div class="muted">
        {{ $company->address }}<br>
        TIN {{ $company->tin }} &nbsp; Branch {{ $company->branch_code }}
    </div>
    <hr>
    <table>
        <tr>
            <td>
                <strong>INVOICE</strong><br>
                No.: {{ $invoice->number }}<br>
                Date: {{ $invoice->invoice_date->toDateString() }}<br>
                Due: {{ optional($invoice->due_date)->toDateString() }}
            </td>
            <td class="right">
                <strong>Bill To</strong><br>
                {{ $invoice->customer->name }}<br>
                TIN {{ $invoice->customer->tin }}
            </td>
        </tr>
    </table>

    <br>
    <table class="lines">
        <thead>
            <tr>
                <th>Description</th><th class="right">Qty</th><th class="right">Unit Price</th>
                <th>Tax</th><th class="right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($invoice->lines as $line)
                <tr>
                    <td>
                        {{ $line->description }}
                        @if ($line->taxCode && $line->taxCode->isExempt())
                            <span class="exempt">— VAT-EXEMPT SALE</span>
                        @endif
                    </td>
                    <td class="right">{{ $line->qty }}</td>
                    <td class="right">{{ $line->unit_price->format() }}</td>
                    <td>{{ optional($line->taxCode)->code }}</td>
                    <td class="right">{{ $line->line_total->plus($line->vat_amount)->format() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <br>
    <table class="totals" style="width: 40%; float: right;">
        <tr><td>VATable Sales</td><td class="right">{{ $invoice->vatable_sales->format() }}</td></tr>
        <tr><td>VAT (12%)</td><td class="right">{{ $invoice->vat_amount->format() }}</td></tr>
        <tr><td>VAT-Exempt Sales</td><td class="right">{{ $invoice->exempt_sales->format() }}</td></tr>
        <tr><td>Zero-Rated Sales</td><td class="right">{{ $invoice->zero_rated_sales->format() }}</td></tr>
        <tr><td><strong>Total</strong></td><td class="right"><strong>{{ $invoice->total->format() }}</strong></td></tr>
    </table>

    <table class="sig">
        <tr>
            <td>Prepared by<br>{{ optional($invoice->preparedBy)->name }}</td>
            <td>Checked by<br>{{ optional($invoice->checkedBy)->name }}</td>
            <td>Approved by<br>{{ optional($invoice->approvedBy)->name }}</td>
        </tr>
    </table>
</body>
</html>
