<?php

namespace App\Support;

/**
 * Roles y permisos de AutoNexo (PBI-02, RN-05).
 * Si se agrega un permiso en una etapa posterior, se crea con una migración nueva.
 */
final class Roles
{
    public const DUENO = 'dueño';
    public const CAJERO = 'cajero';
    public const VENDEDOR = 'vendedor';
    public const ALMACENERO = 'almacenero';

    /** Nombre visible de cada rol. */
    public const NOMBRES = [
        self::DUENO => 'Dueño',
        self::CAJERO => 'Cajero',
        self::VENDEDOR => 'Vendedor',
        self::ALMACENERO => 'Almacenero',
    ];

    public const PERMISOS = [
        'usuarios.gestionar' => 'Crear usuarios y asignar roles',
        'vehiculos.ver' => 'Ver vehículos',
        'vehiculos.gestionar' => 'Registrar y corregir vehículos',
        'ventas.registrar' => 'Registrar reservas y ventas',
        'cobros.registrar' => 'Registrar cobros',
        'cobros.anular' => 'Anular cobros con motivo',
        'cobros.confirmar' => 'Confirmar los pagos por QR',
        'datos.importar' => 'Cargar datos desde la plantilla de Excel',
        'cartera.ver' => 'Ver la cartera y la mora',
        'transferencias.gestionar' => 'Seguir las transferencias',
        'repuestos.gestionar' => 'Manejar el kárdex de repuestos',
        'caja.cerrar' => 'Cerrar la caja del día',
        'respaldos.gestionar' => 'Generar respaldos',
        'metricas.ver' => 'Ver la medición de uso',
    ];

    /** Qué puede hacer cada rol. */
    public const MATRIZ = [
        self::DUENO => [
            'usuarios.gestionar', 'vehiculos.ver', 'vehiculos.gestionar', 'ventas.registrar',
            'cobros.registrar', 'cobros.anular', 'cobros.confirmar', 'datos.importar',
            'cartera.ver', 'transferencias.gestionar',
            'repuestos.gestionar', 'caja.cerrar', 'respaldos.gestionar', 'metricas.ver',
        ],
        self::CAJERO => ['vehiculos.ver', 'cobros.registrar', 'cartera.ver', 'caja.cerrar'],
        self::VENDEDOR => ['vehiculos.ver', 'vehiculos.gestionar', 'ventas.registrar', 'transferencias.gestionar'],
        self::ALMACENERO => ['vehiculos.ver', 'repuestos.gestionar'],
    ];
}
