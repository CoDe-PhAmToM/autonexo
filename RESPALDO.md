# Respaldo y restauración de AutoNexo (PBI-21)

## Qué se respalda

- **La base de datos PostgreSQL completa** (volcado con `pg_dump`).
- **Los archivos subidos**: fotos de vehículos y capturas de los pagos por QR (`storage/app/private`).
- No se respalda el código (está en GitHub) ni el archivo `.env`, que contiene la contraseña de la base.

Los respaldos quedan en `storage/app/private/AutoNexo/AAAA-MM-DD-HH-MM-SS.zip`.

## Cómo se genera

- **A mano:** menú **Respaldos → Generar respaldo ahora** (solo el dueño), o `php artisan backup:run`.
- **Automático:** cada noche a las 23:30, si el programador de Laravel está corriendo (`php artisan schedule:work`).
- Cada intento queda en la pantalla Respaldos como **Exitoso** o **Fallido**, con el motivo del fallo.

Requisito: en `.env`, `DB_DUMP_PATH` apunta a la carpeta `bin` de PostgreSQL
(por ejemplo `C:/Program Files/PostgreSQL/18/bin`).

## Cómo se restaura

1. Descomprimir el zip. Dentro está `db-dumps/postgresql-autonexo.sql`.
2. Crear una base vacía, por ejemplo `autonexo_restaurada`, con pgAdmin o con:

   ```bash
   createdb -U postgres autonexo_restaurada
   ```

3. Cargar el volcado:

   ```bash
   psql -U postgres -d autonexo_restaurada -v ON_ERROR_STOP=1 -f db-dumps/postgresql-autonexo.sql
   ```

4. Copiar la carpeta `storage/app/private` del zip a la misma ruta del proyecto (fotos y comprobantes).
5. En `.env`, cambiar `DB_DATABASE=autonexo_restaurada` y ejecutar `php artisan config:clear`.

## Cómo se comprueba (CA-21)

```bash
php artisan autonexo:probar-restauracion --generar
```

El comando genera un respaldo, lo restaura en una base limpia (`autonexo_restauracion`), compara con la base actual
los conteos de todas las tablas, el saldo de la cartera, los cobros confirmados y el stock de repuestos, y borra la base
de prueba. Termina con «Restauración verificada» si todo coincide.

Prueba realizada el 6-nov-2026 con los datos de demostración: 17 de 17 datos coinciden
(entre ellos 24 cuotas, saldo de cartera USD 21.932,73 y cobros confirmados por USD 5.262,27).

## Exportación de registros

Menú **Respaldos → Descargar Excel**: vehículos, ventas, cuotas, cobros y repuestos del negocio.
Solo la puede descargar el dueño (permiso `respaldos.gestionar`).
