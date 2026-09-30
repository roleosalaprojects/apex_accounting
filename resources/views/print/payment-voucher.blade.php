@extends('print.layout', ['title' => 'PAYMENT VOUCHER', 'number' => $payment->voucher_no ?? $payment->number, 'date' => $payment->payment_date->format('M j, Y')])

@section('body')
    <table class="box">
        <tr>
            <td style="width: 18%"><strong>Pay to</strong></td>
            <td>
                {{ $payment->vendor->name }}
                @if ($payment->vendor->tin)<span class="muted">· TIN {{ $payment->vendor->tin }}</span>@endif
                @if ($payment->vendor->address)<br><span class="muted">{{ $payment->vendor->address }}</span>@endif
            </td>
        </tr>
        <tr>
            <td><strong>The sum of</strong></td>
            <td>{{ $words }} &nbsp; <strong>({{ $payment->net_paid->format() }})</strong></td>
        </tr>
        <tr>
            <td><strong>Paid by</strong></td>
            <td>
                {{ $method }} from {{ $account }}
                @if ($payment->external_reference_no) &nbsp;·&nbsp; Check no. {{ $payment->external_reference_no }}@endif
                @if ($payment->number) &nbsp;·&nbsp; Payment {{ $payment->number }}@endif
            </td>
        </tr>
    </table>

    <br>
    <table class="lines">
        <thead>
            <tr><th>Bill</th><th>Bill date</th><th class="right">Bill total</th><th class="right">Applied</th><th class="right">Balance after</th></tr>
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
    <table class="totals" style="width: 50%; float: right;">
        <tr><td>Gross amount applied</td><td class="right">{{ $payment->gross_applied->format() }}</td></tr>
        @foreach ($withholdings as $row)
            <tr><td>Less: EWT {{ $row['atc'] }} at {{ $row['rate'] }} on {{ $row['base'] }}</td><td class="right">({{ $row['ewt'] }})</td></tr>
        @endforeach
        <tr class="grand"><td>Net paid</td><td class="right">{{ $payment->net_paid->format() }}</td></tr>
    </table>
    <div style="clear: both"></div>
    @if ($payment->journalEntry)
        <div class="small muted" style="clear: both; padding-top: 6px">Journal entry {{ $payment->journalEntry->number }}</div>
    @endif
@endsection

@section('signatories')
    <td>Prepared by<br>{{ optional($payment->preparedBy)->name }}</td>
    <td class="gap"></td>
    <td>Checked by<br>{{ optional($payment->checkedBy)->name }}</td>
    <td class="gap"></td>
    <td>Approved by<br>{{ optional($payment->approvedBy)->name }}</td>
    <td class="gap"></td>
    <td>Received by (payee)<br>&nbsp;</td>
@endsection
