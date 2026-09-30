<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }} {{ $number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; margin: 0; }
        h1 { font-size: 15px; margin: 0; }
        h2 { font-size: 13px; margin: 0; letter-spacing: 1px; }
        .muted { color: #555; }
        .small { font-size: 9px; }
        table { width: 100%; border-collapse: collapse; }
        .head td { vertical-align: top; padding: 0; }
        .box { border: 1px solid #333; padding: 6px 8px; }
        .lines th, .lines td { border: 1px solid #999; padding: 4px 6px; text-align: left; vertical-align: top; }
        .lines th { background: #f0f0f0; font-weight: bold; }
        .right { text-align: right; }
        .center { text-align: center; }
        .totals td { padding: 3px 6px; }
        .totals .grand td { border-top: 1px solid #333; font-weight: bold; }
        .words { border: 1px solid #333; padding: 6px 8px; margin-top: 8px; font-weight: bold; }
        .sig { margin-top: 34px; }
        .sig td { padding-top: 22px; border-top: 1px solid #333; text-align: center; font-size: 10px; }
        .sig td.gap { border: 0; width: 4%; }
        .note { margin-top: 14px; font-size: 9px; color: #444; }
        hr { border: 0; border-top: 1px solid #333; margin: 8px 0; }
    </style>
</head>
<body>
    <table class="head">
        <tr>
            <td style="width: 60%">
                <h1>{{ $company->name }}</h1>
                <div class="muted">
                    @if ($company->address){{ $company->address }}<br>@endif
                    TIN {{ $company->tin }} &nbsp; Branch {{ $company->branch_code }}
                    @if (isset($company->taxpayer_type)) &nbsp; {{ str($company->taxpayer_type->value ?? $company->taxpayer_type)->replace('_', '-')->upper() }}@endif
                </div>
            </td>
            <td class="right">
                <h2>{{ $title }}</h2>
                <div><strong>No. {{ $number }}</strong></div>
                <div>Date: {{ $date }}</div>
                @yield('head-extra')
            </td>
        </tr>
    </table>
    <hr>

    @yield('body')

    @hasSection('signatories')
        <table class="sig">
            <tr>
                @yield('signatories')
            </tr>
        </table>
    @endif

    @hasSection('note')
        <div class="note">@yield('note')</div>
    @endif
</body>
</html>
