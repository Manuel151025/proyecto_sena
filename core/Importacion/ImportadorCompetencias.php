<?php
declare(strict_types=1);

namespace Core\Importacion;

use Core\Database;
use Core\Formularios\CompetenciaFormulario;
use Core\Models\CompetenciasModel;
use Core\Services\Auditoria;
use Core\Services\EvaluacionesSyncService;
use Core\Support\Actor;
use Core\Support\Transaccion;
use Core\Support\Validador;
use PDO;

/** Importación masiva de competencias, identificando el programa por su código. */
final class ImportadorCompetencias extends Importador {
    private PDO $db;
    /** @var array<string,int> código de programa en mayúsculas => id */
    private array $programas = [];

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
        foreach ($this->db->query("SELECT id, codigo FROM programas") as $p) {
            $this->programas[mb_strtoupper(trim((string)$p['codigo']), 'UTF-8')] = (int)$p['id'];
        }
    }

    public function clave(): string { return 'competencias'; }
    public function titulo(): string { return 'competencias'; }

    public function columnas(): array {
        return [
            'codigo_programa' => ['etiqueta' => 'Código del programa', 'alias' => ['programa', 'cod_programa'], 'obligatorio' => true],
            'codigo'          => ['etiqueta' => 'Código de la competencia', 'alias' => ['codigo_competencia', 'cod_competencia'], 'obligatorio' => true],
            'nombre'          => ['etiqueta' => 'Nombre', 'alias' => ['competencia', 'denominacion', 'nombre_competencia'], 'obligatorio' => true],
            'horas'           => ['etiqueta' => 'Horas', 'alias' => ['duracion', 'duracion_horas'], 'obligatorio' => true],
            'descripcion'     => ['etiqueta' => 'Descripción', 'alias' => []],
            'etapa_practica'  => ['etiqueta' => 'Etapa práctica (sí/no)', 'alias' => ['es_etapa_practica'], 'ayuda' => 'Déjelo vacío o "no" salvo la competencia de etapa productiva'],
        ];
    }

    public function ejemplo(): array {
        return ['codigo_programa' => '228118', 'codigo' => '220501094', 'nombre' => 'ESTABLECER REQUISITOS DE LA SOLUCIÓN DE SOFTWARE',
                'horas' => '48', 'descripcion' => '', 'etapa_practica' => 'no'];
    }

    public function validarFila(array $fila, Actor $actor, array $contexto): array {
        $codProg = mb_strtoupper(trim($fila['codigo_programa']), 'UTF-8');
        $etapa = mb_strtolower(trim($fila['etapa_practica']), 'UTF-8');
        $v = new Validador([
            'programa_id' => (string)($this->programas[$codProg] ?? ''),
            'codigo' => $fila['codigo'], 'nombre' => $fila['nombre'], 'horas' => $fila['horas'],
            'descripcion' => $fila['descripcion'], 'estado' => 'activo',
            'es_etapa_practica' => in_array($etapa, ['si', 'sí', '1', 'x', 'true'], true) ? '1' : '',
        ]);
        $d = CompetenciaFormulario::validar($v);
        $errores = $v->errores();
        if ($codProg !== '' && !isset($this->programas[$codProg])) {
            $errores = array_values(array_filter($errores, static fn($e) => !str_starts_with($e, 'El programa')));
            $errores[] = "No existe un programa con el código $codProg.";
        }
        $avisos = [];
        if ($errores === [] && (new CompetenciasModel($this->db))->idsPorCodigo($d['codigo'], $d['programa_id']) !== []) {
            $avisos[] = 'Ya existe en ese programa: se omitirá.';
        }
        return ['datos' => $d, 'errores' => $errores, 'avisos' => $avisos, 'clave' => $d['programa_id'] . '|' . $d['codigo']];
    }

    public function guardar(array $datos, Actor $actor, array $contexto): array {
        return Transaccion::ejecutar($this->db, function () use ($datos, $actor) {
            $modelo = new CompetenciasModel($this->db);
            $creadas = 0;
            $programas = [];
            foreach ($datos as $d) {
                if ($modelo->crearSiNoExiste($d)) {
                    $creadas++;
                    $programas[$d['programa_id']] = true;
                }
            }
            (new Auditoria($this->db))->operacion($actor, 'Importar', 'Competencias', 'competencias', null,
                "$creadas competencias importadas");
            return ['creados' => $creadas, 'omitidos' => count($datos) - $creadas];
        });
    }
}
