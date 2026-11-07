@props(['animado' => false])
{{-- Isotipo de AutoNexo: una N trazada como dos carriles unidos por la diagonal (el nexo). --}}
@php $id = 'gn'.substr(md5(uniqid('', true)), 0, 6); @endphp
<svg viewBox="0 0 120 120" xmlns="http://www.w3.org/2000/svg" {{ $attributes }} role="img" aria-label="AutoNexo">
    <defs>
        <linearGradient id="{{ $id }}" x1="0" y1="1" x2="1" y2="0">
            <stop offset="0" stop-color="#2F6BFF" />
            <stop offset=".55" stop-color="#20E3F2" />
            <stop offset="1" stop-color="#8B5CFF" />
            @if ($animado)
                <animateTransform attributeName="gradientTransform" type="rotate" values="0 .5 .5;360 .5 .5" dur="8s" repeatCount="indefinite" />
            @endif
        </linearGradient>
        <linearGradient id="{{ $id }}b" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="rgba(255,255,255,.65)" />
            <stop offset=".5" stop-color="rgba(255,255,255,.08)" />
            <stop offset="1" stop-color="rgba(32,227,242,.75)" />
        </linearGradient>
    </defs>
    <rect x="3" y="3" width="114" height="114" rx="30" fill="rgba(255,255,255,.07)" stroke="url(#{{ $id }}b)" stroke-width="3" />
    <path @class(['logo-trazo' => $animado]) d="M38 84 V36 L82 84 V36" fill="none" stroke="url(#{{ $id }})" stroke-width="11" stroke-linecap="round" stroke-linejoin="round" />
    <path d="M45 44 L75 76" stroke="#05060B" stroke-width="2" stroke-dasharray="5 6" stroke-linecap="round" opacity=".9" />
    <circle @class(['logo-nodo' => $animado]) cx="38" cy="84" r="8" fill="#20E3F2" />
    <circle @class(['logo-nodo' => $animado]) cx="82" cy="36" r="8" fill="#20E3F2" />
</svg>
