<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use Core\Services\InstructorAccessService;
use Core\Support\Actor;
use PDO;

/**
 * Eventos del calendario, en el formato de FullCalendar, acotados por rol
 * (el mismo alcance de fichas que el resto del sistema).
 *
 * Fuentes: eventos creados a mano, inicio y fin de las fichas, fases del
 * proyecto formativo, entregas de actividades, fechas límite de los planes
 * de mejoramiento y juicios emitidos (los propios, para instructor y
 * aprendiz).
 *
 * Los textos viajan como datos: el navegador los pinta con textContent.
 * Antes el detalle de un evento se armaba con innerHTML y la descripción
 * de un evento creado por un instructor se ejecutaba como HTML en el
 * navegador de quien lo abría.
 */
class CalendarioModel {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    /** @return array{0:string, 1:array} Condición sobre el alias `f`. */
    private function alcance(Actor $actor): array {
        if ($actor->esCoordinador()) {
            return ['1=1', []];
        }
        if ($actor->esInstructor()) {
            return ['f.id IN (' . InstructorAccessService::sqlFichasDelInstructor() . ')', [$actor->id, $actor->id, $actor->id]];
        }
        return ['f.id IN (SELECT ficha_id FROM aprendices WHERE usuario_id = ?)', [$actor->id]];
    }

    private function filas(string $sql, array $p): array {
        $st = $this->db->prepare($sql);
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function evento(string $id, string $titulo, string $fecha, string $color, string $tipo, ?string $ruta, array $datos = []): array {
        return ['id' => $id, 'title' => $titulo, 'start' => $fecha, 'allDay' => true, 'color' => $color, 'textColor' => '#fff',
                'url' => $ruta !== null ? APP_URL . '/index.php' . $ruta : null,
                'extendedProps' => ['tipo' => $tipo] + array_filter($datos, static fn($v) => $v !== null && $v !== '')];
    }

    /** Todos los eventos visibles para el actor entre dos fechas (incluidas). */
    public function eventos(Actor $actor, string $desde, string $hasta): array {
        [$w, $p] = $this->alcance($actor);
        $rango = [$desde, $hasta];
        $ev = [];

        foreach ($this->filas("
            SELECT ec.id, ec.titulo, ec.descripcion, ec.fecha, ec.creado_por, u.nombre AS creador, f.id AS ficha_id, f.numero_ficha
              FROM eventos_calendario ec JOIN fichas f ON f.id = ec.ficha_id JOIN usuarios u ON u.id = ec.creado_por
             WHERE ec.fecha BETWEEN ? AND ? AND $w ORDER BY ec.fecha LIMIT 500", array_merge($rango, $p)) as $e) {
            $ev[] = self::evento('evento-' . $e['id'], $e['titulo'], $e['fecha'], '#f59e0b', 'Evento', null, [
                'ficha' => $e['numero_ficha'], 'descripcion' => $e['descripcion'], 'creador' => $e['creador'],
                'eventoId' => (int)$e['id'], 'puedeEliminar' => $actor->esCoordinador() || (int)$e['creado_por'] === $actor->id,
            ]);
        }

        foreach ($this->filas("
            SELECT f.id, f.numero_ficha, f.fecha_inicio, f.fecha_fin, f.estado, p.nombre AS programa
              FROM fichas f JOIN programas p ON p.id = f.programa_id
             WHERE (f.fecha_inicio BETWEEN ? AND ? OR f.fecha_fin BETWEEN ? AND ?) AND $w", array_merge($rango, $rango, $p)) as $f) {
            foreach ([['fecha_inicio', 'Inicio', '#39A900'], ['fecha_fin', 'Fin', '#6366f1']] as [$col, $txt, $color]) {
                if ($f[$col] !== null && $f[$col] >= $desde && $f[$col] <= $hasta) {
                    $ev[] = self::evento("ficha-$col-{$f['id']}", "$txt de la ficha {$f['numero_ficha']}", $f[$col], $color, "$txt de ficha",
                        '/fichas/ver?id=' . $f['id'], ['programa' => $f['programa'], 'estado' => $f['estado']]);
                }
            }
        }

        foreach ($this->filas("
            SELECT DISTINCT fp.id, fp.nombre, fp.fecha_inicio, fp.fecha_fin, fp.estado, pr.nombre AS proyecto
              FROM fases_proyecto fp JOIN proyectos pr ON pr.id = fp.proyecto_id JOIN fichas f ON f.proyecto_id = pr.id
             WHERE (fp.fecha_inicio BETWEEN ? AND ? OR fp.fecha_fin BETWEEN ? AND ?) AND $w", array_merge($rango, $rango, $p)) as $fa) {
            foreach ([['fecha_inicio', 'Inicia', '#3b82f6'], ['fecha_fin', 'Cierra', '#0ea5e9']] as [$col, $txt, $color]) {
                if ($fa[$col] !== null && $fa[$col] >= $desde && $fa[$col] <= $hasta) {
                    $ev[] = self::evento("fase-$col-{$fa['id']}", "$txt: {$fa['nombre']}", $fa[$col], $color, 'Fase del proyecto formativo',
                        '/fases', ['proyecto' => $fa['proyecto'], 'estado' => $fa['estado']]);
                }
            }
        }

        foreach ($this->filas("
            SELECT act.id, act.nombre, act.fecha_fin, act.estado, act.cumplimiento_porcentaje, f.numero_ficha
              FROM actividades act JOIN fichas f ON f.id = act.ficha_id
             WHERE act.fecha_fin BETWEEN ? AND ? AND act.estado <> 'cancelada' AND $w LIMIT 500", array_merge($rango, $p)) as $a) {
            $hecha = $a['estado'] === 'completada';
            $ev[] = self::evento('actividad-' . $a['id'], ($hecha ? 'Entregada: ' : 'Entrega: ') . $a['nombre'], $a['fecha_fin'],
                $hecha ? '#94a3b8' : '#14b8a6', 'Actividad del proyecto', '/actividades',
                ['ficha' => $a['numero_ficha'], 'estado' => $a['estado'], 'avance' => (int)$a['cumplimiento_porcentaje'] . '%']);
        }

        [$wPlan, $pPlan] = $actor->esInstructor()
            ? ['pm.instructor_id = ?', [$actor->id]]
            : [$w, $p];
        foreach ($this->filas("
            SELECT pm.id, pm.fecha_limite, ra.codigo, u.nombre AS aprendiz, f.numero_ficha
              FROM planes_mejoramiento pm JOIN fichas f ON f.id = pm.ficha_id
              JOIN evaluaciones e ON e.id = pm.evaluacion_id JOIN resultados_aprendizaje ra ON ra.id = e.resultado_aprendizaje_id
              JOIN aprendices ap ON ap.id = pm.aprendiz_id JOIN usuarios u ON u.id = ap.usuario_id
             WHERE pm.estado IN ('abierto','en_curso') AND pm.fecha_limite BETWEEN ? AND ? AND $wPlan
               " . ($actor->esAprendiz() ? 'AND ap.usuario_id = ?' : '') . " LIMIT 500",
            array_merge($rango, $pPlan, $actor->esAprendiz() ? [$actor->id] : [])) as $pl) {
            $ev[] = self::evento('plan-' . $pl['id'], 'Vence plan: ' . $pl['codigo'] . ($actor->esAprendiz() ? '' : ' · ' . $pl['aprendiz']),
                $pl['fecha_limite'], '#ef4444', 'Plan de mejoramiento', '/mejoramiento?estado=vigente',
                ['ficha' => $pl['numero_ficha'], 'ra' => $pl['codigo'], 'aprendiz' => $actor->esAprendiz() ? null : $pl['aprendiz']]);
        }

        if (!$actor->esCoordinador()) {
            // Los juicios emitidos: el instructor, los suyos; el aprendiz, los que recibió.
            [$wJ, $pJ] = $actor->esInstructor() ? ['e.instructor_id = ?', [$actor->id]] : ['ap.usuario_id = ?', [$actor->id]];
            foreach ($this->filas("
                SELECT e.id, e.fecha_evaluacion, e.concepto, ra.codigo, u.nombre AS aprendiz, f.numero_ficha
                  FROM evaluaciones e JOIN resultados_aprendizaje ra ON ra.id = e.resultado_aprendizaje_id
                  JOIN aprendices ap ON ap.id = e.aprendiz_id JOIN usuarios u ON u.id = ap.usuario_id JOIN fichas f ON f.id = e.ficha_id
                 WHERE e.concepto IN ('A','D') AND e.fecha_evaluacion BETWEEN ? AND ? AND $wJ
                 ORDER BY e.fecha_evaluacion LIMIT 300", array_merge($rango, $pJ)) as $j) {
                $ev[] = self::evento('juicio-' . $j['id'], $j['concepto'] . ' · ' . $j['codigo'] . ($actor->esInstructor() ? ' · ' . $j['aprendiz'] : ''),
                    $j['fecha_evaluacion'], $j['concepto'] === 'A' ? '#22c55e' : '#dc2626', 'Juicio ' . $j['concepto'], '/evaluaciones',
                    ['ficha' => $j['numero_ficha'], 'ra' => $j['codigo'], 'aprendiz' => $actor->esInstructor() ? $j['aprendiz'] : null]);
            }
        }
        return $ev;
    }

    public function crearEvento(string $titulo, ?string $descripcion, string $fecha, int $fichaId, int $creadoPor): int {
        $this->db->prepare("INSERT INTO eventos_calendario (titulo, descripcion, fecha, ficha_id, creado_por) VALUES (?, ?, ?, ?, ?)")
                 ->execute([$titulo, $descripcion, $fecha, $fichaId, $creadoPor]);
        return (int)$this->db->lastInsertId();
    }

    public function findEvento(int $id): ?array {
        $st = $this->db->prepare("SELECT ec.*, f.numero_ficha FROM eventos_calendario ec JOIN fichas f ON f.id = ec.ficha_id WHERE ec.id = ?");
        $st->execute([$id]);
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function eliminarEvento(int $id): void {
        $this->db->prepare("DELETE FROM eventos_calendario WHERE id = ?")->execute([$id]);
    }

    /** Usuarios de los aprendices en formación de una ficha (para avisarles). */
    public function usuariosDeFicha(int $fichaId): array {
        $st = $this->db->prepare("SELECT usuario_id FROM aprendices WHERE ficha_id = ? AND estado IN ('matriculado','suspendido','etapa_practica')");
        $st->execute([$fichaId]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }
}
