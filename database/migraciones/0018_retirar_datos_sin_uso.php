<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

use Core\Support\Migracion;

/**
 * Retira datos guardados que ya nada lee y que solo inducían a error:
 *
 *  - fichas.cantidad_aprendices y fichas.cumplimiento_porcentaje: contadores
 *    que nadie recalculaba (el de cumplimiento lo sobrescribía el módulo de
 *    evidencias con otra fórmula). Los indicadores de la ficha se calculan
 *    al leer (FichaModel, AnaliticaModel).
 *  - configuraciones_sistema: 'pass_score' contradecía el semáforo y nada lo
 *    usaba; 'smtp_server' no tenía efecto (el correo se configura en el
 *    entorno, MAIL_*).
 */
return new class extends Migracion {
    public string $descripcion = 'Retirar contadores y configuraciones sin uso';

    public function aplicar(PDO $db): void {
        foreach (['cantidad_aprendices', 'cumplimiento_porcentaje'] as $columna) {
            if ($this->existeColumna($db, 'fichas', $columna)) {
                $db->exec("ALTER TABLE fichas DROP COLUMN `$columna`");
                $this->informar("fichas.$columna eliminada");
            }
        }
        if ($this->existeTabla($db, 'configuraciones_sistema')) {
            $n = $db->exec("DELETE FROM configuraciones_sistema WHERE clave IN ('pass_score', 'smtp_server')");
            $this->informar("$n configuraciones sin uso eliminadas");
        }
    }
};
