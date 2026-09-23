<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

use Core\Support\Migracion;

/**
 * Limitador de intentos de acceso y de recuperación, por identidad e IP.
 * Vivía en $_SESSION y se evadía descartando la cookie.
 * Antes: migrations/create_intentos_acceso.php
 */
return new class extends Migracion {
    public string $descripcion = 'Tabla intentos_acceso';

    public function aplicar(PDO $db): void {
        if ($this->existeTabla($db, 'intentos_acceso')) {
            return;
        }
        $db->exec("
            CREATE TABLE intentos_acceso (
                id             INT AUTO_INCREMENT PRIMARY KEY,
                accion         VARCHAR(40) NOT NULL COMMENT 'login | recuperacion',
                clave          VARCHAR(80) NOT NULL COMMENT 'hash de identidad (id:) o de IP (ip:)',
                intentos       INT NOT NULL DEFAULT 0,
                primer_intento TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                ultimo_intento TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unico_accion_clave (accion, clave),
                KEY idx_ultimo (ultimo_intento)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->informar('tabla intentos_acceso creada');
    }
};
