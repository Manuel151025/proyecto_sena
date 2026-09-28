<?php
declare(strict_types=1);

namespace Core\Importacion;

use Core\Database;
use Core\Formularios\CompetenciaFormulario;
use Core\Models\ResultadosAprendizajeModel;
use Core\Services\Auditoria;
use Core\Services\EvaluacionesSyncService;
use Core\Support\Actor;
use Core\Support\Transaccion;
use Core\Support\Validador;
use PDO;

/**
 * Importación masiva de resultados de aprendizaje.
 *
 * La competencia se identifica por su código y, si ese código se repite en
 * varios programas (competencias transversales), también por el del
 * programa. El importador anterior buscaba solo por código de competencia
 * y, ante la ambigüedad, colgaba el RAP de la primera que encontrara.
 */
final class ImportadorResultados extends Importador {
    private PDO $db;
    /** @var array<string, list<array{id:int, programa:string}>> */
    private array $competencias = [];

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
        foreach ($this->db->query("SELECT c.id, c.codigo, p.codigo AS programa FROM competencias c JOIN programas p ON p.id = c.programa_id") as $c) {
            $this->competencias[mb_strtoupper(trim((string)$c['codigo']), 'UTF-8')][] =
                ['id' => (int)$c['id'], 'programa' => mb_strtoupper(trim((string)$c['programa']), 'UTF-8')];
        }
    }

    public function clave(): string { return 'resultados'; }
    public function titulo(): string { return 'resultados de aprendizaje'; }
    public function maxFilas(): int { return 3000; }

    public function columnas(): array {
        return [
            'codigo_competencia' => ['etiqueta' => 'Código de la competencia', 'alias' => ['competencia', 'cod_competencia'], 'obligatorio' => true],
            'codigo'             => ['etiqueta' => 'Código del RAP', 'alias' => ['codigo_rap', 'cod_rap', 'rap'], 'obligatorio' => true],
            'denominacion'       => ['etiqueta' => 'Denominación', 'alias' => ['nombre', 'resultado', 'resultado_de_aprendizaje'], 'obligatorio' => true],
            'codigo_programa'    => ['etiqueta' => 'Código del programa', 'alias' => ['programa'], 'ayuda' => 'Solo si la competencia existe en varios programas'],
        ];
    }

    public function ejemplo(): array {
        return ['codigo_competencia' => '220501094', 'codigo' => '220501094-01',
                'denominacion' => 'CARACTERIZAR LOS PROCESOS DE LA ORGANIZACIÓN', 'codigo_programa' => ''];
    }

    public function validarFila(array $fila, Actor $actor, array $contexto): array {
        $cod = mb_strtoupper(trim($fila['codigo_competencia']), 'UTF-8');
        $prog = mb_strtoupper(trim($fila['codigo_programa']), 'UTF-8');
        $candidatas = $this->competencias[$cod] ?? [];
        if ($prog !== '') {
            $candidatas = array_values(array_filter($candidatas, static fn($c) => $c['programa'] === $prog));
        }
        $errores = [];
        $compId = '';
        if ($cod === '') {
            // El validador lo informará como obligatorio.
        } elseif ($candidatas === []) {
            $errores[] = "No existe la competencia $cod" . ($prog !== '' ? " en el programa $prog" : '') . '.';
        } elseif (count($candidatas) > 1) {
            $errores[] = "La competencia $cod existe en varios programas: indique codigo_programa.";
        } else {
            $compId = (string)$candidatas[0]['id'];
        }
        $v = new Validador(['competencia_id' => $compId, 'codigo' => $fila['codigo'], 'denominacion' => $fila['denominacion']]);
        $d = CompetenciaFormulario::validarRap($v);
        $errores = array_merge($errores, $compId === '' ? array_values(array_filter($v->errores(), static fn($e) => !str_starts_with($e, 'La competencia'))) : $v->errores());
        $avisos = [];
        if ($errores === []) {
            $st = $this->db->prepare("SELECT competencia_id FROM resultados_aprendizaje WHERE codigo = ?");
            $st->execute([$d['codigo']]);
            $existente = $st->fetchColumn();
            if ($existente !== false) {
                if ((int)$existente !== (int)$d['competencia_id']) {
                    $errores[] = "El código {$d['codigo']} ya está en uso en otra competencia.";
                } else {
                    $avisos[] = 'Ya existe: se omitirá.';
                }
            }
        }
        return ['datos' => $d, 'errores' => $errores, 'avisos' => $avisos, 'clave' => $d['codigo']];
    }

    public function guardar(array $datos, Actor $actor, array $contexto): array {
        return Transaccion::ejecutar($this->db, function () use ($datos, $actor) {
            $modelo = new ResultadosAprendizajeModel($this->db);
            $sync = new EvaluacionesSyncService($this->db);
            $creados = 0;
            $competencias = [];
            foreach ($datos as $d) {
                if ($modelo->crearSiNoExiste($d)) {
                    $creados++;
                    $competencias[(int)$d['competencia_id']] = true;
                }
            }
            // Los RAP nuevos llegan a los aprendices ya matriculados.
            $habilitadas = 0;
            foreach (array_keys($competencias) as $cid) {
                $habilitadas += $sync->sincronizar(['competencia_id' => $cid])['creadas'];
            }
            (new Auditoria($this->db))->operacion($actor, 'Importar', 'RAP', 'resultados_aprendizaje', null,
                "$creados RAP importados; $habilitadas evaluaciones pendientes habilitadas");
            return ['creados' => $creados, 'omitidos' => count($datos) - $creados,
                    'detalle' => $habilitadas > 0 ? ["Se habilitaron $habilitadas evaluaciones pendientes para aprendices ya matriculados."] : []];
        });
    }
}
