<?php

namespace App\Servicios;

use App\Models\Respaldo;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * PBI-21 (CA-21): respaldo, restauración verificada y resumen para comparar.
 *
 * - generar(): ejecuta spatie/laravel-backup (base de datos + fotos y comprobantes).
 *   Los eventos de spatie dejan cada resultado en la tabla respaldos (ver AppServiceProvider).
 * - probarRestauracion(): restaura el último respaldo en una base limpia y compara
 *   conteos y saldos con la base actual.
 */
final class Respaldos
{
    public const BASE_DE_PRUEBA = 'autonexo_restauracion';

    public function generar(): Respaldo
    {
        $antes = Respaldo::max('id') ?? 0;

        try {
            Artisan::call('backup:run', ['--disable-notifications' => true]);
        } catch (\Throwable $e) {
            Respaldo::create(['user_id' => auth()->id(), 'estado' => 'fallido', 'mensaje' => $e->getMessage()]);
        }

        // Si spatie no disparó ningún evento, se registra como fallido con su salida.
        return Respaldo::where('id', '>', $antes)->latest('id')->first()
            ?? Respaldo::create(['user_id' => auth()->id(), 'estado' => 'fallido', 'mensaje' => trim(Artisan::output()) ?: 'El respaldo no terminó.']);
    }

    /** Ruta absoluta del respaldo más reciente (zip), o null. */
    public function ultimoArchivo(): ?string
    {
        $disco = Storage::disk(config('backup.backup.destination.disks')[0]);
        $archivos = collect($disco->allFiles(config('backup.backup.name')))
            ->filter(fn ($f) => str_ends_with($f, '.zip'))
            ->sortByDesc(fn ($f) => $disco->lastModified($f));

        return $archivos->isEmpty() ? null : $disco->path($archivos->first());
    }

    /**
     * Conteos y saldos que deben coincidir entre la base original y la restaurada.
     *
     * @return array<string, string>
     */
    public function resumen(?string $conexion = null): array
    {
        $db = DB::connection($conexion);
        $resumen = [];

        foreach (['negocios', 'users', 'vehiculos', 'compradores', 'reservas', 'ventas', 'cuotas', 'cobros', 'cobro_cuota', 'transferencias', 'repuestos', 'kardex', 'cierres_caja', 'bitacora'] as $tabla) {
            $resumen["Filas en {$tabla}"] = (string) $db->table($tabla)->count();
        }

        $resumen['Saldo de cartera (cuotas)'] = (string) ($db->table('cuotas')->selectRaw('COALESCE(SUM(monto - pagado), 0) AS s')->value('s'));
        $resumen['Cobros confirmados vigentes'] = (string) ($db->table('cobros')->where('estado', 'confirmado')->whereNull('anulado_at')->sum('monto_aplicado'));
        $resumen['Stock total de repuestos'] = (string) $db->table('repuestos')->sum('stock');

        return $resumen;
    }

    /**
     * CA-21: restaura el respaldo más reciente en una base limpia y compara.
     *
     * @return array{archivo:string, original: array<string,string>, restaurada: array<string,string>, coincide:bool}
     */
    public function probarRestauracion(bool $conservar = false): array
    {
        $archivo = $this->ultimoArchivo() ?? throw new RuntimeException('No hay respaldos: genera uno primero.');
        $sql = $this->extraerVolcado($archivo);
        $cfg = config('database.connections.pgsql');

        try {
            DB::statement('DROP DATABASE IF EXISTS '.self::BASE_DE_PRUEBA);
            DB::statement('CREATE DATABASE '.self::BASE_DE_PRUEBA);

            $psql = rtrim((string) ($cfg['dump']['dump_binary_path'] ?? ''), '/\\');
            $proceso = new Process(
                [($psql !== '' ? $psql.'/' : '').'psql', '-h', $cfg['host'], '-p', (string) $cfg['port'], '-U', $cfg['username'], '-d', self::BASE_DE_PRUEBA, '-v', 'ON_ERROR_STOP=1', '-q', '-f', $sql],
                null,
                ['PGPASSWORD' => (string) $cfg['password']], // la contraseña va por variable de entorno, no en la línea de comandos
                null,
                300,
            );
            $proceso->run();
            if (! $proceso->isSuccessful()) {
                throw new RuntimeException('psql no pudo restaurar el respaldo: '.trim($proceso->getErrorOutput()));
            }

            config(['database.connections.restauracion' => array_merge($cfg, ['database' => self::BASE_DE_PRUEBA])]);
            DB::purge('restauracion');

            $original = $this->resumen();
            $restaurada = $this->resumen('restauracion');
        } finally {
            DB::purge('restauracion');
            @unlink($sql);
            if (! $conservar) {
                DB::statement('DROP DATABASE IF EXISTS '.self::BASE_DE_PRUEBA.' WITH (FORCE)');
            }
        }

        return ['archivo' => basename($archivo), 'original' => $original, 'restaurada' => $restaurada, 'coincide' => $original === $restaurada];
    }

    private function extraerVolcado(string $zip): string
    {
        $za = new ZipArchive;
        if ($za->open($zip) !== true) {
            throw new RuntimeException("No se pudo abrir {$zip}.");
        }

        for ($i = 0; $i < $za->numFiles; $i++) {
            $nombre = str_replace('\\', '/', $za->getNameIndex($i)); // en Windows el zip usa "\"
            if (str_starts_with($nombre, 'db-dumps/') && str_ends_with($nombre, '.sql')) {
                $destino = storage_path('app/restauracion-'.uniqid().'.sql');
                file_put_contents($destino, $za->getFromIndex($i));
                $za->close();

                return $destino;
            }
        }

        $za->close();
        throw new RuntimeException('El respaldo no tiene volcado de la base de datos.');
    }
}
