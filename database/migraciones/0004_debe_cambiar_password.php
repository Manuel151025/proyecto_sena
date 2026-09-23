<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

use Core\Support\Migracion;

/**
 * Cambio obligatorio de la contraseña temporal en el primer acceso.
 * Antes: migrations/add_password_change_required.php
 */
return new class extends Migracion {
    public string $descripcion = 'usuarios.debe_cambiar_password';

    public function aplicar(PDO $db): void {
        if (!$this->existeColumna($db, 'usuarios', 'debe_cambiar_password')) {
            $db->exec("ALTER TABLE usuarios ADD COLUMN debe_cambiar_password TINYINT(1) NOT NULL DEFAULT 0 AFTER password");
            $this->informar('columna usuarios.debe_cambiar_password creada');
        }
    }
};
