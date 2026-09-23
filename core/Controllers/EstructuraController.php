<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Models\ProgramasModel;
use Core\Models\ProyectosModel;
use Core\Services\EstructuraImportService;
use Core\Services\EstructuraPdfParser;
use Core\Support\Actor;
use Core\Support\ArchivoSubido;
use Core\Support\ErrorDeNegocio;
use Throwable;

/**
 * Estructura curricular: resumen e importación desde los PDF del SENA.
 *
 *   GET  /estructura                         resumen (coordinación)
 *   GET  /estructura/importar                formulario o vista previa
 *   POST /estructura/importar                analiza los PDF (paso 1)
 *   POST /estructura/importar action=confirmar   registra (paso 2)
 *   POST /estructura/importar action=cancelar    descarta la vista previa
 *
 * Antes esta pantalla tenía además su propia edición y borrado de programas
 * y de proyectos, copia de /programas y /proyectos con reglas distintas: la
 * edición de proyecto validaba el estado con la lista de estados de
 * PROGRAMA, así que rechazaba "finalizado" y aceptaba "archivado", que la
 * base rechaza. Ahora esas operaciones viven solo en su módulo.
 */
class EstructuraController extends BaseController {
    private const RUTA_IMPORTAR = '/estructura/importar';
    private const MAX_PDF_MB = 10;
    private const CLAVE_SESION = 'importacion_estructura';

    private ProgramasModel $programas;
    private ProyectosModel $proyectos;
    private EstructuraPdfParser $parser;
    private EstructuraImportService $importador;

    public function __construct(?ProgramasModel $programas = null, ?ProyectosModel $proyectos = null,
                                ?EstructuraPdfParser $parser = null, ?EstructuraImportService $importador = null) {
        $this->programas = $programas ?? new ProgramasModel();
        $this->proyectos = $proyectos ?? new ProyectosModel();
        $this->parser = $parser ?? new EstructuraPdfParser();
        $this->importador = $importador ?? new EstructuraImportService();
    }

    public function index(): void {
        $errors = [];
        $totales = [];
        $programas = $proyectos = [];
        try {
            $totales = $this->programas->totalesEstructura();
            $programas = $this->programas->getAll();
            $proyectos = $this->proyectos->listar(Actor::actual());
        } catch (Throwable $e) {
            $errors[] = ErrorDeNegocio::mensajeSeguro($e, 'Error al cargar la estructura curricular');
        }
        $this->render(BASE_PATH . 'modules/estructura/views/index.view.php', compact('errors', 'totales', 'programas', 'proyectos'),
            'Estructura Curricular · SENA');
    }

    public function importar(): void {
        $pendiente = $_SESSION[self::CLAVE_SESION] ?? null;
        $this->render(BASE_PATH . 'modules/estructura/views/importar.view.php', [
            'preview_mode'      => is_array($pendiente),
            'parsed_estructura' => $pendiente['estructura'] ?? null,
            'parsed_proyecto'   => $pendiente['proyecto'] ?? null,
            'max_mb'            => self::MAX_PDF_MB,
        ], 'Importar Estructura · SENA');
    }

    /** Paso 1: extrae el contenido de los PDF y lo deja para revisar. */
    public function analizar(): never {
        $this->exigirRol(ROL_COORDINADOR);
        try {
            $hayEstructura = ($_FILES['pdf_estructura']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
            $hayProyecto   = ($_FILES['pdf_proyecto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
            if (!$hayEstructura && !$hayProyecto) {
                throw new ErrorDeNegocio('Sube al menos uno de los dos PDF: estructura curricular o proyecto formativo.');
            }

            $pendiente = ['estructura' => null, 'proyecto' => null];
            if ($hayEstructura) {
                $pdf = ArchivoSubido::desde($_FILES['pdf_estructura'], ['pdf'], self::MAX_PDF_MB, 'El PDF de estructura curricular');
                $pendiente['estructura'] = $this->parser->parseEstructuraCurricular($this->texto($pdf, 'estructura curricular'));
            }
            if ($hayProyecto) {
                $pdf = ArchivoSubido::desde($_FILES['pdf_proyecto'], ['pdf'], self::MAX_PDF_MB, 'El PDF de proyecto formativo');
                $pendiente['proyecto'] = $this->parser->parseProyectoFormativo($this->texto($pdf, 'proyecto formativo'));
            }
            $_SESSION[self::CLAVE_SESION] = $pendiente;
        } catch (Throwable $e) {
            $this->fallo(ErrorDeNegocio::mensajeSeguro($e, 'No se pudieron analizar los documentos'), self::RUTA_IMPORTAR);
        }
        $this->irA(self::RUTA_IMPORTAR);
    }

    /** Paso 2: registra lo revisado. */
    public function confirmar(): never {
        $this->exigirRol(ROL_COORDINADOR);
        $pendiente = $_SESSION[self::CLAVE_SESION] ?? null;
        if (!is_array($pendiente)) {
            $this->fallo('No hay una importación pendiente. Vuelve a subir los documentos.', self::RUTA_IMPORTAR);
        }
        try {
            $r = $this->importador->confirmar($pendiente, Actor::actual());
        } catch (Throwable $e) {
            $this->fallo(ErrorDeNegocio::mensajeSeguro($e, 'No se pudo registrar la estructura'), self::RUTA_IMPORTAR);
        }
        unset($_SESSION[self::CLAVE_SESION]);

        $msg = "Estructura registrada: {$r['competencias']} competencias, {$r['resultados']} resultados de aprendizaje y {$r['fases']} fases.";
        if ($r['evaluaciones_creadas'] > 0) {
            $msg .= " Se habilitaron {$r['evaluaciones_creadas']} evaluaciones pendientes para aprendices ya matriculados.";
        }
        if ($r['omitidas_sin_instructor'] > 0) {
            $msg .= " {$r['omitidas_sin_instructor']} quedaron sin crear en fichas sin instructor líder.";
        }
        $this->exito($msg, '/estructura');
    }

    public function cancelar(): never {
        unset($_SESSION[self::CLAVE_SESION]);
        $this->irA(self::RUTA_IMPORTAR);
    }

    private function texto(ArchivoSubido $pdf, string $cual): string {
        $texto = $this->parser->extractText($pdf->ruta);
        if (trim($texto) === '') {
            throw new ErrorDeNegocio("No se pudo leer texto del PDF de $cual. Si es un documento escaneado (imagen), descárguelo de nuevo desde Sofia Plus.");
        }
        // Un PDF de estructura real ronda los 60 KB de texto; por encima de
        // 2 MB no es un documento curricular y analizarlo solo gasta memoria.
        if (strlen($texto) > 2 * 1024 * 1024) {
            throw new ErrorDeNegocio("El PDF de $cual tiene demasiado texto para ser un documento curricular.");
        }
        return $texto;
    }
}
