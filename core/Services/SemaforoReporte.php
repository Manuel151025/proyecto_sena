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
 * Aquí quedan declaradas dos cosas: qué columna de cada reporte lleva
 * semáforo (ESTILOS) y qué color le corresponde a cada valor (`clase`).
 */
final class SemaforoReporte {
    /**
     * Qué columnas de cada reporte llevan color, por índice de posición.
     *
     * - columnas_concepto:  celdas con 'A' / 'D' / 'pendiente'.
     * - columna_porcentaje: celda con un % de cumplimiento.
     *
     * Los índices siguen el orden de `$headers` de cada reporte en
     * ReportesController. Si cambia el orden de las columnas, cambia aquí.
     */
    public const ESTILOS = [
        'evaluaciones_ficha'       => ['columnas_concepto'  => [5]],
        'cumplimiento_instructor'  => ['columna_porcentaje' => 8],
        'cumplimiento_competencia' => ['columna_porcentaje' => 6],
        'historial_cambios'        => ['columnas_concepto'  => [3, 4]],
    ];

    /** Umbral a partir del cual un cumplimiento se considera al día. */
    private const UMBRAL_ALDIA = 80.0;

    /** Por debajo de este umbral, el cumplimiento es crítico. */
    private const UMBRAL_RIESGO = 60.0;

    /**
     * Clase CSS del semáforo, o '' si la celda no lleva color.
     *
     * @param int   $col     Índice de la columna dentro de la fila.
     * @param array $estilos Entrada de ESTILOS del reporte en curso.
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
            $n = (float)str_replace(['%', ','], ['', '.'], $valor);
            if ($n >= self::UMBRAL_ALDIA)  return 'aldia';
            if ($n >= self::UMBRAL_RIESGO) return 'riesgo';
            return 'critico';
        }

        return is_numeric($valor) ? 'num' : '';
    }

    /**
     * Estilos declarados para un tipo de reporte. Devuelve [] si el reporte
     * no lleva semáforo, para que el llamador no tenga que comprobarlo.
     */
    public static function paraReporte(string $tipo): array {
        return self::ESTILOS[$tipo] ?? [];
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
