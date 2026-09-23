<?php
declare(strict_types=1);

namespace Core\Services;

use Core\Database;
use Core\Formularios\EvidenciaFormulario;
use Core\Models\EvidenciasModel;
use Core\Support\Actor;
use Core\Support\ArchivoSubido;
use Core\Support\ErrorDeNegocio;
use Core\Support\Transaccion;
use PDO;
use Throwable;

/**
 * Envío, revisión, retiro y descarga de evidencias.
 *
 * Reglas nuevas o corregidas:
 *  - El archivo ya no se sirve desde `uploads/`: se descarga por aquí, y
 *    solo quien puede ver la evidencia (el aprendiz, quien la revisa o la
 *    coordinación).
 *  - Revisar una evidencia ya no cambia sola el juicio del RAP. Antes
 *    «rechazada» lo devolvía a pendiente sin motivo, aunque estuviera en A.
 *    Ahora quien revisa decide aparte si registra A o D, y queda en el
 *    historial con la retroalimentación como motivo.
 *  - El aprendiz puede ligar la evidencia a uno de SUS RAP y retirarla
 *    mientras no se haya revisado.
 *  - Ya no se escribe `fichas.cumplimiento_porcentaje` (evidencias aprobadas
 *    / enviadas): mezclaba entregas con juicios y los indicadores de la
 *    ficha se calculan al leer.
 */
final class EvidenciasService {
    private const CARPETA = 'uploads/evidencias';

    private PDO $db;
    private EvidenciasModel $modelo;
    private InstructorAccessService $acceso;
    private string $raiz;

    public function __construct(?PDO $db = null, ?string $raiz = null) {
        $this->db = $db ?? Database::getConnection();
        $this->modelo = new EvidenciasModel($this->db);
        $this->acceso = new InstructorAccessService($this->db);
        $this->raiz = rtrim($raiz ?? BASE_PATH, '/\\') . '/';
    }

    public function enviar(array $d, ?ArchivoSubido $archivo, Actor $actor): int {
        if (!$actor->esAprendiz()) {
            throw new ErrorDeNegocio('Solo los aprendices envían evidencias.');
        }
        $ap = $this->modelo->aprendizDeUsuario($actor->id) ?? throw new ErrorDeNegocio('Tu cuenta no tiene una matrícula asociada.');
        if (in_array($ap['estado'], ['desertado', 'egresado'], true)) {
            throw new ErrorDeNegocio('Tu matrícula no está activa: no puedes enviar evidencias.');
        }
        if ($d['evaluacion_id'] !== null && !$this->modelo->evaluacionDelAprendiz($d['evaluacion_id'], (int)$ap['id'])) {
            throw new ErrorDeNegocio('El resultado de aprendizaje elegido no es tuyo.');
        }
        if ($archivo === null && trim($d['descripcion']) === '') {
            throw new ErrorDeNegocio('Adjunta un archivo o describe la evidencia.');
        }

        $guardado = $archivo !== null ? $this->guardarArchivo($archivo) : null;
        try {
            return Transaccion::ejecutar($this->db, function () use ($d, $ap, $archivo, $guardado, $actor) {
                $id = $this->modelo->crear([
                    'aprendiz_id' => (int)$ap['id'], 'ficha_id' => (int)$ap['ficha_id'], 'evaluacion_id' => $d['evaluacion_id'],
                    'titulo' => $d['titulo'], 'descripcion' => $d['descripcion'],
                    'archivo_url' => $guardado, 'tipo_archivo' => $archivo?->extension, 'tamano_kb' => $archivo ? (int)ceil($archivo->bytes / 1024) : 0,
                ]);
                (new Auditoria($this->db))->operacion($actor, 'Crear', 'Evidencias', 'evidencias', $id, "Envió la evidencia «{$d['titulo']}»");
                // Entregar evidencia de un RAP con plan abierto lo pone en curso.
                if ($d['evaluacion_id'] !== null) {
                    (new \Core\Models\MejoramientoModel($this->db))->marcarEnCurso($d['evaluacion_id']);
                }
                (new Notificador($this->db))->notificarVarios($this->modelo->instructoresAAvisar((int)$ap['id'], $d['evaluacion_id']),
                    'Nueva evidencia por revisar', "«{$d['titulo']}» espera tu revisión.", 'info', '/index.php/evidencias?estado=enviada');
                return $id;
            });
        } catch (Throwable $e) {
            if ($guardado !== null) {
                @unlink($this->raiz . $guardado);
            }
            throw $e;
        }
    }

    public function revisar(array $d, Actor $actor): void {
        $ev = $this->modelo->findById($d['id']) ?? throw new ErrorDeNegocio('La evidencia no existe.');
        if (!$this->puedeRevisar($ev, $actor)) {
            throw new ErrorDeNegocio('No revisas las evidencias de este aprendiz.');
        }
        if ($d['juicio'] !== '' && !$ev['evaluacion_id']) {
            throw new ErrorDeNegocio('La evidencia no está ligada a un resultado de aprendizaje: no se puede registrar un juicio desde aquí.');
        }
        Transaccion::ejecutar($this->db, function () use ($d, $ev, $actor) {
            $this->modelo->revisar((int)$ev['id'], $d['estado'], $d['retroalimentacion']);
            $juicio = '';
            if ($d['juicio'] !== '' && $d['juicio'] !== $ev['concepto']) {
                (new EvaluacionService($this->db))->actualizarPorId((int)$ev['evaluacion_id'], [
                    'concepto'   => $d['juicio'],
                    'usuario_id' => $actor->id,
                    'motivo'     => mb_substr("Evidencia «{$ev['titulo']}»: {$d['retroalimentacion']}", 0, 255),
                    'retroalimentacion' => false,
                    'notificar'  => false,
                ]);
                $juicio = " y el RAP {$ev['ra_codigo']} quedó en {$d['juicio']}";
            }
            $this->modelo->registrarRetroalimentacion($ev['evaluacion_id'] ? (int)$ev['evaluacion_id'] : null, (int)$ev['aprendiz_id'], $actor->id,
                $d['estado'] === 'aprobada' ? 'fortaleza' : 'aspecto_mejorar', $d['retroalimentacion']);
            (new Auditoria($this->db))->operacion($actor, 'Revisar', 'Evidencias', 'evidencias', (int)$ev['id'],
                "Revisó «{$ev['titulo']}»: {$d['estado']}$juicio");
            $texto = EvidenciaFormulario::REVISION[$d['estado']];
            (new Notificador($this->db))->notificar((int)$ev['aprendiz_usuario_id'], "Evidencia revisada: $texto",
                "«{$ev['titulo']}»: $texto$juicio. Revisa la retroalimentación.",
                $d['estado'] === 'aprobada' ? 'success' : 'warning', '/index.php/evidencias');
        });
    }

    /** El aprendiz retira la suya mientras no se ha revisado; la coordinación, cualquiera. */
    public function eliminar(int $id, Actor $actor): void {
        $ev = $this->modelo->findById($id) ?? throw new ErrorDeNegocio('La evidencia no existe.');
        $propia = $actor->esAprendiz() && (int)$ev['aprendiz_usuario_id'] === $actor->id;
        if (!$actor->esCoordinador() && !$propia) {
            throw new ErrorDeNegocio('No puedes eliminar esta evidencia.');
        }
        if ($propia && $ev['estado'] !== 'enviada') {
            throw new ErrorDeNegocio('La evidencia ya fue revisada: forma parte de tu historial y no se puede retirar.');
        }
        Transaccion::ejecutar($this->db, function () use ($ev, $actor) {
            $this->modelo->eliminar((int)$ev['id']);
            (new Auditoria($this->db))->operacion($actor, 'Eliminar', 'Evidencias', 'evidencias', (int)$ev['id'], "Eliminó la evidencia «{$ev['titulo']}»");
        });
        $ruta = $this->rutaSegura((string)($ev['archivo_url'] ?? ''));
        if ($ruta !== null && is_file($ruta)) {
            @unlink($ruta);
        }
    }

    /** @return array{0:string, 1:string, 2:string} [ruta en disco, nombre de descarga, extensión] */
    public function archivo(int $id, Actor $actor): array {
        $ev = $this->modelo->findById($id) ?? throw new ErrorDeNegocio('La evidencia no existe.');
        $propia = $actor->esAprendiz() && (int)$ev['aprendiz_usuario_id'] === $actor->id;
        if (!$actor->esCoordinador() && !$propia && !$this->puedeRevisar($ev, $actor)) {
            throw new ErrorDeNegocio('No tienes acceso a esta evidencia.');
        }
        $ruta = $this->rutaSegura((string)($ev['archivo_url'] ?? ''));
        if ($ruta === null || !is_file($ruta)) {
            throw new ErrorDeNegocio('La evidencia no tiene un archivo disponible.');
        }
        return [$ruta, $ev['titulo'], (string)$ev['tipo_archivo']];
    }

    private function puedeRevisar(array $ev, Actor $actor): bool {
        if ($actor->esCoordinador()) {
            return true;
        }
        if (!$actor->esInstructor()) {
            return false;
        }
        return $ev['evaluacion_id']
            ? $this->acceso->tieneAccesoEvaluacion((int)$ev['evaluacion_id'], $actor->id)
            : $this->acceso->tieneAccesoAprendiz((int)$ev['aprendiz_id'], $actor->id);
    }

    /** Guarda el archivo con un nombre aleatorio; devuelve la ruta relativa. */
    private function guardarArchivo(ArchivoSubido $archivo): string {
        $dir = $this->raiz . self::CARPETA;
        if (!is_dir($dir) && !@mkdir($dir, 0750, true) && !is_dir($dir)) {
            throw new \RuntimeException('No se pudo crear la carpeta de evidencias.');
        }
        $relativa = self::CARPETA . '/' . bin2hex(random_bytes(16)) . '.' . $archivo->extension;
        $ok = PHP_SAPI === 'cli' ? @copy($archivo->ruta, $this->raiz . $relativa) : @move_uploaded_file($archivo->ruta, $this->raiz . $relativa);
        if (!$ok) {
            throw new \RuntimeException('No se pudo guardar el archivo de la evidencia.');
        }
        return $relativa;
    }

    /**
     * Ruta en disco de un archivo de evidencia, solo si el valor guardado
     * tiene la forma que genera guardarArchivo(): así un `archivo_url`
     * alterado en la base no puede apuntar a otro archivo del servidor.
     */
    private function rutaSegura(string $relativa): ?string {
        return preg_match('#^uploads/evidencias/[a-f0-9]{32}\.[a-z0-9]{2,4}$#', $relativa) ? $this->raiz . $relativa : null;
    }
}
