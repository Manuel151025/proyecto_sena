<?php
declare(strict_types=1);

namespace Core\Support;

/**
 * Clasificación Crítico / Riesgo / Al día, en un solo sitio.
 *
 * El criterio estaba escrito a mano dentro de la vista de seguimiento, una
 * vez en PHP y otra en su JavaScript, y otra más con otros umbrales en el
 * panel del instructor. Un cambio de umbral en uno no llegaba a los demás.
 *
 * Criterio para un aprendiz (el que ya usaba el seguimiento):
 *  - Crítico:  menos del 60 % de sus RAP evaluados en A, o más de 2 en D.
 *  - Riesgo:   menos del 80 %, o algún RAP en D.
 *  - Al día:   el resto.
 *
 * Para una ficha o un grupo se aplican los mismos umbrales al porcentaje.
 */
final class Semaforo {
    public const UMBRAL_AL_DIA = 80.0;
    public const UMBRAL_RIESGO = 60.0;
    public const MAX_D_RIESGO = 2;

    public const CRITICO = 'critico';
    public const RIESGO = 'riesgo';
    public const AL_DIA = 'al_dia';
    public const SIN_DATOS = 'sin_datos';

    private const ETIQUETAS = [
        self::CRITICO   => ['Crítico', 'danger'],
        self::RIESGO    => ['Riesgo', 'warning'],
        self::AL_DIA    => ['Al día', 'success'],
        self::SIN_DATOS => ['Sin juicios', 'secondary'],
    ];

    /**
     * @param float|null $porcentajeA % de RAP en A sobre los evaluados (A+D). null = sin juicios.
     * @param int $enD Número de RAP en D.
     */
    public static function aprendiz(?float $porcentajeA, int $enD): string {
        if ($porcentajeA === null) {
            return self::SIN_DATOS;
        }
        if ($porcentajeA < self::UMBRAL_RIESGO || $enD > self::MAX_D_RIESGO) {
            return self::CRITICO;
        }
        if ($porcentajeA < self::UMBRAL_AL_DIA || $enD > 0) {
            return self::RIESGO;
        }
        return self::AL_DIA;
    }

    public static function porcentaje(?float $pct): string {
        if ($pct === null) {
            return self::SIN_DATOS;
        }
        return $pct >= self::UMBRAL_AL_DIA ? self::AL_DIA : ($pct >= self::UMBRAL_RIESGO ? self::RIESGO : self::CRITICO);
    }

    public static function etiqueta(string $clave): string {
        return self::ETIQUETAS[$clave][0] ?? $clave;
    }

    /** Clase de color de Bootstrap (success, warning, danger, secondary). */
    public static function clase(string $clave): string {
        return self::ETIQUETAS[$clave][1] ?? 'secondary';
    }

    /**
     * Expresión SQL que clasifica con el mismo criterio, para contar en la
     * base sin traer cada aprendiz a PHP. Espera las columnas `pct_a` y
     * `en_d` (o las expresiones que se indiquen).
     */
    public static function sqlAprendiz(string $pct = 'pct_a', string $enD = 'en_d'): string {
        $r = self::UMBRAL_RIESGO;
        $a = self::UMBRAL_AL_DIA;
        $m = self::MAX_D_RIESGO;
        return "CASE WHEN $pct IS NULL THEN '" . self::SIN_DATOS . "'
                     WHEN $pct < $r OR $enD > $m THEN '" . self::CRITICO . "'
                     WHEN $pct < $a OR $enD > 0 THEN '" . self::RIESGO . "'
                     ELSE '" . self::AL_DIA . "' END";
    }
}
