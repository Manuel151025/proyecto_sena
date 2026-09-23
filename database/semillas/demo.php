<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/**
 * Datos de demostración para una instalación nueva (`bin/instalar.php --demo`)
 * y para el CI.
 *
 * Todo es sintético: nombres, documentos y correos inventados. La semilla
 * es reproducible (generador con semilla fija): dos instalaciones dan los
 * mismos datos, así que una prueba que falla en CI falla igual en local.
 *
 * A diferencia de la semilla del antiguo install.php, esta ejercita TODAS
 * las tablas del dominio. Seis de ellas (asignaciones, actividades,
 * evidencias, retroalimentación, notificaciones y eventos) llegaron a
 * producción sin haberse usado nunca con datos, y las dos ramas de la regla
 * de acceso del instructor que dependen de `asignaciones` no se habían
 * ejecutado jamás fuera de las pruebas.
 *
 * @return callable(PDO):array<string,int>
 */
return static function (PDO $db): array {
    mt_srand(2026);
    $azar = static fn(array $a) => $a[mt_rand(0, count($a) - 1)];
    $hoy = new DateTimeImmutable('today');
    $fecha = static fn(string $mod) => $hoy->modify($mod)->format('Y-m-d');

    $hash = password_hash('Demo2026*', PASSWORD_DEFAULT);
    $ins = static function (string $sql, array $p) use ($db): int {
        $db->prepare($sql)->execute($p);
        return (int)$db->lastInsertId();
    };

    // -----------------------------------------------------------------
    // PERSONAS
    // -----------------------------------------------------------------
    $coord = $ins("INSERT INTO usuarios (email, password, nombre, rol, avatar_color) VALUES (?,?,?,?,?)",
        ['coordinador@sena.edu.co', $hash, 'CARLOS ANDRÉS MARTÍNEZ', 'coordinador', '#39A900']);

    $instructores = [];
    foreach ([
        ['instructor@sena.edu.co',  'MARÍA FERNANDA LÓPEZ', '#3B82F6'],
        ['instructor2@sena.edu.co', 'JORGE SALAS',          '#8B5CF6'],
        ['instructor3@sena.edu.co', 'DIANA CRUZ',           '#EC4899'],
        ['instructor4@sena.edu.co', 'ROBERTO GÓMEZ',        '#10B981'],
        ['instructor5@sena.edu.co', 'ANA TORRES',           '#F43F5E'],
        // Recién vinculado, todavía sin fichas ni competencias a cargo: es
        // el caso que prueban los controles de acceso ("instructor ajeno").
        ['instructor6@sena.edu.co', 'PABLO REYES',          '#0EA5E9'],
    ] as [$email, $nombre, $color]) {
        $instructores[] = $ins("INSERT INTO usuarios (email, password, nombre, rol, avatar_color) VALUES (?,?,?,?,?)",
            [$email, $hash, $nombre, 'instructor', $color]);
    }

    // -----------------------------------------------------------------
    // ESTRUCTURA CURRICULAR
    // -----------------------------------------------------------------
    $programas = [
        'ADSO' => ['Análisis y Desarrollo de Software', 3984, [
            ['220501094', 'Establecer requisitos de la solución de software de acuerdo con estándares y procedimiento técnico', 0, [
                'Caracterizar los procesos de la organización de acuerdo con el software a construir',
                'Recolectar información del software a construir de acuerdo con las necesidades del cliente',
                'Establecer los requisitos del software de acuerdo con la información recolectada',
                'Validar el informe de requisitos del software de acuerdo con las necesidades del cliente',
            ]],
            ['220501095', 'Evaluar requisitos de la solución de software de acuerdo con metodologías de análisis y estándares', 0, [
                'Plantear la solución de software de acuerdo con los requisitos',
                'Elaborar los artefactos del análisis de acuerdo con la metodología seleccionada',
                'Verificar el modelo de la solución de acuerdo con los requisitos',
            ]],
            ['220501096', 'Desarrollar la solución de software de acuerdo con el diseño y metodologías de desarrollo', 0, [
                'Planificar la construcción del software de acuerdo con el diseño',
                'Construir la base de datos de acuerdo con el modelo de datos',
                'Codificar los módulos del software de acuerdo con el diseño establecido',
                'Realizar pruebas al software de acuerdo con el plan de pruebas',
            ]],
            ['220501097', 'Implantar la solución de software de acuerdo con los requerimientos de la organización', 0, [
                'Preparar el entorno de producción de acuerdo con el plan de implantación',
                'Elaborar los manuales técnico y de usuario de acuerdo con la solución',
            ]],
            ['999999999', 'Resultado de aprendizaje de la etapa práctica', 1, [
                'Aplicar en la resolución de problemas reales del sector productivo los conocimientos adquiridos',
            ]],
        ]],
        'CONT' => ['Gestión Contable y Financiera', 2640, [
            ['210301001', 'Contabilizar operaciones de acuerdo con las normas vigentes y las políticas organizacionales', 0, [
                'Registrar los hechos económicos de acuerdo con las normas contables',
                'Elaborar los comprobantes de contabilidad según la normativa vigente',
                'Clasificar los documentos soporte según su naturaleza',
            ]],
            ['210301002', 'Analizar los resultados contables según los criterios de evaluación establecidos', 0, [
                'Interpretar los estados financieros según las normas internacionales',
                'Calcular indicadores financieros para la toma de decisiones',
            ]],
            ['210301003', 'Preparar y presentar la información contable y financiera según la normativa', 0, [
                'Presentar la información contable ante las entidades de control',
                'Preparar declaraciones tributarias según la legislación colombiana',
            ]],
            ['999999998', 'Resultado de aprendizaje de la etapa práctica', 1, [
                'Aplicar en la empresa los procedimientos contables del sector productivo',
            ]],
        ]],
        'ACUI' => ['Producción Acuícola', 2200, [
            ['226101001', 'Alimentar especies acuícolas de acuerdo con el protocolo técnico de producción', 0, [
                'Calcular la ración de alimento según biomasa y temperatura del agua',
                'Suministrar el alimento de acuerdo con el plan de alimentación',
                'Registrar el consumo de alimento en los formatos de control',
            ]],
            ['226101002', 'Monitorear la calidad del agua de acuerdo con los parámetros de la especie', 0, [
                'Medir los parámetros fisicoquímicos del agua según el protocolo',
                'Aplicar correctivos de calidad de agua según los resultados del monitoreo',
            ]],
            ['226101003', 'Controlar la sanidad de la producción acuícola según normativa sanitaria', 0, [
                'Identificar signos de enfermedad en los peces según el protocolo sanitario',
                'Aplicar medidas de bioseguridad en la unidad productiva',
            ]],
        ]],
    ];

    $progIds = $compIds = $rapPorPrograma = $etapaPracticaPorPrograma = [];
    $nRap = 0;
    foreach ($programas as $codigo => [$nombre, $horas, $comps]) {
        $pid = $ins("INSERT INTO programas (nombre, codigo, descripcion, duracion_horas) VALUES (?,?,?,?)",
            [$nombre, $codigo, "Programa de formación tecnológica en $nombre", $horas]);
        $progIds[$codigo] = $pid;
        foreach ($comps as $ic => [$cCod, $cNom, $etapa, $raps]) {
            $cid = $ins("INSERT INTO competencias (programa_id, nombre, es_etapa_practica, codigo, horas) VALUES (?,?,?,?,?)",
                [$pid, $cNom, $etapa, $cCod, $etapa ? 880 : 240 + 60 * $ic]);
            $compIds[$codigo][] = $cid;
            if ($etapa) {
                $etapaPracticaPorPrograma[$codigo] = $cid;
            }
            foreach ($raps as $ir => $den) {
                $rid = $ins("INSERT INTO resultados_aprendizaje (competencia_id, codigo, denominacion) VALUES (?,?,?)",
                    [$cid, $cCod . '-' . str_pad((string)($ir + 1), 2, '0', STR_PAD_LEFT), $den]);
                $rapPorPrograma[$codigo][] = ['id' => $rid, 'competencia' => $cid, 'etapa' => $etapa];
                $nRap++;
            }
        }
    }

    // -----------------------------------------------------------------
    // PROYECTOS FORMATIVOS Y FASES
    // -----------------------------------------------------------------
    $proyectos = [
        'ADSO' => ['PF-ADSO-01', 'Sistema de información para la gestión de inventarios de una mipyme',
                   'Desarrollar un sistema web que controle entradas, salidas y existencias de inventario'],
        'CONT' => ['PF-CONT-01', 'Sistema contable para microempresas de la región amazónica',
                   'Implementar el ciclo contable completo de una microempresa real de la región'],
        'ACUI' => ['PF-ACUI-01', 'Producción sostenible de cachama en estanques de tierra',
                   'Desarrollar un ciclo productivo de cachama con registro técnico de alimentación y sanidad'],
    ];
    $faseNombres = [
        ['Análisis', 'Diagnóstico de la necesidad y levantamiento de requisitos'],
        ['Planeación', 'Diseño de la solución y plan de trabajo'],
        ['Ejecución', 'Construcción y desarrollo de la solución'],
        ['Evaluación', 'Pruebas, socialización y cierre del proyecto'],
    ];
    $proyIds = $fasesPorProyecto = [];
    foreach ($proyectos as $prog => [$cod, $nom, $obj]) {
        $pr = $ins("INSERT INTO proyectos (nombre, codigo, objetivo, descripcion) VALUES (?,?,?,?)",
            [$nom, $cod, $obj, "Proyecto formativo del programa {$programas[$prog][0]}"]);
        $proyIds[$prog] = $pr;
        foreach ($faseNombres as $i => [$fn, $fd]) {
            $estado = $i === 0 ? 'completada' : ($i === 1 ? 'en_ejecucion' : 'planeada');
            $fasesPorProyecto[$prog][] = $ins(
                "INSERT INTO fases_proyecto (proyecto_id, numero_fase, nombre, descripcion, fecha_inicio, fecha_fin, estado)
                 VALUES (?,?,?,?,?,?,?)",
                [$pr, $i + 1, $fn, $fd, $fecha('-' . (12 - 3 * $i) . ' months'), $fecha('-' . (9 - 3 * $i) . ' months'), $estado]
            );
        }
    }

    // -----------------------------------------------------------------
    // FICHAS
    // -----------------------------------------------------------------
    $fichas = [
        ['2845671', 'ADSO', $instructores[0], 'ejecucion', 22],
        ['2912345', 'ADSO', $instructores[1], 'induccion', 12],
        ['3389756', 'CONT', $instructores[2], 'ejecucion', 16],
        ['2823782', 'ACUI', $instructores[3], 'ejecucion', 14],
    ];
    $fichaIds = [];
    foreach ($fichas as [$num, $prog, $lider, $estado]) {
        $fichaIds[$num] = $ins(
            "INSERT INTO fichas (numero_ficha, programa_id, proyecto_id, instructor_id, coordinador_id, estado, fecha_inicio, fecha_fin)
             VALUES (?,?,?,?,?,?,?,?)",
            [$num, $progIds[$prog], $proyIds[$prog], $lider, $coord, $estado, $fecha('-14 months'), $fecha('+10 months')]
        );
    }

    // Asignaciones: en la ficha ADSO principal, dos competencias las lleva
    // otro instructor. Así se ejercitan las tres vías de acceso.
    $nAsig = 0;
    foreach ([[0, 2, 4], [0, 3, 1], [2, 1, 4]] as [$fi, $ci, $ii]) {
        $num = $fichas[$fi][0];
        $prog = $fichas[$fi][1];
        $ins("INSERT INTO asignaciones (ficha_id, competencia_id, instructor_id) VALUES (?,?,?)",
            [$fichaIds[$num], $compIds[$prog][$ci], $instructores[$ii]]);
        $nAsig++;
    }

    // -----------------------------------------------------------------
    // APRENDICES
    // -----------------------------------------------------------------
    $nombresF = ['VALENTINA', 'LAURA', 'CAMILA', 'SOFÍA', 'DANIELA', 'MARIANA', 'ISABELLA', 'ANA MARÍA', 'PAULA', 'NATALIA'];
    $nombresM = ['JUAN DAVID', 'SANTIAGO', 'ANDRÉS', 'MATEO', 'SEBASTIÁN', 'DIEGO', 'CARLOS', 'LUIS', 'SAMUEL', 'NICOLÁS'];
    $apellidos = ['GARCÍA', 'RAMÍREZ', 'VARGAS', 'ROJAS', 'MORENO', 'CASTRO', 'ORTIZ', 'MUÑOZ', 'SUÁREZ', 'CÁRDENAS', 'LOSADA', 'PERDOMO', 'CUÉLLAR'];
    $colores = ['#F59E0B', '#06B6D4', '#84CC16', '#D946EF', '#EAB308', '#3B82F6', '#EF4444', '#10B981', '#8B5CF6'];
    $doc = 1117500100;
    $contador = 0;
    $aprendicesPorFicha = [];

    foreach ($fichas as $fi => [$num, $prog, , , $cantidad]) {
        for ($i = 0; $i < $cantidad; $i++) {
            $contador++;
            $mujer = mt_rand(0, 1) === 1;
            $nombre = ($mujer ? $azar($nombresF) : $azar($nombresM)) . ' ' . $azar($apellidos) . ' ' . $azar($apellidos);
            // Las cinco primeras cuentas tienen correo fácil de recordar.
            $email = $contador <= 5 ? ($contador === 1 ? 'aprendiz@sena.edu.co' : "aprendiz$contador@sena.edu.co")
                                    : "aprendiz$contador.f$num@soy.sena.edu.co";
            $uid = $ins("INSERT INTO usuarios (email, password, nombre, rol, avatar_color) VALUES (?,?,?,?,?)",
                [$email, $hash, $nombre, 'aprendiz', $azar($colores)]);

            // Un par de deserciones y suspensiones por ficha: la analítica
            // de retención necesita casos que contar.
            $estado = match (true) {
                $i === $cantidad - 1 && $fi !== 1 => 'desertado',
                $i === $cantidad - 2 && $fi === 0 => 'suspendido',
                $i === 0 && $fi === 0             => 'etapa_practica',
                default                           => 'matriculado',
            };
            $seguimiento = $estado === 'etapa_practica' ? $instructores[4] : null;
            $aid = $ins(
                "INSERT INTO aprendices (usuario_id, ficha_id, instructor_seguimiento_id, numero_documento, tipo_documento,
                                         genero, fecha_nacimiento, telefono, ciudad, estado)
                 VALUES (?,?,?,?,?,?,?,?,?,?)",
                [$uid, $fichaIds[$num], $seguimiento, (string)$doc++, mt_rand(0, 9) === 0 ? 'TI' : 'CC',
                 $mujer ? 'F' : 'M', $fecha('-' . mt_rand(17, 29) . ' years'), '31' . mt_rand(10000000, 99999999),
                 $azar(['Florencia', 'Morelia', 'Belén de los Andaquíes', 'La Montañita', 'El Doncello']), $estado]
            );
            $aprendicesPorFicha[$num][] = ['id' => $aid, 'usuario' => $uid, 'estado' => $estado];
        }
    }

    // -----------------------------------------------------------------
    // EVALUACIONES E HISTORIAL
    // -----------------------------------------------------------------
    $nEval = $nHist = 0;
    $evalD = [];
    $comentA = ['Demuestra dominio del resultado de aprendizaje.', 'Cumple todos los criterios de evaluación.',
                'Evidencia aplicación práctica de lo aprendido.'];
    $comentD = ['No alcanza los criterios mínimos; requiere plan de mejoramiento.',
                'Debe reforzar la aplicación práctica del resultado.', 'Entrega incompleta frente a la guía.'];

    foreach ($fichas as $fi => [$num, $prog, $lider, $estadoFicha]) {
        $raps = $rapPorPrograma[$prog];
        // Proporción de RAP ya evaluados según lo avanzada que va la ficha.
        $proporcion = $estadoFicha === 'induccion' ? 0.15 : 0.6;
        foreach ($aprendicesPorFicha[$num] as $ap) {
            if ($ap['estado'] === 'desertado') {
                continue;
            }
            foreach ($raps as $ir => $rap) {
                $evaluado = !$rap['etapa'] && $ir < (int)round(count($raps) * $proporcion);
                $concepto = 'pendiente';
                if ($evaluado) {
                    $concepto = mt_rand(1, 100) <= 82 ? 'A' : 'D';
                }
                $fechaEval = $evaluado ? $fecha('-' . mt_rand(5, 200) . ' days') : null;
                $eid = $ins(
                    "INSERT INTO evaluaciones (resultado_aprendizaje_id, aprendiz_id, instructor_id, ficha_id, concepto, comentario, fecha_evaluacion)
                     VALUES (?,?,?,?,?,?,?)",
                    [$rap['id'], $ap['id'], $lider, $fichaIds[$num], $concepto,
                     $concepto === 'A' ? $azar($comentA) : ($concepto === 'D' ? $azar($comentD) : null), $fechaEval]
                );
                $nEval++;
                if ($concepto !== 'pendiente') {
                    $ins("INSERT INTO historial_evaluaciones (evaluacion_id, usuario_id, concepto_anterior, concepto_nuevo, motivo, fecha_cambio)
                          VALUES (?,?,?,?,?,?)",
                        [$eid, $lider, 'pendiente', $concepto, 'Juicio evaluativo inicial', $fechaEval . ' 10:00:00']);
                    $nHist++;
                }
                if ($concepto === 'D') {
                    $evalD[] = ['id' => $eid, 'aprendiz' => $ap['id'], 'usuario' => $ap['usuario'],
                                'ficha' => $fichaIds[$num], 'instructor' => $lider];
                }
            }
        }
    }

    // -----------------------------------------------------------------
    // PLANES DE MEJORAMIENTO: uno por cada tercer RAP en D, en varios estados
    // -----------------------------------------------------------------
    $nPlanes = 0;
    foreach ($evalD as $k => $d) {
        if ($k % 3 !== 0) {
            continue;
        }
        $estado = ['abierto', 'en_curso', 'no_cumplido'][intdiv($k, 3) % 3];
        $ins("INSERT INTO planes_mejoramiento (evaluacion_id, aprendiz_id, ficha_id, instructor_id, actividades,
                                               fecha_inicio, fecha_limite, estado, observaciones_cierre, fecha_cierre,
                                               cerrado_por, creado_por)
              VALUES (?,?,?,?,?,?,?,?,?,?,?,?)",
            [$d['id'], $d['aprendiz'], $d['ficha'], $d['instructor'],
             'Repetir la evidencia de desempeño corrigiendo las observaciones y sustentarla ante el instructor.',
             $fecha('-20 days'), $estado === 'no_cumplido' ? $fecha('-2 days') : $fecha('+' . mt_rand(3, 25) . ' days'),
             $estado, $estado === 'no_cumplido' ? 'El aprendiz no entregó la evidencia en el plazo.' : null,
             $estado === 'no_cumplido' ? $fecha('-1 days') . ' 09:00:00' : null,
             $estado === 'no_cumplido' ? $d['instructor'] : null, $d['instructor']]);
        $nPlanes++;
    }

    // -----------------------------------------------------------------
    // ACTIVIDADES DEL PROYECTO POR FASE
    // -----------------------------------------------------------------
    $nAct = 0;
    foreach ($fichas as [$num, $prog, $lider, $estadoFicha]) {
        foreach ($fasesPorProyecto[$prog] as $i => $faseId) {
            foreach ([1, 2] as $j) {
                $estado = match (true) {
                    $i === 0                           => 'completada',
                    $i === 1 && $j === 1               => $estadoFicha === 'induccion' ? 'en_progreso' : 'completada',
                    $i === 1                           => 'en_progreso',
                    $i === 2 && $j === 1 && $estadoFicha === 'ejecucion' => 'en_progreso',
                    default                            => 'pendiente',
                };
                $avance = ['completada' => 100, 'en_progreso' => mt_rand(20, 80), 'pendiente' => 0][$estado];
                $ins("INSERT INTO actividades (ficha_id, competencia_id, fase_id, nombre, descripcion, fecha_inicio, fecha_fin,
                                               responsable_id, estado, cumplimiento_porcentaje)
                      VALUES (?,?,?,?,?,?,?,?,?,?)",
                    [$fichaIds[$num], $compIds[$prog][min($i, count($compIds[$prog]) - 1)], $faseId,
                     "{$faseNombres[$i][0]}: actividad de aprendizaje $j",
                     "Actividad $j de la fase de {$faseNombres[$i][0]} del proyecto formativo.",
                     $fecha('-' . (12 - 3 * $i) . ' months'), $fecha('-' . (9 - 3 * $i) . ' months +' . $j . ' weeks'),
                     $lider, $estado, $avance]);
                $nAct++;
            }
        }
    }

    // -----------------------------------------------------------------
    // RETROALIMENTACIÓN, NOTIFICACIONES Y CALENDARIO
    // -----------------------------------------------------------------
    $nRetro = 0;
    foreach (array_slice($evalD, 0, 12) as $d) {
        $ins("INSERT INTO retroalimentacion (evaluacion_id, aprendiz_id, instructor_id, tipo, contenido) VALUES (?,?,?,?,?)",
            [$d['id'], $d['aprendiz'], $d['instructor'], 'aspecto_mejorar',
             'Revise la guía de aprendizaje y corrija los puntos señalados antes de la nueva entrega.']);
        $nRetro++;
    }
    $nNotif = 0;
    foreach (array_slice($evalD, 0, 8) as $d) {
        $ins("INSERT INTO notificaciones (usuario_id, titulo, mensaje, tipo, url) VALUES (?,?,?,?,?)",
            [$d['usuario'], 'Resultado de aprendizaje por mejorar',
             'Tienes un resultado de aprendizaje en D. Revisa tu plan de mejoramiento.', 'warning',
             (defined('APP_URL') ? APP_URL : '') . '/index.php/mejoramiento']);
        $nNotif++;
    }
    $nEv = 0;
    foreach ($fichas as $k => [$num]) {
        $ins("INSERT INTO eventos_calendario (titulo, descripcion, fecha, ficha_id, creado_por, color) VALUES (?,?,?,?,?,?)",
            ['Socialización de avance del proyecto', 'Presentación de la fase en curso ante el equipo instructor.',
             $fecha('+' . (7 + 3 * $k) . ' days'), $fichaIds[$num], $coord, '#39A900']);
        $nEv++;
    }

    // Recuento guardado coherente con el real (lo usa quien lea la tabla).
    $db->exec("UPDATE fichas f SET cantidad_aprendices =
               (SELECT COUNT(*) FROM aprendices a WHERE a.ficha_id = f.id AND a.estado <> 'desertado')");

    return [
        'usuarios'                => 1 + count($instructores) + $contador,
        'programas'               => count($progIds),
        'resultados de aprendizaje' => $nRap,
        'fichas'                  => count($fichaIds),
        'asignaciones'            => $nAsig,
        'evaluaciones'            => $nEval,
        'historial'               => $nHist,
        'planes de mejoramiento'  => $nPlanes,
        'actividades'             => $nAct,
        'retroalimentaciones'     => $nRetro,
        'notificaciones'          => $nNotif,
        'eventos de calendario'   => $nEv,
    ];
};
