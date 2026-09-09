{{-- ═══════════════════════════════════════════════════════════════
  Logo SEER — Loupe avec bar chart intégré (Variante 3)
  Concept : "voir les données" — analytics + investigation
  ═══════════════════════════════════════════════════════════════ --}}
@props(['size' => 80])

@php
    // ID unique pour éviter les collisions si le logo apparaît plusieurs fois
    $uid = 'seer-' . uniqid();
@endphp

<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" style="display: inline-block;">
    <defs>
        <linearGradient id="{{ $uid }}-grad" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#60a5fa"/>
            <stop offset="100%" stop-color="#a78bfa"/>
        </linearGradient>
        <linearGradient id="{{ $uid }}-accent" x1="0%" y1="0%" x2="0%" y2="100%">
            <stop offset="0%" stop-color="#fbbf24"/>
            <stop offset="100%" stop-color="#f59e0b"/>
        </linearGradient>
        <clipPath id="{{ $uid }}-clip">
            <circle cx="80" cy="80" r="52"/>
        </clipPath>
    </defs>

    {{-- Cercle intérieur de la loupe (fond blanc) --}}
    <circle cx="80" cy="80" r="60" fill="#ffffff"/>

    {{-- Bar chart + ligne de tendance dans la loupe (clippé au cercle) --}}
    <g clip-path="url(#{{ $uid }}-clip)">
        {{-- Bars --}}
        <rect x="45" y="90"  width="12" height="20" rx="2" fill="url(#{{ $uid }}-grad)"/>
        <rect x="63" y="75"  width="12" height="35" rx="2" fill="url(#{{ $uid }}-grad)"/>
        <rect x="81" y="60"  width="12" height="50" rx="2" fill="url(#{{ $uid }}-accent)"/>
        <rect x="99" y="82"  width="12" height="28" rx="2" fill="url(#{{ $uid }}-grad)"/>
        {{-- Ligne de tendance ascendante --}}
        <path d="M 50 90 Q 70 70, 100 55" fill="none" stroke="url(#{{ $uid }}-accent)" stroke-width="3" stroke-linecap="round"/>
    </g>

    {{-- Bordure de la loupe --}}
    <circle cx="80" cy="80" r="60" fill="none" stroke="url(#{{ $uid }}-grad)" stroke-width="14"/>

    {{-- Manche --}}
    <line x1="122" y1="122" x2="180" y2="180" stroke="url(#{{ $uid }}-grad)" stroke-width="18" stroke-linecap="round"/>
</svg>
