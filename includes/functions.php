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

/**
 * Escapa un valor para imprimirlo en HTML (texto o atributo).
 *
 * `htmlspecialchars` a secas no escapa la comilla simple con los flags por
 * defecto de PHP < 8.1 y devuelve '' ante UTF-8 inválido; aquí se fijan
 * ENT_QUOTES y ENT_SUBSTITUTE para que el resultado no dependa de la versión.
 */
function e(mixed $valor): string {
    return htmlspecialchars((string)($valor ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * JSON listo para un atributo data-* (`data-valores="<?= datosJson($x) ?>"`).
 *
 * Sustituye al patrón `onclick="abrir(<?= json_encode(...) ?>)"`, que
 * mezclaba tres contextos de escape (HTML, atributo y JavaScript) y obligaba
 * a la CSP a admitir código en línea. Los JSON_HEX_* impiden que un valor
 * con comillas o `</script>` cierre el contexto.
 */
function datosJson(mixed $datos): string {
    $json = json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE);
    return htmlspecialchars($json === false ? '{}' : $json, ENT_QUOTES, 'UTF-8');
}

/**
 * JSON para un bloque <script type="application/json">, que el navegador
 * no ejecuta y la CSP no bloquea. JSON_HEX_TAG impide que un texto con
 * `</script>` cierre el bloque.
 */
function jsonParaScript(mixed $datos): string {
    $json = json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE);
    return $json === false ? 'null' : $json;
}

/** Atributo nonce de la CSP para los pocos <script> que siguen en línea. */
function nonce(): string {
    return e(Core\Support\Seguridad::nonce());
}

/** Clase de color del semáforo de avance (0-100). */
function claseAvance(?float $pct): string {
    if ($pct === null) {
        return 'secondary';
    }
    return $pct >= 75 ? 'success' : ($pct >= 40 ? 'warning' : 'danger');
}
