<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white dark:bg-zinc-800">
        <flux:sidebar sticky stashable class="border-r border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('dashboard') }}" class="mr-5 flex items-center space-x-2" wire:navigate>
                <x-app-logo class="size-8" href="#"></x-app-logo>
            </a>

            <flux:navlist variant="outline">
                <flux:navlist.group :heading="auth()->user()->negocio?->nombre ?? 'AutoNexo'" class="grid">
                    <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>Panel</flux:navlist.item>
                    @can('vehiculos.ver')
                        <flux:navlist.item icon="truck" :href="route('vehiculos.index')" :current="request()->routeIs('vehiculos.*')" wire:navigate>Vehículos</flux:navlist.item>
                    @endcan
                    @can('ver-ventas')
                        <flux:navlist.item icon="banknotes" :href="route('ventas.index')" :current="request()->routeIs('ventas.*')" wire:navigate>Ventas</flux:navlist.item>
                    @endcan
                    @can('cobros.registrar')
                        <flux:navlist.item icon="currency-dollar" :href="route('cobros.index')" :current="request()->routeIs('cobros.*')" wire:navigate>Cobros</flux:navlist.item>
                    @endcan
                    @can('cartera.ver')
                        <flux:navlist.item icon="chart-bar" :href="route('cartera.index')" :current="request()->routeIs('cartera.*')" wire:navigate>Cartera</flux:navlist.item>
                    @endcan
                    @can('caja.cerrar')
                        <flux:navlist.item icon="lock-closed" :href="route('caja.index')" :current="request()->routeIs('caja.*')" wire:navigate>Cierre de caja</flux:navlist.item>
                    @endcan
                    @can('transferencias.gestionar')
                        <flux:navlist.item icon="clipboard-document-check" :href="route('transferencias.index')" :current="request()->routeIs('transferencias.*')" wire:navigate>Transferencias</flux:navlist.item>
                    @endcan
                    @can('repuestos.gestionar')
                        <flux:navlist.item icon="wrench-screwdriver" :href="route('repuestos.index')" :current="request()->routeIs('repuestos.*')" wire:navigate>Repuestos</flux:navlist.item>
                    @endcan
                </flux:navlist.group>

                @can('usuarios.gestionar')
                    <flux:navlist.group heading="Administración" class="grid">
                        <flux:navlist.item icon="users" :href="route('usuarios.index')" :current="request()->routeIs('usuarios.*')" wire:navigate>Usuarios</flux:navlist.item>
                        @can('respaldos.gestionar')
                            <flux:navlist.item icon="circle-stack" :href="route('respaldos.index')" :current="request()->routeIs('respaldos.*')" wire:navigate>Respaldos</flux:navlist.item>
                        @endcan
                        @can('datos.importar')
                            <flux:navlist.item icon="arrow-up-tray" :href="route('carga.index')" :current="request()->routeIs('carga.*')" wire:navigate>Carga inicial</flux:navlist.item>
                        @endcan
                    </flux:navlist.group>
                @endcan
            </flux:navlist>

            <flux:spacer />

            <!-- Desktop User Menu -->
            <flux:dropdown position="bottom" align="start">
                <flux:profile
                    :name="auth()->user()->name"
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevrons-up-down"
                />

                <flux:menu class="w-[220px]">
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-left text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->nombreRol() }} · {{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item href="/settings/profile" icon="cog" wire:navigate>Ajustes</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            Cerrar sesión
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="auth()->user()->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                                <span class="relative flex h-8 w-8 shrink-0 overflow-hidden rounded-lg">
                                    <span
                                        class="flex h-full w-full items-center justify-center rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ auth()->user()->initials() }}
                                    </span>
                                </span>

                                <div class="grid flex-1 text-left text-sm leading-tight">
                                    <span class="truncate font-semibold">{{ auth()->user()->name }}</span>
                                    <span class="truncate text-xs">{{ auth()->user()->nombreRol() }} · {{ auth()->user()->email }}</span>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item href="/settings/profile" icon="cog" wire:navigate>Ajustes</flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                            Cerrar sesión
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @fluxScripts
    </body>
</html>
