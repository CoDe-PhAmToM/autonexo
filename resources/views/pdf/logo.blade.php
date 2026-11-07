{{-- Isotipo de AutoNexo para los PDF (dompdf lo lee como imagen SVG). --}}
<img class="logo" alt="AutoNexo" src="data:image/svg+xml;base64,{{ base64_encode(file_get_contents(public_path('favicon.svg'))) }}">
<span class="marca-texto">AUTO<span class="marca-nexo">NEXO</span></span>
<br>
