<?php
declare(strict_types=1);

namespace Core\Services;

use Core\Database;
use PDO;

/**
 * Única fuente de verdad para "¿este instructor tiene autoridad sobre este
 * aprendiz/resultado de aprendizaje?". Un instructor puede tener relación
 * con un aprendiz por tres vías: ser el instructor líder de su ficha, tener
 * una asignación por competencia (tabla asignaciones), o ser su instructor
 * de seguimiento individual (aprendices.instructor_seguimiento_id, propio
 * de etapa práctica).
 *
 * Antes de esta clase, esta misma regla estaba copiada de forma divergente
 * en SeguimientoModel y RetroalimentacionModel: la copia de Retroalimentacion
 * solo validaba el instructor líder, dejando fuera instructores asignados
 * por competencia o por seguimiento individual.
 */
class InstructorAccessService {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Subconsulta con los ids de las fichas en las que un instructor tiene
     * alguna autoridad: líder, asignación por competencia o seguimiento de
     * alguno de sus aprendices. Espera TRES parámetros con el id del
     * instructor.
     *
     * Había seis copias de esta lista, cada una con su criterio: la de
     * actividades solo reconocía al líder (un instructor asignado a una
     * competencia no podía programar actividades de la ficha), la de
     * evaluaciones ignoraba el seguimiento de etapa práctica y las demás
     * reconocían las tres vías. Un mismo instructor veía fichas distintas
     * según la pantalla.
     *
     * Uso: "... WHERE f.id IN (" . InstructorAccessService::sqlFichasDelInstructor() . ")"
     */
    public static function sqlFichasDelInstructor(): string {
        return "SELECT fi.id FROM fichas fi WHERE fi.instructor_id = ?
                UNION SELECT asg.ficha_id FROM asignaciones asg WHERE asg.instructor_id = ?
                UNION SELECT apx.ficha_id FROM aprendices apx
                       WHERE apx.instructor_seguimiento_id = ? AND apx.ficha_id IS NOT NULL";
    }

    /** @return int[] ids de las fichas del instructor */
    public function fichasDelInstructor(int $instructorId): array {
        $stmt = $this->db->prepare(self::sqlFichasDelInstructor());
        $stmt->execute([$instructorId, $instructorId, $instructorId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function tieneAccesoFicha(int $fichaId, int $instructorId): bool {
        if ($fichaId <= 0) {
            return false;
        }
        return in_array($fichaId, $this->fichasDelInstructor($instructorId), true);
    }

    /**
     * ¿Puede el actor gestionar (escribir en) esta ficha? El coordinador,
     * todas; el instructor, las suyas; el aprendiz, ninguna.
     */
    public function puedeGestionarFicha(\Core\Support\Actor $actor, int $fichaId): bool {
        if ($actor->esCoordinador()) {
            return $fichaId > 0;
        }
        return $actor->esInstructor() && $this->tieneAccesoFicha($fichaId, $actor->id);
    }

    /**
     * ¿El instructor tiene alguna relación con este aprendiz (ficha líder,
     * asignación por cualquier competencia, o seguimiento individual)?
     * Usado para acciones que no dependen de una competencia específica,
     * como registrar retroalimentación general.
     */
    public function tieneAccesoAprendiz(int $aprendizId, int $instructorId): bool {
        $stmt = $this->db->prepare("
            SELECT 1 FROM aprendices ap
            JOIN fichas f ON ap.ficha_id = f.id
            LEFT JOIN asignaciones asg ON asg.ficha_id = f.id
            WHERE ap.id = ? AND (f.instructor_id = ? OR asg.instructor_id = ? OR ap.instructor_seguimiento_id = ?)
            LIMIT 1
        ");
        $stmt->execute([$aprendizId, $instructorId, $instructorId, $instructorId]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * ¿El instructor tiene autoridad para calificar/evaluar este Resultado de
     * Aprendizaje específico de este aprendiz en esta ficha? Considera:
     * asignación por competencia, instructor líder de la ficha (salvo
     * competencias de Etapa Práctica), o instructor de seguimiento en
     * Etapa Práctica.
     */
    public function tieneAccesoResultadoAprendizaje(int $raId, int $aprendizId, int $fichaId, int $instructorId): bool {
        $stmt = $this->db->prepare("
            SELECT 1 FROM fichas f
            JOIN resultados_aprendizaje ra ON ra.id = ?
            JOIN competencias c ON ra.competencia_id = c.id
            JOIN aprendices ap ON ap.id = ?
            WHERE f.id = ? AND (" . self::CONDICION_ACCESO . ")
        ");
        $stmt->execute([$raId, $aprendizId, $fichaId, $instructorId, $instructorId, $instructorId]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * La misma autoridad, pero partiendo de una evaluación ya existente.
     *
     * Existe porque hay dos formas de llegar a la pregunta: con el RAP, el
     * aprendiz y la ficha por separado (al calificar desde /seguimiento) o
     * con el id de una evaluación (al calificar una evidencia o editar un
     * juicio). Antes cada camino traía su propia copia del SQL.
     */
    public function tieneAccesoEvaluacion(int $evaluacionId, int $instructorId): bool {
        $stmt = $this->db->prepare("
            SELECT 1
              FROM evaluaciones e
              JOIN resultados_aprendizaje ra ON ra.id = e.resultado_aprendizaje_id
              JOIN competencias c            ON c.id  = ra.competencia_id
              JOIN fichas f                  ON f.id  = e.ficha_id
              JOIN aprendices ap             ON ap.id = e.aprendiz_id
             WHERE e.id = ? AND (" . self::CONDICION_ACCESO . ")
        ");
        $stmt->execute([$evaluacionId, $instructorId, $instructorId, $instructorId]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * La condición en crudo, para las consultas que no preguntan "¿puede?"
     * sino que filtran un listado por lo que ese instructor puede ver.
     *
     * El listado de /evaluaciones tenía su propia copia dentro del WHERE, y
     * el de /mejoramiento otra. Al compartir esta constante, un cambio en
     * la regla alcanza a la vez a quien pregunta por un permiso y a quien
     * enumera lo permitido, que es donde más fácil era que divergieran.
     *
     * Requiere en el ámbito los alias `f`, `c` y `ap`, y tres parámetros
     * con el id del instructor (ver CONDICION_ACCESO).
     */
    public static function sqlCondicionAcceso(): string {
        return self::CONDICION_ACCESO;
    }

    /**
     * Condición que decide la autoridad de un instructor sobre un RAP
     * concreto de un aprendiz. Vive aquí como constante para que las
     * consultas que la necesitan la compartan en vez de copiarla.
     *
     * Espera en el ámbito los alias `f` (ficha), `c` (competencia) y `ap`
     * (aprendiz), y tres parámetros con el id del instructor, en este orden:
     * asignación por competencia, instructor líder, instructor de seguimiento.
     *
     * Las tres vías, en el orden en que se evalúan:
     *
     *  1. Tiene la competencia asignada en esa ficha. Manda sobre el resto.
     *  2. Es el instructor líder de la ficha, la competencia NO es de etapa
     *     práctica y nadie tiene esa competencia asignada. El líder cubre lo
     *     que no se ha repartido.
     *  3. La competencia es de etapa práctica y él es el instructor de
     *     seguimiento de ese aprendiz concreto.
     *
     * El paso de `c.nombre LIKE '%ETAPA PRÁCTICA%'` a `c.es_etapa_practica`
     * no es cosmético: antes el permiso dependía de cómo estuviera escrito
     * un campo de texto libre, y una competencia mal tecleada cambiaba en
     * silencio quién podía poner la nota.
     */
    private const CONDICION_ACCESO = "
                EXISTS (
                    SELECT 1 FROM asignaciones asg
                     WHERE asg.ficha_id = f.id
                       AND asg.competencia_id = c.id
                       AND asg.instructor_id = ?
                )
                OR (
                    f.instructor_id = ?
                    AND c.es_etapa_practica = 0
                    AND NOT EXISTS (
                        SELECT 1 FROM asignaciones asg
                         WHERE asg.ficha_id = f.id
                           AND asg.competencia_id = c.id
                    )
                )
                OR (
                    c.es_etapa_practica = 1
                    AND ap.instructor_seguimiento_id = ?
                )
    ";
}
