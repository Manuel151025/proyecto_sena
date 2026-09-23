<?php
declare(strict_types=1);

namespace Tests\Integration;

use Core\Support\Enums;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Tests\CasoConBaseDeDatos;

/**
 * Invariantes del esquema.
 *
 * El proyecto tiene tres fuentes de esquema que no coinciden entre sí
 * (install.php, el volcado .sql y las migraciones), y de esa divergencia
 * salió que `configuraciones_sistema` no existiera aunque el código la
 * consultara. Estas pruebas comprueban contra la base real lo que el
 * código da por hecho.
 */
final class EsquemaTest extends CasoConBaseDeDatos {

    // =================================================================
    // TABLAS QUE EL CÓDIGO NECESITA
    // =================================================================

    #[DataProvider('tablasRequeridas')]
    #[TestDox('la tabla que el código consulta existe de verdad')]
    public function testTablaExiste(string $tabla): void {
        $this->assertNotFalse(
            $this->db->query("SELECT 1 FROM `$tabla` LIMIT 1"),
            "el código consulta `$tabla` pero no está en la base de datos"
        );
    }

    public static function tablasRequeridas(): array {
        return array_map(
            static fn($t) => [$t],
            [
                'usuarios', 'aprendices', 'fichas', 'programas', 'competencias',
                'resultados_aprendizaje', 'evaluaciones', 'historial_evaluaciones',
                'evidencias', 'retroalimentacion', 'actividades', 'proyectos',
                'fases_proyecto', 'logs_sistema', 'notificaciones', 'password_resets',
                // Estas tres faltaban en alguna de las fuentes de esquema:
                'asignaciones', 'eventos_calendario', 'configuraciones_sistema',
                // Añadida para el control de fuerza bruta:
                'intentos_acceso',
            ]
        );
    }

    // =================================================================
    // ENUMS
    // =================================================================

    /**
     * `Enums.php` duplica en PHP los valores del esquema para poder validar
     * antes de tocar la base. Si las dos listas se separan, el validador
     * rechaza valores legítimos y el fallo aparece como "no puedo guardar"
     * sin ninguna explicación.
     */
    #[TestDox('Enums.php coincide exactamente con los ENUM del esquema')]
    public function testEnumsCoincidenConElEsquema(): void {
        $enBase = [];
        $stmt = $this->db->query("
            SELECT TABLE_NAME AS t, COLUMN_NAME AS c, COLUMN_TYPE AS ct
              FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND DATA_TYPE = 'enum'
        ");
        foreach ($stmt as $r) {
            preg_match_all("/'((?:[^']|'')*)'/", $r['ct'], $m);
            $enBase[$r['t'] . '.' . $r['c']] = array_map(
                static fn($v) => str_replace("''", "'", $v),
                $m[1]
            );
        }

        $enPhp = Enums::mapaEsquema();

        $this->assertSame(
            [],
            array_keys(array_diff_key($enBase, $enPhp)),
            'hay columnas ENUM en el esquema que Enums.php no declara'
        );
        $this->assertSame(
            [],
            array_keys(array_diff_key($enPhp, $enBase)),
            'Enums.php declara columnas que ya no existen'
        );

        foreach ($enBase as $columna => $valores) {
            $this->assertSame(
                $valores,
                $enPhp[$columna],
                "los valores de $columna no coinciden entre el esquema y Enums.php"
            );
        }
    }

    /**
     * Sin STRICT_TRANS_TABLES, un ENUM inventado se guardaba como cadena
     * vacía y una fecha ilegible como '0000-00-00', sin lanzar error. Se
     * activa en la conexión, no en my.cnf, para que la garantía viaje con
     * el proyecto.
     */
    #[TestDox('la conexión fuerza el modo estricto de MariaDB')]
    public function testModoEstrictoActivo(): void {
        $modo = (string)$this->db->query("SELECT @@session.sql_mode")->fetchColumn();
        $this->assertStringContainsString('STRICT_TRANS_TABLES', $modo,
            'sin modo estricto, la base acepta en silencio lo que no cabe');
    }

    #[TestDox('la base rechaza un valor fuera del ENUM')]
    public function testBaseRechazaEnumInvalido(): void {
        $this->expectException(\PDOException::class);
        $this->db->prepare("UPDATE fichas SET estado = ? WHERE id = ?")
                 ->execute(['estado_inventado', $this->idFicha()]);
    }

    #[TestDox('la base rechaza una fecha ilegible')]
    public function testBaseRechazaFechaInvalida(): void {
        $this->expectException(\PDOException::class);
        $this->db->prepare("UPDATE fichas SET fecha_inicio = ? WHERE id = ?")
                 ->execute(['no-es-fecha', $this->idFicha()]);
    }

    #[TestDox('la base rechaza un texto que no cabe en la columna')]
    public function testBaseRechazaTextoLargo(): void {
        $this->expectException(\PDOException::class);
        $this->db->prepare("UPDATE fichas SET numero_ficha = ? WHERE id = ?")
                 ->execute([str_repeat('9', 100), $this->idFicha()]);
    }

    // =================================================================
    // INTEGRIDAD REFERENCIAL
    // =================================================================

    #[TestDox('no hay evaluaciones huérfanas')]
    public function testSinEvaluacionesHuerfanas(): void {
        $huerfanas = (int)$this->db->query("
            SELECT COUNT(*) FROM evaluaciones e
             WHERE NOT EXISTS (SELECT 1 FROM aprendices a WHERE a.id = e.aprendiz_id)
                OR NOT EXISTS (SELECT 1 FROM resultados_aprendizaje r WHERE r.id = e.resultado_aprendizaje_id)
                OR NOT EXISTS (SELECT 1 FROM fichas f WHERE f.id = e.ficha_id)
        ")->fetchColumn();

        $this->assertSame(0, $huerfanas, 'hay evaluaciones que apuntan a registros inexistentes');
    }

    #[TestDox('no hay historial apuntando a evaluaciones borradas')]
    public function testSinHistorialHuerfano(): void {
        $huerfano = (int)$this->db->query("
            SELECT COUNT(*) FROM historial_evaluaciones h
             WHERE NOT EXISTS (SELECT 1 FROM evaluaciones e WHERE e.id = h.evaluacion_id)
                OR NOT EXISTS (SELECT 1 FROM usuarios u WHERE u.id = h.usuario_id)
        ")->fetchColumn();

        $this->assertSame(0, $huerfano);
    }

    #[TestDox('cada aprendiz tiene como mucho una evaluación por RAP')]
    public function testSinEvaluacionesDuplicadas(): void {
        $duplicadas = (int)$this->db->query("
            SELECT COUNT(*) FROM (
                SELECT aprendiz_id, resultado_aprendizaje_id
                  FROM evaluaciones
                 GROUP BY aprendiz_id, resultado_aprendizaje_id
                HAVING COUNT(*) > 1
            ) t
        ")->fetchColumn();

        $this->assertSame(0, $duplicadas, 'hay RAP evaluados dos veces para el mismo aprendiz');
    }

    // =================================================================
    // TRAZABILIDAD (RNF02)
    // =================================================================

    /**
     * La invariante central del sistema: todo juicio emitido tiene que
     * tener al menos una fila de historial. Si esta prueba falla, algún
     * camino de escritura ha vuelto a saltarse el servicio.
     */
    #[TestDox('todo juicio emitido tiene trazabilidad')]
    public function testCoberturaDeTrazabilidad(): void {
        $sinHistorial = (int)$this->db->query("
            SELECT COUNT(*) FROM evaluaciones e
             WHERE e.concepto IN ('A','D')
               AND NOT EXISTS (SELECT 1 FROM historial_evaluaciones h WHERE h.evaluacion_id = e.id)
        ")->fetchColumn();

        $this->assertSame(
            0,
            $sinHistorial,
            "$sinHistorial juicios emitidos no tienen historial: algún camino escribe sin pasar por EvaluacionService"
        );
    }

    #[TestDox('las evaluaciones pendientes no generan historial de relleno')]
    public function testPendientesSinHistorialDeRelleno(): void {
        $conRuido = (int)$this->db->query("
            SELECT COUNT(*) FROM evaluaciones e
              JOIN historial_evaluaciones h ON h.evaluacion_id = e.id
             WHERE e.concepto = 'pendiente'
               AND h.motivo LIKE 'Registro reconstruido%'
        ")->fetchColumn();

        $this->assertSame(0, $conRuido);
    }

    // =================================================================
    // ÍNDICES
    // =================================================================

    #[DataProvider('indicesEsperados')]
    #[TestDox('existe el índice que sostiene una consulta frecuente')]
    public function testIndiceExiste(string $tabla, array $columnas, string $para): void {
        $porNombre = [];
        foreach ($this->db->query("SHOW INDEX FROM `$tabla`") as $i) {
            $porNombre[$i['Key_name']][(int)$i['Seq_in_index']] = $i['Column_name'];
        }

        $encontrado = false;
        foreach ($porNombre as $partes) {
            ksort($partes);
            if (array_slice(array_values($partes), 0, count($columnas)) === $columnas) {
                $encontrado = true;
                break;
            }
        }

        $this->assertTrue($encontrado, "falta el índice $tabla(" . implode(',', $columnas) . ") para: $para");
    }

    public static function indicesEsperados(): array {
        return [
            'recuento de matriculados' => ['aprendices', ['ficha_id', 'estado'], 'recuento de aprendices por ficha'],
            'matriz de seguimiento'    => ['evaluaciones', ['ficha_id', 'concepto'], 'matriz y planes de mejoramiento'],
            'regla de acceso'          => ['competencias', ['es_etapa_practica'], 'autoridad del instructor'],
            'trazabilidad'             => ['historial_evaluaciones', ['evaluacion_id'], 'reporte RNF02'],
            'correo único'             => ['usuarios', ['email'], 'identidad del usuario'],
        ];
    }

    /**
     * La columna sustituyó a un `LIKE` sobre el nombre de la competencia.
     * Si desapareciera, la regla de acceso volvería a depender de cómo esté
     * escrito un campo de texto libre.
     */
    #[TestDox('competencias.es_etapa_practica existe y está poblada')]
    public function testColumnaEtapaPractica(): void {
        $columna = $this->db->query("SHOW COLUMNS FROM competencias LIKE 'es_etapa_practica'")->fetch();
        $this->assertNotFalse($columna, 'la regla de acceso volvería a depender de un LIKE sobre texto libre');

        // Coherencia: lo que el nombre dice y lo que la columna marca.
        $incoherentes = (int)$this->db->query("
            SELECT COUNT(*) FROM competencias
             WHERE (nombre LIKE '%ETAPA PRÁCTICA%' OR nombre LIKE '%ETAPA PRACTICA%')
               AND es_etapa_practica = 0
        ")->fetchColumn();

        $this->assertSame(0, $incoherentes,
            'hay competencias que se llaman "etapa práctica" pero no están marcadas como tal');
    }

    // =================================================================
    // MOTOR Y CODIFICACIÓN
    // =================================================================

    #[TestDox('todas las tablas usan InnoDB: sin transacciones no hay atomicidad')]
    public function testMotorInnoDb(): void {
        $otras = $this->db->query("
            SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND ENGINE <> 'InnoDB' AND TABLE_TYPE = 'BASE TABLE'
        ")->fetchAll(PDO::FETCH_COLUMN);

        $this->assertSame([], $otras, 'estas tablas no soportan transacciones: ' . implode(', ', $otras));
    }

    #[TestDox('la codificación admite tildes y eñes en todas las tablas')]
    public function testCodificacionUtf8mb4(): void {
        $otras = $this->db->query("
            SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_COLLATION NOT LIKE 'utf8mb4%'
               AND TABLE_TYPE = 'BASE TABLE'
        ")->fetchAll(PDO::FETCH_COLUMN);

        $this->assertSame([], $otras, 'tablas sin utf8mb4: ' . implode(', ', $otras));
    }
}
