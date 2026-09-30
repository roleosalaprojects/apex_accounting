@extends('print.layout', ['title' => 'COLLECTION RECEIPT', 'number' => $payment->collection_receipt_no, 'date' => $payment->payment_date->format('M j, Y')])

@section('body')
    <table class="box">
        <tr>
            <td style="width: 18%"><strong>Received from</strong></td>
            <td>
                {{ $payment->customer->name }}
                @if ($payment->customer->tin)<span class="muted">· TIN {{ $payment->customer->tin }}</span>@endif
                @if ($payment->customer->address)<br><span class="muted">{{ $payment->customer->address }}</span>@endif
            </td>
        </tr>
        <tr>
            <td><strong>The sum of</strong></td>
            <td>{{ $words }} &nbsp; <strong>({{ $payment->amount->format() }})</strong></td>
        </tr>
        <tr>
            <td><strong>Form of payment</strong></td>
            <td>
                {{ $method }}
                @if ($payment->external_reference_no) &nbsp;·&nbsp; Ref. {{ $payment->external_reference_no }}@endif
                @if ($payment->number) &nbsp;·&nbsp; Payment {{ $payment->number }}@endif
            </td>
        </tr>
    </table>

    <br>
    <table class="lines">
        <thead>
            <tr><th>In payment of</th><th>Invoice date</th><th class="right">Invoice total</th><th class="right">Applied</th><th class="right">Balance after</th></tr>
        </thead>
        <tbody>
            @foreach ($applications as $row)
                <tr>
                    <td>{{ $row['number'] }}</td>
                    <td>{{ $row['date'] }}</td>
                    <td class="right">{{ $row['total'] }}</td>
                    <td class="right">{{ $row['applied'] }}</td>
                    <td class="right">{{ $row['balance'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <br>
    <table class="totals" style="width: 46%; float: right;">
        <tr><td>Applied to invoices</td><td class="right">{{ $payment->amount->plus($payment->ewt_withheld)->format() }}</td></tr>
        @if (! $payment->ewt_withheld->isZero())
            <tr><td>Less: creditable withholding tax (BIR 2307 to follow)</td><td class="right">({{ $payment->ewt_withheld->format() }})</td></tr>
        @endif
        <tr class="grand"><td>Amount received</td><td class="right">{{ $payment->amount->format() }}</td></tr>
    </table>
    <div style="clear: both"></div>
@endsection

@section('signatories')
    <td style="width: 48%">Received by<br>{{ optional($payment->preparedBy)->name }}</td>
    <td class="gap"></td>
    <td style="width: 48%">Customer's acknowledgement</td>
@endsection

@section('note')
    This Collection Receipt is a supplementary document and is not valid for claiming input taxes. Refer to the Sales Invoice(s) listed above.
@endsection
