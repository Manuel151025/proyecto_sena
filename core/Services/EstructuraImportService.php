<?php
declare(strict_types=1);

namespace Core\Services;

use Core\Database;
use Core\Support\Actor;
use Core\Support\ErrorDeNegocio;
use PDO;
use Throwable;

/**
 * Registra en la base la estructura curricular y el proyecto formativo
 * extraídos de los PDF institucionales (ver EstructuraPdfParser).
 *
 * Estaba dentro de EstructuraController::import(), 170 líneas de SQL en un
 * método de controlador, y el error se mostraba con `$e->getMessage()`: el
 * texto de la PDOException, con nombres de tabla, llegaba a la pantalla.
 *
 * Lo extraído de un PDF es entrada del usuario como cualquier otra, así que
 * se normaliza y se acota antes de escribir: un PDF mal formado podía
 * producir un "nombre" de 3.000 caracteres o un código vacío, que la base
 * en modo estricto rechaza y el usuario veía como un error sin explicación.
 */
final class EstructuraImportService {
    private const ORDEN_FASES = ['ANÁLISIS' => 1, 'ANALISIS' => 1, 'PLANEACIÓN' => 2, 'PLANEACION' => 2,
                                 'EJECUCIÓN' => 3, 'EJECUCION' => 3, 'EVALUACIÓN' => 4, 'EVALUACION' => 4];

    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * @param array{estructura:?array, proyecto:?array} $datos Lo que devolvió el analizador.
     * @return array{programa:?int, proyecto:?int, competencias:int, resultados:int, fases:int, evaluaciones_creadas:int, omitidas_sin_instructor:int}
     */
    public function confirmar(array $datos, Actor $actor): array {
        if (!$actor->esCoordinador()) {
            throw new ErrorDeNegocio('Solo la coordinación importa la estructura curricular.');
        }
        if (empty($datos['estructura']) && empty($datos['proyecto'])) {
            throw new ErrorDeNegocio('No hay datos pendientes de importación. Vuelve a subir los documentos.');
        }

        $res = ['programa' => null, 'proyecto' => null, 'competencias' => 0, 'resultados' => 0, 'fases' => 0,
                'evaluaciones_creadas' => 0, 'omitidas_sin_instructor' => 0];

        $this->db->beginTransaction();
        try {
            if (!empty($datos['estructura'])) {
                $this->importarEstructura($datos['estructura'], $res);
            }
            if (!empty($datos['proyecto'])) {
                $this->importarProyecto($datos['proyecto'], $res);
            }

            // Los RAP recién creados deben llegar a los aprendices ya
            // matriculados; dentro de la transacción, para que estructura y
            // evaluaciones entren o se descarten juntas.
            $sync = (new EvaluacionesSyncService($this->db))->sincronizar();
            $res['evaluaciones_creadas'] = $sync['creadas'];
            $res['omitidas_sin_instructor'] = $sync['omitidas_sin_instructor'];

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        (new Auditoria($this->db))->operacion($actor, 'Importar', 'Estructura', 'programas', $res['programa'],
            "Importó estructura: {$res['competencias']} competencias, {$res['resultados']} RAP, {$res['fases']} fases");
        return $res;
    }

    private function importarEstructura(array $est, array &$res): void {
        $codigo = self::codigo($est['programa_codigo'] ?? '');
        $nombre = self::texto($est['programa_nombre'] ?? '', 200);
        if ($codigo === '' || $nombre === '') {
            throw new ErrorDeNegocio('El PDF de estructura no trae el código o el nombre del programa. Revise que sea el documento oficial.');
        }
        $horas = max(0, min(20000, (int)($est['programa_duracion'] ?? 0)));
        $progId = $this->asegurarPrograma($codigo, $nombre, $horas);
        $res['programa'] = $progId;

        foreach (($est['competencias'] ?? []) as $c) {
            $cCod = self::codigo($c['codigo'] ?? '');
            $cNom = self::texto($c['nombre'] ?? '', 255);
            if ($cCod === '' || $cNom === '') {
                continue;
            }
            $horasC = preg_match('/(\d+)/', (string)($c['duracion'] ?? ''), $m) ? min(20000, (int)$m[1]) : null;
            $compId = $this->asegurarCompetencia($progId, $cCod, $cNom, $horasC);
            $res['competencias']++;

            foreach (($c['resultados'] ?? []) as $ra) {
                $num = max(1, min(99, (int)($ra['numero'] ?? 0)));
                $den = self::texto($ra['denominacion'] ?? '', 2000);
                if ($den === '') {
                    continue;
                }
                $this->asegurarRap($compId, $cCod . '-' . str_pad((string)$num, 2, '0', STR_PAD_LEFT), $den);
                $res['resultados']++;
            }
        }
    }

    private function importarProyecto(array $proj, array &$res): void {
        $progId = $res['programa'];
        $progCod = self::codigo($proj['programa_codigo'] ?? '');
        if ($progId === null && $progCod !== '') {
            $progId = $this->asegurarPrograma($progCod, self::texto($proj['programa_nombre'] ?? '', 200) ?: 'Programa de formación', 0, false);
            $res['programa'] = $progId;
        }

        $pCod = self::codigo($proj['proyecto_codigo'] ?? '');
        $pNom = self::texto($proj['proyecto_nombre'] ?? '', 200);
        if ($pCod === '' || $pNom === '') {
            throw new ErrorDeNegocio('El PDF de proyecto no trae el código o el nombre del proyecto formativo.');
        }
        $objetivo = self::texto($proj['proyecto_objetivo'] ?? '', 2000);

        $st = $this->db->prepare("SELECT id FROM proyectos WHERE codigo = ?");
        $st->execute([$pCod]);
        $projId = (int)($st->fetchColumn() ?: 0);
        if ($projId > 0) {
            $this->db->prepare("UPDATE proyectos SET nombre = ?, objetivo = ? WHERE id = ?")->execute([$pNom, $objetivo, $projId]);
        } else {
            $this->db->prepare("INSERT INTO proyectos (nombre, codigo, objetivo, estado) VALUES (?, ?, ?, 'activo')")
                     ->execute([$pNom, $pCod, $objetivo]);
            $projId = (int)$this->db->lastInsertId();
        }
        $res['proyecto'] = $projId;

        // Las fases que no se reconocen van después de las cuatro estándar,
        // cada una con su número: antes todas recibían el 0 y chocaban con
        // la unicidad (proyecto, número de fase).
        $siguiente = 5;
        foreach (($proj['fases'] ?? []) as $faseNombre) {
            $faseNombre = self::texto((string)$faseNombre, 150);
            if ($faseNombre === '') {
                continue;
            }
            $num = self::ORDEN_FASES[mb_strtoupper($faseNombre, 'UTF-8')] ?? $siguiente++;
            $visible = mb_convert_case(mb_strtolower($faseNombre, 'UTF-8'), MB_CASE_TITLE, 'UTF-8');
            $st = $this->db->prepare("SELECT id FROM fases_proyecto WHERE proyecto_id = ? AND numero_fase = ?");
            $st->execute([$projId, $num]);
            $faseId = (int)($st->fetchColumn() ?: 0);
            if ($faseId > 0) {
                $this->db->prepare("UPDATE fases_proyecto SET nombre = ? WHERE id = ?")->execute([$visible, $faseId]);
            } else {
                $this->db->prepare("INSERT INTO fases_proyecto (proyecto_id, nombre, numero_fase, descripcion) VALUES (?, ?, ?, ?)")
                         ->execute([$projId, $visible, $num, "Fase de $visible del proyecto formativo"]);
            }
            $res['fases']++;
        }

        if ($progId === null) {
            return;
        }
        $comps = [];
        foreach (($proj['competencias'] ?? []) as $cCod => $cNom) {
            $cCod = self::codigo((string)$cCod);
            $cNom = self::texto((string)$cNom, 255);
            if ($cCod !== '' && $cNom !== '') {
                $comps[$cCod] = $this->asegurarCompetencia($progId, $cCod, $cNom, null, false);
            }
        }
        foreach (($proj['resultados'] ?? []) as $ra) {
            $cCod = self::codigo($ra['competencia_code'] ?? '');
            if (!isset($comps[$cCod])) {
                continue;
            }
            $den = self::texto($ra['denominacion'] ?? '', 2000);
            if ($den === '') {
                continue;
            }
            $raCod = self::codigo($ra['ra_code'] ?? $cCod) . '-' . str_pad((string)max(1, min(99, (int)($ra['ra_num'] ?? 0))), 2, '0', STR_PAD_LEFT);
            $this->asegurarRap($comps[$cCod], $raCod, $den);
        }
    }

    private function asegurarPrograma(string $codigo, string $nombre, int $horas, bool $actualizar = true): int {
        $st = $this->db->prepare("SELECT id FROM programas WHERE codigo = ?");
        $st->execute([$codigo]);
        $id = (int)($st->fetchColumn() ?: 0);
        if ($id > 0) {
            if ($actualizar) {
                $this->db->prepare("UPDATE programas SET nombre = ?, duracion_horas = ? WHERE id = ?")->execute([$nombre, $horas, $id]);
            }
            return $id;
        }
        $this->db->prepare("INSERT INTO programas (nombre, codigo, duracion_horas, estado) VALUES (?, ?, ?, 'activo')")
                 ->execute([$nombre, $codigo, $horas]);
        return (int)$this->db->lastInsertId();
    }

    private function asegurarCompetencia(int $progId, string $codigo, string $nombre, ?int $horas, bool $actualizar = true): int {
        $st = $this->db->prepare("SELECT id FROM competencias WHERE programa_id = ? AND codigo = ?");
        $st->execute([$progId, $codigo]);
        $id = (int)($st->fetchColumn() ?: 0);
        if ($id > 0) {
            if ($actualizar) {
                $this->db->prepare("UPDATE competencias SET nombre = ?, horas = COALESCE(?, horas) WHERE id = ?")->execute([$nombre, $horas, $id]);
            }
            return $id;
        }
        $etapa = (int)(stripos(self::sinTildes($nombre), 'ETAPA PRACTICA') !== false);
        $this->db->prepare("INSERT INTO competencias (programa_id, codigo, nombre, es_etapa_practica, horas, estado) VALUES (?, ?, ?, ?, ?, 'activo')")
                 ->execute([$progId, $codigo, $nombre, $etapa, $horas]);
        return (int)$this->db->lastInsertId();
    }

    private function asegurarRap(int $compId, string $codigo, string $denominacion): void {
        $st = $this->db->prepare("SELECT id, competencia_id FROM resultados_aprendizaje WHERE codigo = ?");
        $st->execute([$codigo]);
        $fila = $st->fetch(PDO::FETCH_ASSOC);
        if ($fila) {
            if ((int)$fila['competencia_id'] !== $compId) {
                // El código de RAP es único en todo el sistema: si ya existe
                // en otra competencia no se reasigna en silencio.
                throw new ErrorDeNegocio("El resultado de aprendizaje $codigo ya existe en otra competencia. Revise el documento.");
            }
            $this->db->prepare("UPDATE resultados_aprendizaje SET denominacion = ? WHERE id = ?")->execute([$denominacion, (int)$fila['id']]);
            return;
        }
        $this->db->prepare("INSERT INTO resultados_aprendizaje (competencia_id, codigo, denominacion) VALUES (?, ?, ?)")
                 ->execute([$compId, $codigo, $denominacion]);
    }

    /** Código normalizado: mayúsculas, sin espacios, solo caracteres de código. */
    private static function codigo(mixed $v): string {
        $v = mb_strtoupper(trim((string)$v), 'UTF-8');
        $v = preg_replace('/[^A-Z0-9\-_.]/', '', $v) ?? '';
        return mb_substr($v, 0, 50);
    }

    /** Texto de una sola línea, sin marcado ni controles, acotado. */
    private static function texto(mixed $v, int $max): string {
        $v = strip_tags((string)$v);
        $v = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $v) ?? '';
        $v = trim(preg_replace('/\s+/u', ' ', $v) ?? '');
        return mb_substr($v, 0, $max);
    }

    private static function sinTildes(string $s): string {
        return strtr(mb_strtoupper($s, 'UTF-8'), ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U']);
    }
}
