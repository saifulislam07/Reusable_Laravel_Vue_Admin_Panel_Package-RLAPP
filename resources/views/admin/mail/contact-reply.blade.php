{{--
    Expects: $contactMessage, $reply (both public readonly on the Mailable)
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
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #e5e7eb;border-radius:10px;padding:28px 32px;">
                    <tr>
                        <td>
                            <p style="margin:0 0 16px;font-size:15px;font-weight:700;color:#4f46e5;">{{ $brand }}</p>

                            <p style="margin:0 0 20px;font-size:14px;line-height:1.6;color:#374151;white-space:pre-line;">{{ $reply }}</p>

                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:8px;border-top:1px solid #e5e7eb;padding-top:16px;">
                                <tr>
                                    <td>
                                        <p style="margin:0 0 6px;font-size:12px;color:#9ca3af;">{{ __('Your original message:') }}</p>
                                        <p style="margin:0;font-size:13px;line-height:1.6;color:#6b7280;white-space:pre-line;">{{ $contactMessage->message }}</p>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
