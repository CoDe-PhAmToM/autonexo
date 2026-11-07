{{-- Silueta de auto en el estilo de la marca, para vehículos sin foto. --}}
@props(['etiqueta' => true])
@php $id = 'sa'.substr(md5(uniqid('', true)), 0, 6); @endphp
<div {{ $attributes->class(['relative grid place-items-center overflow-hidden bg-[radial-gradient(120%_90%_at_50%_100%,rgba(47,107,255,.22),transparent_60%),linear-gradient(180deg,#0B0F1A,#05060B)]']) }}>
    <svg viewBox="0 0 240 120" class="w-3/4 max-w-[260px]" role="img" aria-label="Vehículo sin foto">
        <defs>
            <linearGradient id="{{ $id }}" x1="0" y1="0" x2="1" y2="0">
                <stop offset="0" stop-color="#2F6BFF" />
                <stop offset=".55" stop-color="#20E3F2" />
                <stop offset="1" stop-color="#8B5CFF" />
            </linearGradient>
        </defs>
        <ellipse cx="122" cy="98" rx="102" ry="7" fill="#20E3F2" opacity=".18" />
        <path d="M18 84 C20 72 36 67 60 65 L90 47 C98 42 110 39 124 39 L154 39 C168 39 181 45 193 55 L205 65 C217 67 225 73 226 83 L226 88 L208 88 A18 18 0 0 0 172 88 L84 88 A18 18 0 0 0 48 88 L18 88 Z"
              fill="rgba(255,255,255,.04)" stroke="url(#{{ $id }})" stroke-width="2.5" stroke-linejoin="round" />
        <path d="M70 64 L96 48 C102 45 110 43 120 43 L134 43 L138 64 Z M144 64 L140 43 L152 43 C163 43 173 48 182 56 L190 64 Z"
              fill="rgba(32,227,242,.08)" stroke="rgba(238,242,248,.35)" stroke-width="1.2" />
        <circle cx="66" cy="88" r="13" fill="#05060B" stroke="url(#{{ $id }})" stroke-width="2.5" />
        <circle cx="190" cy="88" r="13" fill="#05060B" stroke="url(#{{ $id }})" stroke-width="2.5" />
        <circle cx="66" cy="88" r="4" fill="#20E3F2" />
        <circle cx="190" cy="88" r="4" fill="#20E3F2" />
        <path d="M212 70 L222 72" stroke="#20E3F2" stroke-width="3" stroke-linecap="round" />
    </svg>
    @if ($etiqueta)
        <span class="absolute bottom-2 font-mono text-[10px] uppercase tracking-[.2em] text-niebla">Sin foto</span>
    @endif
</div>
