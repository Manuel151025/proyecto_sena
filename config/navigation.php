<?php
/**
 * NAVIGATION.PHP — Estructura de menús por rol
 */

$MENU_CONFIG = [
    ROL_COORDINADOR => [
        'Principal' => [
            'icon' => 'bi bi-grid-1x2',
            'items' => [
                ['title' => 'Dashboard', 'url' => APP_URL . '/index.php/dashboard'],
                ['title' => 'Calendario', 'url' => APP_URL . '/index.php/calendario'],
                ['title' => 'Usuarios', 'url' => APP_URL . '/index.php/usuarios'],
            ]
        ],
        'Academia' => [
            'icon' => 'bi bi-mortarboard',
            'items' => [
                ['title' => 'Estructura Curricular', 'url' => APP_URL . '/index.php/estructura'],
                ['title' => 'Fichas de Formación', 'url' => MODULES_PATH . '/fichas/'],
                ['title' => 'Matrículas', 'url' => APP_URL . '/index.php/matriculas'],
                ['title' => 'Asignar Instructores', 'url' => APP_URL . '/index.php/asignaciones'],
            ]
        ],
        'Proyectos Formativos' => [
            'icon' => 'bi bi-kanban',
            'items' => [
                ['title' => 'Proyectos', 'url' => APP_URL . '/index.php/proyectos'],
                ['title' => 'Actividades', 'url' => APP_URL . '/index.php/actividades'],
                ['title' => 'Seguimiento', 'url' => APP_URL . '/index.php/seguimiento'],
            ]
        ],
        'Evaluación y Calidad' => [
            'icon' => 'bi bi-pencil-square',
            'items' => [
                ['title' => 'Evaluaciones', 'url' => APP_URL . '/index.php/evaluaciones'],
                ['title' => 'Evidencias', 'url' => APP_URL . '/index.php/evidencias'],
                ['title' => 'Retroalimentación', 'url' => APP_URL . '/index.php/retroalimentacion'],
                ['title' => 'Plan de Mejora', 'url' => APP_URL . '/index.php/mejoramiento'],
            ]
        ],
        'Sistema' => [
            'icon' => 'bi bi-gear',
            'items' => [
                ['title' => 'Reportes', 'url' => APP_URL . '/index.php/reportes'],
                ['title' => 'Configuración', 'url' => APP_URL . '/index.php/configuracion'],
                ['title' => 'Auditoría de Logs', 'url' => APP_URL . '/index.php/logs'],
            ]
        ],
    ],

    ROL_INSTRUCTOR => [
        'Principal' => [
            'icon' => 'bi bi-grid-1x2',
            'items' => [
                ['title' => 'Dashboard', 'url' => APP_URL . '/index.php/dashboard'],
                ['title' => 'Calendario', 'url' => APP_URL . '/index.php/calendario'],
            ]
        ],
        'Mis Fichas' => [
            'icon' => 'bi bi-folder2-open',
            'items' => [
                ['title' => 'Fichas asignadas', 'url' => MODULES_PATH . '/fichas/'],
                ['title' => 'Mis aprendices', 'url' => APP_URL . '/index.php/matriculas'],
            ]
        ],
        'Proyectos' => [
            'icon' => 'bi bi-kanban',
            'items' => [
                ['title' => 'Proyectos', 'url' => APP_URL . '/index.php/proyectos'],
                ['title' => 'Actividades', 'url' => APP_URL . '/index.php/actividades'],
                ['title' => 'Seguimiento', 'url' => APP_URL . '/index.php/seguimiento'],
            ]
        ],
        'Evaluación' => [
            'icon' => 'bi bi-pencil-square',
            'items' => [
                ['title' => 'Evidencias', 'url' => APP_URL . '/index.php/evidencias'],
                ['title' => 'Evaluaciones', 'url' => APP_URL . '/index.php/evaluaciones'],
                ['title' => 'Retroalimentación', 'url' => APP_URL . '/index.php/retroalimentacion'],
                ['title' => 'Plan mejora', 'url' => APP_URL . '/index.php/mejoramiento'],
            ]
        ],
        'Otros' => [
            'icon' => 'bi bi-gear',
            'items' => [
                ['title' => 'Reportes', 'url' => APP_URL . '/index.php/reportes'],
            ]
        ],
    ],
    ROL_APRENDIZ => [
        'Principal' => [
            'icon' => 'bi bi-grid-1x2',
            'items' => [
                ['title' => 'Dashboard', 'url' => APP_URL . '/index.php/dashboard'],
                ['title' => 'Calendario', 'url' => APP_URL . '/index.php/calendario'],
            ]
        ],
        'Mi Formación' => [
            'icon' => 'bi bi-mortarboard',
            'items' => [
                ['title' => 'Mi ficha', 'url' => MODULES_PATH . '/fichas/'],
                ['title' => 'Mi proyecto', 'url' => MODULES_PATH . '/proyectos/'],
                ['title' => 'Actividades', 'url' => APP_URL . '/index.php/actividades'],
            ]
        ],
        'Entregas' => [
            'icon' => 'bi bi-file-earmark-arrow-up',
            'items' => [
                ['title' => 'Mis evidencias', 'url' => APP_URL . '/index.php/evidencias'],
                ['title' => 'Evaluaciones', 'url' => APP_URL . '/index.php/evaluaciones'],
                ['title' => 'Retroalimentación', 'url' => APP_URL . '/index.php/retroalimentacion'],
            ]
        ],
        'Otros' => [
            'icon' => 'bi bi-gear',
            'items' => [
                ['title' => 'Plan mejora', 'url' => APP_URL . '/index.php/mejoramiento'],
            ]
        ],
    ],
];

return $MENU_CONFIG;
