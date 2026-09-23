<?php
/**
 * FUNCTIONS.PHP — Helpers generales
 */

/**
 * Obtener la URL activa para marcar menú
 */
function isActiveMenu(string $url): string {
    $currentPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    return (strpos($currentPath, $url) !== false) ? 'active' : '';
}

/**
 * Generar breadcrumb array
 */
function getBreadcrumbs(): array {
    $crumbs = [['label' => 'Inicio', 'url' => APP_URL . '/index.php/dashboard']];
    $uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $segments = explode('/', $uri);
    
    // Mapeo de segmentos a labels legibles
    $labels = [
        'dashboard' => 'Dashboard', 'usuarios' => 'Usuarios', 'programas' => 'Programas',
        'competencias' => 'Competencias', 'resultados-aprendizaje' => 'Resultados RA',
        'fichas' => 'Fichas', 'matriculas' => 'Matrículas', 'asignaciones' => 'Asignaciones',
        'proyectos' => 'Proyectos', 'fases' => 'Fases', 'actividades' => 'Actividades',
        'seguimiento' => 'Seguimiento', 'evidencias' => 'Evidencias', 'evaluaciones' => 'Evaluaciones',
        'retroalimentacion' => 'Retroalimentación', 'mejoramiento' => 'Plan de Mejora',
        'reportes' => 'Reportes', 'configuracion' => 'Configuración', 'logs' => 'Auditoría',
        'perfil' => 'Mi Perfil',
    ];

    foreach ($segments as $segment) {
        if (isset($labels[$segment])) {
            $crumbs[] = ['label' => $labels[$segment], 'url' => null];
        }
    }

    return $crumbs;
}

/**
 * Formatear fecha en español
 */
function formatDateEs(string $date, string $format = 'long'): string {
    $timestamp = strtotime($date);
    $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    
    if ($format === 'long') {
        return date('d', $timestamp) . ' de ' . $meses[date('n', $timestamp) - 1] . ' de ' . date('Y', $timestamp);
    }
    return date('d', $timestamp) . ' ' . substr($meses[date('n', $timestamp) - 1], 0, 3) . ' ' . date('Y', $timestamp);
}

/**
 * Tiempo relativo
 */
function timeAgo(string $datetime): string {
    $now = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->diff($past);

    if ($diff->y > 0) return "Hace {$diff->y} año" . ($diff->y > 1 ? 's' : '');
    if ($diff->m > 0) return "Hace {$diff->m} mes" . ($diff->m > 1 ? 'es' : '');
    if ($diff->d > 0) return "Hace {$diff->d} día" . ($diff->d > 1 ? 's' : '');
    if ($diff->h > 0) return "Hace {$diff->h}h";
    if ($diff->i > 0) return "Hace {$diff->i} min";
    return "Ahora mismo";
}

/**
 * Sanitizar input
 */
function sanitize(string $str): string {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

/**
 * Genera una contraseña temporal aleatoria y fácil de transcribir
 * (sin caracteres ambiguos como 0/O o 1/l/I), para cuentas creadas
 * manualmente o por importación masiva. El usuario debe cambiarla
 * en su primer inicio de sesión (ver `debe_cambiar_password`).
 */
function generateTempPassword(int $length = 10): string {
    $chars = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
    $password = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[random_int(0, $max)];
    }
    return $password;
}

/**
 * Inicializa los registros de evaluación en estado 'pendiente'
 * para todos los Resultados de Aprendizaje (RAs) del programa de la ficha
 * asociada a un aprendiz.
 *
 * Se mantiene como función porque es la firma que ya usan
 * MatriculaController y AprendizModel, pero la lógica vive en
 * Core\Services\EvaluacionesSyncService: era la mitad de una misma regla
 * ("un aprendiz en formación debe tener una fila por cada RAP de su
 * programa") y tenerla duplicada fue lo que dejó pasar el caso inverso,
 * el del RAP creado después de la matrícula.
 *
 * @return int Número de filas creadas.
 */
function inicializarEvaluacionesAprendiz(PDO $db, int $aprendizId, int $fichaId): int {
    $resultado = (new Core\Services\EvaluacionesSyncService($db))->sincronizar([
        'aprendiz_id' => $aprendizId,
        'ficha_id'    => $fichaId,
    ]);

    if ($resultado['omitidas_sin_instructor'] > 0) {
        error_log(
            "inicializarEvaluacionesAprendiz: la ficha $fichaId no tiene instructor líder, " .
            "así que no se crearon {$resultado['omitidas_sin_instructor']} evaluaciones del aprendiz $aprendizId. " .
            "Asigne un instructor a la ficha y vuelva a sincronizar."
        );
    }

    return $resultado['creadas'];
}
