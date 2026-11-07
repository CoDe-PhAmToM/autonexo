{{-- Showroom del login: carrusel lento de fotos libres de Unsplash (ver estilos en layouts/auth/simple). --}}
<div {{ $attributes->class(['vidrio entrar showroom']) }}>
    <img class="diapo" src="{{ asset('img/showroom/showroom-principal.jpg') }}" alt="Auto de frente en un estudio oscuro, entre líneas de luz" decoding="async">
    <img class="diapo" src="{{ asset('img/showroom/showroom-azul.jpg') }}" alt="Auto azul con luces horizontales en un estudio" loading="lazy" decoding="async">
    <img class="diapo" src="{{ asset('img/showroom/showroom-estudio.jpg') }}" alt="SUV azul oscuro bajo un foco de estudio" loading="lazy" decoding="async">
    <span class="sombra"></span>
    <span class="piso"></span>
    <span class="chip inline-flex items-center gap-2 rounded-full border border-white/15 bg-noche/50 px-3 py-1 font-mono text-[11px] uppercase tracking-[.18em] text-cromo backdrop-blur-md">
        <span class="punto-vivo"></span> Showroom
    </span>
    <span class="credito">Fotos: Hyundai Motor Group y ObjectType RAW · Unsplash</span>
</div>
