<?php
/**
 * Simple line icons as inline SVG (no icon fonts, no external files).
 * Used by the admin panel and, later, by the public website.
 */
declare(strict_types=1);

function icon_paths(): array
{
    return [
        // --- admin interface ---
        'grid'      => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'sliders'   => '<path d="M4 6h10M18 6h2M4 12h4M12 12h8M4 18h12"/><circle cx="16" cy="6" r="2"/><circle cx="10" cy="12" r="2"/><circle cx="18" cy="18" r="2"/>',
        'pen'       => '<path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4zM13.5 6.5l4 4"/>',
        'quote'     => '<path d="M7 7h4v4c0 3-1.5 5-4 6M15 7h4v4c0 3-1.5 5-4 6"/>',
        'mail'      => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'image'     => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 16-5-5-9 9"/>',
        'lock'      => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
        'logout'    => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3M10 17l5-5-5-5M15 12H3"/>',
        'external'  => '<path d="M14 4h6v6M20 4l-9 9M18 14v4a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4"/>',
        'menu'      => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'close'     => '<path d="M6 6l12 12M18 6 6 18"/>',
        'plus'      => '<path d="M12 5v14M5 12h14"/>',
        'edit'      => '<path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16v4z"/>',
        'trash'     => '<path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3"/>',
        'up'        => '<path d="m6 15 6-6 6 6"/>',
        'down'      => '<path d="m6 9 6 6 6-6"/>',
        'search'    => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
        'upload'    => '<path d="M12 16V4M7 9l5-5 5 5M4 16v3a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-3"/>',
        'copy'      => '<rect x="8" y="8" width="12" height="12" rx="2"/><path d="M16 8V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v10a1 1 0 0 0 1 1h3"/>',
        'file'      => '<path d="M14 3H6a1 1 0 0 0-1 1v16a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V8z"/><path d="M14 3v5h5M9 13h6M9 17h6"/>',
        'check'     => '<path d="m5 12 5 5 9-10"/>',
        'eye'       => '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        // --- business / service icons ---
        'globe'     => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
        'ship'      => '<path d="M3 17l2 3h14l2-3-9-3-9 3zM12 14V4M8 7h4l4 3"/><path d="M5 13V9h14v4"/>',
        'truck'     => '<path d="M2 6h11v10H2zM13 9h4l4 4v3h-8"/><circle cx="6" cy="17.5" r="2"/><circle cx="17" cy="17.5" r="2"/>',
        'building'  => '<path d="M4 21V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v16M16 9h2a2 2 0 0 1 2 2v10M2 21h20M8 7h4M8 11h4M8 15h4"/>',
        'landmark'  => '<path d="M3 21h18M5 21V10M9.5 21V10M14.5 21V10M19 21V10M2 10h20L12 3z"/>',
        'briefcase' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M3 13h18"/>',
        'handshake' => '<path d="M8 12l3 3a2 2 0 0 0 3-3l-3-3-2 1-2-2 3-3h3l5 5-3 3M3 9l4-4 2 2M3 9l6 6M16 17l-2-2"/>',
        'package'   => '<path d="M21 8 12 3 3 8v8l9 5 9-5z"/><path d="m3 8 9 5 9-5M12 13v8M7.5 5.5l9 5"/>',
        'clipboard' => '<rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4V3h6v1M9 11l2 2 4-4M9 17h6"/>',
        'hardhat'   => '<path d="M2 18h20M4 18v-3a8 8 0 0 1 16 0v3M10 7V5h4v2M9 7.5V12M15 7.5V12"/>',
        'tools'     => '<path d="M14 7a4 4 0 0 0 5 5l2 2-7 7-2-2a4 4 0 0 0-5-5L3 10l4-4 3 3 3-3z"/>',
        'factory'   => '<path d="M2 21V10l6 4V10l6 4V6h4l1 15zM2 21h20"/>',
        'award'     => '<circle cx="12" cy="9" r="6"/><path d="M8.5 14 7 22l5-3 5 3-1.5-8"/>',
        'shield'    => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6z"/><path d="m9 12 2 2 4-4"/>',
        'users'     => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6 6 0 0 1 3.5 6"/>',
        'phone'     => '<path d="M5 3h4l2 5-3 2a12 12 0 0 0 6 6l2-3 5 2v4a2 2 0 0 1-2 2A17 17 0 0 1 3 5a2 2 0 0 1 2-2z"/>',
        'leaf'      => '<path d="M5 19C5 9 11 4 20 4c0 9-5 15-15 15zM5 19l8-8"/>',
        'bolt'      => '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
    ];
}

/** Icons the owner can choose for services (name => label). */
function service_icon_choices(): array
{
    return [
        'globe' => 'Globe (import & export)', 'ship' => 'Ship', 'truck' => 'Truck / delivery',
        'package' => 'Package / supply', 'building' => 'Building', 'landmark' => 'Government building',
        'hardhat' => 'Hard hat / construction', 'tools' => 'Tools', 'factory' => 'Factory',
        'briefcase' => 'Briefcase', 'handshake' => 'Handshake', 'clipboard' => 'Clipboard / orders',
        'shield' => 'Shield / quality', 'award' => 'Award', 'bolt' => 'Electrical', 'leaf' => 'Leaf / agriculture',
        'users' => 'People', 'phone' => 'Phone',
    ];
}

function icon_svg(string $name, int $size = 20, string $class = 'icon'): string
{
    $paths = icon_paths();
    return '<svg class="' . htmlspecialchars($class, ENT_QUOTES) . '" viewBox="0 0 24 24" width="' . $size . '" height="' . $size
        . '" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . ($paths[$name] ?? $paths['grid']) . '</svg>';
}
