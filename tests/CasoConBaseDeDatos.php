<?php
declare(strict_types=1);

namespace Tests;

use Core\Database;
use PDO;

/**
 * Base de las pruebas que tocan la base de datos.
 *
 * Cada prueba corre dentro de una transacción que se revierte al terminar,
 * de modo que la suite puede ejecutarse contra la base de trabajo sin
 * dejar rastro y sin necesidad de una base de pruebas aparte.
 *
 * Límite conocido: el código que abre su propia transacción con
 * `beginTransaction()` —EvidenciasModel::calificarEvidencia y
 * JuiciosImportService— no puede ejecutarse dentro de otra, porque PDO no
 * anida. Esas pruebas heredan de aquí pero llaman a `sinTransaccion()` y
 * limpian a mano lo que crean.
 */
abstract class CasoConBaseDeDatos extends CasoDePrueba {
    protected PDO $db;

    /** Si esta prueba concreta gestiona su propia limpieza. */
    private bool $envuelta = true;

    protected function setUp(): void {
        parent::setUp();
        $this->db = Database::getConnection();

        if ($this->envuelta) {
            $this->db->beginTransaction();
        }
    }

    protected function tearDown(): void {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
        parent::tearDown();
    }

    /**
     * Desactiva la transacción envolvente para esta prueba. Debe llamarse
     * antes de que setUp la abra, es decir, desde la propia prueba no sirve:
     * se usa sobreescribiendo setUp y llamando a esto antes de parent.
     */
    protected function sinTransaccion(): void {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
        $this->envuelta = false;
    }

    // -----------------------------------------------------------------
    // SUJETOS REALES DE LA BASE
    // -----------------------------------------------------------------
    // Las pruebas trabajan con datos que ya existen en vez de sembrar los
    // suyos: así se comprueban también las consultas contra la forma real
    // que tienen los datos, que es donde aparecieron varios de los fallos.

    protected function idCoordinador(): int {
        return $this->unId("SELECT id FROM usuarios WHERE rol = 'coordinador' AND estado = 'activo' LIMIT 1");
    }

    protected function idInstructorConFicha(): int {
        return $this->unId("SELECT instructor_id FROM fichas WHERE instructor_id IS NOT NULL LIMIT 1");
    }

    /** Un instructor que no es líder de ninguna ficha: el "ajeno" de las pruebas de acceso. */
    protected function idInstructorAjeno(): int {
        return $this->unId("
            SELECT id FROM usuarios
             WHERE rol = 'instructor' AND estado = 'activo'
               AND id NOT IN (SELECT instructor_id FROM fichas WHERE instructor_id IS NOT NULL)
             LIMIT 1
        ");
    }

    protected function idUsuarioAprendiz(): int {
        return $this->unId("SELECT usuario_id FROM aprendices WHERE usuario_id IS NOT NULL LIMIT 1");
    }

    protected function idAprendiz(): int {
        return $this->unId("SELECT id FROM aprendices LIMIT 1");
    }

    protected function idFicha(): int {
        return $this->unId("SELECT id FROM fichas LIMIT 1");
    }

    protected function idEvaluacion(string $concepto = 'pendiente'): int {
        return $this->unId("SELECT id FROM evaluaciones WHERE concepto = " . $this->db->quote($concepto) . " LIMIT 1");
    }

    private function unId(string $sql): int {
        $v = $this->db->query($sql)->fetchColumn();
        if ($v === false || (int)$v === 0) {
            $this->markTestSkipped('No hay datos en la base para esta prueba: ' . $sql);
        }
        return (int)$v;
    }

    /** Cuenta filas con una condición, para comprobar efectos secundarios. */
    protected function contar(string $tabla, string $where = '1', array $params = []): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM `$tabla` WHERE $where");
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }
}
