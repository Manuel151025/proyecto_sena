# Rutas y permisos

<!-- Generado por bin/generar-docs.php. No editar a mano: vuelve a ejecutar el script. -->

La tabla de rutas (`config/rutas.php`) es también la matriz de control de acceso: cada pantalla y cada operación declara qué roles la alcanzan. El enrutador rechaza a los demás antes de llegar al controlador, y los servicios vuelven a comprobar el permiso sobre el dato concreto (la ficha, el RAP, el aprendiz).

- **Pantallas y descargas** (`GET`), y envíos sin `action` (`POST`).
- **Acciones**: `POST` a la misma ruta con el campo `action`; cada una con su propio permiso.

| Método | Ruta | Acción | Controlador::método | Roles |
|---|---|---|---|---|
| GET | `/actividades` | — | ActividadesController::index | Coordinador, Instructor, Aprendiz |
| POST | `/actividades` | `avance` | ActividadesController::avance | Coordinador, Instructor |
| POST | `/actividades` | `crear` | ActividadesController::crear | Coordinador, Instructor |
| POST | `/actividades` | `editar` | ActividadesController::editar | Coordinador, Instructor |
| POST | `/actividades` | `eliminar` | ActividadesController::eliminar | Coordinador, Instructor |
| GET | `/api/notificaciones` | — | NotificacionesController::listar | Coordinador, Instructor, Aprendiz |
| POST | `/api/notificaciones` | — | NotificacionesController::marcar | Coordinador, Instructor, Aprendiz |
| GET | `/asignaciones` | — | AsignacionesController::index | Coordinador, Instructor |
| POST | `/asignaciones` | `asignar` | AsignacionesController::asignar | Coordinador |
| POST | `/asignaciones` | `eliminar` | AsignacionesController::eliminar | Coordinador |
| POST | `/asignaciones` | `reasignar` | AsignacionesController::reasignar | Coordinador |
| GET | `/calendario` | — | CalendarioController::index | Coordinador, Instructor, Aprendiz |
| POST | `/calendario` | `crear` | CalendarioController::crear | Coordinador, Instructor |
| POST | `/calendario` | `eliminar` | CalendarioController::eliminar | Coordinador, Instructor |
| GET | `/calendario/api` | — | CalendarioController::apiEvents | Coordinador, Instructor, Aprendiz |
| GET | `/competencias` | — | CompetenciasController::index | Coordinador, Instructor |
| POST | `/competencias` | `crear` | CompetenciasController::crear | Coordinador |
| POST | `/competencias` | `editar` | CompetenciasController::editar | Coordinador |
| POST | `/competencias` | `eliminar` | CompetenciasController::eliminar | Coordinador |
| GET | `/competencias/importar` | — | ImportacionController::formulario | Coordinador |
| POST | `/competencias/importar` | — | ImportacionController::analizar | Coordinador |
| POST | `/competencias/importar` | `cancelar` | ImportacionController::cancelar | Coordinador |
| POST | `/competencias/importar` | `confirmar` | ImportacionController::confirmar | Coordinador |
| GET | `/competencias/importar/plantilla` | — | ImportacionController::plantilla | Coordinador |
| GET | `/configuracion` | — | ConfiguracionController::index | Coordinador |
| POST | `/configuracion` | `guardar` | ConfiguracionController::guardar | Coordinador |
| GET | `/dashboard` | — | DashboardController::index | Coordinador, Instructor, Aprendiz |
| GET | `/estructura` | — | EstructuraController::index | Coordinador |
| GET | `/estructura/importar` | — | EstructuraController::importar | Coordinador |
| POST | `/estructura/importar` | — | EstructuraController::analizar | Coordinador |
| POST | `/estructura/importar` | `cancelar` | EstructuraController::cancelar | Coordinador |
| POST | `/estructura/importar` | `confirmar` | EstructuraController::confirmar | Coordinador |
| GET | `/evaluaciones` | — | EvaluacionesController::index | Coordinador, Instructor, Aprendiz |
| POST | `/evaluaciones` | `evaluar` | EvaluacionesController::evaluar | Coordinador, Instructor |
| GET | `/evaluaciones/exportar` | — | EvaluacionesController::exportar | Coordinador, Instructor, Aprendiz |
| GET | `/evaluaciones/importar` | — | ImportacionController::formulario | Coordinador, Instructor |
| POST | `/evaluaciones/importar` | — | ImportacionController::analizar | Coordinador, Instructor |
| POST | `/evaluaciones/importar` | `cancelar` | ImportacionController::cancelar | Coordinador, Instructor |
| POST | `/evaluaciones/importar` | `confirmar` | ImportacionController::confirmar | Coordinador, Instructor |
| GET | `/evaluaciones/importar/plantilla` | — | ImportacionController::plantilla | Coordinador, Instructor |
| GET | `/evidencias` | — | EvidenciasController::index | Coordinador, Instructor, Aprendiz |
| POST | `/evidencias` | `eliminar` | EvidenciasController::eliminar | Coordinador, Aprendiz |
| POST | `/evidencias` | `enviar` | EvidenciasController::enviar | Aprendiz |
| POST | `/evidencias` | `revisar` | EvidenciasController::revisar | Coordinador, Instructor |
| GET | `/evidencias/archivo` | — | EvidenciasController::archivo | Coordinador, Instructor, Aprendiz |
| GET | `/fases` | — | FasesController::index | Coordinador, Instructor, Aprendiz |
| POST | `/fases` | `crear` | FasesController::crear | Coordinador, Instructor |
| POST | `/fases` | `editar` | FasesController::editar | Coordinador, Instructor |
| POST | `/fases` | `eliminar` | FasesController::eliminar | Coordinador |
| GET | `/fichas` | — | FichaController::index | Coordinador, Instructor, Aprendiz |
| POST | `/fichas` | `crear` | FichaController::crear | Coordinador |
| POST | `/fichas` | `editar` | FichaController::editar | Coordinador |
| POST | `/fichas` | `eliminar` | FichaController::eliminar | Coordinador |
| GET | `/fichas/exportar` | — | FichaController::exportar | Coordinador, Instructor |
| GET | `/fichas/ver` | — | FichaController::ver | Coordinador, Instructor, Aprendiz |
| POST | `/logout` | — | SesionController::cerrar | Coordinador, Instructor, Aprendiz |
| GET | `/logs` | — | LogsController::index | Coordinador |
| GET | `/logs/exportar` | — | LogsController::exportar | Coordinador |
| GET | `/matriculas` | — | MatriculaController::index | Coordinador, Instructor |
| POST | `/matriculas` | `editar` | MatriculaController::editar | Coordinador |
| POST | `/matriculas` | `matricular` | MatriculaController::matricular | Coordinador |
| POST | `/matriculas` | `retirar` | MatriculaController::retirar | Coordinador |
| GET | `/matriculas/exportar` | — | MatriculaController::exportar | Coordinador, Instructor |
| GET | `/matriculas/importar` | — | ImportacionController::formulario | Coordinador |
| POST | `/matriculas/importar` | — | ImportacionController::analizar | Coordinador |
| POST | `/matriculas/importar` | `cancelar` | ImportacionController::cancelar | Coordinador |
| POST | `/matriculas/importar` | `confirmar` | ImportacionController::confirmar | Coordinador |
| GET | `/matriculas/importar/plantilla` | — | ImportacionController::plantilla | Coordinador |
| GET | `/mejoramiento` | — | MejoramientoController::index | Coordinador, Instructor, Aprendiz |
| POST | `/mejoramiento` | `cerrar` | MejoramientoController::cerrar | Coordinador, Instructor |
| POST | `/mejoramiento` | `crear` | MejoramientoController::crear | Coordinador, Instructor |
| POST | `/mejoramiento` | `editar` | MejoramientoController::editar | Coordinador, Instructor |
| GET | `/mejoramiento/exportar` | — | MejoramientoController::exportar | Coordinador, Instructor, Aprendiz |
| GET | `/perfil` | — | PerfilController::index | Coordinador, Instructor, Aprendiz |
| POST | `/perfil` | `contrasena` | PerfilController::contrasena | Coordinador, Instructor, Aprendiz |
| POST | `/perfil` | `datos` | PerfilController::datos | Coordinador, Instructor, Aprendiz |
| GET | `/programas` | — | ProgramasController::index | Coordinador, Instructor |
| POST | `/programas` | `crear` | ProgramasController::crear | Coordinador |
| POST | `/programas` | `editar` | ProgramasController::editar | Coordinador |
| POST | `/programas` | `eliminar` | ProgramasController::eliminar | Coordinador |
| GET | `/proyectos` | — | ProyectosController::index | Coordinador, Instructor, Aprendiz |
| POST | `/proyectos` | `crear` | ProyectosController::crear | Coordinador |
| POST | `/proyectos` | `editar` | ProyectosController::editar | Coordinador |
| POST | `/proyectos` | `eliminar` | ProyectosController::eliminar | Coordinador |
| GET | `/reportes` | — | ReportesController::index | Coordinador, Instructor |
| GET | `/reportes/descargar` | — | ReportesController::descargar | Coordinador, Instructor |
| GET | `/resultados-aprendizaje` | — | ResultadosAprendizajeController::index | Coordinador, Instructor |
| POST | `/resultados-aprendizaje` | `crear` | ResultadosAprendizajeController::crear | Coordinador |
| POST | `/resultados-aprendizaje` | `editar` | ResultadosAprendizajeController::editar | Coordinador |
| POST | `/resultados-aprendizaje` | `eliminar` | ResultadosAprendizajeController::eliminar | Coordinador |
| GET | `/resultados-aprendizaje/importar` | — | ImportacionController::formulario | Coordinador |
| POST | `/resultados-aprendizaje/importar` | — | ImportacionController::analizar | Coordinador |
| POST | `/resultados-aprendizaje/importar` | `cancelar` | ImportacionController::cancelar | Coordinador |
| POST | `/resultados-aprendizaje/importar` | `confirmar` | ImportacionController::confirmar | Coordinador |
| GET | `/resultados-aprendizaje/importar/plantilla` | — | ImportacionController::plantilla | Coordinador |
| GET | `/retroalimentacion` | — | RetroalimentacionController::index | Coordinador, Instructor, Aprendiz |
| POST | `/retroalimentacion` | `registrar` | RetroalimentacionController::registrar | Coordinador, Instructor |
| GET | `/seguimiento` | — | SeguimientoController::index | Coordinador, Instructor, Aprendiz |
| POST | `/seguimiento` | `evaluar` | SeguimientoController::evaluar | Coordinador, Instructor |
| POST | `/seguimiento` | `observar` | SeguimientoController::observar | Coordinador, Instructor |
| GET | `/usuarios` | — | UsuarioController::index | Coordinador |
| POST | `/usuarios` | `crear` | UsuarioController::crear | Coordinador |
| POST | `/usuarios` | `editar` | UsuarioController::editar | Coordinador |
| POST | `/usuarios` | `estado` | UsuarioController::estado | Coordinador |
| POST | `/usuarios` | `restablecer` | UsuarioController::restablecer | Coordinador |
| GET | `/usuarios/exportar` | — | UsuarioController::exportar | Coordinador |
| GET | `/usuarios/importar` | — | ImportacionController::formulario | Coordinador |
| POST | `/usuarios/importar` | — | ImportacionController::analizar | Coordinador |
| POST | `/usuarios/importar` | `cancelar` | ImportacionController::cancelar | Coordinador |
| POST | `/usuarios/importar` | `confirmar` | ImportacionController::confirmar | Coordinador |
| GET | `/usuarios/importar/plantilla` | — | ImportacionController::plantilla | Coordinador |

**111 destinos.** La prueba `tests/Integration/RutasYPermisosTest.php` cuenta este número: añadir o quitar una ruta obliga a revisar su permiso.
