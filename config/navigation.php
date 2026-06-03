<?php
/**
 * NAVIGATION.PHP — Estructura de menús por rol
 */

$MENU_CONFIG = [
    ROL_COORDINADOR => [
        'Principal' => [
            ['title' => 'Dashboard', 'icon' => 'bi bi-grid-1x2', 'url' => MODULES_PATH . '/dashboard/coordinador.php'],
            ['title' => 'Calendario', 'icon' => 'bi bi-calendar3', 'url' => MODULES_PATH . '/calendario/'],
            ['title' => 'Usuarios', 'icon' => 'bi bi-people', 'url' => MODULES_PATH . '/usuarios/'],
        ],
        'Estructura Académica' => [
            ['title' => 'Estructura Curricular', 'icon' => 'bi bi-journal-code', 'url' => MODULES_PATH . '/estructura/'],
        ],
        'Gestión de Fichas' => [
            ['title' => 'Fichas de Formación', 'icon' => 'bi bi-folder2-open', 'url' => MODULES_PATH . '/fichas/'],
            ['title' => 'Matrículas', 'icon' => 'bi bi-person-plus', 'url' => MODULES_PATH . '/matriculas/'],
            ['title' => 'Asignaciones', 'icon' => 'bi bi-person-badge', 'url' => MODULES_PATH . '/asignaciones/'],
        ],
        'Proyectos Formativos' => [
            ['title' => 'Proyectos', 'icon' => 'bi bi-kanban', 'url' => MODULES_PATH . '/proyectos/'],
            ['title' => 'Actividades', 'icon' => 'bi bi-check2-square', 'url' => MODULES_PATH . '/actividades/'],
            ['title' => 'Seguimiento', 'icon' => 'bi bi-graph-up', 'url' => MODULES_PATH . '/seguimiento/'],
        ],
        'Evaluación y Calidad' => [
            ['title' => 'Evaluaciones', 'icon' => 'bi bi-pencil-square', 'url' => MODULES_PATH . '/evaluaciones/'],
            ['title' => 'Evidencias', 'icon' => 'bi bi-file-earmark-arrow-up', 'url' => MODULES_PATH . '/evidencias/'],
            ['title' => 'Retroalimentación', 'icon' => 'bi bi-chat-left-text', 'url' => MODULES_PATH . '/retroalimentacion/'],
            ['title' => 'Plan de Mejora', 'icon' => 'bi bi-arrow-up-circle', 'url' => MODULES_PATH . '/mejoramiento/'],
        ],
        'Sistema' => [
            ['title' => 'Reportes', 'icon' => 'bi bi-bar-chart-line', 'url' => MODULES_PATH . '/reportes/'],
            ['title' => 'Configuración', 'icon' => 'bi bi-gear', 'url' => MODULES_PATH . '/configuracion/'],
            ['title' => 'Auditoría de Logs', 'icon' => 'bi bi-shield-check', 'url' => MODULES_PATH . '/logs/'],
        ],
    ],

    ROL_INSTRUCTOR => [
        'Principal' => [
            ['title' => 'Dashboard', 'icon' => 'bi bi-grid-1x2', 'url' => MODULES_PATH . '/dashboard/instructor.php'],
            ['title' => 'Calendario', 'icon' => 'bi bi-calendar3', 'url' => MODULES_PATH . '/calendario/'],
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
            ['title' => 'Calendario', 'icon' => 'bi bi-calendar3', 'url' => MODULES_PATH . '/calendario/'],
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
