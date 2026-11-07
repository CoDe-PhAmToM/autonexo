# AutoNexo

Sistema web para la gestión y control de **pagos, inventarios y transferencias** de comercializadoras de autos en Bolivia.

Proyecto de la materia *Preparación y Evaluación de Proyectos* — Ingeniería de Sistemas, UPEA, gestión 2026.
Desarrollo: Jhon Kevin Laura López.

Construye los **17 PBI del MVP** del Entregable Parcial I, en el orden del **backlog v2** del documento de Kanban.

---

## Stack

| Pieza | Tecnología |
|---|---|
| Framework | Laravel 12 |
| Pantallas | Livewire 3 (Volt) + Flux + Tailwind CSS 4 |
| Base de datos | PostgreSQL |
| Librerías | spatie/laravel-permission (roles), maatwebsite/excel (carga y exportación), barryvdh/laravel-dompdf (PDF), spatie/laravel-backup (respaldo) |
| Pruebas | Pest 4: **454 pruebas** |

## Instalación (Windows)

Requisitos: PHP 8.3 o superior con las extensiones `pdo_pgsql`, `pgsql`, `gd`, `zip`, `bcmath` e `intl`; Composer; Node.js 20; PostgreSQL 16 o superior; Git.

```bash
git clone https://github.com/CoDe-PhAmToM/autonexo.git
cd autonexo
composer install
npm install
copy .env.example .env
php artisan key:generate
```

1. Crea en PostgreSQL dos bases vacías: `autonexo` y `autonexo_test` (la segunda es para las pruebas).
2. En `.env` escribe tu contraseña en `DB_PASSWORD` y revisa que `DB_DUMP_PATH` apunte a la carpeta `bin` de PostgreSQL.
3. Carga los datos de demostración y levanta el sistema:

```bash
php artisan autonexo:demo
```

```bash
composer run dev
```

Entra en <http://localhost:8000>. `composer run dev` también deja corriendo el respaldo automático de cada noche.

### Usuarios de demostración

La contraseña de todos es `password` (solo para la demo local).

| Rol | Correo | Qué ve |
|---|---|---|
| Dueño | `dueno@autonexo.test` | Todo: cartera, anulaciones, confirmar QR, usuarios, respaldos, medición |
| Cajero | `cajero@autonexo.test` | Cobros, cartera, cierre de caja |
| Vendedor | `vendedor@autonexo.test` | Vehículos, reservas, ventas, transferencias |
| Almacenero | `almacen@autonexo.test` | Repuestos (kárdex) |

## Verlo desde un celular

1. El celular y la computadora deben estar en la misma red WiFi.
2. En la computadora ejecuta `composer run celular`. Si Windows pregunta por el firewall para PHP, permite el acceso en **redes privadas**.
3. Busca la IP de la computadora con `ipconfig` (línea «Dirección IPv4» del adaptador Wi-Fi, por ejemplo `192.168.100.5`).
4. En el navegador del celular abre `http://192.168.100.5:8000` (con tu IP).

## Correos reales (opcional)

Por defecto los correos («olvidé mi contraseña», verificación) se guardan en `storage/logs/laravel.log` y no se envían.
Para enviarlos de verdad con Gmail:

1. En tu cuenta de Google activa la verificación en dos pasos y crea una **contraseña de aplicación**.
2. En `.env` cambia:

   ```env
   MAIL_MAILER=smtp
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=587
   MAIL_SCHEME=null
   MAIL_USERNAME=tu_correo@gmail.com
   MAIL_PASSWORD=la_contraseña_de_aplicacion
   MAIL_FROM_ADDRESS=tu_correo@gmail.com
   MAIL_FROM_NAME=AutoNexo
   ```

3. Ejecuta `php artisan config:clear`.

## Comandos útiles

| Comando | Para qué |
|---|---|
| `php artisan autonexo:demo` | Borra la base y carga los datos de demostración (pide confirmación) |
| `php artisan test` | Corre las 454 pruebas sobre la base `autonexo_test` |
| `php artisan test --filter=CalculadoraDePlan` | Informe del spike PBI-01 (300 casos) |
| `php artisan autonexo:probar-restauracion --generar` | Respalda, restaura en una base limpia y compara conteos y saldos (CA-21) |
| `npm run build` | Recompila los estilos si no usas `composer run dev` |
| `composer run celular` | Abre el sistema para verlo desde un celular en la misma red WiFi |

---

## Trazabilidad: PBI → criterio de aceptación → prueba

| PBI | Qué hace en el sistema | Criterio | Prueba |
|---|---|---|---|
| 02 | Registro del negocio y su dueño; usuarios con 4 roles; cada negocio ve solo lo suyo | CA-02, RNF-04 | `UsuariosYRolesTest` |
| 01 | `CalculadoraDePlan`: cuotas, conversión Bs/USD y pagos parciales, en centavos | CA-01 (300 casos, 0 diferencias) | `CalculadoraDePlanTest` |
| 03 | Vehículos por chasis único; corregir la placa conserva el historial | CA-03 | `VehiculosTest` |
| 20 | Registros solo anulables con motivo; bitácora inmutable (también en PostgreSQL) | CA-20 | `BitacoraTest`, `CobrosTest` |
| 11 | Kárdex de repuestos; sin stock negativo; alerta bajo el mínimo | CA-11 | `RepuestosTest` |
| 06 | Venta al contado o a crédito, plan de cuotas; rechaza montos que no cuadran | CA-06.1 a 06.3 | `VentasTest` |
| 04 | Stock por estado y búsqueda por chasis, placa o modelo | CA-04 | `StockYReservasTest` |
| 05 | Reserva con anticipo y fecha límite; vencimiento automático | CA-05 | `StockYReservasTest` |
| 07 | Cobro en Bs o USD, efectivo o QR por confirmar; tipo de cambio del día; anulación | CA-07.1 a 07.4 | `CobrosTest` |
| 13 | Carga inicial desde plantilla de Excel; filas inválidas reportadas | CA-13 | `CargaInicialTest` |
| 10 | Transferencia: 5 pasos, límite del IMT en días hábiles (sin fines de semana ni feriados de Bolivia), alerta y cierre | CA-10.1 a 10.3 | `TransferenciasTest` |
| 08 | Cartera por tramos de antigüedad; QR por confirmar aparte; compradores en mora | CA-08 | `CarteraYCajaTest` |
| 22 | Cierre de caja diario con diferencias registradas y recordatorio | CA-22 | `CarteraYCajaTest` |
| 21 | Respaldo diario y manual, restauración verificada, exportación solo del dueño | CA-21 | `RespaldosTest` y `autonexo:probar-restauracion` |
| 09 | Alertas de cuotas vencidas en el panel | CA-09 | `AlertasComprobantesMedicionTest` |
| 12 | Comprobante y estado de cuenta en PDF, solo con consentimiento; WhatsApp con enlace firmado para descargar el PDF | CA-12 | `AlertasComprobantesMedicionTest` |
| 23 | Medición de uso: cobros del mismo día, consultas y tiempos; sin datos personales | CA-23 | `AlertasComprobantesMedicionTest` |

Los 6 PBI diferidos (14 a 19) no se construyen, como dicen los documentos.

### Evidencia del orden Kanban

Hay un commit por PBI, en el orden del backlog v2:

```bash
git log --oneline --reverse
```

## Reglas de dinero

- Montos en `decimal(14,2)` y tipo de cambio en `decimal(10,4)`; los cálculos se hacen en centavos enteros, nunca con `float`.
- Cada cuota se redondea a 2 decimales y la diferencia va en la última.
- Un cobro nunca se edita ni se borra: se anula con motivo y queda en la bitácora.
- Un pago por QR «por confirmar» no cuenta en la cartera hasta que el dueño lo confirma.
- Zona horaria `America/La_Paz`.

---

## Guion de la demo (5 minutos)

Antes: `php artisan autonexo:demo` y `composer run dev`.

1. **Dueño** (`dueno@`): el panel muestra el total por cobrar, las cuotas vencidas y la alerta del IMT de una transferencia (PBI-08, 09 y 10).
2. **Vehículos**: buscar «hilux» o una placa, filtrar por estado, abrir un vehículo y ver su historial (PBI-03 y 04). Opcional: **Carga inicial**, descargar la plantilla (PBI-13).
3. **Vender** un vehículo en exhibición: a crédito, inicial y número de cuotas, botón **Calcular**, vista previa del plan, **Registrar venta** (PBI-06). Escribir una cuota que no cuadre para mostrar el error con la diferencia (CA-06.2).
4. **Cajero** (`cajero@`): **Cobros**, buscar al comprador, **Cobrar** en bolivianos; la vista previa muestra la conversión y las cuotas que se pagan (PBI-07). Cambiar el tipo de cambio para mostrar que pide motivo.
5. **Dueño**: en la venta, **Anular** un cobro con motivo; el saldo se recalcula y queda en la bitácora (PBI-20). Registrar el consentimiento y abrir el **comprobante PDF** (PBI-12).
6. **Cartera** por tramos y compradores en mora (PBI-08); **Cierre de caja** con una diferencia y su observación (PBI-22).
7. **Transferencias**: marcar el pago del IMT y los demás pasos hasta que el vehículo quede «Entregado» (PBI-10).
8. **Medición de uso** (PBI-23), **Respaldos** (PBI-21) y el tablero Kanban en GitHub Projects.

## Créditos de las fotos

Fotos del showroom del login, de uso gratuito bajo la [Licencia Unsplash](https://unsplash.com/license):

- Auto entre líneas de luz y auto azul con luces horizontales: [Hyundai Motor Group](https://unsplash.com/@hyundaimotorgroup).
- SUV azul bajo un foco de estudio: [ObjectType RAW](https://unsplash.com/@objecttyperaw).
