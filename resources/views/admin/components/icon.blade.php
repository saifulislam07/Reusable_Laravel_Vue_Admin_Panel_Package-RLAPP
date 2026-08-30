{{--
    <x-admin::icon name="users" />

    Self-hosted inline SVG set -- no icon font, no CDN, so the panel renders
    identically offline and carries no third-party branding. Add new glyphs to
    the map below; unknown names fall back to a neutral dot.
--}}
@props(['name' => 'dot', 'size' => null])

@php
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'settings' => '<path d="M4 21v-7"/><path d="M4 10V3"/><path d="M12 21v-9"/><path d="M12 8V3"/><path d="M20 21v-5"/><path d="M20 12V3"/><path d="M1 14h6"/><path d="M9 8h6"/><path d="M17 16h6"/>',
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'key' => '<path d="m15.5 7.5 2.3 2.3a1 1 0 0 0 1.4 0l1.8-1.8a1 1 0 0 0 0-1.4L18.7 4.3a1 1 0 0 0-1.4 0l-1.8 1.8a1 1 0 0 0 0 1.4z"/><path d="m14 8-7.7 7.7a2.4 2.4 0 0 0-.7 1.7v1.6a1 1 0 0 0 1 1h1.6a2.4 2.4 0 0 0 1.7-.7L17.6 12"/><path d="m9 15 1.5 1.5"/>',
        'file-text' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h8"/>',
        'image' => '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/>',
        'mail' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/>',
        'bell' => '<path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
        'activity' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
        'database' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.7 4 3 9 3s9-1.3 9-3V5"/><path d="M3 12c0 1.7 4 3 9 3s9-1.3 9-3"/>',
        'layers' => '<path d="m12 2 9 5-9 5-9-5 9-5z"/><path d="m3 17 9 5 9-5"/><path d="m3 12 9 5 9-5"/>',
        'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
        'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
        'menu' => '<path d="M3 6h18"/><path d="M3 12h18"/><path d="M3 18h18"/>',
        'panel-left' => '<rect x="3" y="3" width="18" height="18" rx="2"/><path d="M9 3v18"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
        'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2"/><path d="M12 20v2"/><path d="m4.9 4.9 1.4 1.4"/><path d="m17.7 17.7 1.4 1.4"/><path d="M2 12h2"/><path d="M20 12h2"/><path d="m4.9 19.1 1.4-1.4"/><path d="m17.7 6.3 1.4-1.4"/>',
        'moon' => '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
        'monitor' => '<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8"/><path d="M12 17v4"/>',
        'log-out' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5"/><path d="M21 12H9"/>',
        'external' => '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/>',
        'alert-triangle' => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'lock' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'compass' => '<circle cx="12" cy="12" r="9"/><path d="m16 8-2 6-6 2 2-6 6-2z"/>',
        'refresh' => '<path d="M21 12a9 9 0 1 1-2.6-6.4"/><path d="M21 3v6h-6"/>',
        'wrench' => '<path d="M14.6 5.4a5 5 0 0 0 6.4 6.4l-8.6 8.6a2.1 2.1 0 0 1-3-3l8.6-8.6a5 5 0 0 0-3.4-3.4z"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'check-check' => '<path d="M18 6 7 17l-5-5"/><path d="m22 10-7.5 7.5L13 16"/>',
        'dot' => '<circle cx="12" cy="12" r="3"/>',
    ];

    $glyph = $paths[$name] ?? $paths['dot'];
    $dimension = $size ? ['width' => $size, 'height' => $size] : [];
@endphp

<svg
    {{ $attributes->class(['nk-icon'])->merge($dimension) }}
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    focusable="false"
>{!! $glyph !!}</svg>
