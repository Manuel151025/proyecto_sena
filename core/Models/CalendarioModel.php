<?php
declare(strict_types=1);

namespace Core\Models;

use Core\Database;
use PDO;

class CalendarioModel {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    public function getCoordinadorEvents(string $start, string $end): array {
        $events = [];

        // Inicios y fines de fichas
        $stmt = $this->db->prepare("
            SELECT f.id, f.numero_ficha, f.fecha_inicio, f.fecha_fin, f.estado,
                   f.cumplimiento_porcentaje, p.nombre as programa
            FROM fichas f
            JOIN programas p ON f.programa_id = p.id
            WHERE f.fecha_inicio BETWEEN ? AND ?
               OR f.fecha_fin    BETWEEN ? AND ?
        ");
        $stmt->execute([$start, $end, $start, $end]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
            $pct   = (float)$f['cumplimiento_porcentaje'];

            if ($f['fecha_inicio'] >= $start && $f['fecha_inicio'] <= $end) {
                $events[] = [
                    'id'       => 'ficha-inicio-' . $f['id'],
                    'title'    => '📋 Inicio Ficha #' . $f['numero_ficha'],
                    'start'    => $f['fecha_inicio'],
                    'color'    => '#39A900',
                    'textColor'=> '#fff',
                    'url'      => APP_URL . '/index.php/fichas/ver?id=' . $f['id'],
                    'extendedProps' => [
                        'tipo'     => 'Inicio de Ficha',
                        'programa' => $f['programa'],
                        'estado'   => $f['estado'],
                        'cumpl'    => $pct . '%',
                    ],
                ];
            }
            if ($f['fecha_fin'] && $f['fecha_fin'] >= $start && $f['fecha_fin'] <= $end) {
                $events[] = [
                    'id'       => 'ficha-fin-' . $f['id'],
                    'title'    => '🏁 Fin Ficha #' . $f['numero_ficha'],
                    'start'    => $f['fecha_fin'],
                    'color'    => '#6366f1',
                    'textColor'=> '#fff',
                    'url'      => APP_URL . '/index.php/fichas/ver?id=' . $f['id'],
                    'extendedProps' => [
                        'tipo'     => 'Fin de Ficha',
                        'programa' => $f['programa'],
                        'estado'   => $f['estado'],
                        'cumpl'    => $pct . '%',
                    ],
                ];
            }
        }

        // Evaluaciones D registradas
        $stmt = $this->db->prepare("
            SELECT e.fecha_evaluacion, u.nombre as aprendiz, f.numero_ficha, f.id as ficha_id,
                   ra.codigo as ra_codigo
            FROM evaluaciones e
            JOIN aprendices ap ON e.aprendiz_id = ap.id
            JOIN usuarios u    ON ap.usuario_id = u.id
            JOIN fichas f      ON e.ficha_id = f.id
            JOIN resultados_aprendizaje ra ON e.resultado_aprendizaje_id = ra.id
            WHERE e.concepto = 'D'
              AND e.fecha_evaluacion BETWEEN ? AND ?
            ORDER BY e.fecha_evaluacion
            LIMIT 100
        ");
        $stmt->execute([$start, $end]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ev) {
            $events[] = [
                'id'       => 'mejora-coord-' . $ev['ra_codigo'] . '-' . $ev['aprendiz'],
                'title'    => '⚠️ Plan mejora: ' . $ev['aprendiz'],
                'start'    => $ev['fecha_evaluacion'],
                'color'    => '#ef4444',
                'textColor'=> '#fff',
                'url'      => APP_URL . '/index.php/fichas/ver?id=' . $ev['ficha_id'],
                'extendedProps' => [
                    'tipo'   => 'Plan de Mejoramiento',
                    'ficha'  => '#' . $ev['numero_ficha'],
                    'ra'     => $ev['ra_codigo'],
                ],
            ];
        }

        return $events;
    }

    public function getInstructorEvents(int $user_id, string $start, string $end): array {
        $events = [];

        // Sus fichas (inicio y fin)
        $stmt = $this->db->prepare("
            SELECT DISTINCT f.id, f.numero_ficha, f.fecha_inicio, f.fecha_fin,
                   f.estado, f.cumplimiento_porcentaje, p.nombre as programa
            FROM fichas f
            JOIN programas p ON f.programa_id = p.id
            LEFT JOIN asignaciones asg ON asg.ficha_id = f.id AND asg.instructor_id = ?
            WHERE (f.instructor_id = ? OR asg.instructor_id = ?)
              AND (f.fecha_inicio BETWEEN ? AND ? OR f.fecha_fin BETWEEN ? AND ?)
        ");
        $stmt->execute([$user_id, $user_id, $user_id, $start, $end, $start, $end]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $f) {
            if ($f['fecha_inicio'] >= $start && $f['fecha_inicio'] <= $end) {
                $events[] = [
                    'id'       => 'inst-ficha-inicio-' . $f['id'],
                    'title'    => '📋 Inicio Ficha #' . $f['numero_ficha'],
                    'start'    => $f['fecha_inicio'],
                    'color'    => '#39A900',
                    'textColor'=> '#fff',
                    'url'      => APP_URL . '/index.php/fichas/ver?id=' . $f['id'],
                    'extendedProps' => [
                        'tipo'     => 'Inicio de Ficha',
                        'programa' => $f['programa'],
                        'estado'   => $f['estado'],
                    ],
                ];
            }
            if ($f['fecha_fin'] && $f['fecha_fin'] >= $start && $f['fecha_fin'] <= $end) {
                $events[] = [
                    'id'       => 'inst-ficha-fin-' . $f['id'],
                    'title'    => '🏁 Fin Ficha #' . $f['numero_ficha'],
                    'start'    => $f['fecha_fin'],
                    'color'    => '#6366f1',
                    'textColor'=> '#fff',
                    'url'      => APP_URL . '/index.php/fichas/ver?id=' . $f['id'],
                    'extendedProps' => [
                        'tipo'  => 'Fin de Ficha',
                        'programa' => $f['programa'],
                    ],
                ];
            }
        }

        // Evaluaciones que registró el instructor en el período
        $stmt = $this->db->prepare("
            SELECT e.fecha_evaluacion, e.concepto, u.nombre as aprendiz,
                   ra.codigo as ra_codigo, f.numero_ficha, f.id as ficha_id
            FROM evaluaciones e
            JOIN resultados_aprendizaje ra ON e.resultado_aprendizaje_id = ra.id
            JOIN aprendices ap ON e.aprendiz_id = ap.id
            JOIN usuarios u    ON ap.usuario_id = u.id
            JOIN fichas f      ON e.ficha_id = f.id
            WHERE e.instructor_id = ?
              AND e.concepto != 'pendiente'
              AND e.fecha_evaluacion BETWEEN ? AND ?
            ORDER BY e.fecha_evaluacion
            LIMIT 150
        ");
        $stmt->execute([$user_id, $start, $end]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ev) {
            $color = $ev['concepto'] === 'A' ? '#10b981' : '#ef4444';
            $emoji = $ev['concepto'] === 'A' ? '✅' : '⚠️';
            $events[] = [
                'id'       => 'eval-inst-' . $ev['ra_codigo'] . '-' . md5($ev['aprendiz'] . $ev['fecha_evaluacion']),
                'title'    => $emoji . ' ' . $ev['aprendiz'] . ' — ' . $ev['ra_codigo'],
                'start'    => $ev['fecha_evaluacion'],
                'color'    => $color,
                'textColor'=> '#fff',
                'url'      => APP_URL . '/index.php/evaluaciones?ficha_id=' . $ev['ficha_id'],
                'extendedProps' => [
                    'tipo'    => 'Evaluación (' . $ev['concepto'] . ')',
                    'ra'      => $ev['ra_codigo'],
                    'ficha'   => '#' . $ev['numero_ficha'],
                    'aprendiz'=> $ev['aprendiz'],
                ],
            ];
        }

        return $events;
    }

    public function getAprendizEvents(int $user_id, string $start, string $end): array {
        $events = [];

        // Obtener el aprendiz_id y proyecto_id de este usuario
        $stmtAp = $this->db->prepare("
            SELECT a.id as aprendiz_id, a.ficha_id, f.proyecto_id
            FROM aprendices a
            JOIN fichas f ON a.ficha_id = f.id
            WHERE a.usuario_id = ?
            LIMIT 1
        ");
        $stmtAp->execute([$user_id]);
        $ap = $stmtAp->fetch(PDO::FETCH_ASSOC);

        if ($ap) {
            $aprendiz_id = (int)$ap['aprendiz_id'];
            $proyecto_id = (int)($ap['proyecto_id'] ?? 0);

            // Fases del proyecto
            if ($proyecto_id > 0) {
                $stmt = $this->db->prepare("
                    SELECT nombre, fecha_inicio, fecha_fin, estado, numero_fase
                    FROM fases_proyecto
                    WHERE proyecto_id = ?
                      AND (fecha_inicio BETWEEN ? AND ? OR fecha_fin BETWEEN ? AND ?)
                ");
                $stmt->execute([$proyecto_id, $start, $end, $start, $end]);
                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fase) {
                    $colorFase = match($fase['estado']) {
                        'completada'  => '#39A900',
                        'en_ejecucion'=> '#3B82F6',
                        default       => '#9ca3af',
                    };
                    if ($fase['fecha_inicio'] >= $start && $fase['fecha_inicio'] <= $end) {
                        $events[] = [
                            'id'       => 'fase-inicio-' . $fase['numero_fase'],
                            'title'    => '🚀 Inicio: ' . $fase['nombre'],
                            'start'    => $fase['fecha_inicio'],
                            'color'    => $colorFase,
                            'textColor'=> '#fff',
                            'url'      => APP_URL . '/index.php/proyectos',
                            'extendedProps' => [
                                'tipo'   => 'Fase del Proyecto',
                                'estado' => $fase['estado'],
                            ],
                        ];
                    }
                    if ($fase['fecha_fin'] && $fase['fecha_fin'] >= $start && $fase['fecha_fin'] <= $end) {
                        $events[] = [
                            'id'       => 'fase-fin-' . $fase['numero_fase'],
                            'title'    => '🏁 Fin: ' . $fase['nombre'],
                            'start'    => $fase['fecha_fin'],
                            'color'    => '#6366f1',
                            'textColor'=> '#fff',
                            'url'      => APP_URL . '/index.php/proyectos',
                            'extendedProps' => [
                                'tipo'   => 'Fin de Fase',
                                'estado' => $fase['estado'],
                            ],
                        ];
                    }
                }
            }

            // Evaluaciones del aprendiz
            $stmt = $this->db->prepare("
                SELECT e.fecha_evaluacion, e.concepto, ra.codigo as ra_codigo,
                       ra.denominacion, u.nombre as instructor
                FROM evaluaciones e
                JOIN resultados_aprendizaje ra ON e.resultado_aprendizaje_id = ra.id
                LEFT JOIN usuarios u ON e.instructor_id = u.id
                WHERE e.aprendiz_id = ?
                  AND e.concepto != 'pendiente'
                  AND e.fecha_evaluacion BETWEEN ? AND ?
                ORDER BY e.fecha_evaluacion
                LIMIT 100
            ");
            $stmt->execute([$aprendiz_id, $start, $end]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $ev) {
                $color = $ev['concepto'] === 'A' ? '#10b981' : '#ef4444';
                $emoji = $ev['concepto'] === 'A' ? '✅' : '⚠️';
                $events[] = [
                    'id'       => 'eval-ap-' . $ev['ra_codigo'] . '-' . $ev['fecha_evaluacion'],
                    'title'    => $emoji . ' Eval: ' . $ev['ra_codigo'],
                    'start'    => $ev['fecha_evaluacion'],
                    'color'    => $color,
                    'textColor'=> '#fff',
                    'url'      => APP_URL . '/index.php/evaluaciones',
                    'extendedProps' => [
                        'tipo'       => 'Evaluación (' . $ev['concepto'] . ')',
                        'ra'         => $ev['ra_codigo'],
                        'instructor' => $ev['instructor'] ?? '—',
                    ],
                ];
            }
        }

        return $events;
    }
}
