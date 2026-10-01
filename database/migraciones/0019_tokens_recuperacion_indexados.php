<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

use Core\Support\Migracion;

/**
 * Los tokens de recuperación de contraseña pasan a guardarse como su huella
 * SHA-256 y a buscarse por ella con un índice.
 *
 * Antes se guardaban con bcrypt, que no se puede buscar: recover.php probaba
 * el token contra los 20 tokens vigentes más recientes de todo el sistema,
 * así que con más de 20 solicitudes en media hora (inicio de trimestre) los
 * enlaces anteriores dejaban de funcionar. Los pendientes con el formato
 * anterior se anulan: caducan en 30 minutos de todos modos.
 */
return new class extends Migracion {
    public string $descripcion = 'Tokens de recuperación buscables por su huella SHA-256';

    public function aplicar(PDO $db): void {
        if (!$this->existeIndice($db, 'password_resets', 'idx_token_hash')) {
            $db->exec('ALTER TABLE password_resets ADD INDEX idx_token_hash (token_hash(64))');
            $this->informar('índice idx_token_hash creado');
        }
        $n = $db->exec("UPDATE password_resets SET usado = 1 WHERE usado = 0 AND token_hash LIKE '\$2y\$%'");
        if ($n > 0) {
            $this->informar("$n enlaces pendientes con el formato anterior anulados");
        }
    }
};
