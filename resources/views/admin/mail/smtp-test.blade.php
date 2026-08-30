{{--
    Sent by the "Send test email" button on the SMTP settings tab. Plain and
    short on purpose -- its only job is to prove the configured SMTP
    credentials can actually deliver.
--}}
@php
    $brand = (string) config('admin-kit.brand.name');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $brand }}</title>
</head>
<body style="margin:0;padding:32px 16px;background:#f4f5f7;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border:1px solid #e5e7eb;border-radius:10px;padding:28px 32px;">
                    <tr>
                        <td>
                            <p style="margin:0 0 16px;font-size:15px;font-weight:700;color:#4f46e5;">{{ $brand }}</p>
                            <h1 style="margin:0 0 12px;font-size:18px;">{{ __('SMTP is working') }}</h1>
                            <p style="margin:0;font-size:14px;line-height:1.6;color:#374151;">
                                {{ __('This test email confirms the SMTP settings saved in the admin panel can send mail.') }}
                            </p>
                            <p style="margin:16px 0 0;font-size:12px;color:#9ca3af;">
                                {{ now()->toDayDateTimeString() }}
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
