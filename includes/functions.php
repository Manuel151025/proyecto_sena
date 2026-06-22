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
    $role = getCurrentRole();
    $startUrl = ($role === ROL_COORDINADOR) 
        ? APP_URL . '/index.php/dashboard' 
        : APP_URL . '/modules/dashboard/' . $role . '.php';
    $crumbs = [['label' => 'Inicio', 'url' => $startUrl]];
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
 * Inicializa los registros de evaluación en estado 'pendiente'
 * para todos los Resultados de Aprendizaje (RAs) del programa de la ficha
 * asociada a un aprendiz.
 */
function inicializarEvaluacionesAprendiz(PDO $db, int $aprendizId, int $fichaId): void {
    // 1. Obtener el programa_id y el instructor responsable de la ficha
    $stmtFicha = $db->prepare("SELECT programa_id, instructor_id FROM fichas WHERE id = ?");
    $stmtFicha->execute([$fichaId]);
    $ficha = $stmtFicha->fetch(PDO::FETCH_ASSOC);
    if (!$ficha) return;

    $programaId = (int)$ficha['programa_id'];
    $instructorId = (int)($ficha['instructor_id'] ?: 0);

    // 2. Obtener todos los RAs asociados a este programa
    $stmtRas = $db->prepare("
        SELECT ra.id
        FROM resultados_aprendizaje ra
        JOIN competencias c ON ra.competencia_id = c.id
        WHERE c.programa_id = ?
    ");
    $stmtRas->execute([$programaId]);
    $ras = $stmtRas->fetchAll(PDO::FETCH_COLUMN);

    if (empty($ras)) return;

    // 3. Insertar registros en evaluaciones (si no existen)
    $stmtInsert = $db->prepare("
        INSERT INTO evaluaciones (resultado_aprendizaje_id, aprendiz_id, instructor_id, ficha_id, concepto, comentario, fecha_evaluacion)
        VALUES (?, ?, ?, ?, 'pendiente', NULL, NULL)
        ON DUPLICATE KEY UPDATE concepto = concepto
    ");

    foreach ($ras as $raId) {
        $stmtInsert->execute([
            (int)$raId, 
            $aprendizId, 
            $instructorId > 0 ? $instructorId : null, 
            $fichaId
        ]);
    }
}
