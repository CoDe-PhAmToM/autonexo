/*
 * AutoNexo — efectos visuales: estelas de luz, tarjetas en cascada, brillo e
 * inclinación al pasar el mouse y números que cuentan hasta su valor.
 * Se vuelve a aplicar después de cada navegación de Livewire (wire:navigate).
 */

const reducir = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/* ---------------------------------------------------------------- estelas */

function iniciarEstelas() {
    const lienzo = document.getElementById('estelas');
    if (!lienzo || lienzo.dataset.listo || reducir) return;
    lienzo.dataset.listo = '1';

    const ctx = lienzo.getContext('2d');
    const colores = ['32,227,242', '47,107,255', '139,92,255', '238,242,248'];
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    let ancho = 0, alto = 0, estelas = [];

    const medir = () => {
        ancho = lienzo.clientWidth;
        alto = lienzo.clientHeight;
        lienzo.width = ancho * dpr;
        lienzo.height = alto * dpr;
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    };

    const nueva = (alInicio) => {
        const largo = 120 + Math.random() * 240;
        return {
            x: alInicio ? Math.random() * (ancho + 400) - 200 : -largo - Math.random() * 400,
            y: Math.random() * alto * 1.1,
            v: 1.8 + Math.random() * 4.2,
            l: largo,
            g: .6 + Math.random() * 1.6,
            c: colores[Math.floor(Math.random() * colores.length)],
            a: .18 + Math.random() * .4,
        };
    };

    medir();
    const cantidad = Math.round(Math.min(22, ancho / 70));
    for (let i = 0; i < cantidad; i++) estelas.push(nueva(true));
    window.addEventListener('resize', medir);

    const inclinacion = -0.18;
    const cuadro = () => {
        if (!document.hidden) {
            ctx.clearRect(0, 0, ancho, alto);
            estelas.forEach((e, i) => {
                e.x += e.v;
                e.y += e.v * inclinacion;
                if (e.x - e.l > ancho || e.y < -60) {
                    estelas[i] = nueva(false);
                    return;
                }
                const x2 = e.x - e.l, y2 = e.y - e.l * inclinacion;
                const grad = ctx.createLinearGradient(e.x, e.y, x2, y2);
                grad.addColorStop(0, `rgba(${e.c},${e.a})`);
                grad.addColorStop(1, `rgba(${e.c},0)`);
                ctx.strokeStyle = grad;
                ctx.lineWidth = e.g;
                ctx.lineCap = 'round';
                ctx.beginPath();
                ctx.moveTo(e.x, e.y);
                ctx.lineTo(x2, y2);
                ctx.stroke();
                ctx.fillStyle = `rgba(${e.c},${Math.min(1, e.a + .3)})`;
                ctx.beginPath();
                ctx.arc(e.x, e.y, e.g * 1.3, 0, Math.PI * 2);
                ctx.fill();
            });
        }
        requestAnimationFrame(cuadro);
    };
    requestAnimationFrame(cuadro);
}

/* ------------------------------------------------- tarjetas y números */

const formato = (valor, decimales) =>
    valor.toLocaleString('es-BO', { minimumFractionDigits: decimales, maximumFractionDigits: decimales });

function contar(raiz) {
    raiz.querySelectorAll('[data-contar]').forEach((el, i) => {
        if (el.dataset.contado) return;
        el.dataset.contado = '1';
        const fin = parseFloat(el.dataset.contar);
        const decimales = el.dataset.decimales !== undefined ? parseInt(el.dataset.decimales, 10) : 2;
        const prefijo = el.dataset.prefijo || '';
        const sufijo = el.dataset.sufijo || '';
        const finalTexto = prefijo + formato(fin, decimales) + sufijo;
        if (reducir || !isFinite(fin)) {
            el.textContent = finalTexto;
            return;
        }
        const duracion = 1200, retraso = 120 + i * 90;
        let inicio = null;
        const paso = (t) => {
            if (inicio === null) inicio = t;
            const p = Math.min(1, Math.max(0, (t - inicio - retraso) / duracion));
            const suave = 1 - Math.pow(1 - p, 4);
            el.textContent = p < 1 ? prefijo + formato(fin * suave, decimales) + sufijo : finalTexto;
            if (p < 1) requestAnimationFrame(paso);
        };
        requestAnimationFrame(paso);
    });
}

function prepararTarjetas(raiz) {
    raiz.querySelectorAll('[data-flux-main] [data-flux-card], [data-flux-main] .entrar').forEach((tarjeta, i) => {
        tarjeta.style.setProperty('--i', Math.min(i, 10));
    });

    raiz.querySelectorAll('[data-flux-card], .vidrio').forEach((tarjeta) => {
        if (tarjeta.dataset.brillo) return;
        tarjeta.dataset.brillo = '1';
        const inclina = tarjeta.classList.contains('tarjeta-dato') && !reducir;

        tarjeta.addEventListener('pointermove', (e) => {
            const r = tarjeta.getBoundingClientRect();
            const x = e.clientX - r.left, y = e.clientY - r.top;
            tarjeta.style.setProperty('--mx', `${x}px`);
            tarjeta.style.setProperty('--my', `${y}px`);
            if (inclina) {
                const rx = ((.5 - y / r.height) * 8).toFixed(2);
                const ry = ((x / r.width - .5) * 10).toFixed(2);
                tarjeta.style.transform = `perspective(900px) rotateX(${rx}deg) rotateY(${ry}deg) translateY(-4px)`;
            }
        });

        if (inclina) {
            tarjeta.addEventListener('pointerleave', () => { tarjeta.style.transform = ''; });
        }
    });

    contar(raiz);
}

function aplicar() {
    iniciarEstelas();
    prepararTarjetas(document);
}

document.addEventListener('DOMContentLoaded', aplicar);
document.addEventListener('livewire:navigated', aplicar);
