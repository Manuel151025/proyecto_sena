<?php
/**
 * Script de migración — Crear tabla notificaciones e insertar datos de ejemplo
 * Ejecutar: php create_notificaciones.php
 */
declare(strict_types=1);

require_once __DIR__ . '/../../../../xampp/htdocs/proyecto_sena/includes/config.php';
require_once __DIR__ . '/../../../../xampp/htdocs/proyecto_sena/core/Database.php';

use Core\Database;

try {
    $db = Database::getConnection();

    // Crear tabla de notificaciones
    $db->exec("
        CREATE TABLE IF NOT EXISTS notificaciones (
            id int(11) NOT NULL AUTO_INCREMENT,
            usuario_id int(11) NOT NULL,
            titulo varchar(200) NOT NULL,
            mensaje text NOT NULL,
            tipo enum('info','success','warning','danger') DEFAULT 'info',
            leida tinyint(1) DEFAULT 0,
            url varchar(500) DEFAULT NULL,
            fecha_creacion timestamp NOT NULL DEFAULT current_timestamp(),
            PRIMARY KEY (id),
            KEY usuario_id (usuario_id),
            KEY idx_leida (leida),
            CONSTRAINT notif_ibfk_1 FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    echo "✅ Tabla 'notificaciones' creada correctamente.\n";

    // Insertar notificaciones de ejemplo para usuarios 1-5
    $notificaciones = [
        // Usuario 1
        [1, 'Nueva ficha asignada', 'Se le ha asignado la ficha #2894301 del programa ADSO.', 'info', '/proyecto_sena/modules/fichas/ver.php?id=1'],
        [1, 'Cumplimiento bajo', 'La ficha #2894301 tiene un cumplimiento inferior al 60%.', 'danger', '/proyecto_sena/modules/fichas/ver.php?id=1'],
        [1, 'Instructor registrado', 'El instructor Carlos Ramírez ha sido registrado exitosamente.', 'success', '/proyecto_sena/modules/usuarios/'],
        // Usuario 2
        [2, 'Evaluación pendiente', 'Tiene 3 evaluaciones pendientes por calificar.', 'warning', '/proyecto_sena/modules/evaluaciones/'],
        [2, 'Proyecto actualizado', 'El proyecto "App Gestión Inventario" fue actualizado.', 'info', '/proyecto_sena/modules/proyectos/'],
        // Usuario 3
        [3, 'Evidencia recibida', 'Se ha recibido una nueva evidencia del aprendiz María López.', 'info', '/proyecto_sena/modules/evidencias/'],
        [3, 'Fase completada', 'La fase de Análisis del proyecto ha sido completada.', 'success', '/proyecto_sena/modules/fases/'],
        [3, 'Alerta de retención', 'La ficha #2756123 presenta riesgo de deserción.', 'danger', '/proyecto_sena/modules/fichas/'],
        // Usuario 4
        [4, 'Matrícula aprobada', 'Su matrícula ha sido aprobada para la ficha #2894301.', 'success', '/proyecto_sena/modules/matriculas/'],
        [4, 'Nueva actividad', 'Se ha publicado una nueva actividad en la fase de Ejecución.', 'info', '/proyecto_sena/modules/actividades/'],
        // Usuario 5
        [5, 'Retroalimentación recibida', 'Su instructor ha dejado retroalimentación en su última evidencia.', 'info', '/proyecto_sena/modules/retroalimentacion/'],
        [5, 'Plan de mejora', 'Se le ha asignado un plan de mejoramiento.', 'warning', '/proyecto_sena/modules/mejoramiento/'],
        [5, 'Calificación publicada', 'Se ha publicado la calificación de la competencia Análisis de Sistemas.', 'success', '/proyecto_sena/modules/evaluaciones/'],
    ];

    $stmt = $db->prepare("
        INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo, leida, url, fecha_creacion)
        VALUES (?, ?, ?, ?, 0, ?, NOW() - INTERVAL FLOOR(RAND() * 72) HOUR)
    ");

    foreach ($notificaciones as $n) {
        $stmt->execute([$n[0], $n[1], $n[2], $n[3], $n[4]]);
    }

    echo "✅ " . count($notificaciones) . " notificaciones de ejemplo insertadas.\n";
    echo "🎉 Migración completada exitosamente.\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
