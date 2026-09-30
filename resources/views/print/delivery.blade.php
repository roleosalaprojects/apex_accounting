@extends('print.layout', ['title' => 'DELIVERY RECEIPT', 'number' => $delivery->number ?? '—', 'date' => $delivery->delivery_date->format('M j, Y')])

@section('head-extra')
    <div>Sales order: {{ $order->number ?? '—' }} ({{ $order->order_date->format('M j, Y') }})</div>
    @if ($order->reference)<div>Ref.: {{ $order->reference }}</div>@endif
@endsection

@section('body')
    <table class="box">
        <tr>
            <td style="width: 18%"><strong>Deliver to</strong></td>
            <td>
                {{ $customer->name }}
                @if ($customer->tin)<span class="muted">· TIN {{ $customer->tin }}</span>@endif
                @if ($customer->address)<br><span class="muted">{{ $customer->address }}</span>@endif
            </td>
        </tr>
    </table>

    <br>
    <table class="lines">
        <thead>
            <tr><th style="width: 4%">#</th><th>Description</th><th class="right">Delivered</th><th class="right">Ordered</th><th class="right">Delivered to date</th></tr>
        </thead>
        <tbody>
            @foreach ($lines as $i => $row)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $row['description'] }}</td>
                    <td class="right"><strong>{{ $row['qty'] }}</strong></td>
                    <td class="right">{{ $row['ordered'] }}</td>
                    <td class="right">{{ $row['delivered_to_date'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($delivery->notes)
        <br>
        <div class="muted">{{ $delivery->notes }}</div>
    @endif
@endsection

@section('signatories')
    <td>Prepared by<br>{{ optional($delivery->createdBy)->name }}</td>
    <td class="gap"></td>
    <td>Delivered by</td>
    <td class="gap"></td>
    <td>Received by<br>{{ $delivery->received_by ?? '' }}</td>
@endsection

@section('note')
    Received the above goods in good order and condition. Prices and VAT are on the sales invoice.
@endsection
