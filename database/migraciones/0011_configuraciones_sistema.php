<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

use Core\Support\Migracion;

/**
 * Configuración institucional. El modelo la consultaba sin que la tabla
 * existiera y un catch lo ocultaba: el módulo descartaba lo guardado.
 * Antes: migrations/create_configuraciones_sistema.php
 */
return new class extends Migracion {
    public string $descripcion = 'Tabla configuraciones_sistema con valores iniciales';

    public function aplicar(PDO $db): void {
        $db->exec("
            CREATE TABLE IF NOT EXISTS configuraciones_sistema (
                clave               VARCHAR(60) PRIMARY KEY,
                valor               TEXT NOT NULL,
                fecha_actualizacion TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $iniciales = [
            'system_title' => 'SENA - Seguimiento de Proyectos Formativos',
            'regional'     => 'Regional Caquetá - Centro Tecnológico de la Amazonia',
            'pass_score'   => '70%',
            'smtp_server'  => 'smtp.gmail.com',
        ];
        // INSERT IGNORE: lo que el coordinador ya configuró se respeta.
        $st = $db->prepare("INSERT IGNORE INTO configuraciones_sistema (clave, valor) VALUES (?, ?)");
        $n = 0;
        foreach ($iniciales as $clave => $valor) {
            $st->execute([$clave, $valor]);
            $n += $st->rowCount();
        }
        if ($n > 0) {
            $this->informar("valores iniciales: $n");
        }
    }
};
