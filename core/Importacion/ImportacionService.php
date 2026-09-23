<?php
declare(strict_types=1);

namespace Core\Importacion;

use Core\Support\Actor;
use Core\Support\ArchivoSubido;
use Core\Support\ErrorDeNegocio;

/**
 * Importación en dos pasos, común a todos los tipos.
 *
 *   1. analizar(): lee el archivo, reconoce las columnas, valida cada fila
 *      y devuelve una vista previa con el resultado fila por fila. No
 *      escribe nada.
 *   2. confirmar(): guarda SOLO las filas válidas de esa vista previa.
 *
 * Antes cada importador validaba y escribía en el mismo paso: el usuario
 * subía el archivo a ciegas y descubría después qué filas habían fallado.
 * La vista previa previa en el navegador dependía de SheetJS cargado desde
 * un CDN que la política de seguridad bloqueaba, así que tampoco funcionaba.
 */
final class ImportacionService {
    private const CLAVE_SESION = 'importaciones';
    /** Una vista previa sin confirmar caduca a la hora. */
    private const VIGENCIA = 3600;

    /**
     * La vista previa se guarda en disco, fuera de la web, y la sesión solo
     * lleva su nombre. Un reporte de juicios tiene miles de filas: en la
     * sesión pesaba varios MB que PHP leía y reescribía en CADA petición
     * del usuario mientras la importación estaba pendiente.
     */
    private string $carpeta;

    public function __construct(?string $carpeta = null) {
        $this->carpeta = $carpeta ?? BASE_PATH . 'cache/importaciones';
    }

    public function analizar(Importador $imp, ArchivoSubido $archivo, Actor $actor, array $contexto): array {
        $lectura = LectorTabular::leer($archivo, $imp->maxFilas());
        $filas = $lectura['filas'];
        if ($filas === []) {
            throw new ErrorDeNegocio('El archivo no tiene filas con datos.');
        }
        $preparado = $imp->prepararFilas($filas, $contexto, $actor);
        $filas = $preparado['filas'];
        $contexto = $preparado['contexto'];
        if ($filas === []) {
            throw new ErrorDeNegocio('El archivo no tiene filas con datos.');
        }

        [$indices, $reconocido] = $this->mapearColumnas($imp, $filas[0]);
        if ($reconocido) {
            array_shift($filas);
        }
        if ($filas === []) {
            throw new ErrorDeNegocio('El archivo solo tiene la fila de encabezados.');
        }

        $resultado = [];
        $vistas = [];
        $validas = 0;
        $numeroBase = $reconocido ? 2 : 1;
        foreach ($filas as $i => $fila) {
            $mapeada = [];
            foreach ($indices as $col => $idx) {
                $mapeada[$col] = $idx === null ? '' : (string)($fila[$idx] ?? '');
            }
            $r = $imp->validarFila($mapeada, $actor, $contexto);
            $errores = $r['errores'];
            $claveFila = $r['clave'] ?? null;
            if ($errores === [] && $claveFila !== null) {
                if (isset($vistas[$claveFila])) {
                    $errores[] = "Repetida: es igual a la fila {$vistas[$claveFila]} del archivo.";
                } else {
                    $vistas[$claveFila] = $i + $numeroBase;
                }
            }
            if ($errores === []) {
                $validas++;
            }
            $resultado[] = [
                'linea'   => $i + $numeroBase,
                'valores' => $mapeada,
                'datos'   => $errores === [] ? $r['datos'] : null,
                'errores' => $errores,
                'avisos'  => $r['avisos'] ?? [],
            ];
        }

        $previa = [
            'tipo'        => $imp->clave(),
            'archivo'     => $archivo->nombreOriginal,
            'formato'     => $lectura['formato'],
            'codificacion'=> $lectura['codificacion'],
            'separador'   => $lectura['separador'],
            'truncado'    => $lectura['truncado'],
            'encabezado'  => $reconocido,
            'columnas'    => array_map(static fn($c) => $c['etiqueta'], $imp->columnas()),
            'filas'       => $resultado,
            'total'       => count($resultado),
            'validas'     => $validas,
            'contexto'    => $contexto,
            'avisos'      => $preparado['avisos'] ?? [],
            'resumen'     => $imp->resumen($resultado, $contexto),
            'actor'       => $actor->id,
            'creada'      => time(),
        ];
        $this->guardarPrevia($imp, $previa);
        return $previa;
    }

    /** Vista previa pendiente de este tipo, si existe y no ha caducado. */
    public function pendiente(Importador $imp, Actor $actor): ?array {
        $ref = $_SESSION[self::CLAVE_SESION][$imp->clave()] ?? null;
        if (!is_array($ref) || ($ref['actor'] ?? 0) !== $actor->id || time() - (int)($ref['creada'] ?? 0) > self::VIGENCIA) {
            $this->descartar($imp);
            return null;
        }
        $ruta = $this->ruta((string)($ref['archivo'] ?? ''));
        $p = $ruta !== null && is_file($ruta) ? unserialize((string)file_get_contents($ruta), ['allowed_classes' => false]) : null;
        if (!is_array($p) || ($p['actor'] ?? 0) !== $actor->id) {
            $this->descartar($imp);
            return null;
        }
        return $p;
    }

    public function descartar(Importador $imp): void {
        $ref = $_SESSION[self::CLAVE_SESION][$imp->clave()] ?? null;
        $ruta = is_array($ref) ? $this->ruta((string)($ref['archivo'] ?? '')) : null;
        if ($ruta !== null && is_file($ruta)) {
            @unlink($ruta);
        }
        unset($_SESSION[self::CLAVE_SESION][$imp->clave()]);
    }

    private function guardarPrevia(Importador $imp, array $previa): void {
        $this->descartar($imp);
        if (!is_dir($this->carpeta) && !@mkdir($this->carpeta, 0770, true) && !is_dir($this->carpeta)) {
            throw new \RuntimeException('No se pudo crear la carpeta de importaciones pendientes.');
        }
        $this->limpiarCaducadas();
        $archivo = bin2hex(random_bytes(16)) . '.previa';
        if (file_put_contents($this->carpeta . '/' . $archivo, serialize($previa), LOCK_EX) === false) {
            throw new \RuntimeException('No se pudo guardar la vista previa de la importación.');
        }
        $_SESSION[self::CLAVE_SESION][$imp->clave()] = ['archivo' => $archivo, 'actor' => $previa['actor'], 'creada' => $previa['creada']];
    }

    /** Ruta de una vista previa guardada; null si el nombre no es uno generado aquí. */
    private function ruta(string $archivo): ?string {
        return preg_match('/^[a-f0-9]{32}\.previa$/', $archivo) ? $this->carpeta . '/' . $archivo : null;
    }

    /** Borra las vistas previas abandonadas (nadie confirmó ni canceló). */
    private function limpiarCaducadas(): void {
        foreach (glob($this->carpeta . '/*.previa') ?: [] as $f) {
            if (time() - (int)@filemtime($f) > self::VIGENCIA) {
                @unlink($f);
            }
        }
    }

    /** @return array{creados:int, omitidos:int, con_error:int, detalle?:list<string>, credenciales?:list<array>} */
    public function confirmar(Importador $imp, Actor $actor): array {
        $p = $this->pendiente($imp, $actor);
        if ($p === null) {
            throw new ErrorDeNegocio('No hay una importación pendiente o caducó. Vuelve a subir el archivo.');
        }
        if (!$imp->permitido($actor)) {
            throw new ErrorDeNegocio('No tienes permiso para esta importación.');
        }
        $datos = [];
        foreach ($p['filas'] as $f) {
            if ($f['errores'] === [] && $f['datos'] !== null) {
                $datos[] = $f['datos'];
            }
        }
        if ($datos === []) {
            throw new ErrorDeNegocio('Ninguna fila del archivo es válida. Corrige el archivo y vuelve a subirlo.');
        }
        $r = $imp->guardar($datos, $actor, $p['contexto']);
        $this->descartar($imp);
        $r['con_error'] = $p['total'] - $p['validas'];
        return $r;
    }

    /**
     * Relaciona cada columna esperada con su posición en el archivo.
     *
     * Si la primera fila contiene los nombres de todas las columnas
     * obligatorias (o sus alias), se usa como encabezado y el orden del
     * archivo da igual. Si no, se asume el orden de la plantilla y se
     * avisa en la vista previa.
     *
     * @return array{0: array<string, ?int>, 1: bool}
     */
    private function mapearColumnas(Importador $imp, array $primera): array {
        $norm = array_map([LectorTabular::class, 'normalizarEncabezado'], $primera);
        $indices = [];
        $faltan = false;
        foreach ($imp->columnas() as $col => $def) {
            $nombres = array_map([LectorTabular::class, 'normalizarEncabezado'],
                array_merge([$col, $def['etiqueta']], $def['alias'] ?? []));
            $pos = null;
            foreach ($norm as $i => $h) {
                if ($h !== '' && in_array($h, $nombres, true)) {
                    $pos = $i;
                    break;
                }
            }
            $indices[$col] = $pos;
            if ($pos === null && ($def['obligatorio'] ?? false)) {
                $faltan = true;
            }
        }
        if (!$faltan) {
            return [$indices, true];
        }
        // Sin encabezado reconocible: orden de la plantilla.
        $i = 0;
        foreach ($indices as $col => $_) {
            $indices[$col] = $i++;
        }
        return [$indices, false];
    }

    /** CSV de la plantilla: encabezados y una fila de ejemplo, con BOM para Excel. */
    public function plantilla(Importador $imp): string {
        $h = fopen('php://temp', 'r+');
        fwrite($h, "\xEF\xBB\xBF");
        fputcsv($h, array_keys($imp->columnas()), ';', '"', '');
        fputcsv($h, array_values($imp->ejemplo()), ';', '"', '');
        rewind($h);
        $csv = (string)stream_get_contents($h);
        fclose($h);
        return $csv;
    }
}
