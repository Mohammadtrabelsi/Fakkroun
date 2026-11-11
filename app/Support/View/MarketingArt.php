<?php

namespace App\Support\View;

/**
 * SVG path data and colour pairs used by the public pages. They are
 * presentation constants: kept here so the Blade views hold no PHP.
 */
final class MarketingArt
{
    /** Home page: one icon per priority category theme. */
    public const HOME_ICONS = [
        'leaf' => 'M11 20A7 7 0 0 1 4 13c0-6 6-9 16-9 0 10-3 16-9 16zM4 20c4-4 7-7 11-10',
        'wave' => 'M2 12c2.5 0 2.5-2 5-2s2.5 2 5 2 2.5-2 5-2 2.5 2 5 2M2 18c2.5 0 2.5-2 5-2s2.5 2 5 2 2.5-2 5-2 2.5 2 5 2M2 6c2.5 0 2.5-2 5-2s2.5 2 5 2 2.5-2 5-2 2.5 2 5 2',
        'shield-leaf' => 'M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6zM9 13c0-3 2-5 6-5 0 4-2 6-5 6',
        'thermometer' => 'M14 14.8V4a2 2 0 0 0-4 0v10.8a4 4 0 1 0 4 0zM12 8v7',
        'sun' => 'M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10zM12 1v2M12 21v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M1 12h2M21 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4',
        'turtle' => 'M12 6c4.4 0 8 2.7 8 6s-3.6 6-8 6-8-2.7-8-6 3.6-6 8-6zM20 12h2M7 17l-2 3M17 17l2 3M7 7 5 4M17 7l2-3',
        'globe' => 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zM2 12h20M12 2a15 15 0 0 1 0 20M12 2a15 15 0 0 0 0 20',
    ];

    /** Home page: tile colours, in category order. */
    public const HOME_TILES = ['bg-tint-green text-sea', 'bg-tint-sea text-sea', 'bg-tint-green text-sea', 'bg-tint-sun text-sun-text', 'bg-tint-sun text-sun-text', 'bg-tint-sea text-sea', 'bg-tint-green text-sea'];

    /** About page icons. */
    public const ABOUT_ICONS = [
        'leaf' => 'M11 20A7 7 0 0 1 4 13c0-6 6-9 16-9 0 10-3 16-9 16zM4 20c4-4 7-7 11-10',
        'wave' => 'M2 12c2.5 0 2.5-2 5-2s2.5 2 5 2 2.5-2 5-2 2.5 2 5 2M2 18c2.5 0 2.5-2 5-2s2.5 2 5 2 2.5-2 5-2 2.5 2 5 2M2 6c2.5 0 2.5-2 5-2s2.5 2 5 2 2.5-2 5-2 2.5 2 5 2',
        'globe' => 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zM2 12h20M12 2a15 15 0 0 1 0 20M12 2a15 15 0 0 0 0 20',
        'people' => 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8zM22 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8',
        'book' => 'M4 19.5A2.5 2.5 0 0 1 6.5 17H20V3H6.5A2.5 2.5 0 0 0 4 5.5zM4 19.5A2.5 2.5 0 0 0 6.5 22H20v-5',
        'shield' => 'M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6z',
        'chat' => 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z',
        'scale' => 'M12 3v18M5 7h14M5 7l-3 7a4 4 0 0 0 6 0zM19 7l-3 7a4 4 0 0 0 6 0z',
        'tag' => 'M20.6 13.4 13.4 20.6a2 2 0 0 1-2.8 0L3 13V3h10l7.6 7.6a2 2 0 0 1 0 2.8zM7.5 7.5h.01',
        'handshake' => 'M8 12l3 3 6-6M3 12l4-4 4 2 4-2 6 6-7 7z',
        'home' => 'M3 10l9-7 9 7v11H3zM9 21v-7h6v7',
        'class' => 'M2 7l10-4 10 4-10 4zM6 9v5c0 1.7 2.7 3 6 3s6-1.3 6-3V9',
        'trophy' => 'M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0zM17 5h3v2a3 3 0 0 1-3 3M7 5H4v2a3 3 0 0 0 3 3',
        'flag' => 'M4 22V4M4 4h13l-2 4 2 4H4',
        'clock' => 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zM12 6v6l4 2',
        'bolt' => 'M13 2 3 14h9l-1 8 10-12h-9z',
        'brain' => 'M9 3a3 3 0 0 0-3 3 3 3 0 0 0-2 5 3 3 0 0 0 2 5 3 3 0 0 0 6 1V5a3 3 0 0 0-3-2zM15 3a3 3 0 0 1 3 3 3 3 0 0 1 2 5 3 3 0 0 1-2 5 3 3 0 0 1-6 1',
        'lock' => 'M5 11h14v10H5zM8 11V7a4 4 0 0 1 8 0v4',
        'chart' => 'M3 3v18h18M7 15l4-4 3 3 6-6',
        'keys' => 'M2 6h20v12H2zM6 10h.01M10 10h.01M14 10h.01M18 10h.01M7 14h10',
    ];

    public const CHECK_ICON = 'M20 6 9 17l-5-5';

    /** About page: background / text colour pairs, cycled. */
    public const ABOUT_TINTS = [
        ['bg-tint-green', 'text-sea'],
        ['bg-tint-sea', 'text-sea'],
        ['bg-tint-sun', 'text-sun-text'],
    ];

    public const ABOUT_SECTIONS = ['story', 'mission', 'audience', 'values', 'how', 'rules', 'questions', 'partners', 'faq'];
}
