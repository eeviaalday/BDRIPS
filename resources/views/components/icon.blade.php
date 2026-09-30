@props([
    'name',
    'class' => 'w-6 h-6',
])

{{--
    Centralised BDRPIS icon set.

    Icons are inline SVG (no icon-font dependency, no extra network request).
    Every icon is decorative by default: the parent element must carry the
    accessible label. Pass an `alt`/`title` when the icon is the only content.
--}}

@php
    $paths = [
        'clipboard' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M9 8h6M6 4h12a1 1 0 011 1v14a1 1 0 01-1 1H6a1 1 0 01-1-1V5a1 1 0 011-1z"/>',
        'heart-hands' => '<path stroke-linecap="round" stroke-linejoin="round" d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l8.84 8.84 8.84-8.84a5.5 5.5 0 000-7.78z"/>',
        'briefcase' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21 13.255A23.93 23.93 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>',
        'home' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 12l9-9 9 9M5 10v10a1 1 0 001 1h12a1 1 0 001-1V10"/>',
        'user' => '<path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM4 21v-1a7 7 0 0114 0v1"/>',
        'users' => '<path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-1a4 4 0 00-3-3.87M9 20H4v-1a5 5 0 019.33-2.5M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>',
        'phone' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h2.2a1 1 0 01.98.804l.7 4.2a1 1 0 01-.553.935l-1.6.8a12 12 0 005.51 5.51l.8-1.6a1 1 0 01.935-.553l4.2.7A1 1 0 0121 15.2V17a2 2 0 01-2 2A16 16 0 013 5z"/>',
        'mail' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 8l9 6 9-6M4 6h16a1 1 0 011 1v10a1 1 0 01-1 1H4a1 1 0 01-1-1V7a1 1 0 011-1z"/>',
        'pin' => '<path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>',
        'clock' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 2m6-2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        'calendar' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8 7V5a1 1 0 011-1h6a1 1 0 011 1v2m-9 0h10a1 1 0 011 1v10a1 1 0 01-1 1H8a1 1 0 01-1-1V8a1 1 0 011-1z"/><path stroke-linecap="round" stroke-linejoin="round" d="M11 12h2v2h-2z"/>',
        'check' => '<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>',
        'check-circle' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        'x-circle' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15 9l-6 6m0-6l6 6m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>',
        'search' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/>',
        'arrow-right' => '<path stroke-linecap="round" stroke-linejoin="round" d="M5 12h14m0 0l-6-6m6 6l-6 6"/>',
        'shield' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m5.6 2A9 9 0 113 12a9 9 0 0118 0z"/>',
        'chart' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-2m3 2v-4m3 4v-6M5 17v-8m3 8v-7m3 7v-9m3 9V9m3 8v-4m0-10l2 2m0 0l-2 2"/>',
        'megaphone' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 11v2a1 1 0 001 1h2l4 4V6L6 10H4a1 1 0 00-1 1zm12-3a7 7 0 010 10m3-14a11 11 0 010 18"/>',
        'sparkles' => '<path stroke-linecap="round" stroke-linejoin="round" d="M5 3v4m3-4v4M5 11h8M7.5 7.5h8M19 13v4m2-2h-4m-2 8v-2m0-4h4m-2-2h.01"/>',
        'building' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 21h18M5 21V5a2 2 0 012-2h6a2 2 0 012 2v16M15 21V9h3a2 2 0 012 2v10M9 7h2M9 11h2M9 15h2"/>',
        'lock' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v9a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>',
        'inbox' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 12h5l2 3h4l2-3h5M5 5h14a1 1 0 011 1v12a1 1 0 01-1 1H5a1 1 0 01-1-1V6a1 1 0 011-1z"/>',
        'download' => '<path stroke-linecap="round" stroke-linejoin="round" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M12 4v12m0 0l-4-4m4 4l4-4"/>',
        'document' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6M9 8h6M5 4h11l3 3v13a1 1 0 01-1 1H5a1 1 0 01-1-1V5a1 1 0 011-1z"/>',
        'hand' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 11V5a1.5 1.5 0 013 0v6m0-3a1.5 1.5 0 013 0v3m0-2a1.5 1.5 0 013 0v5a6 6 0 01-6 6h-1a7 7 0 01-7-7v-3a1.5 1.5 0 013 0"/>',
        'leaf' => '<path stroke-linecap="round" stroke-linejoin="round" d="M5 21c0-9 4-16 16-16 0 12-4 16-11 16H5zm5-5c3-3 6-5 11-6"/>',
        'scale' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 3v18M7 7l-4 8h8L7 7zm10 0l-4 8h8l-4-8zM5 7h14"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
        'gift' => '<path stroke-linecap="round" stroke-linejoin="round" d="M20 12v9H4v-9M2 7h20v5H2zM12 22V7M12 7H7.5a2.5 2.5 0 110-5C11 2 12 7 12 7zM12 7h4.5a2.5 2.5 0 100-5C13 2 12 7 12 7z"/>',
        'id-badge' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 5h18a1 1 0 011 1v12a1 1 0 01-1 1H3a1 1 0 01-1-1V6a1 1 0 011-1z"/><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 9.5l1.6 1.6 2.9-2.9"/><circle cx="8.5" cy="10.5" r="1.8"/><path stroke-linecap="round" stroke-linejoin="round" d="M5.5 16c.6-1.2 1.8-1.8 3-1.8s2.4.6 3 1.8"/>',
        // Drawn inside a rounded square rather than as a solid brand mark: this
        // set is stroke-only and fill="none", so a filled glyph would render as
        // a hollow outline. The shape still reads as Facebook at button size.
        'facebook' => '<rect x="3" y="3" width="18" height="18" rx="3"/><path stroke-linecap="round" stroke-linejoin="round" d="M13.9 21v-7.5h2.5l.4-2.9h-2.9V8.8c0-.83.23-1.4 1.43-1.4h1.52V4.79A19.4 19.4 0 0014.63 4.7c-2.02 0-3.4 1.23-3.4 3.5v2.3H8.5v2.9h2.73V21"/>',
    ];

    $path = $paths[$name] ?? $paths['document'];
@endphp

<svg {{ $attributes->merge(['class' => $class]) }} fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
    {!! $path !!}
</svg>
