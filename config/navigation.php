<?php
/**
 * NAVIGATION.PHP — Estructura de menús por rol
 */

$MENU_CONFIG = [
    ROL_COORDINADOR => [
        'Principal' => [
            ['title' => 'Dashboard', 'icon' => 'bi bi-grid-1x2', 'url' => MODULES_PATH . '/dashboard/coordinador.php'],
            ['title' => 'Usuarios', 'icon' => 'bi bi-people', 'url' => MODULES_PATH . '/usuarios/', 'badge' => null],
        ],
        'Académico' => [
            ['title' => 'Programas', 'icon' => 'bi bi-book', 'url' => MODULES_PATH . '/programas/'],
            ['title' => 'Competencias', 'icon' => 'bi bi-diagram-3', 'url' => MODULES_PATH . '/competencias/'],
            ['title' => 'Resultados RA', 'icon' => 'bi bi-clipboard-check', 'url' => MODULES_PATH . '/resultados-aprendizaje/'],
        ],
        'Gestión' => [
            ['title' => 'Fichas', 'icon' => 'bi bi-folder2-open', 'url' => MODULES_PATH . '/fichas/'],
            ['title' => 'Matrículas', 'icon' => 'bi bi-person-plus', 'url' => MODULES_PATH . '/matriculas/'],
            ['title' => 'Asignaciones', 'icon' => 'bi bi-person-badge', 'url' => MODULES_PATH . '/asignaciones/'],
        ],
        'Proyectos' => [
            ['title' => 'Proyectos', 'icon' => 'bi bi-kanban', 'url' => MODULES_PATH . '/proyectos/'],
            ['title' => 'Fases', 'icon' => 'bi bi-list-task', 'url' => MODULES_PATH . '/fases/'],
            ['title' => 'Actividades', 'icon' => 'bi bi-check2-square', 'url' => MODULES_PATH . '/actividades/'],
            ['title' => 'Seguimiento', 'icon' => 'bi bi-graph-up', 'url' => MODULES_PATH . '/seguimiento/'],
        ],
        'Evaluación' => [
            ['title' => 'Evidencias', 'icon' => 'bi bi-file-earmark-arrow-up', 'url' => MODULES_PATH . '/evidencias/'],
            ['title' => 'Evaluaciones', 'icon' => 'bi bi-pencil-square', 'url' => MODULES_PATH . '/evaluaciones/'],
            ['title' => 'Retroalimentación', 'icon' => 'bi bi-chat-left-text', 'url' => MODULES_PATH . '/retroalimentacion/'],
            ['title' => 'Plan mejora', 'icon' => 'bi bi-arrow-up-circle', 'url' => MODULES_PATH . '/mejoramiento/'],
        ],
        'Sistema' => [
            ['title' => 'Reportes', 'icon' => 'bi bi-bar-chart-line', 'url' => MODULES_PATH . '/reportes/'],
            ['title' => 'Configuración', 'icon' => 'bi bi-gear', 'url' => MODULES_PATH . '/configuracion/'],
            ['title' => 'Auditoría', 'icon' => 'bi bi-shield-check', 'url' => MODULES_PATH . '/logs/'],
        ],
    ],
    ROL_INSTRUCTOR => [
        'Principal' => [
            ['title' => 'Dashboard', 'icon' => 'bi bi-grid-1x2', 'url' => MODULES_PATH . '/dashboard/instructor.php'],
        ],
        'Mis Fichas' => [
            ['title' => 'Fichas asignadas', 'icon' => 'bi bi-folder2-open', 'url' => MODULES_PATH . '/fichas/'],
            ['title' => 'Mis aprendices', 'icon' => 'bi bi-people', 'url' => MODULES_PATH . '/matriculas/'],
        ],
        'Proyectos' => [
            ['title' => 'Proyectos', 'icon' => 'bi bi-kanban', 'url' => MODULES_PATH . '/proyectos/'],
            ['title' => 'Actividades', 'icon' => 'bi bi-check2-square', 'url' => MODULES_PATH . '/actividades/', 'badge' => '5'],
            ['title' => 'Seguimiento', 'icon' => 'bi bi-graph-up', 'url' => MODULES_PATH . '/seguimiento/'],
        ],
        'Evaluación' => [
            ['title' => 'Evidencias', 'icon' => 'bi bi-file-earmark-arrow-up', 'url' => MODULES_PATH . '/evidencias/', 'badge' => '3'],
            ['title' => 'Evaluaciones', 'icon' => 'bi bi-pencil-square', 'url' => MODULES_PATH . '/evaluaciones/'],
            ['title' => 'Retroalimentación', 'icon' => 'bi bi-chat-left-text', 'url' => MODULES_PATH . '/retroalimentacion/'],
            ['title' => 'Plan mejora', 'icon' => 'bi bi-arrow-up-circle', 'url' => MODULES_PATH . '/mejoramiento/'],
        ],
        'Otros' => [
            ['title' => 'Reportes', 'icon' => 'bi bi-bar-chart-line', 'url' => MODULES_PATH . '/reportes/'],
        ],
    ],
    ROL_APRENDIZ => [
        'Principal' => [
            ['title' => 'Dashboard', 'icon' => 'bi bi-grid-1x2', 'url' => MODULES_PATH . '/dashboard/aprendiz.php'],
        ],
        'Mi Formación' => [
            ['title' => 'Mi ficha', 'icon' => 'bi bi-folder2-open', 'url' => MODULES_PATH . '/fichas/'],
            ['title' => 'Mi proyecto', 'icon' => 'bi bi-kanban', 'url' => MODULES_PATH . '/proyectos/'],
            ['title' => 'Actividades', 'icon' => 'bi bi-check2-square', 'url' => MODULES_PATH . '/actividades/', 'badge' => '4'],
        ],
        'Entregas' => [
            ['title' => 'Mis evidencias', 'icon' => 'bi bi-file-earmark-arrow-up', 'url' => MODULES_PATH . '/evidencias/'],
            ['title' => 'Evaluaciones', 'icon' => 'bi bi-pencil-square', 'url' => MODULES_PATH . '/evaluaciones/'],
            ['title' => 'Retroalimentación', 'icon' => 'bi bi-chat-left-text', 'url' => MODULES_PATH . '/retroalimentacion/'],
        ],
        'Otros' => [
            ['title' => 'Plan mejora', 'icon' => 'bi bi-arrow-up-circle', 'url' => MODULES_PATH . '/mejoramiento/'],
        ],
    ],
];

return $MENU_CONFIG;
