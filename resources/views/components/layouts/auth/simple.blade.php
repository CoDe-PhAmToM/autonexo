<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        <style>
            /* Showroom: carrusel lento de fotos (Unsplash), cada una 6 s, con fundido y acercamiento. */
            .showroom { position: relative; aspect-ratio: 16 / 9; max-height: 340px; width: 100%; border-radius: 1.5rem; overflow: hidden; background: #05060B; }
            .showroom .diapo { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0; animation: diapo 18s infinite; }
            .showroom .diapo:nth-of-type(1) { opacity: 1; object-position: 50% 46%; animation-delay: 0s; }
            .showroom .diapo:nth-of-type(2) { object-position: 50% 55%; animation-delay: 6s; }
            .showroom .diapo:nth-of-type(3) { object-position: 50% 60%; animation-delay: 12s; }
            .showroom .sombra { position: absolute; inset: 0; background: linear-gradient(180deg, rgba(5,6,11,.15), transparent 35%, transparent 55%, rgba(5,6,11,.85)), radial-gradient(120% 90% at 50% 40%, transparent 55%, rgba(5,6,11,.6)); }
            .showroom .piso { position: absolute; left: 12%; right: 12%; bottom: 6%; height: 40px; border-radius: 50%; background: radial-gradient(ellipse, rgba(32,227,242,.45), rgba(47,107,255,.18) 45%, transparent 72%); filter: blur(10px); mix-blend-mode: screen; animation: brillo 4s ease-in-out infinite alternate; }
            .showroom .chip { position: absolute; left: 16px; top: 14px; }
            .showroom .credito { position: absolute; right: 14px; bottom: 10px; font-size: 10px; letter-spacing: .04em; color: rgba(238,242,248,.55); }
            @keyframes diapo { 0% { opacity: 0; transform: scale(1.08); } 6% { opacity: 1; } 33% { opacity: 1; } 40% { opacity: 0; transform: scale(1); } 100% { opacity: 0; transform: scale(1); } }
            @keyframes brillo { from { opacity: .55; } to { opacity: 1; } }
        </style>
    </head>
    <body class="min-h-screen bg-noche font-sans text-cromo antialiased">
        <x-fondo-vivo />

        <div class="mx-auto grid min-h-svh max-w-6xl items-center gap-10 px-5 py-10 lg:grid-cols-[minmax(0,1.15fr)_minmax(0,430px)] lg:gap-16 lg:px-10">
            {{-- Portada (pantallas grandes) --}}
            <section class="hidden flex-col gap-8 lg:flex">
                <a href="{{ route('home') }}" class="relative flex items-center gap-5" wire:navigate>
                    <span class="logo-halo absolute -left-3 -top-3 size-28 rounded-[30%] bg-[radial-gradient(circle,rgba(32,227,242,.55),rgba(47,107,255,.22)_50%,transparent_72%)] blur-xl"></span>
                    <x-app-logo-icon :animado="true" class="relative size-22" />
                    <span class="relative grid gap-2">
                        <span class="font-display text-3xl tracking-[.12em] text-cromo">AUTO<span class="texto-vivo">NEXO</span></span>
                        <span class="font-mono text-xs tracking-[.3em] text-niebla">VENTAS · COBROS · TRANSFERENCIAS</span>
                    </span>
                </a>

                <h1 class="max-w-xl font-display text-4xl leading-tight tracking-wide text-balance">
                    De la vitrina a la <span class="texto-vivo">transferencia</span>
                </h1>
                <p class="max-w-lg text-lg text-niebla">
                    Inventario por chasis, ventas a crédito, cobros en bolivianos o dólares y el seguimiento del trámite, en un solo lugar.
                </p>

                <x-showroom style="--i: 1" />

                <div class="grid grid-cols-3 gap-3">
                    <div class="vidrio entrar px-4 py-3" style="--i: 2">
                        <div class="font-mono text-xl text-cromo">USD · Bs</div>
                        <div class="text-xs text-niebla">Cobros con tipo de cambio del día</div>
                    </div>
                    <div class="vidrio entrar px-4 py-3" style="--i: 3">
                        <div class="font-mono text-xl text-cromo">&lt; 60 s</div>
                        <div class="text-xs text-niebla">Para registrar un cobro</div>
                    </div>
                    <div class="vidrio entrar px-4 py-3" style="--i: 4">
                        <div class="font-mono text-xl text-cromo">IMT</div>
                        <div class="text-xs text-niebla">Plazo y alertas del trámite</div>
                    </div>
                </div>
            </section>

            {{-- Formulario --}}
            <div class="mx-auto flex w-full max-w-sm flex-col gap-6 lg:max-w-none">
                <a href="{{ route('home') }}" class="flex flex-col items-center gap-3 lg:hidden" wire:navigate>
                    <x-app-logo-icon :animado="true" class="size-16" />
                    <span class="font-display text-xl tracking-[.12em]">AUTO<span class="texto-vivo">NEXO</span></span>
                </a>

                <x-showroom class="lg:hidden" style="--i: 0" />

                <div class="vidrio entrar flex flex-col gap-6 p-6 sm:p-8">
                    {{ $slot }}
                </div>

                <p class="text-center font-mono text-[11px] tracking-[.2em] text-niebla">EL ALTO · LA PAZ · BOLIVIA</p>
            </div>
        </div>

        @fluxScripts
    </body>
</html>
