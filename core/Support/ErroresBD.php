<?php
declare(strict_types=1);

namespace Core\Support;

use PDOException;
use Throwable;

/**
 * Traduce las violaciones de integridad de la base a mensajes de negocio.
 *
 * Cada modelo reconocía a su manera el "Duplicate entry" de un código
 * repetido o la clave foránea que impide borrar algo en uso, casi siempre
 * con `str_contains($e->getMessage(), ...)`, y los que no lo hacían dejaban
 * que el usuario viera "Ha ocurrido un error interno" por algo que tiene una
 * explicación perfectamente clara.
 *
 * Se decide por el código de error de MySQL/MariaDB, no por el texto:
 *   1062  entrada duplicada en un índice UNIQUE
 *   1451  no se puede borrar/actualizar: hay filas hijas que la usan
 *   1452  la fila referenciada no existe
 *   3819  CHECK violado
 */
final class ErroresBD {
    public const DUPLICADO     = 1062;
    public const EN_USO        = 1451;
    public const REFERENCIA    = 1452;
    public const CHECK         = 3819;

    /** Código de error del motor, o 0 si no es un error de la base. */
    public static function codigo(Throwable $e): int {
        if (!$e instanceof PDOException) {
            return 0;
        }
        return (int)($e->errorInfo[1] ?? 0);
    }

    /**
     * Relanza como ErrorDeNegocio si el fallo es una violación conocida.
     * Si no lo es, relanza el original sin tocarlo.
     *
     * @param array<int,string> $mensajes Código de error => mensaje para el usuario.
     * @return never
     */
    public static function relanzar(Throwable $e, array $mensajes): never {
        $codigo = self::codigo($e);
        if ($codigo !== 0 && isset($mensajes[$codigo])) {
            throw new ErrorDeNegocio($mensajes[$codigo], 0, $e);
        }
        throw $e;
    }
}
