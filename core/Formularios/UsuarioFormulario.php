<?php
declare(strict_types=1);

namespace Core\Formularios;

use Core\Support\Enums;
use Core\Support\Validador;

/**
 * Reglas de entrada de un usuario. La contraseña no está aquí: la genera
 * el sistema (temporal, de un solo uso) y la cambia el propio usuario.
 */
final class UsuarioFormulario {
    public const MAX_NOMBRE = 150;
    public const MAX_EMAIL = 120;
    public const COLORES = ['#39A900', '#3B82F6', '#8B5CF6', '#EC4899', '#F59E0B', '#EF4444', '#10B981', '#0EA5E9'];

    public static function validar(Validador $v, bool $conEstado = false): array {
        $d = [
            'nombre'       => mb_strtoupper($v->patron('nombre', 'El nombre', Validador::PATRON_PERSONA, 'letras, espacios, apóstrofo y guion', 3, self::MAX_NOMBRE), 'UTF-8'),
            'email'        => $v->email('email'),
            'rol'          => $v->enum('rol', 'El rol', Enums::USUARIO_ROL),
            'avatar_color' => $v->colorHex('avatar_color', 'El color', self::COLORES[0]),
        ];
        if ($d['email'] !== '' && mb_strlen($d['email']) > self::MAX_EMAIL) {
            $v->agregarError('El correo no puede exceder los ' . self::MAX_EMAIL . ' caracteres.');
        }
        if ($conEstado) {
            $d['estado'] = $v->enum('estado', 'El estado', Enums::USUARIO_ESTADO);
        }
        return $d;
    }
}
