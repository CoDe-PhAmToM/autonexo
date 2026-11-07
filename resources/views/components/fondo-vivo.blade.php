{{-- Fondo de la marca: niebla de color, estelas de luz y grano. Se conserva entre pantallas (wire:navigate). --}}
@persist('fondo-vivo')
    <div class="fondo-vivo" aria-hidden="true">
        <span class="mancha m1"></span>
        <span class="mancha m2"></span>
        <span class="mancha m3"></span>
        <span class="mancha m4"></span>
        <canvas id="estelas"></canvas>
        <div class="grano"></div>
    </div>
@endpersist
