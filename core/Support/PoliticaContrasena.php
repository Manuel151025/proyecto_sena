<?php
declare(strict_types=1);

namespace Core\Support;

/**
 * Reglas de las contraseñas que elige el usuario (perfil y recuperación).
 *
 * Antes cada pantalla tenía la suya: el perfil pedía 6 caracteres, la
 * recuperación 8, y ninguna ponía límite superior. bcrypt solo usa los
 * primeros 72 BYTES: una contraseña más larga se truncaba en silencio y
 * dos contraseñas distintas con los mismos 72 primeros bytes eran la misma.
 */
final class PoliticaContrasena {
    public const MIN = 8;
    /** Límite de bcrypt, en bytes (una ñ o una tilde ocupan dos). */
    public const MAX_BYTES = 72;

    /** @return string[] Errores; vacío si es válida. */
    public static function errores(string $clave, string $email = '', string $nombre = ''): array {
        $e = [];
        if (mb_strlen($clave, 'UTF-8') < self::MIN) {
            $e[] = 'La contraseña debe tener al menos ' . self::MIN . ' caracteres.';
        }
        if (strlen($clave) > self::MAX_BYTES) {
            $e[] = 'La contraseña es demasiado larga (máximo ' . self::MAX_BYTES . ' bytes).';
        }
        if (!preg_match('/\p{L}/u', $clave) || !preg_match('/\d/', $clave)) {
            $e[] = 'La contraseña debe combinar letras y números.';
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $clave)) {
            $e[] = 'La contraseña no puede contener caracteres de control.';
        }
        $minus = mb_strtolower($clave, 'UTF-8');
        $usuarioCorreo = mb_strtolower(explode('@', $email)[0] ?? '', 'UTF-8');
        if ($usuarioCorreo !== '' && mb_strlen($usuarioCorreo) >= 4 && str_contains($minus, $usuarioCorreo)) {
            $e[] = 'La contraseña no puede contener tu usuario de correo.';
        }
        if (in_array($minus, ['sena2026', 'password1', 'contrasena1', '12345678a', 'admin1234', 'demo2026*'], true)) {
            $e[] = 'Esa contraseña es demasiado común.';
        }
        return $e;
    }

    /**
     * Contraseña temporal aleatoria, fácil de dictar (sin 0/O ni 1/l/I) y
     * que cumple la política: siempre lleva letras y números.
     */
    public static function temporal(int $largo = 10): string {
        $letras = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz';
        $digitos = '23456789';
        $todo = $letras . $digitos;
        $c = [$letras[random_int(0, strlen($letras) - 1)], $digitos[random_int(0, strlen($digitos) - 1)]];
        for ($i = 2; $i < $largo; $i++) {
            $c[] = $todo[random_int(0, strlen($todo) - 1)];
        }
        // Mezcla con random_int (shuffle usa un generador no criptográfico).
        for ($i = count($c) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$c[$i], $c[$j]] = [$c[$j], $c[$i]];
        }
        return implode('', $c);
    }
}
