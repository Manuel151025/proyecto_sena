<?php
declare(strict_types=1);

namespace Core\Importacion;

use Core\Formularios\UsuarioFormulario;
use Core\Models\UsuarioModel;
use Core\Services\Auditoria;
use Core\Support\Actor;
use Core\Support\PoliticaContrasena;
use Core\Support\Validador;
use PDO;

/**
 * Importación masiva de cuentas: nombre, correo y rol.
 *
 * Los aprendices se importan mejor desde Matrículas, que además crea su
 * matrícula en la ficha; aquí se admiten para cuentas sin ficha todavía.
 */
final class ImportadorUsuarios extends Importador {
    public function __construct(private ?PDO $db = null) {}

    public function clave(): string { return 'usuarios'; }
    public function titulo(): string { return 'usuarios'; }
    public function maxFilas(): int { return 1000; }

    public function columnas(): array {
        return [
            'nombre' => ['etiqueta' => 'Nombre completo', 'alias' => ['nombres', 'nombre_completo', 'nombres_y_apellidos'], 'obligatorio' => true],
            'email'  => ['etiqueta' => 'Correo', 'alias' => ['correo', 'correo_electronico', 'e_mail', 'mail'], 'obligatorio' => true],
            'rol'    => ['etiqueta' => 'Rol', 'alias' => ['perfil', 'tipo'], 'obligatorio' => true,
                         'ayuda' => 'coordinador, instructor o aprendiz'],
        ];
    }

    public function ejemplo(): array {
        return ['nombre' => 'MARÍA FERNANDA LÓPEZ', 'email' => 'mflopez@sena.edu.co', 'rol' => 'instructor'];
    }

    public function validarFila(array $fila, Actor $actor, array $contexto): array {
        $fila['rol'] = mb_strtolower(trim($fila['rol']), 'UTF-8');
        $v = new Validador($fila + ['avatar_color' => '']);
        $d = UsuarioFormulario::validar($v);
        $avisos = [];
        if (!$v->hayErrores() && (new UsuarioModel($this->db))->existeEmail($d['email'])) {
            $avisos[] = 'El correo ya tiene cuenta: se omitirá.';
        }
        return ['datos' => $d, 'errores' => $v->errores(), 'avisos' => $avisos, 'clave' => $d['email']];
    }

    public function guardar(array $datos, Actor $actor, array $contexto): array {
        $colores = UsuarioFormulario::COLORES;
        $filas = [];
        foreach ($datos as $i => $d) {
            $temporal = PoliticaContrasena::temporal();
            $d['avatar_color'] = $colores[$i % count($colores)];
            $d['temporal'] = $temporal;
            $d['hash'] = password_hash($temporal, PASSWORD_DEFAULT);
            $filas[] = $d;
        }
        $r = (new UsuarioModel($this->db))->importar($filas);
        (new Auditoria($this->db))->operacion($actor, 'Importar', 'Usuarios', 'usuarios', null,
            count($r['insertados']) . ' cuentas creadas, ' . count($r['omitidos']) . ' omitidas');
        return [
            'creados'      => count($r['insertados']),
            'omitidos'     => count($r['omitidos']),
            // Solo de las cuentas creadas: la de una omitida no serviría.
            'credenciales' => array_map(static fn($u) => ['nombre' => $u['nombre'], 'email' => $u['email'], 'password' => $u['temporal']], $r['insertados']),
        ];
    }
}
