{{--
    Invite email for a new admin user (App\Notifications\Admin\AdminUserInvited).
    Same hand-rolled, inline-styled layout as admin::mail.reset-password --
    mail clients strip <style> blocks and ignore CSS custom properties.
--}}
@php
    $primary = config('admin-kit.theme.tokens.primary', '#4f46e5');
    $onPrimary = config('admin-kit.theme.tokens.primary-contrast', '#ffffff');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $brand }}</title>
</head>
<body style="margin:0;padding:0;background:#f4f5f7;font-family:-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;padding:32px 16px;">
        <tr>
            <td align="center">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#ffffff;border:1px solid #e5e7eb;border-radius:10px;">
                    <tr>
                        <td style="padding:28px 32px 8px;">
                            <p style="margin:0 0 24px;font-size:15px;font-weight:700;color:{{ $primary }};">
                                {{ $brand }}
                            </p>

                            <h1 style="margin:0 0 12px;font-size:20px;line-height:1.3;">
                                {{ __('Welcome to :brand', ['brand' => $brand]) }}
                            </h1>

                            <p style="margin:0 0 16px;font-size:14px;line-height:1.6;color:#374151;">
                                @if ($greetingName)
                                    {{ __('Hi :name,', ['name' => $greetingName]) }}
                                @else
                                    {{ __('Hi,') }}
                                @endif
                            </p>

                            <p style="margin:0 0 24px;font-size:14px;line-height:1.6;color:#374151;">
                                {{ __('An account has been created for you on :brand. Set a password to get started.', ['brand' => $brand]) }}
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 32px 24px;">
                            <table role="presentation" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="background:{{ $primary }};border-radius:6px;">
                                        <a
                                            href="{{ $url }}"
                                            style="display:inline-block;padding:12px 24px;font-size:14px;font-weight:600;color:{{ $onPrimary }};text-decoration:none;"
                                        >{{ __('Set your password') }}</a>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:0 32px 28px;">
                            <p style="margin:0 0 16px;font-size:13px;line-height:1.6;color:#6b7280;">
                                {{ __('This link expires in :count minutes and can only be used once.', ['count' => $expiresInMinutes]) }}
                            </p>

                            <p style="margin:0;font-size:12px;line-height:1.6;color:#9ca3af;word-break:break-all;">
                                {{ __('Button not working? Paste this into your browser:') }}<br>
                                <a href="{{ $url }}" style="color:{{ $primary }};">{{ $url }}</a>
                            </p>
                        </td>
                    </tr>
                </table>

                <p style="margin:20px 0 0;font-size:12px;color:#9ca3af;">
                    &copy; {{ now()->year }} {{ $brand }}
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
