<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $company->name }}</title>
</head>
<body style="margin:0; padding:0; background:#f4f4f5; font-family: -apple-system, 'Segoe UI', Helvetica, Arial, sans-serif; color:#18181b;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5; padding:24px 12px;">
        <tr><td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px; width:100%; background:#ffffff; border-radius:8px; overflow:hidden;">
                <tr>
                    <td style="padding:20px 28px; background:#18181b; color:#ffffff;">
                        <div style="font-size:18px; font-weight:600;">{{ $company->name }}</div>
                        <div style="font-size:13px; color:#d4d4d8; margin-top:4px;">For {{ $party }}</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px; font-size:15px; line-height:1.55;">
                        {!! nl2br(e($body)) !!}
                    </td>
                </tr>
                @if ($facts !== [])
                    <tr>
                        <td style="padding:0 28px 24px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse; width:100%; background:#fafafa; border:1px solid #e4e4e7; border-radius:6px;">
                                @foreach ($facts as $label => $value)
                                    <tr>
                                        <td style="padding:8px 14px; font-size:13px; color:#71717a; width:40%;">{{ $label }}</td>
                                        <td style="padding:8px 14px; font-size:14px; font-weight:600;">{{ $value }}</td>
                                    </tr>
                                @endforeach
                            </table>
                        </td>
                    </tr>
                @endif
                @if ($table !== null)
                    <tr>
                        <td style="padding:0 28px 24px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse; width:100%; font-size:13px;">
                                <thead>
                                    <tr>
                                        @foreach ($table['columns'] as $column)
                                            <th align="left" style="padding:8px 10px; border-bottom:2px solid #e4e4e7; color:#71717a; font-weight:600;">{{ $column }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($table['rows'] as $row)
                                        <tr>
                                            @foreach ($row as $cell)
                                                <td style="padding:8px 10px; border-bottom:1px solid #f4f4f5;">{{ $cell }}</td>
                                            @endforeach
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </td>
                    </tr>
                @endif
                <tr>
                    <td style="padding:18px 28px; background:#fafafa; border-top:1px solid #e4e4e7; font-size:12px; color:#71717a; line-height:1.5;">
                        {{ $company->name }}@if ($company->tin) · TIN {{ $company->tin }}@endif<br>
                        @if ($company->address){{ $company->address }}<br>@endif
                        @if ($company->email){{ $company->email }}@endif
                    </td>
                </tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
