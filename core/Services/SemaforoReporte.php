<?php
declare(strict_types=1);

namespace Core\Services;

/**
 * Criterio de color de los reportes exportados, en un solo sitio.
 *
 * Un juicio 'A' tiene que verse verde tanto en el Excel como en el PDF, y
 * el umbral del 80 % tiene que ser el mismo en los dos. Cuando la regla
 * estaba escrita a mano dentro del `foreach` del export a Excel, añadir un
 * formato nuevo significaba copiarla, y dos copias divergen en cuanto
 * alguien ajusta un umbral en una y olvida la otra.
 *
 * Qué columnas llevan semáforo lo declara cada reporte en ReportesService
 * ('columnas_concepto' y 'columna_porcentaje'); aquí se decide el color de
 * cada valor, con los umbrales de Core\Support\Semaforo (antes tenía su
 * propia copia del 80 % y el 60 %).
 */
final class SemaforoReporte {
    /**
     * Clase CSS del semáforo, o '' si la celda no lleva color.
     *
     * @param int   $col     Índice de la columna dentro de la fila.
     * @param array $estilos 'columnas_concepto' y 'columna_porcentaje' del reporte.
     * @return string 'aldia' | 'riesgo' | 'critico' | 'num' | ''
     */
    public static function clase(int $col, string $valor, array $estilos): string {
        $concepto = $estilos['columnas_concepto'] ?? [];

        if (in_array($col, $concepto, true)) {
            return match ($valor) {
                'A'     => 'aldia',
                'D'     => 'critico',
                default => 'riesgo',
            };
        }

        if (isset($estilos['columna_porcentaje']) && $col === $estilos['columna_porcentaje']) {
            // El valor puede venir como "85", "85,5" o "85.5%" según el
            // reporte, así que se normaliza antes de comparar.
            if (trim($valor) === '') {
                return '';
            }
            return match (\Core\Support\Semaforo::porcentaje((float)str_replace(['%', ','], ['', '.'], $valor))) {
                \Core\Support\Semaforo::AL_DIA => 'aldia',
                \Core\Support\Semaforo::RIESGO => 'riesgo',
                default => 'critico',
            };
        }

        return is_numeric($valor) ? 'num' : '';
    }

    /**
     * Neutraliza una celda antes de escribirla en un CSV.
     *
     * Excel y LibreOffice interpretan como fórmula toda celda que empiece
     * por `=`, `+`, `-` o `@`. Los reportes exportan texto escrito por
     * usuarios —el motivo de un cambio de nota, el nombre de un aprendiz—,
     * así que un motivo como
     *
     *     =HYPERLINK("http://sitio-del-atacante","Ver nota")
     *
     * se convertía en un enlace ejecutable en la hoja de quien abriera el
     * reporte. Es el vector conocido como CSV injection, y el objetivo no
     * es quien exporta sino el coordinador que recibe el archivo.
     *
     * Se antepone un apóstrofo, que es la marca estándar de "esto es
     * texto": la hoja muestra el contenido tal cual y no lo evalúa.
     */
    public static function protegerCsv(mixed $valor): string {
        $texto = (string)($valor ?? '');
        if ($texto === '') {
            return '';
        }

        // El tabulador y el retorno de carro al principio también sirven
        // para colar una fórmula saltándose una comprobación ingenua.
        $primero = mb_substr(ltrim($texto, "\t\r\n "), 0, 1);

        return in_array($primero, ['=', '+', '-', '@'], true) ? "'" . $texto : $texto;
    }

    /**
     * Aplica `protegerCsv` a una fila entera.
     *
     * @return string[]
     */
    public static function protegerFilaCsv(array $fila): array {
        return array_map([self::class, 'protegerCsv'], array_values($fila));
    }
}
