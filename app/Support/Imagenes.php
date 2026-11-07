<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Guarda una foto subida reducida a un tamaño razonable (máximo 1600 px de lado) y en JPEG,
 * para que la vitrina cargue rápido también en el celular. Usa la extensión GD de PHP.
 */
final class Imagenes
{
    public static function guardar(UploadedFile $archivo, string $carpeta, int $lado = 1600): string
    {
        // Una foto de celular (12 MP) ocupa unos 48 MB sin comprimir: se da margen solo aquí.
        $limite = ini_get('memory_limit');
        if ($limite !== '-1' && self::bytes($limite) < 512 * 1024 * 1024) {
            ini_set('memory_limit', '512M');
        }

        $origen = @imagecreatefromstring((string) file_get_contents($archivo->getRealPath()));
        if ($origen === false) {
            return $archivo->store($carpeta, 'local');
        }

        $ancho = imagesx($origen);
        $alto = imagesy($origen);
        $escala = min(1, $lado / max($ancho, $alto));
        $nuevoAncho = max(1, (int) round($ancho * $escala));
        $nuevoAlto = max(1, (int) round($alto * $escala));

        $destino = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
        imagefill($destino, 0, 0, imagecolorallocate($destino, 255, 255, 255)); // fondo para PNG con transparencia
        imagecopyresampled($destino, $origen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);

        ob_start();
        imagejpeg($destino, null, 82);
        $binario = (string) ob_get_clean();

        imagedestroy($origen);
        imagedestroy($destino);

        $ruta = trim($carpeta, '/').'/'.Str::uuid().'.jpg';
        Storage::disk('local')->put($ruta, $binario);

        return $ruta;
    }

    private static function bytes(string $valor): int
    {
        $numero = (int) $valor;

        return match (strtolower(substr(trim($valor), -1))) {
            'g' => $numero * 1024 ** 3,
            'm' => $numero * 1024 ** 2,
            'k' => $numero * 1024,
            default => $numero,
        };
    }

    /** Respuesta con la imagen y caché de una hora en el navegador. */
    public static function responder(?string $ruta)
    {
        abort_unless($ruta && Storage::disk('local')->exists($ruta), 404);

        return Storage::disk('local')->response($ruta, null, ['Cache-Control' => 'private, max-age=3600']);
    }
}
