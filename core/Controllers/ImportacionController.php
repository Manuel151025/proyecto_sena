<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Importacion\ImportacionService;
use Core\Importacion\Importador;
use Core\Importacion\ImportadorCompetencias;
use Core\Importacion\ImportadorMatriculas;
use Core\Importacion\ImportadorResultados;
use Core\Importacion\ImportadorUsuarios;
use Core\Importacion\LectorTabular;
use Core\Router;
use Core\Support\Actor;
use Core\Support\ArchivoSubido;
use Core\Support\ErrorDeNegocio;
use Throwable;

/**
 * Importaciones tabulares (CSV, XLSX, XLS), en dos pasos.
 *
 *   GET  /<modulo>/importar                    formulario o vista previa
 *   GET  /<modulo>/importar/plantilla          plantilla CSV con los encabezados
 *   POST /<modulo>/importar                    analiza el archivo (paso 1)
 *   POST /<modulo>/importar action=confirmar   guarda las filas válidas (paso 2)
 *   POST /<modulo>/importar action=cancelar    descarta la vista previa
 *
 * Un solo controlador para los cuatro tipos: antes eran cuatro copias de
 * ~150 líneas, cada una con su propia forma de leer el CSV.
 */
class ImportacionController extends BaseController {
    /** Ruta base de cada importación => importador. */
    private const TIPOS = [
        '/usuarios/importar'               => ImportadorUsuarios::class,
        '/competencias/importar'           => ImportadorCompetencias::class,
        '/resultados-aprendizaje/importar' => ImportadorResultados::class,
        '/matriculas/importar'             => ImportadorMatriculas::class,
    ];
    private const VOLVER = [
        'usuarios' => ['/usuarios', 'Usuarios'],
        'competencias' => ['/competencias', 'Competencias'],
        'resultados' => ['/resultados-aprendizaje', 'Resultados de aprendizaje'],
        'matriculas' => ['/matriculas', 'Matrículas'],
    ];

    private ImportacionService $servicio;

    public function __construct(?ImportacionService $servicio = null) {
        $this->servicio = $servicio ?? new ImportacionService();
    }

    public function formulario(): void {
        [$ruta, $imp] = $this->importador();
        $actor = Actor::actual();
        // Resultado de la última confirmación: se muestra UNA vez (lleva
        // contraseñas temporales) y se borra.
        $clave = 'resultado_importacion_' . $imp->clave();
        $resultado = $_SESSION[$clave] ?? null;
        unset($_SESSION[$clave]);

        $this->render(BASE_PATH . 'modules/importacion/views/importar.view.php', [
            'ruta'       => $ruta,
            'importador' => $imp,
            'previa'     => $this->servicio->pendiente($imp, $actor),
            'resultado'  => $resultado,
            'volver'     => self::VOLVER[$imp->clave()] ?? ['/dashboard', 'Inicio'],
            'max_mb'     => LectorTabular::MAX_MB,
            'extras'     => $this->extrasFormulario($imp, $actor),
        ], 'Importar ' . $imp->titulo() . ' · SENA');
    }

    public function analizar(): never {
        [$ruta, $imp] = $this->importador();
        $actor = Actor::actual();
        try {
            if (!$imp->permitido($actor)) {
                throw new ErrorDeNegocio('No tienes permiso para esta importación.');
            }
            [$contexto, $errores] = $imp->contexto($_POST, $actor);
            if ($errores !== []) {
                $this->fallo($errores, $ruta);
            }
            $archivo = ArchivoSubido::desde($_FILES['archivo'] ?? null, LectorTabular::EXTENSIONES, LectorTabular::MAX_MB);
            @set_time_limit(120);
            $this->servicio->analizar($imp, $archivo, $actor, $contexto);
        } catch (Throwable $e) {
            $this->fallo(ErrorDeNegocio::mensajeSeguro($e, 'No se pudo analizar el archivo'), $ruta);
        }
        $this->irA($ruta);
    }

    public function confirmar(): never {
        [$ruta, $imp] = $this->importador();
        try {
            // Crear cientos de cuentas supone cientos de hash bcrypt.
            @set_time_limit(300);
            $r = $this->servicio->confirmar($imp, Actor::actual());
        } catch (Throwable $e) {
            $this->fallo(ErrorDeNegocio::mensajeSeguro($e, 'No se pudo completar la importación'), $ruta);
        }
        $_SESSION['resultado_importacion_' . $imp->clave()] = $r;
        $this->irA($ruta);
    }

    public function cancelar(): never {
        [$ruta, $imp] = $this->importador();
        $this->servicio->descartar($imp);
        $this->irA($ruta);
    }

    public function plantilla(): never {
        [, $imp] = $this->importador(true);
        $csv = $this->servicio->plantilla($imp);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="plantilla_' . $imp->clave() . '.csv"');
        header('X-Content-Type-Options: nosniff');
        header('Content-Length: ' . strlen($csv));
        echo $csv;
        exit;
    }

    /** @return array{0:string, 1:Importador} */
    private function importador(bool $esPlantilla = false): array {
        $ruta = Router::normalizarRuta((string)($_SERVER['REQUEST_URI'] ?? ''));
        if ($esPlantilla) {
            $ruta = preg_replace('#/plantilla$#', '', $ruta) ?? $ruta;
        }
        $clase = self::TIPOS[$ruta] ?? null;
        if ($clase === null) {
            Router::responder(404, 'Página no encontrada', 'Esta importación no existe.');
            exit;
        }
        return [$ruta, new $clase()];
    }

    /** Datos extra del formulario de subida (la ficha, en matrículas). */
    private function extrasFormulario(Importador $imp, Actor $actor): array {
        return method_exists($imp, 'opcionesFormulario') ? $imp->opcionesFormulario($actor) : [];
    }
}
