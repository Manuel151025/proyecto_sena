<?php
declare(strict_types=1);

namespace Core\Support;

use Exception;
use Throwable;

/**
 * Error cuyo mensaje está escrito para que lo lea el usuario.
 *
 * El problema que resuelve: había 49 bloques `catch` que mostraban
 * `$e->getMessage()` directamente en pantalla. Con eso se mezclaban dos
 * cosas muy distintas:
 *
 *   - "El aprendiz no pertenece a ninguna de sus fichas asignadas."
 *     Un mensaje de negocio, escrito a propósito para el usuario.
 *
 *   - "SQLSTATE[42S22]: Column not found: 1054 Unknown column
 *     'f.cantidad' in 'field list'"
 *     El texto de una PDOException, que revela nombres de tabla y de
 *     columna a cualquiera que provoque el fallo.
 *
 * Como el `catch` no podía distinguirlos, o se enseñaba todo (y se filtraba
 * el esquema) o no se enseñaba nada (y el usuario perdía la explicación de
 * por qué no le dejan hacer algo).
 *
 * Con esta clase la distinción es explícita: lo que se lanza como
 * ErrorDeNegocio se muestra; cualquier otra cosa se registra y al usuario
 * le llega un mensaje genérico con una referencia.
 */
class ErrorDeNegocio extends Exception {
    /**
     * Devuelve un mensaje que se puede enseñar sin filtrar nada.
     *
     * @param string    $contexto  Se antepone al mensaje genérico ("Error al
     *        guardar la evaluación"), para que el usuario sepa qué falló
     *        aunque no sepa por qué.
     * @param bool|null $detallado Si se incluye la causa técnica. Por
     *        defecto sigue a DEV_MODE. Es un parámetro y no una lectura
     *        directa de la constante para que se pueda comprobar en una
     *        prueba el comportamiento de producción sin tener que
     *        desplegar con DEV_MODE apagado.
     */
    public static function mensajeSeguro(Throwable $e, string $contexto = '', ?bool $detallado = null): string {
        if ($e instanceof self) {
            return $e->getMessage();
        }

        $detallado ??= (defined('DEV_MODE') && DEV_MODE);

        // Cualquier otra cosa (PDOException, TypeError, ErrorException...)
        // se registra entera y se resume con una referencia cruzable.
        $ref = strtoupper(bin2hex(random_bytes(3)));
        error_log(sprintf(
            '[%s] %s: %s en %s:%d',
            $ref, get_class($e), $e->getMessage(), $e->getFile(), $e->getLine()
        ));

        $base = $contexto !== '' ? rtrim($contexto, '.') . '. ' : '';

        // En desarrollo sí interesa ver la causa sin ir al log.
        if ($detallado) {
            return $base . 'Error interno [' . $ref . ']: ' . $e->getMessage();
        }

        return $base . 'Ha ocurrido un error interno. Referencia: ' . $ref;
    }
}
