<?php
declare(strict_types=1);

namespace Core\Support;

use PDO;
use Throwable;

/**
 * Ejecuta una operación dentro de una transacción, o dentro de la del
 * llamador si ya hay una abierta.
 *
 * PDO no anida transacciones: un segundo beginTransaction() lanza
 * excepción, y un commit() interno cerraría a medias la transacción del
 * llamador. Por eso un servicio que puede usarse suelto o como parte de
 * una operación mayor (calificar una evidencia, confirmar una importación)
 * solo abre la suya si no la hay.
 */
final class Transaccion {
    /**
     * @template T
     * @param callable():T $operacion
     * @return T
     */
    public static function ejecutar(PDO $db, callable $operacion): mixed {
        $propia = !$db->inTransaction();
        if ($propia) {
            $db->beginTransaction();
        }
        try {
            $resultado = $operacion();
            if ($propia) {
                $db->commit();
            }
            return $resultado;
        } catch (Throwable $e) {
            if ($propia && $db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }
}
