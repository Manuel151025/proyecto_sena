<?php
/**
 * NAVIGATION.PHP — Estructura de menús por rol
 */

$MENU_CONFIG = [
    ROL_COORDINADOR => [
        'Principal' => [
            'icon' => 'bi bi-grid-1x2',
            'items' => [
                ['title' => 'Dashboard', 'url' => MODULES_PATH . '/dashboard/coordinador.php'],
                ['title' => 'Calendario', 'url' => MODULES_PATH . '/calendario/'],
                ['title' => 'Usuarios', 'url' => APP_URL . '/index.php/usuarios'],
            ]
        ],
        'Academia' => [
            'icon' => 'bi bi-mortarboard',
            'items' => [
                ['title' => 'Estructura Curricular', 'url' => APP_URL . '/index.php/estructura'],
                ['title' => 'Fichas de Formación', 'url' => MODULES_PATH . '/fichas/'],
                ['title' => 'Matrículas', 'url' => APP_URL . '/index.php/matriculas'],
                ['title' => 'Asignaciones', 'url' => MODULES_PATH . '/asignaciones/'],
            ]
        ],
        'Proyectos Formativos' => [
            'icon' => 'bi bi-kanban',
            'items' => [
                ['title' => 'Proyectos', 'url' => MODULES_PATH . '/proyectos/'],
                ['title' => 'Actividades', 'url' => MODULES_PATH . '/actividades/'],
                ['title' => 'Seguimiento', 'url' => MODULES_PATH . '/seguimiento/'],
            ]
        ],
        'Evaluación y Calidad' => [
            'icon' => 'bi bi-pencil-square',
            'items' => [
                ['title' => 'Evaluaciones', 'url' => MODULES_PATH . '/evaluaciones/'],
                ['title' => 'Evidencias', 'url' => MODULES_PATH . '/evidencias/'],
                ['title' => 'Retroalimentación', 'url' => MODULES_PATH . '/retroalimentacion/'],
                ['title' => 'Plan de Mejora', 'url' => MODULES_PATH . '/mejoramiento/'],
            ]
        ],
        'Sistema' => [
            'icon' => 'bi bi-gear',
            'items' => [
                ['title' => 'Reportes', 'url' => MODULES_PATH . '/reportes/'],
                ['title' => 'Configuración', 'url' => MODULES_PATH . '/configuracion/'],
                ['title' => 'Auditoría de Logs', 'url' => MODULES_PATH . '/logs/'],
            ]
        ],
    ],

    ROL_INSTRUCTOR => [
        'Principal' => [
            'icon' => 'bi bi-grid-1x2',
            'items' => [
                ['title' => 'Dashboard', 'url' => MODULES_PATH . '/dashboard/instructor.php'],
                ['title' => 'Calendario', 'url' => MODULES_PATH . '/calendario/'],
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
                ['title' => 'Proyectos', 'url' => MODULES_PATH . '/proyectos/'],
                ['title' => 'Actividades', 'url' => MODULES_PATH . '/actividades/'],
                ['title' => 'Seguimiento', 'url' => MODULES_PATH . '/seguimiento/'],
            ]
        ],
        'Evaluación' => [
            'icon' => 'bi bi-pencil-square',
            'items' => [
                ['title' => 'Evidencias', 'url' => MODULES_PATH . '/evidencias/'],
                ['title' => 'Evaluaciones', 'url' => MODULES_PATH . '/evaluaciones/'],
                ['title' => 'Retroalimentación', 'url' => MODULES_PATH . '/retroalimentacion/'],
                ['title' => 'Plan mejora', 'url' => MODULES_PATH . '/mejoramiento/'],
            ]
        ],
        'Otros' => [
            'icon' => 'bi bi-gear',
            'items' => [
                ['title' => 'Reportes', 'url' => MODULES_PATH . '/reportes/'],
            ]
        ],
    ],
    ROL_APRENDIZ => [
        'Principal' => [
            'icon' => 'bi bi-grid-1x2',
            'items' => [
                ['title' => 'Dashboard', 'url' => MODULES_PATH . '/dashboard/aprendiz.php'],
                ['title' => 'Calendario', 'url' => MODULES_PATH . '/calendario/'],
            ]
        ],
        'Mi Formación' => [
            'icon' => 'bi bi-mortarboard',
            'items' => [
                ['title' => 'Mi ficha', 'url' => MODULES_PATH . '/fichas/'],
                ['title' => 'Mi proyecto', 'url' => MODULES_PATH . '/proyectos/'],
                ['title' => 'Actividades', 'url' => MODULES_PATH . '/actividades/'],
            ]
        ],
        'Entregas' => [
            'icon' => 'bi bi-file-earmark-arrow-up',
            'items' => [
                ['title' => 'Mis evidencias', 'url' => MODULES_PATH . '/evidencias/'],
                ['title' => 'Evaluaciones', 'url' => MODULES_PATH . '/evaluaciones/'],
                ['title' => 'Retroalimentación', 'url' => MODULES_PATH . '/retroalimentacion/'],
            ]
        ],
        'Otros' => [
            'icon' => 'bi bi-gear',
            'items' => [
                ['title' => 'Plan mejora', 'url' => MODULES_PATH . '/mejoramiento/'],
            ]
        ],
    ],
];

return $MENU_CONFIG;
