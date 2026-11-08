# Guía de AutoNexo

Esta guía sirve para entender cómo funciona el sistema y para presentarlo.
Tiene 9 partes:

1. Qué es AutoNexo, en 30 segundos
2. Antes de presentar
3. Los datos de demostración
4. Recorrido por cada pantalla
5. Cómo funciona por dentro
6. Guion de la presentación (10 minutos)
7. Preguntas probables del docente
8. Si algo falla
9. Glosario

La instalación y los comandos están en el [README](README.md). El respaldo está explicado en [RESPALDO.md](RESPALDO.md).

---

## 1. Qué es AutoNexo, en 30 segundos

> AutoNexo es un sistema web para las comercializadoras de autos de Bolivia, como las de El Alto. Reúne en un solo lugar tres cosas que hoy se llevan en cuadernos y en Excel:
>
> 1. **Pagos.** Ventas a crédito con su plan de cuotas, cobros en bolivianos o en dólares, y la cartera de quién debe y cuánto.
> 2. **Inventario.** Los vehículos por chasis y su estado, y los repuestos con su kárdex.
> 3. **Transferencias.** El trámite de cada auto vendido, paso a paso, con alerta antes de que venza el plazo del IMT.
>
> Cada persona entra con su rol (dueño, cajero, vendedor o almacenero) y ve solo lo que le toca. Ningún cobro se borra: si hay un error, se anula con motivo y queda registrado.

**El problema que resuelve:**
- El dueño no sabe al momento cuánto le deben.
- Los cobros se anotan tarde o se pierden.
- Se pagan multas por pasarse del plazo del IMT.

**Lo que se construyó:**
- Los 17 PBI del MVP, en el orden del backlog v2 del documento de Kanban.
- Un commit por PBI.
- 461 pruebas automáticas, todas en verde.

---

## 2. Antes de presentar

Hazlo el día anterior y repítelo 10 minutos antes.

| ✓ | Paso | Comando o acción |
|---|---|---|
| ☐ | Abrir PostgreSQL | Debe estar corriendo; normalmente arranca solo con Windows |
| ☐ | Dejar los datos limpios | `php artisan autonexo:demo` y responder `yes` |
| ☐ | Levantar el sistema | `composer run dev`. Si también lo mostrarás en el celular, usa `composer run celular` |
| ☐ | Abrir el navegador | <http://localhost:8000> |
| ☐ | Probar el ingreso | Correo `dueno@autonexo.test` y contraseña `password` |
| ☐ | Tener las pruebas listas | Una segunda terminal con `php artisan test`, lista para ejecutar |
| ☐ | Tener GitHub abierto | El repositorio, la pestaña **Commits** y el tablero de **Projects** |
| ☐ | Navegador con zoom al 100 % o 110 % | Así se leen bien las tarjetas en el proyector |

> **Importante:** `autonexo:demo` borra todo y vuelve a cargar la demo. Úsalo antes de presentar, nunca durante la presentación.

**Por qué se reinicia la demo antes de presentar:** las fechas de la demo se calculan desde el día en que la cargas. Las cuotas vencidas, la reserva de 5 días y la alerta del IMT quedan bien solo si la cargas ese mismo día o el anterior.

---

## 3. Los datos de demostración

Los datos son simulados, como permitió el docente. Todo pertenece a un negocio llamado **Automotores Illimani (demo)**, de El Alto.

### Usuarios

La contraseña de todos es `password`.

| Rol | Nombre | Correo | Qué puede hacer |
|---|---|---|---|
| Dueño | Fernando Quispe | `dueno@autonexo.test` | Todo: anular cobros, confirmar QR, usuarios, respaldos, medición, carga inicial |
| Cajero | Rosa Mamani | `cajero@autonexo.test` | Cobrar, ver la cartera y cerrar la caja |
| Vendedor | Luis Condori | `vendedor@autonexo.test` | Registrar vehículos, reservar, vender y seguir transferencias |
| Almacenero | Marco Choque | `almacen@autonexo.test` | El kárdex de repuestos; ve los vehículos sin poder cambiarlos |

### Stock de 12 vehículos

| Vehículo | Estado en la demo | Detalle |
|---|---|---|
| Nissan Frontier 2016 | **Vendido** | Vendido hace 5 meses a crédito a **Juan Carlos Mamani**; tiene cuotas vencidas, cobros y la **transferencia en curso** con alerta del IMT |
| Kia Rio 2017 | **Vendido** | Vendido hace 2 meses a crédito a **María Elena Quispe** |
| Toyota Corolla 2020 | **Reservado** | Reserva de **Pedro Limachi**: anticipo de 500 USD que vence en 5 días |
| Las otras 9 | En exhibición | Amarok, Tucson, Swift, Sportage, Hilux, L200, RAV4, Grand Vitara y Accent |

- Las dos ventas son a 12 cuotas, con una inicial del 30 %.
- El Frontier y el Rio no tienen foto, así que el sistema muestra una silueta. Eso deja ver cómo queda un auto que se registra sin foto.

### Otros datos

- **Tipo de cambio de hoy:** 12,26 Bs por dólar (oficial).
- **Cobros:** 3 cobros hechos por la cajera. Dos son de Juan Carlos, uno en dólares y otro de 3000 Bs, y uno es de María Elena.
- **Consentimiento:** Juan Carlos autorizó recibir comprobantes, así que su venta permite sacar el PDF y el WhatsApp. María Elena todavía no autorizó; sirve para mostrar que sin consentimiento no se genera el PDF.
- **Repuestos:** 5 repuestos. Dos están **bajo el mínimo** y muestran alerta:
  - Pastillas de freno: hay 6 y el mínimo es 8.
  - Batería: hay 1 y el mínimo es 2.
- **Medición de uso:** 3 semanas anteriores con métricas simuladas. Los cobros del mismo día suben de 71 % a 84 %, como dice el Entregable en el capítulo 6.

---

## 4. Recorrido por cada pantalla

El menú de la izquierda cambia según el rol. Aquí van en el orden del menú.

### Panel (inicio)

**Qué es:** el resumen del día.

**Qué muestra:**
- Total por cobrar, en USD y en Bs.
- Cuotas vencidas, con los compradores más atrasados.
- La alerta del IMT de la transferencia que está por vencer.
- El recordatorio de cerrar la caja, si hubo cobros hoy.

**PBI:** 08, 09, 10 y 22.

**Qué decir:** «El dueño abre el sistema y en un vistazo sabe cuánto le deben y qué está por vencer.»

### Vehículos

**Qué es:** el inventario de autos. Se ve como **vitrina** (tarjetas con foto) o como **tabla**.

**Qué hace:**
- Busca por chasis, placa, marca o modelo, y filtra por estado: en exhibición, reservado, vendido o entregado.
- Al abrir un vehículo se ve su galería de fotos (hasta 8), sus datos y su **historial** de todo lo que le pasó.

**Reglas:**
- El chasis es único dentro del negocio. Se detecta aunque se escriba con minúsculas o con guiones.
- Si se corrige la placa, el vehículo es el mismo y el historial guarda el valor de antes y el de después.
- Una reserva vencida se libera sola al abrir la lista.

**PBI:** 03, 04 y 05.

### Reservar y vender (desde un vehículo)

**Reservar:** se registran el comprador, el anticipo y la fecha límite. El auto pasa a «Reservado».

**Vender:**
- Al contado o a crédito.
- A crédito se ponen la inicial, el número de cuotas y la fecha del primer vencimiento.
- El botón **Calcular** muestra el plan de cuotas antes de guardar.

**Reglas:**
- Si la cuota escrita no cuadra con el precio, el sistema rechaza la venta y dice la diferencia exacta.
- Cada cuota se redondea a 2 decimales y la diferencia va en la última, así el total cuadra al centavo.

**PBI:** 05 y 06.

### Ventas

**Qué es:** la lista de ventas. Al abrir una se ven el plan de cuotas, los cobros y el saldo.

**Qué se hace en una venta:**
- Registrar el **consentimiento** del comprador para enviarle comprobantes.
- Descargar el **comprobante PDF** de cada cobro y el **estado de cuenta**.
- Enviar el comprobante por **WhatsApp**. El mensaje lleva un enlace firmado: el comprador descarga su PDF sin cuenta, y el enlace vence en 7 días.
- **Anular** un cobro (solo el dueño). Pide un motivo de al menos 5 letras, recalcula el saldo y lo deja en la bitácora.

**PBI:** 06, 12 y 20.

### Cobros

**Qué es:** donde trabaja el cajero. Busca al comprador y cobra.

**Qué hace:**
- Se cobra en **bolivianos o dólares**, en **efectivo o QR**.
- Si se paga en otra moneda que la de la venta, convierte con el **tipo de cambio del día**.
- La vista previa muestra qué cuotas se pagan con ese monto. Un pago parcial queda a cuenta de la siguiente cuota.

**Reglas:**
- Si el cajero cambia el tipo de cambio oficial, el sistema le pide un motivo.
- Un pago por **QR** queda «por confirmar» hasta que el dueño lo confirma. Mientras tanto no baja la deuda en la cartera.

**PBI:** 07.

### Cartera

**Qué es:** quién debe y cuánto.

**Qué muestra:**
- La deuda separada por **tramos de antigüedad**: por vencer, 1 a 30 días, 31 a 60 días y más de 60 días.
- Los pagos QR por confirmar, aparte.
- La lista de **compradores en mora**.

**PBI:** 08.

### Cierre de caja

**Qué es:** al final del día, el cajero escribe lo que contó en efectivo y en QR, en cada moneda.

**Qué hace:**
- Compara lo contado con lo que el sistema registró.
- Si hay diferencia, pide una **observación** y la guarda.
- Solo se cierra una vez por día.

**PBI:** 22. Es el pivote de la hipótesis HP-03: el recordatorio subió los cobros del mismo día.

### Transferencias

**Qué es:** el trámite de cada auto vendido, en 5 pasos:
1. Minuta firmada
2. Pago del IMT
3. Protocolización notarial
4. Registro en Derechos Reales
5. Trámite en Tránsito

**Qué hace:**
- Calcula la **fecha límite del IMT**: 10 días hábiles desde la minuta, sin contar fines de semana ni feriados de Bolivia.
- Muestra la **alerta** cuando faltan 2 días hábiles o menos.
- Al cumplir el último paso, el vehículo queda «Entregado».

**PBI:** 10.

### Repuestos

**Qué es:** el kárdex. Registra entradas y salidas, y cada movimiento guarda el saldo.

**Reglas:**
- No permite una salida mayor al stock: el stock nunca queda negativo.
- Un repuesto bajo el mínimo muestra alerta.
- Cada repuesto puede tener su foto.

**PBI:** 11.

### Administración (solo el dueño)

| Pantalla | Qué hace | PBI |
|---|---|---|
| **Usuarios** | Crear usuarios y asignarles uno de los 4 roles | 02 |
| **Medición de uso** | Cobros del mismo día, consultas de cartera y tiempos, sin datos personales | 23 |
| **Respaldos** | Respaldo manual y el automático de cada noche; exportar todos los registros a Excel | 21 |
| **Carga inicial** | Descargar la plantilla de Excel, llenarla y subirla; las filas con error se reportan sin cargar nada malo | 13 |

---

## 5. Cómo funciona por dentro

### El camino de un clic

```text
Navegador  →  Laravel (PHP)  →  PostgreSQL
 (pantalla)    (reglas)          (datos)
```

1. **La pantalla** está hecha con Livewire y Tailwind. Cuando tocas un botón, Livewire avisa al servidor sin recargar toda la página.
2. **Laravel** revisa que tengas permiso para esa acción y aplica las reglas del negocio. Las reglas viven en los «servicios», en la carpeta `app/Servicios`:
   - `CalculadoraDePlan`: las cuotas y las conversiones de moneda.
   - `Ventas`: reservar y vender.
   - `Cobros`: cobrar, convertir y anular.
   - `Cartera`: la deuda por tramos.
   - `CierresDeCaja`: el cierre diario.
   - `Transferencias`: los 5 pasos y el plazo del IMT.
   - `CargaInicial`, `Respaldos`, `Comprobantes` y `Metricas`.
3. **PostgreSQL** guarda los datos. Si algo falla a mitad de una operación, se deshace todo (es una transacción), así nunca queda una venta sin su plan de cuotas.

### Las 5 decisiones importantes y por qué

| Decisión | Por qué |
|---|---|
| **El dinero se calcula en centavos enteros**, nunca con decimales sueltos (`float`) | Con decimales sueltos, 0,1 + 0,2 da 0,30000000000000004. Con centavos enteros, el plan cuadra siempre. Se probó con 300 casos sin una sola diferencia (spike PBI-01) |
| **Cada negocio ve solo lo suyo** | Todas las tablas tienen `negocio_id`, y el sistema filtra solo por el negocio del usuario. Un usuario de otro negocio recibe «no encontrado» aunque adivine la dirección |
| **Los cobros no se editan ni se borran, solo se anulan con motivo** | Así nadie puede esconder un cobro. Además, la **bitácora** está protegida dentro de PostgreSQL con un *trigger*: ni siquiera con acceso directo a la base se puede cambiar |
| **Roles y permisos** (spatie/laravel-permission) | Cada botón y cada ruta pide un permiso. El cajero no ve «Anular» y, si escribe la dirección a mano, recibe «prohibido» |
| **Días hábiles con feriados de Bolivia** | El plazo del IMT se cuenta en días hábiles. La clase `Feriados` conoce los feriados fijos y los móviles, como Carnaval, Viernes Santo y Corpus Christi |

### Pruebas automáticas

- Hay **461 pruebas** hechas con Pest. Corren sobre una base aparte, `autonexo_test`, así nunca tocan los datos de la demo.
- Cada criterio de aceptación (CA) de los documentos tiene su prueba. La tabla de trazabilidad del README dice cuál es cuál.
- Se ejecutan con `php artisan test`, y en GitHub también corren solas en cada commit (GitHub Actions).

### Respaldo

- Hay un respaldo **automático cada noche** mientras `composer run dev` está corriendo, y uno **manual** desde la pantalla Respaldos.
- La restauración se probó de verdad con `php artisan autonexo:probar-restauracion --generar`: respalda, restaura en una base limpia y compara conteos y saldos.

### Tecnologías

| Pieza | Herramienta | Para qué |
|---|---|---|
| Lenguaje y marco | PHP 8 con Laravel 12 | El servidor y las reglas |
| Pantallas | Livewire 3 (Volt), Flux y Tailwind CSS 4 | Pantallas que responden sin recargar |
| Base de datos | PostgreSQL | Los datos, las transacciones y el *trigger* de la bitácora |
| PDF | barryvdh/laravel-dompdf | Comprobantes y estado de cuenta |
| Excel | maatwebsite/excel | Plantilla de carga inicial y exportación |
| Respaldo | spatie/laravel-backup | Respaldo diario y manual |
| Pruebas | Pest 4 | Las 461 pruebas |
| Código | Git y GitHub | Un commit por PBI y pruebas automáticas |

---

## 6. Guion de la presentación (10 minutos)

Usa dos pestañas del navegador, o una normal y otra de incógnito: una con el **dueño** y otra con el **cajero**.

| Min. | Qué haces | Qué dices |
|---|---|---|
| 0:00 | Muestras el login | «AutoNexo es un sistema para comercializadoras de autos. Junta pagos, inventario y transferencias. Cada usuario entra con su rol.» |
| 0:30 | Entras como **dueño** y quedas en el Panel | «El dueño ve al instante cuánto le deben, las cuotas vencidas y esta alerta: a la transferencia del Frontier le queda poco para el plazo del IMT.» |
| 1:30 | **Vehículos**: vitrina, buscas «hilux», filtras «Vendido», abres el Frontier | «El inventario es por chasis, que no se puede repetir. Cada auto tiene su historial: quién lo registró, quién lo vendió y qué se corrigió.» |
| 2:30 | Abres un auto en exhibición y le das **Vender**: a crédito, inicial, 12 cuotas y **Calcular** | «El plan se calcula en centavos, así que siempre cuadra. Si escribo una cuota que no cuadra…» (cambias la cuota) «…el sistema me dice la diferencia exacta y no me deja guardar.» Luego registras la venta |
| 4:00 | Pestaña del **cajero**: Cobros, buscas al comprador nuevo y cobras en **bolivianos** | «El cajero cobra en Bs aunque la venta sea en dólares. Usa el tipo de cambio de hoy y me muestra qué cuotas se pagan.» Cambias el tipo de cambio: «Si lo cambio, me pide el motivo.» |
| 5:30 | El cajero intenta entrar a Usuarios o a Respaldos | «El cajero no ve lo que no le corresponde. Aunque escriba la dirección, el sistema se lo prohíbe.» |
| 6:00 | **Dueño**: abres la venta de Juan Carlos, **Anulas** un cobro con motivo y abres el **PDF** | «Un cobro nunca se borra: se anula con motivo, el saldo se recalcula y queda en la bitácora, que ni siquiera desde la base de datos se puede cambiar. Como el comprador dio su consentimiento, puedo sacar su comprobante en PDF o mandárselo por WhatsApp.» |
| 7:00 | **Cartera** por tramos y **Cierre de caja** | «Aquí está la deuda por antigüedad y quién está en mora. Al final del día el cajero cierra la caja; si falta plata, tiene que explicar por qué.» |
| 8:00 | **Transferencias**: marcas el pago del IMT | «Los 5 pasos del trámite. El plazo del IMT se cuenta en días hábiles, sin fines de semana ni feriados de Bolivia. Al terminar los pasos, el auto queda entregado.» |
| 9:00 | **Medición de uso** y **Respaldos** | «Medimos si el sistema ayuda: los cobros del mismo día subieron de 71 % a 84 % con el recordatorio de cierre. Y hay respaldo automático cada noche, con la restauración probada.» |
| 9:30 | Terminal: `php artisan test`, y GitHub: commits y tablero | «Son 461 pruebas automáticas, una por cada criterio de aceptación, y hay un commit por cada PBI en el orden del Kanban.» |

**Si tienes menos tiempo:** usa el guion corto de 5 minutos que está al final del [README](README.md).

**Extra si sobra tiempo:**
- Mostrar el sistema en el **celular** (`composer run celular`).
- Hacer una **Carga inicial** con la plantilla de Excel.
- Registrar un vehículo nuevo con fotos.

---

## 7. Preguntas probables del docente

**¿Por qué Laravel y PostgreSQL?**
Laravel trae resueltos el login, los permisos, las colas y las pruebas, así que el tiempo se usó en las reglas del negocio. PostgreSQL maneja bien las transacciones y permite proteger la bitácora con un *trigger* dentro de la misma base.

**¿Qué pasa si el cajero se equivoca en un cobro?**
No lo puede borrar ni editar. El dueño lo anula con un motivo, el saldo se recalcula y la anulación queda en la bitácora con quién la hizo y cuándo.

**¿Cómo evitan errores de redondeo con el dinero?**
Todo se calcula en centavos enteros. Cada cuota se redondea a 2 decimales y la diferencia va en la última. El spike del PBI-01 lo comprobó con 300 casos sin ninguna diferencia.

**¿Qué tipo de cambio usa?**
El del día, que registra el negocio. La venta puede usar el tipo oficial o uno **pactado** con el comprador. Si el cajero lo cambia, debe dar un motivo, y queda guardado.

**¿Qué pasa con un pago por QR?**
Queda «por confirmar» hasta que el dueño ve el dinero en su cuenta y lo confirma. Mientras tanto no baja la deuda en la cartera, pero sí cuenta en el cierre de caja del cajero, porque él lo recibió.

**¿Un negocio puede ver los datos de otro?**
No. Cada registro pertenece a un negocio y el sistema filtra siempre por el negocio del usuario. Hay pruebas que lo comprueban: un usuario de otro negocio recibe «no encontrado».

**¿Cómo cuenta el plazo del IMT?**
Son 10 días hábiles desde la minuta, sin fines de semana ni feriados de Bolivia, incluidos los móviles como Carnaval. La alerta aparece cuando faltan 2 días hábiles o menos.

**¿Y si se daña la computadora?**
Hay un respaldo automático cada noche y uno manual. La restauración se probó: respalda, restaura en una base limpia y compara que los conteos y los saldos sean iguales. El dueño también puede exportar todo a Excel.

**¿Cómo saben que funciona?**
Hay 461 pruebas automáticas, y cada criterio de aceptación tiene la suya. Corren en la computadora y en GitHub en cada commit.

**¿Los datos son reales?**
No, son simulados, como se permitió. Las fotos son libres, de Unsplash, y cada una corresponde a su modelo. Los créditos están en el README.

**¿Qué falta?**
- Los 6 PBI diferidos (14 a 19), que según los documentos no entran en el MVP.
- El envío real de correos, que se activa con una contraseña de aplicación de Gmail.
- Publicarlo en internet, que necesita un hosting.

Nada de eso hace falta para usarlo en el negocio.

**¿Funciona en el celular?**
Sí. Las pantallas se adaptan, y en la misma red WiFi se entra con la IP de la computadora.

---

## 8. Si algo falla

| Síntoma | Solución |
|---|---|
| La página no abre | Revisa que la terminal de `composer run dev` siga abierta y sin errores |
| Error de conexión a la base | Abre PostgreSQL (en Servicios de Windows, `postgresql-x64-18`) y recarga |
| Se ve sin estilos | Ejecuta `npm run build` o vuelve a lanzar `composer run dev` |
| No aparece la alerta del IMT ni las cuotas vencidas | La demo se cargó hace días: ejecuta `php artisan autonexo:demo` |
| Las fotos no cambian después de reiniciar la demo | Recarga con `Ctrl + F5` |
| El celular no entra | La misma red WiFi, permitir PHP en el firewall en redes privadas, y la IP correcta (`ipconfig`) |
| «Registra primero el tipo de cambio del día» | Registra el tipo de cambio en la pantalla de Cobros. Pasa si la demo se cargó otro día |
| Olvidaste la contraseña de un usuario demo | Todas son `password` |

---

## 9. Glosario

| Palabra | Qué significa |
|---|---|
| **PBI** | *Product Backlog Item*: cada funcionalidad del backlog. El MVP tiene 17 |
| **CA** | Criterio de aceptación: cómo se comprueba que un PBI está terminado |
| **MVP** | Producto mínimo viable: lo justo para que el negocio lo use |
| **Kanban** | El método con que se ordenó el trabajo: una tarjeta por PBI, de «por hacer» a «hecho» |
| **Spike** | Una prueba técnica corta para quitar un riesgo; aquí, el cálculo de cuotas del PBI-01 |
| **Cartera** | El total de lo que los compradores deben |
| **Mora** | Cuando un comprador tiene cuotas vencidas sin pagar |
| **Tramo** | Grupo de deuda según cuántos días lleva vencida |
| **Kárdex** | El registro de entradas y salidas de un repuesto, con el saldo después de cada movimiento |
| **Minuta** | El documento de compraventa que firman vendedor y comprador |
| **IMT** | Impuesto Municipal a las Transferencias; tiene plazo y multa si se paga tarde |
| **Derechos Reales** | La oficina donde se registra el cambio de dueño |
| **TC** | Tipo de cambio entre bolivianos y dólares |
| **Bitácora** | El registro de todo lo que pasa en el sistema: quién, qué y cuándo. No se puede cambiar |
| **Anular** | Dejar sin efecto un registro sin borrarlo, con un motivo |
| ***Trigger*** | Una regla dentro de PostgreSQL que se ejecuta sola; aquí impide cambiar la bitácora |
| **Livewire** | Hace que las pantallas respondan sin recargar la página |
| **Migración** | Un archivo que crea o cambia las tablas de la base, siempre igual en cualquier computadora |
| **Seeder** | El archivo que carga los datos de demostración |
