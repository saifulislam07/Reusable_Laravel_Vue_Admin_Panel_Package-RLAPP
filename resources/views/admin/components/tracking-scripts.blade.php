{{--
    <x-admin::tracking-scripts />

    Everything Phase 6's integrations tab configures, rendered as the actual
    tags/widgets: the GA4 loader, the Search Console verification meta tag,
    the Messenger chat plugin, and a self-built floating WhatsApp button.
    Echo this once in the public site's layout head/body -- it renders
    nothing for any integration that isn't both enabled and filled in.

    Resolves its own settings (rather than relying on a view composer) so it
    works from any layout, including ones outside the admin:: namespace --
    resources/views/app.blade.php, in this project.
--}}
@php
    $nkIntegrations = app(\App\Support\Admin\IntegrationSettings::class);
    $nkGa = $nkIntegrations->get('google_analytics');
    $nkGsc = $nkIntegrations->get('google_search_console');
    $nkMessenger = $nkIntegrations->get('facebook_messenger');
    $nkWhatsapp = $nkIntegrations->get('whatsapp');

    $nkWhatsappDigits = preg_replace('/\D+/', '', (string) ($nkWhatsapp['number'] ?? ''));
@endphp
{{-- Google Search Console site verification --}}
@if (filled($nkGsc['verification_meta'] ?? null))
    <meta name="google-site-verification" content="{{ $nkGsc['verification_meta'] }}">
@endif

{{-- Google Analytics (GA4) --}}
@if (($nkGa['enabled'] ?? false) && filled($nkGa['measurement_id'] ?? null))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $nkGa['measurement_id'] }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag() { dataLayer.push(arguments); }
        gtag('js', new Date());
        gtag('config', @json($nkGa['measurement_id']));
    </script>
@endif

{{-- Facebook Messenger chat plugin --}}
@if (($nkMessenger['enabled'] ?? false) && filled($nkMessenger['page_id'] ?? null))
    <div id="fb-root"></div>
    <div
        class="fb-customerchat"
        attribution="setup_tool"
        page_id="{{ $nkMessenger['page_id'] }}"
    ></div>
    <script>
        window.fbAsyncInit = function () {
            FB.init({
                xfbml: true,
                version: 'v19.0',
                @if (filled($nkMessenger['app_id'] ?? null))
                    appId: @json($nkMessenger['app_id']),
                @endif
            });
        };
    </script>
    <script async defer crossorigin="anonymous" src="https://connect.facebook.net/en_US/sdk/xfbml.customerchat.js"></script>
@endif

{{--
    WhatsApp floating button (self-built -- not an official Meta widget).

    Styled with an inline <style> block rather than an nk-* class from
    admin.css: this component is meant to be echoed on the public site,
    which loads its own CSS bundle and never loads the admin panel's --
    admin.css being absent there would leave the button completely
    unstyled otherwise.
--}}
@if (($nkWhatsapp['enabled'] ?? false) && $nkWhatsappDigits !== '')
    <style>
        .nk-whatsapp-button {
            position: fixed;
            right: 20px;
            bottom: 20px;
            z-index: 2147483000;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 56px;
            height: 56px;
            color: #ffffff;
            background: #25d366;
            border-radius: 999px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.25);
            text-decoration: none;
        }

        .nk-whatsapp-button:hover {
            background: #20bd5a;
        }

        .nk-whatsapp-button svg {
            width: 28px;
            height: 28px;
        }
    </style>

    <a
        href="https://wa.me/{{ $nkWhatsappDigits }}?text={{ rawurlencode((string) ($nkWhatsapp['message'] ?? '')) }}"
        class="nk-whatsapp-button"
        target="_blank"
        rel="noopener noreferrer"
        aria-label="{{ __('Chat on WhatsApp') }}"
    >
        <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false">
            <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.29-1.39a9.9 9.9 0 0 0 4.75 1.21h.01c5.46 0 9.9-4.45 9.9-9.91C21.96 6.45 17.5 2 12.04 2zm5.86 14.02c-.25.7-1.45 1.34-2 1.42-.51.08-1.15.11-1.86-.12-.43-.13-.98-.32-1.69-.62-2.97-1.28-4.9-4.27-5.05-4.47-.15-.2-1.22-1.62-1.22-3.1 0-1.47.77-2.19 1.05-2.49.27-.3.6-.37.8-.37.2 0 .4 0 .57.01.18.01.43-.07.67.51.25.6.85 2.08.92 2.23.07.15.12.33.02.53-.1.2-.15.32-.3.5-.15.17-.31.39-.44.52-.15.15-.3.31-.13.61.17.3.75 1.24 1.62 2.01 1.11 1 2.05 1.3 2.35 1.45.3.15.47.13.65-.08.17-.2.73-.85.93-1.15.2-.3.4-.24.66-.15.27.1 1.7.8 1.99.95.3.15.49.22.56.35.08.13.08.75-.17 1.45z"/>
        </svg>
    </a>
@endif
