<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 14px; margin: 0; }
        h2 { font-size: 12px; margin: 2px 0 0; font-weight: normal; }
        .muted { color: #555; }
        table { width: 100%; border-collapse: collapse; }
        .box td, .box th { border: 1px solid #999; padding: 4px 6px; text-align: left; }
        .box th { background: #f0f0f0; }
        .right { text-align: right; }
        .sig { margin-top: 48px; width: 100%; }
        .sig td { padding-top: 24px; border-top: 1px solid #333; text-align: center; width: 50%; font-size: 10px; }
    </style>
</head>
<body>
    <div style="text-align:center">
        <h1>Certificate of Creditable Tax Withheld at Source</h1>
        <h2>BIR Form No. 2307</h2>
    </div>

    <p>
        For the period
        <strong>{{ $payment->payment_date->copy()->firstOfQuarter()->toDateString() }}</strong>
        to
        <strong>{{ $payment->payment_date->copy()->lastOfQuarter()->toDateString() }}</strong>
    </p>

    <table class="box">
        <tr>
            <th colspan="2">Payee Information</th>
        </tr>
        <tr>
            <td style="width:50%">Registered Name: <strong>{{ $vendor->name }}</strong></td>
            <td>TIN: {{ $vendor->tin ?? '—' }}</td>
        </tr>
        <tr>
            <th colspan="2">Payor Information</th>
        </tr>
        <tr>
            <td>Registered Name: <strong>{{ $company->name }}</strong></td>
            <td>TIN: {{ $company->tin ?? '—' }} &nbsp; Branch {{ $company->branch_code }}</td>
        </tr>
    </table>

    <br>

    <table class="box">
        <thead>
            <tr>
                <th>Income Payment Subject to Expanded Withholding Tax</th>
                <th>ATC</th>
                <th class="right">Amount of Income Payment</th>
                <th class="right">Rate</th>
                <th class="right">Tax Withheld</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($payment->withholdingTransactions as $wt)
                <tr>
                    <td>{{ $wt->withholdingCode?->name ?? $wt->withholdingCode?->code ?? 'Income payment' }}</td>
                    <td>{{ $wt->atc }}</td>
                    <td class="right">{{ number_format($wt->base->minor / 100, 2) }}</td>
                    <td class="right">{{ rtrim(rtrim(number_format($wt->rate_bp / 100, 2), '0'), '.') }}%</td>
                    <td class="right">{{ number_format($wt->ewt->minor / 100, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <th colspan="2">Total</th>
                <th class="right">{{ number_format($payment->withholdingTransactions->sum(fn ($wt) => $wt->base->minor) / 100, 2) }}</th>
                <th></th>
                <th class="right">{{ number_format($payment->withholdingTransactions->sum(fn ($wt) => $wt->ewt->minor) / 100, 2) }}</th>
            </tr>
        </tfoot>
    </table>

    <p class="muted">
        Reference: payment {{ $payment->number }}@if ($payment->voucher_no), voucher {{ $payment->voucher_no }}@endif,
        dated {{ $payment->payment_date->toDateString() }}.
    </p>

    <table class="sig">
        <tr>
            <td>Payor / Authorized Representative<br>(Signature over printed name)</td>
            <td>Payee / Authorized Representative<br>(Signature over printed name)</td>
        </tr>
    </table>
</body>
</html>
