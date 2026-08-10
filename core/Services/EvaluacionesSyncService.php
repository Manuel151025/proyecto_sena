<?php
declare(strict_types=1);

namespace Core\Services;

use Core\Database;
use PDO;
use Exception;

/**
 * Única fuente de verdad para "qué filas de `evaluaciones` deberían existir".
 *
 * Regla de negocio: todo aprendiz en formación debe tener una fila
 * 'pendiente' por cada RAP del programa de su ficha, de modo que el RAP
 * aparezca en /seguimiento y /evaluaciones y se pueda calificar.
 *
 * Antes esa regla solo se aplicaba en el momento de matricular
 * (`inicializarEvaluacionesAprendiz()`), lo que dejaba fuera el otro lado
 * del problema: los RAP creados o importados DESPUÉS de la matrícula no
 * llegaban a los aprendices ya matriculados. Este servicio cubre ambos
 * sentidos con la misma consulta, y por eso se invoca tanto al matricular
 * como al crear/importar RAP o al cambiar de programa una ficha.
 *
 * Es idempotente: solo crea lo que falta y nunca modifica una evaluación
 * existente (ni su concepto, ni su comentario, ni su fecha).
 */
class EvaluacionesSyncService {
    /**
     * Estados de aprendiz que cuentan como "en formación". 'desertado' y
     * 'egresado' quedan fuera a propósito: no se reabren expedientes
     * cerrados al importar estructura curricular nueva.
     */
    private const ESTADOS_EN_FORMACION = ['matriculado', 'suspendido', 'etapa_practica'];

    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Crea las filas 'pendiente' que falten.
     *
     * @param array $scope Acota el trabajo. Sin claves, revisa todo el
     *   sistema. Claves admitidas: 'aprendiz_id', 'ficha_id',
     *   'competencia_id', 'programa_id'.
     * @return array{creadas:int, omitidas_sin_instructor:int}
     */
    public function sincronizar(array $scope = []): array {
        [$where, $params] = $this->construirFiltro($scope);

        // `evaluaciones.instructor_id` es NOT NULL y tiene clave foránea a
        // `usuarios.id`, así que una ficha sin instructor líder no puede
        // generar filas: se excluye en el JOIN y se cuenta aparte, en vez de
        // dejar que el INSERT falle en bloque. (Hoy todas las fichas tienen
        // instructor, pero el esquema permite que no.)
        $desde = "
              FROM aprendices a
              JOIN fichas f                   ON f.id = a.ficha_id
              JOIN competencias c             ON c.programa_id = f.programa_id
              JOIN resultados_aprendizaje ra  ON ra.competencia_id = c.id
             WHERE a.estado IN ({$this->placeholdersEstados()})
               $where
               AND NOT EXISTS (
                     SELECT 1 FROM evaluaciones e
                      WHERE e.aprendiz_id = a.id
                        AND e.resultado_aprendizaje_id = ra.id
                   )
        ";
        $paramsBase = array_merge(self::ESTADOS_EN_FORMACION, $params);

        // Cuántas quedarían fuera por no tener instructor líder (informativo).
        $stmt = $this->db->prepare("SELECT COUNT(*) $desde AND (f.instructor_id IS NULL OR f.instructor_id = 0)");
        $stmt->execute($paramsBase);
        $sinInstructor = (int)$stmt->fetchColumn();

        // El INSERT ... SELECT se hace en una sola sentencia: recorrer los
        // RAP en PHP suponía miles de idas y venidas a la base en cada
        // importación.
        $stmt = $this->db->prepare("
            INSERT INTO evaluaciones
                (resultado_aprendizaje_id, aprendiz_id, instructor_id, ficha_id, concepto, comentario, fecha_evaluacion)
            SELECT ra.id, a.id, f.instructor_id, a.ficha_id, 'pendiente', NULL, NULL
            $desde
              AND f.instructor_id IS NOT NULL
              AND f.instructor_id <> 0
        ");
        $stmt->execute($paramsBase);

        return [
            'creadas' => $stmt->rowCount(),
            'omitidas_sin_instructor' => $sinInstructor,
        ];
    }

    /**
     * Cuenta lo que falta sin escribir nada. Útil para diagnóstico.
     *
     * @return array{faltantes:int, aprendices:int}
     */
    public function contarFaltantes(array $scope = []): array {
        [$where, $params] = $this->construirFiltro($scope);
        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS faltantes, COUNT(DISTINCT a.id) AS aprendices
              FROM aprendices a
              JOIN fichas f                   ON f.id = a.ficha_id
              JOIN competencias c             ON c.programa_id = f.programa_id
              JOIN resultados_aprendizaje ra  ON ra.competencia_id = c.id
             WHERE a.estado IN ({$this->placeholdersEstados()})
               $where
               AND NOT EXISTS (
                     SELECT 1 FROM evaluaciones e
                      WHERE e.aprendiz_id = a.id
                        AND e.resultado_aprendizaje_id = ra.id
                   )
        ");
        $stmt->execute(array_merge(self::ESTADOS_EN_FORMACION, $params));
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'faltantes' => (int)($row['faltantes'] ?? 0),
            'aprendices' => (int)($row['aprendices'] ?? 0),
        ];
    }

    private function placeholdersEstados(): string {
        return implode(',', array_fill(0, count(self::ESTADOS_EN_FORMACION), '?'));
    }

    /**
     * @return array{0:string, 1:array} Fragmento SQL y sus parámetros.
     */
    private function construirFiltro(array $scope): array {
        $where = '';
        $params = [];
        $columnas = [
            'aprendiz_id'    => 'a.id',
            'ficha_id'       => 'a.ficha_id',
            'competencia_id' => 'c.id',
            'programa_id'    => 'f.programa_id',
        ];
        foreach ($columnas as $clave => $columna) {
            if (isset($scope[$clave]) && (int)$scope[$clave] > 0) {
                $where .= " AND $columna = ?";
                $params[] = (int)$scope[$clave];
            }
        }
        $desconocidas = array_diff(array_keys($scope), array_keys($columnas));
        if ($desconocidas) {
            throw new Exception('Ámbito no reconocido para sincronizar evaluaciones: ' . implode(', ', $desconocidas));
        }
        return [$where, $params];
    }
}
