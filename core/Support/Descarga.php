<?php
declare(strict_types=1);

namespace Core\Support;

/**
 * Envía al navegador un archivo guardado en el servidor, después de que el
 * controlador haya comprobado el permiso.
 *
 * Por qué no un enlace directo: `uploads/` está cerrada a la web. Aquí,
 * además, el archivo se sirve con cabeceras que impiden que el navegador lo
 * interprete como otra cosa (nosniff) y, si se muestra en línea (PDF o
 * imagen), dentro de un sandbox sin scripts ni acceso al origen.
 */
final class Descarga {
    private const MIME = [
        'pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'txt' => 'text/plain; charset=utf-8',
        'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint', 'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ];
    /** Lo único que se deja abrir en el navegador; el resto se descarga. */
    private const EN_LINEA = ['pdf', 'png', 'jpg', 'jpeg'];

    public static function archivo(string $ruta, string $nombre, string $extension): never {
        $extension = strtolower($extension);
        $enLinea = in_array($extension, self::EN_LINEA, true);
        $nombre = self::nombreSeguro($nombre) . '.' . $extension;

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: ' . (self::MIME[$extension] ?? 'application/octet-stream'));
        header('Content-Length: ' . (string)filesize($ruta));
        header(sprintf('Content-Disposition: %s; filename="%s"; filename*=UTF-8\'\'%s',
            $enLinea ? 'inline' : 'attachment', self::ascii($nombre), rawurlencode($nombre)));
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: sandbox; default-src 'none'; img-src 'self'; style-src 'unsafe-inline'");
        header('Cache-Control: private, no-store');
        readfile($ruta);
        exit;
    }

    private static function nombreSeguro(string $nombre): string {
        $n = preg_replace('/[^\p{L}\p{N}\-_. ]+/u', '', $nombre) ?? '';
        $n = trim(preg_replace('/\s+/', '_', $n) ?? '', '._');
        return mb_substr($n !== '' ? $n : 'archivo', 0, 80);
    }

    private static function ascii(string $nombre): string {
        $a = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombre);
        return preg_replace('/[^A-Za-z0-9\-_.]/', '_', $a !== false ? $a : 'archivo') ?? 'archivo';
    }
}
