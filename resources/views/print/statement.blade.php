@extends('print.layout', ['title' => 'STATEMENT OF ACCOUNT', 'number' => $customer->code, 'date' => $asOf])

@section('head-extra')
    <div>Period: {{ $from }} – {{ $asOf }}</div>
@endsection

@section('body')
    <table class="box">
        <tr>
            <td style="width: 18%"><strong>Customer</strong></td>
            <td>
                {{ $customer->name }}
                @if ($customer->tin)<span class="muted">· TIN {{ $customer->tin }}</span>@endif
                @if ($customer->address)<br><span class="muted">{{ $customer->address }}</span>@endif
            </td>
            <td class="right" style="width: 30%">
                <div class="muted">Amount due</div>
                <h1>{{ $closing }}</h1>
            </td>
        </tr>
    </table>

    <br>
    <table class="lines">
        <thead>
            <tr><th>Date</th><th>Reference</th><th>Type</th><th class="right">Charges</th><th class="right">Credits</th><th class="right">Balance</th></tr>
        </thead>
        <tbody>
            <tr><td></td><td></td><td>Balance brought forward</td><td></td><td></td><td class="right">{{ $opening }}</td></tr>
            @foreach ($rows as $row)
                <tr>
                    <td>{{ $row['date'] }}</td>
                    <td>{{ $row['number'] }}</td>
                    <td>{{ $row['type'] }}</td>
                    <td class="right">{{ $row['charge'] }}</td>
                    <td class="right">{{ $row['credit'] }}</td>
                    <td class="right">{{ $row['balance'] }}</td>
                </tr>
            @endforeach
            <tr><td></td><td></td><td><strong>Balance due</strong></td><td></td><td></td><td class="right"><strong>{{ $closing }}</strong></td></tr>
        </tbody>
    </table>

    <br>
    <table class="lines" style="width: 70%">
        <thead>
            <tr>
                @foreach ($aging as $bucket => $amount)<th class="right">{{ $bucket }}</th>@endforeach
            </tr>
        </thead>
        <tbody>
            <tr>
                @foreach ($aging as $amount)<td class="right">{{ $amount }}</td>@endforeach
            </tr>
        </tbody>
    </table>
@endsection

@section('note')
    Amounts are in Philippine pesos. Please quote the invoice numbers when remitting. If you have already paid, kindly disregard this statement.
@endsection
