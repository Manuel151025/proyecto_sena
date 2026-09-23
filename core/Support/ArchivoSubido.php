<?php
declare(strict_types=1);

namespace Core\Support;

use finfo;

/**
 * Un archivo recibido por formulario, ya validado.
 *
 * Reúne las comprobaciones que cada importador y la subida de evidencias
 * hacían a su manera (o no hacían): código de error de PHP traducido a un
 * mensaje comprensible, tamaño máximo propio de cada uso, extensión en lista
 * blanca y tipo REAL del contenido con finfo, no el que declara el
 * navegador ni el que sugiere el nombre.
 *
 * Uso:
 *     $pdf = ArchivoSubido::desde($_FILES['pdf'] ?? null, ['pdf'], 10);
 *     $texto = $parser->extraer($pdf->ruta);
 *
 * Lanza ErrorDeNegocio con un mensaje para el usuario si algo no cuadra.
 */
final class ArchivoSubido {
    /**
     * Tipos MIME admitidos por extensión. Un .docx o .xlsx es un ZIP por
     * dentro y algunas versiones de libmagic lo reportan así.
     */
    public const MIME = [
        'pdf'  => ['application/pdf'],
        'csv'  => ['text/plain', 'text/csv', 'application/csv', 'text/x-csv', 'application/vnd.ms-excel'],
        'txt'  => ['text/plain'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/octet-stream'],
        'xls'  => ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/CDFV2', 'application/octet-stream'],
        'doc'  => ['application/msword', 'application/x-ole-storage', 'application/CDFV2'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'ppt'  => ['application/vnd.ms-powerpoint', 'application/x-ole-storage', 'application/CDFV2'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png'  => ['image/png'],
    ];

    /** Firma de los primeros bytes, para los formatos que la tienen fija. */
    private const FIRMA = [
        'pdf'  => '%PDF-',
        'xlsx' => "PK\x03\x04",
        'docx' => "PK\x03\x04",
        'pptx' => "PK\x03\x04",
        'xls'  => "\xD0\xCF\x11\xE0",
        'doc'  => "\xD0\xCF\x11\xE0",
        'ppt'  => "\xD0\xCF\x11\xE0",
        'png'  => "\x89PNG",
        'jpg'  => "\xFF\xD8\xFF",
        'jpeg' => "\xFF\xD8\xFF",
    ];

    private function __construct(
        public readonly string $ruta,
        public readonly string $nombreOriginal,
        public readonly string $extension,
        public readonly string $mime,
        public readonly int $bytes,
    ) {}

    /**
     * @param array|null $campo    Entrada de $_FILES.
     * @param string[]   $permitidas Extensiones admitidas, en minúsculas.
     * @param float      $maxMb    Tamaño máximo en MB.
     */
    public static function desde(?array $campo, array $permitidas, float $maxMb, string $etiqueta = 'El archivo'): self {
        if ($campo === null || !isset($campo['error']) || is_array($campo['error'])) {
            // `name="archivo[]"` convierte cada clave en un array: se trata
            // como manipulación, igual que en Validador.
            throw new ErrorDeNegocio("$etiqueta es obligatorio.");
        }
        $error = (int)$campo['error'];
        if ($error !== UPLOAD_ERR_OK) {
            throw new ErrorDeNegocio(self::mensajeError($error, $etiqueta, $maxMb));
        }
        $tmp = (string)($campo['tmp_name'] ?? '');
        // Solo archivos que llegaron por HTTP: impide que un valor
        // manipulado apunte a un archivo del servidor.
        if ($tmp === '' || (PHP_SAPI !== 'cli' && !is_uploaded_file($tmp))) {
            throw new ErrorDeNegocio("$etiqueta no se recibió correctamente. Vuelve a intentarlo.");
        }

        $bytes = (int)(@filesize($tmp) ?: 0);
        if ($bytes <= 0) {
            throw new ErrorDeNegocio("$etiqueta está vacío.");
        }
        if ($bytes > (int)($maxMb * 1024 * 1024)) {
            throw new ErrorDeNegocio(sprintf('%s pesa %.1f MB y el máximo es %s MB.', $etiqueta, $bytes / 1048576, self::numero($maxMb)));
        }

        $nombre = self::nombreSeguro((string)($campo['name'] ?? 'archivo'));
        $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        if (!in_array($ext, $permitidas, true) || !isset(self::MIME[$ext])) {
            throw new ErrorDeNegocio("$etiqueta debe ser " . self::lista($permitidas) . '.');
        }

        $mime = (string)((new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: 'application/octet-stream');
        if (!in_array($mime, self::MIME[$ext], true)) {
            throw new ErrorDeNegocio("$etiqueta no es un archivo .$ext válido: su contenido no corresponde a ese formato.");
        }

        if (isset(self::FIRMA[$ext])) {
            $h = @fopen($tmp, 'rb');
            $inicio = $h ? (string)fread($h, 8) : '';
            if ($h) {
                fclose($h);
            }
            if (!str_starts_with($inicio, self::FIRMA[$ext])) {
                throw new ErrorDeNegocio("$etiqueta no es un archivo .$ext válido: su contenido no corresponde a ese formato.");
            }
        }

        return new self($tmp, $nombre, $ext, $mime, $bytes);
    }

    /**
     * Nombre del archivo tal como lo verá el usuario en pantalla: sin ruta
     * (algunos navegadores antiguos mandaban C:\...\archivo.csv), sin
     * caracteres de control y acotado.
     */
    public static function nombreSeguro(string $nombre): string {
        $nombre = str_replace('\\', '/', $nombre);
        $nombre = basename($nombre);
        $nombre = preg_replace('/[\x00-\x1F\x7F]/u', '', $nombre) ?? '';
        $nombre = trim($nombre);
        if ($nombre === '' || $nombre === '.' || $nombre === '..') {
            $nombre = 'archivo';
        }
        return mb_substr($nombre, 0, 150);
    }

    private static function mensajeError(int $codigo, string $etiqueta, float $maxMb): string {
        return match ($codigo) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE =>
                "$etiqueta supera el tamaño máximo permitido (" . self::numero($maxMb) . ' MB).',
            UPLOAD_ERR_PARTIAL  => "$etiqueta llegó incompleto. Revisa tu conexión y vuelve a intentarlo.",
            UPLOAD_ERR_NO_FILE  => "$etiqueta es obligatorio.",
            default             => "$etiqueta no se pudo recibir por un problema del servidor. Avisa a la coordinación.",
        };
    }

    private static function lista(array $ext): string {
        $ext = array_map(static fn($e) => '.' . $e, $ext);
        if (count($ext) === 1) {
            return 'un archivo ' . $ext[0];
        }
        $ultimo = array_pop($ext);
        return 'un archivo ' . implode(', ', $ext) . ' o ' . $ultimo;
    }

    private static function numero(float $n): string {
        return rtrim(rtrim(number_format($n, 1, ',', ''), '0'), ',');
    }
}
