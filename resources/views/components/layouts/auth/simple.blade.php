<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
        <style>
            .showroom { position: relative; height: 230px; border-radius: 1.5rem; overflow: hidden; }
            .showroom .foco { position: absolute; top: -10%; width: 34%; height: 120%; background: linear-gradient(180deg, rgba(238,242,248,.22), transparent 75%); clip-path: polygon(42% 0, 58% 0, 100% 100%, 0 100%); filter: blur(6px); animation: foco 5s ease-in-out infinite alternate; }
            .showroom .f1 { left: 4%; } .showroom .f2 { left: 33%; animation-delay: -1.6s; } .showroom .f3 { left: 62%; animation-delay: -3.2s; }
            .showroom .piso { position: absolute; left: 8%; right: 8%; bottom: 14%; height: 34px; border-radius: 50%; background: radial-gradient(ellipse, rgba(32,227,242,.38), rgba(47,107,255,.14) 45%, transparent 72%); filter: blur(8px); }
            .showroom .marca { position: absolute; inset: 0; display: grid; place-items: center; }
            @keyframes foco { from { opacity: .45; } to { opacity: 1; } }
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

                <div class="vidrio entrar showroom" style="--i: 1">
                    <span class="foco f1"></span><span class="foco f2"></span><span class="foco f3"></span>
                    <span class="piso"></span>
                    <div class="marca"><x-app-logo-icon class="size-20 opacity-90" /></div>
                </div>

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

                <div class="vidrio entrar flex flex-col gap-6 p-6 sm:p-8">
                    {{ $slot }}
                </div>

                <p class="text-center font-mono text-[11px] tracking-[.2em] text-niebla">EL ALTO · LA PAZ · BOLIVIA</p>
            </div>
        </div>

        @fluxScripts
    </body>
</html>
