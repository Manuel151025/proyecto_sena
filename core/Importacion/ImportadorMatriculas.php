<?php
declare(strict_types=1);

namespace Core\Importacion;

use Core\Database;
use Core\Formularios\MatriculaFormulario;
use Core\Formularios\UsuarioFormulario;
use Core\Models\AprendizModel;
use Core\Models\FichaModel;
use Core\Services\Auditoria;
use Core\Services\EvaluacionesSyncService;
use Core\Support\Actor;
use Core\Support\PoliticaContrasena;
use Core\Support\Transaccion;
use Core\Support\Validador;
use PDO;

/**
 * Matrícula masiva de aprendices en una ficha: crea la cuenta (con clave
 * temporal), la matrícula y sus evaluaciones pendientes.
 *
 * El importador anterior leía el CSV con `fgetcsv($h, 1000)`, sustituía en
 * silencio un tipo de documento o un género desconocidos por CC y O (el
 * dato quedaba mal sin que nadie lo supiera) y, si una sola fila fallaba,
 * seguía y reportaba el error mezclado con los éxitos.
 */
final class ImportadorMatriculas extends Importador {
    private PDO $db;

    public function __construct(?PDO $db = null) {
        $this->db = $db ?? Database::getConnection();
    }

    public function clave(): string { return 'matriculas'; }
    public function titulo(): string { return 'matrículas de aprendices'; }
    public function maxFilas(): int { return 500; }

    public function columnas(): array {
        return [
            'nombre'           => ['etiqueta' => 'Nombre completo', 'alias' => ['nombres', 'nombre_completo', 'aprendiz'], 'obligatorio' => true],
            'email'            => ['etiqueta' => 'Correo', 'alias' => ['correo', 'correo_electronico', 'mail'], 'obligatorio' => true],
            'tipo_documento'   => ['etiqueta' => 'Tipo de documento', 'alias' => ['tipo_doc', 'tipo'], 'ayuda' => 'CC, TI, CE, PEP o PA (por defecto CC)'],
            'numero_documento' => ['etiqueta' => 'Número de documento', 'alias' => ['documento', 'numero_doc', 'identificacion', 'cedula'], 'obligatorio' => true],
            'genero'           => ['etiqueta' => 'Género', 'alias' => ['sexo'], 'ayuda' => 'M, F u O'],
            'telefono'         => ['etiqueta' => 'Teléfono', 'alias' => ['celular', 'movil']],
            'ciudad'           => ['etiqueta' => 'Ciudad', 'alias' => ['municipio']],
            'fecha_nacimiento' => ['etiqueta' => 'Fecha de nacimiento', 'alias' => ['nacimiento'], 'ayuda' => 'AAAA-MM-DD'],
        ];
    }

    public function ejemplo(): array {
        return ['nombre' => 'LAURA CAMILA VARGAS', 'email' => 'lcvargas@soy.sena.edu.co', 'tipo_documento' => 'CC',
                'numero_documento' => '1117500123', 'genero' => 'F', 'telefono' => '3101234567', 'ciudad' => 'Florencia', 'fecha_nacimiento' => '2004-05-17'];
    }

    public function contexto(array $post, Actor $actor): array {
        $v = new Validador($post);
        $ficha = $v->id('ficha_id', 'La ficha de destino');
        $errores = $v->errores();
        if ($ficha > 0) {
            $f = (new AprendizModel($this->db))->datosFicha($ficha);
            if ($f === null) {
                $errores[] = 'La ficha de destino no existe.';
            } elseif ($f['estado'] === 'cierre') {
                $errores[] = "La ficha {$f['numero_ficha']} está en cierre: no admite matrículas nuevas.";
            }
        }
        return [['ficha_id' => $ficha], $errores];
    }

    /** Selector de ficha del formulario de subida. */
    public function opcionesFormulario(Actor $actor): array {
        $opciones = [];
        foreach ((new FichaModel($this->db))->getAll() as $f) {
            $opciones[(int)$f['id']] = 'Ficha ' . $f['numero_ficha'] . ' — ' . $f['programa'];
        }
        return ['campos' => [['nombre' => 'ficha_id', 'etiqueta' => 'Ficha de destino', 'opciones' => $opciones]]];
    }

    public function validarFila(array $fila, Actor $actor, array $contexto): array {
        $fila['tipo_documento'] = mb_strtoupper(trim($fila['tipo_documento']), 'UTF-8');
        $fila['genero'] = mb_strtoupper(trim($fila['genero']), 'UTF-8');
        $fila['email'] = mb_strtolower(trim($fila['email']), 'UTF-8');
        $fila['numero_documento'] = preg_replace('/[\s.]/', '', $fila['numero_documento']) ?? '';
        $v = new Validador($fila + ['ficha_id' => (string)$contexto['ficha_id'], 'instructor_seguimiento_id' => '']);
        $d = MatriculaFormulario::validar($v);
        $errores = $v->errores();
        if ($errores === []) {
            $m = new AprendizModel($this->db);
            if ($m->existeEmail($d['email'])) {
                $errores[] = "El correo {$d['email']} ya tiene cuenta.";
            }
            if ($m->existeDocumento($d['numero_documento'])) {
                $errores[] = "El documento {$d['numero_documento']} ya está matriculado.";
            }
        }
        // Clave compuesta: correo y documento no pueden repetirse en el archivo.
        return ['datos' => $d, 'errores' => $errores, 'clave' => $d['email'] . '|' . $d['numero_documento']];
    }

    public function permitido(Actor $actor): bool {
        return $actor->esCoordinador();
    }

    public function guardar(array $datos, Actor $actor, array $contexto): array {
        return Transaccion::ejecutar($this->db, function () use ($datos, $actor, $contexto) {
            $m = new AprendizModel($this->db);
            $sync = new EvaluacionesSyncService($this->db);
            $colores = UsuarioFormulario::COLORES;
            $credenciales = [];
            $omitidos = 0;
            foreach ($datos as $i => $d) {
                // Se revalida contra la base: entre la vista previa y la
                // confirmación otra persona pudo matricular el mismo documento.
                if ($m->existeEmail($d['email']) || $m->existeDocumento($d['numero_documento'])) {
                    $omitidos++;
                    continue;
                }
                $temporal = PoliticaContrasena::temporal();
                $id = $m->crear($d, password_hash($temporal, PASSWORD_DEFAULT), $colores[$i % count($colores)]);
                $sync->sincronizar(['aprendiz_id' => $id]);
                $credenciales[] = ['nombre' => $d['nombre'], 'email' => $d['email'], 'password' => $temporal];
            }
            (new Auditoria($this->db))->operacion($actor, 'Importar', 'Matrículas', 'aprendices', null,
                count($credenciales) . " aprendices matriculados en la ficha {$contexto['ficha_id']}");
            return ['creados' => count($credenciales), 'omitidos' => $omitidos, 'credenciales' => $credenciales];
        });
    }
}
