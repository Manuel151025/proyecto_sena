<?php
declare(strict_types=1);

namespace Core\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Genera los reportes institucionales en PDF real (RNF03).
 *
 * Antes, «exportación en PDF» era un botón que llamaba a `window.print()`:
 * el diálogo de impresión del navegador sobre la propia página web. Eso
 * dependía del navegador de cada quien, arrastraba la barra lateral y los
 * botones al papel, y no producía ningún archivo que el sistema pudiera
 * entregar. La exportación en Excel sí existía, así que RNF03 estaba a
 * medias.
 *
 * Decisiones que conviene conocer antes de tocar esta clase:
 *
 * 1. LA TABLA VA TROCEADA. dompdf mantiene en memoria el mapa de celdas de
 *    cada `<table>`, y el coste crece de forma que una tabla única de 1.683
 *    filas (el reporte de la ficha más grande) agota 512 MB y aborta. Al
 *    emitir un `<table>` independiente cada FILAS_POR_BLOQUE filas, el
 *    mismo reporte baja a ~200 MB. Cada bloque repite el encabezado, así
 *    que el resultado impreso es indistinguible de una tabla continua.
 *
 * 2. LÍMITES SUBIDOS EN CALIENTE. `generar()` eleva memory_limit y
 *    max_execution_time solo durante la generación y los deja como estaban
 *    al terminar. Sin esto, el reporte más grande falla en cualquier
 *    servidor con los valores por defecto de PHP (128 MB / 30 s).
 *
 * 3. SIN ACCESO A RED. `isRemoteEnabled = false`: el HTML que se compone
 *    aquí no debe poder provocar peticiones salientes desde el servidor.
 *
 * 4. EL LOGO ES OPCIONAL. Incrustar un PNG obliga a la extensión GD, que no
 *    está activa en todos los entornos. Si falta, el encabezado se dibuja
 *    solo con CSS y el reporte sale igual; no se convierte en un requisito
 *    de despliegue por un adorno.
 */
final class ReportePdfService {
    /**
     * Filas por `<table>`. Ver nota 1 de la cabecera.
     *
     * 22 sale de medir los cuatro reportes reales: es el mayor valor con el
     * que cada bloque sigue cabiendo en una página A4 apaisada, de modo que
     * el troceado no deja hojas a medias. Con 26 o más, los bloques del
     * reporte más ancho se desbordan y el documento dobla sus páginas.
     * Si se cambia la tipografía de la tabla, hay que volver a medirlo.
     */
    private const FILAS_POR_BLOQUE = 22;

    /** Verde institucional SENA. */
    private const VERDE = '#39A900';

    /** Azul oscuro de los encabezados de tabla, igual que en el Excel. */
    private const AZUL = '#00324D';

    private string $rutaLogo;

    public function __construct(?string $rutaLogo = null) {
        $this->rutaLogo = $rutaLogo ?? (BASE_PATH . 'assets/img/sena_logo.png');
    }

    /**
     * Compone el PDF y devuelve sus bytes.
     *
     * @param string   $titulo    Encabezado del documento.
     * @param string[] $headers   Títulos de columna.
     * @param array[]  $filas     Filas de datos, indexadas por posición.
     * @param array    $opciones  Claves admitidas:
     *   - subtitulo    string  Contexto bajo el título (ficha, programa...).
     *   - generado_por string  Nombre de quien exporta.
     *   - orientacion  string  'landscape' (por defecto) | 'portrait'.
     *   Además acepta las claves de semáforo que declara cada reporte
     *   (`columnas_concepto`, `columna_porcentaje`; ver ReportesService).
     * @return string Bytes del PDF.
     */
    public function generar(string $titulo, array $headers, array $filas, array $opciones = []): string {
        $memoriaPrevia = ini_get('memory_limit');
        $tiempoPrevio  = ini_get('max_execution_time');
        ini_set('memory_limit', '512M');
        set_time_limit(180);

        try {
            $opts = new Options();
            $opts->set('isRemoteEnabled', false);
            $opts->set('isHtml5ParserEnabled', true);
            // DejaVu Sans trae las tildes y la eñe. La fuente por defecto de
            // dompdf las pierde y el reporte sale con «Muoz Prez».
            $opts->set('defaultFont', 'DejaVu Sans');
            // dompdf cachea las métricas de cada fuente la primera vez que la
            // usa. Por defecto escribe dentro de vendor/, que composer
            // reinstala en cada despliegue y que en un servidor endurecido es
            // de solo lectura: la primera exportación fallaría. Se saca a un
            // directorio propio de la aplicación.
            if ($cache = $this->directorioCache()) {
                $opts->set('fontCache', $cache);
            }

            $dompdf = new Dompdf($opts);
            $dompdf->loadHtml($this->componerHtml($titulo, $headers, $filas, $opciones), 'UTF-8');
            $dompdf->setPaper('A4', ($opciones['orientacion'] ?? 'landscape') === 'portrait' ? 'portrait' : 'landscape');
            $dompdf->render();

            $this->numerarPaginas($dompdf);

            return (string)$dompdf->output();
        } finally {
            ini_set('memory_limit', (string)$memoriaPrevia);
            set_time_limit((int)$tiempoPrevio);
        }
    }

    // -----------------------------------------------------------------
    // COMPOSICIÓN
    // -----------------------------------------------------------------

    private function componerHtml(string $titulo, array $headers, array $filas, array $opciones): string {
        $subtitulo   = (string)($opciones['subtitulo'] ?? '');
        $generadoPor = (string)($opciones['generado_por'] ?? '');
        $total       = count($filas);

        $html  = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
        $html .= '<style>' . $this->css() . '</style></head><body>';

        // Encabezado institucional
        $html .= '<div class="cabecera">';
        if ($logo = $this->logoIncrustado()) {
            $html .= '<img class="logo" src="' . $logo . '" alt="">';
        }
        $html .= '<div class="titulos">';
        $html .= '<div class="marca">' . $this->e(\Core\Support\Configuracion::valor('system_title')) . ' · '
               . $this->e(\Core\Support\Configuracion::valor('regional')) . '</div>';
        $html .= '<h1>' . $this->e($titulo) . '</h1>';
        if ($subtitulo !== '') {
            $html .= '<div class="subtitulo">' . $this->e($subtitulo) . '</div>';
        }
        $html .= '</div></div>';

        // Metadatos
        $html .= '<table class="meta"><tr>';
        $html .= '<td><span>Generado</span>' . date('d/m/Y H:i') . '</td>';
        if ($generadoPor !== '') {
            $html .= '<td><span>Por</span>' . $this->e($generadoPor) . '</td>';
        }
        $html .= '<td><span>Registros</span>' . number_format($total, 0, ',', '.') . '</td>';
        $html .= '</tr></table>';

        if ($total === 0) {
            $html .= '<p class="vacio">El reporte no contiene registros para los criterios seleccionados.</p>';
            return $html . '</body></html>';
        }

        // Cuerpo: un `<table>` por bloque (ver nota 1 de la cabecera).
        $porBloque = max(1, (int)($opciones['filas_por_bloque'] ?? self::FILAS_POR_BLOQUE));
        $bloques   = array_chunk($filas, $porBloque);
        $ultimo    = count($bloques) - 1;

        $anchos = $opciones['anchos_columna'] ?? $this->calcularAnchos($headers, $filas);

        foreach ($bloques as $i => $bloque) {
            $clase = $i === $ultimo ? 'datos' : 'datos corte';
            $html .= '<table class="' . $clase . '"><thead><tr>';
            foreach ($headers as $c => $h) {
                $ancho = isset($anchos[$c]) ? ' style="width:' . $anchos[$c] . '%"' : '';
                $html .= '<th' . $ancho . '>' . $this->e($h) . '</th>';
            }
            $html .= '</tr></thead><tbody>';

            foreach ($bloque as $fila) {
                $html .= '<tr>';
                foreach (array_values($fila) as $col => $celda) {
                    $valor = (string)($celda ?? '');
                    $html .= '<td class="' . SemaforoReporte::clase($col, $valor, $opciones) . '">'
                           . $this->e($valor) . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        }

        return $html . '</body></html>';
    }

    /**
     * Directorio donde dompdf guarda las métricas de fuente ya calculadas.
     * Devuelve null si no se puede crear o escribir, en cuyo caso dompdf
     * vuelve a su ruta por defecto y el reporte se genera igual.
     */
    private function directorioCache(): ?string {
        $dir = BASE_PATH . 'cache/dompdf';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            return null;
        }
        return is_writable($dir) ? $dir : null;
    }

    /**
     * Reparte el ancho de la tabla entre las columnas según lo que ocupa su
     * contenido.
     *
     * Con el reparto automático de dompdf, una columna de texto largo («RA
     * Denominación») se queda estrecha y sus celdas crecen a tres líneas de
     * alto, lo que multiplicaba por dos las páginas del reporte. Dando el
     * ancho de antemano, la misma tabla pasa de 14 a 22 filas por página.
     *
     * Se muestrean como mucho 200 filas: con 1.683 el resultado es el mismo
     * y recorrerlas todas solo para medir no compensa.
     *
     * @return float[] Porcentaje por columna, sumando 100.
     */
    private function calcularAnchos(array $headers, array $filas): array {
        $n = count($headers);
        if ($n === 0) {
            return [];
        }

        $muestra = array_slice($filas, 0, 200);
        $largos  = [];

        foreach (array_keys($headers) as $c) {
            // El encabezado marca el mínimo: una columna cuyo título es más
            // largo que sus datos no debe quedar por debajo de él.
            $max = mb_strlen((string)$headers[$c]);
            $suma = 0;
            $cuenta = 0;
            foreach ($muestra as $fila) {
                $valores = array_values($fila);
                if (!isset($valores[$c])) {
                    continue;
                }
                $suma += mb_strlen((string)$valores[$c]);
                $cuenta++;
            }
            $medio = $cuenta > 0 ? $suma / $cuenta : 0;
            // Se pondera hacia la media y no hacia el máximo: una sola
            // denominación larguísima no debe estirar toda la columna.
            $largos[$c] = max($max, $medio);
        }

        $total = array_sum($largos);
        if ($total <= 0) {
            return array_fill(0, $n, round(100 / $n, 2));
        }

        // Cotas: ninguna columna baja del 4 % (ilegible) ni pasa del 30 %
        // (deja a las demás sin sitio).
        $anchos = [];
        foreach ($largos as $c => $l) {
            $anchos[$c] = min(30.0, max(4.0, $l / $total * 100));
        }

        // Renormalizar tras aplicar las cotas, para que sigan sumando 100.
        $suma = array_sum($anchos);
        foreach ($anchos as $c => $a) {
            $anchos[$c] = round($a / $suma * 100, 2);
        }

        return $anchos;
    }

    /**
     * «Página X de Y» en el pie. Se escribe sobre el lienzo ya renderizado
     * porque el total de páginas no se conoce hasta ese momento, y la
     * alternativa de dompdf (PHP embebido en el HTML) exige habilitar la
     * ejecución de código dentro de la plantilla.
     */
    private function numerarPaginas(Dompdf $dompdf): void {
        $canvas = $dompdf->getCanvas();
        if ($canvas === null) {
            return;
        }
        $ancho = $canvas->get_width();
        $alto  = $canvas->get_height();

        $canvas->page_text(
            $ancho - 130, $alto - 28, 'Página {PAGE_NUM} de {PAGE_COUNT}',
            null, 8, [0.42, 0.45, 0.50]
        );
        $canvas->page_text(
            34, $alto - 28, 'SENA · Documento generado automáticamente',
            null, 8, [0.42, 0.45, 0.50]
        );
    }

    /**
     * Devuelve el logo como data URI, o null si no se puede incrustar.
     * Ver nota 4 de la cabecera: sin GD, dompdf aborta al procesar el PNG,
     * así que más vale no intentarlo.
     */
    private function logoIncrustado(): ?string {
        if (!extension_loaded('gd') || !is_readable($this->rutaLogo)) {
            return null;
        }
        $bytes = @file_get_contents($this->rutaLogo);
        if ($bytes === false) {
            return null;
        }
        return 'data:image/png;base64,' . base64_encode($bytes);
    }

    private function e(string $s): string {
        return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }

    private function css(): string {
        $verde = self::VERDE;
        $azul  = self::AZUL;

        return <<<CSS
@page { margin: 26px 34px 46px 34px; }
body  { font-family: "DejaVu Sans", sans-serif; font-size: 7px; color: #1f2933; }

.cabecera { border-bottom: 2.5px solid {$verde}; padding-bottom: 8px; margin-bottom: 10px; }
.cabecera .logo   { width: 42px; vertical-align: middle; margin-right: 10px; }
.cabecera .titulos{ display: inline-block; vertical-align: middle; }
.cabecera .marca  { font-size: 7.5px; color: {$verde}; letter-spacing: .6px; text-transform: uppercase; font-weight: bold; }
.cabecera h1      { font-size: 15px; color: {$azul}; margin: 2px 0 0 0; font-weight: bold; }
.cabecera .subtitulo { font-size: 9px; color: #52606d; margin-top: 2px; }

table.meta { width: 100%; border-collapse: collapse; margin-bottom: 10px;
             background: #f5f7fa; border-left: 3px solid {$verde}; }
table.meta td   { padding: 4px 9px; font-size: 8px; color: #3e4c59; }
table.meta span { display: block; font-size: 6.5px; text-transform: uppercase;
                  letter-spacing: .5px; color: #7b8794; }

/* `table-layout: fixed` hace que se respeten los anchos calculados en
   calcularAnchos(); con el reparto automático dompdf los ignora. */
table.datos { width: 100%; border-collapse: collapse; table-layout: fixed; }
/* Cada bloque salta de página salvo el último: así el troceado no deja
   huecos a mitad de hoja ni una página final en blanco. */
table.datos.corte { page-break-after: always; }
table.datos th { background: {$azul}; color: #fff; font-size: 6.5px; font-weight: bold;
                 padding: 3px; border: .5px solid #24506b; text-align: left; }
table.datos td { padding: 2.5px 3px; border: .5px solid #dde3ea; vertical-align: top;
                 word-wrap: break-word; }
table.datos tbody tr:nth-child(even) td { background: #fafbfc; }

td.num     { text-align: center; }
td.aldia   { background: #e6f4ea !important; color: #137333; font-weight: bold; text-align: center; }
td.riesgo  { background: #fef7e0 !important; color: #b06000; font-weight: bold; text-align: center; }
td.critico { background: #fce8e6 !important; color: #a51d24; font-weight: bold; text-align: center; }

.vacio { margin-top: 30px; padding: 14px; background: #f5f7fa; border-left: 3px solid #cbd2d9;
         color: #52606d; font-size: 9px; }
CSS;
    }
}
