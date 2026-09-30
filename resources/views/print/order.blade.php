@extends('print.layout', ['title' => $title, 'number' => $order->number ?? '—', 'date' => $order->order_date->format('M j, Y')])

@section('head-extra')
    @if ($secondDate)<div>{{ $secondDateLabel }}: {{ $secondDate }}</div>@endif
    @if ($order->reference)<div>Ref.: {{ $order->reference }}</div>@endif
    <div>Status: {{ ucfirst($order->status) }}</div>
@endsection

@section('body')
    <table class="box">
        <tr>
            <td style="width: 18%"><strong>{{ $partyLabel }}</strong></td>
            <td>
                {{ $party->name }}
                @if ($party->tin)<span class="muted">· TIN {{ $party->tin }}</span>@endif
                @if ($party->address)<br><span class="muted">{{ $party->address }}</span>@endif
            </td>
        </tr>
    </table>

    <br>
    <table class="lines">
        <thead>
            <tr><th style="width: 4%">#</th><th>Description</th><th class="right">Qty</th><th class="right">Unit price</th><th>Tax</th><th class="right">Amount</th></tr>
        </thead>
        <tbody>
            @foreach ($lines as $i => $row)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $row['description'] }}</td>
                    <td class="right">{{ $row['qty'] }}</td>
                    <td class="right">{{ $row['unit_price'] }}</td>
                    <td>{{ $row['tax'] }}</td>
                    <td class="right">{{ $row['amount'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <br>
    <table class="totals" style="width: 40%; float: right;">
        <tr class="grand"><td>Total ({{ $pricing }})</td><td class="right">{{ $total }}</td></tr>
    </table>
    <div style="clear: both"></div>
    <div class="words">{{ $words }}</div>
@endsection

@section('signatories')
    <td>Prepared by<br>{{ optional($order->createdBy)->name }}</td>
    <td class="gap"></td>
    <td>Approved by</td>
    <td class="gap"></td>
    <td>{{ $acknowledgeLabel }}</td>
@endsection
