# Historias de usuario

**Sistema de Seguimiento de Proyectos Formativos — SENA, Centro Tecnológico de la Amazonia**

| | |
|---|---|
| Versión | 3.0 (septiembre de 2026) |
| Metodología | Scrum, 4 sprints |
| Total | 47 historias en 12 épicas · 47 implementadas y verificadas |
| Documentos relacionados | [Flujos](FLUJOS.md) · [Analítica](ANALITICA.md) · [Rutas y permisos](RUTAS_Y_PERMISOS.md) · [Manual de usuario](MANUAL_USUARIO.md) |

---

## 1. Contexto

Cada ficha de formación del centro desarrolla un **proyecto formativo** dividido en fases, y sus aprendices deben demostrar los **resultados de aprendizaje (RAP)** de las competencias del programa. El seguimiento se llevaba en hojas de cálculo de cada instructor y en los reportes de Sofia Plus, lo que causaba:

1. **Sin fuente única**: la coordinación no veía el avance real de una ficha sin pedir archivos.
2. **Sin trazabilidad**: cambiar un juicio no dejaba constancia de quién, cuándo ni por qué.
3. **Nivelación manual**: detectar RAP en D y dar seguimiento a su plan de mejoramiento dependía de revisar cada archivo.
4. **Sin visión del proyecto**: las fases y actividades del proyecto formativo no estaban ligadas al avance de la ficha.

El sistema reúne la estructura curricular, las fichas y matrículas, el proyecto formativo, los juicios con su historial, las evidencias, los planes de mejoramiento y la analítica por rol, con permisos por rol y por dato y bitácora de auditoría.

## 2. Actores

| Rol | Alcance |
|---|---|
| **Coordinador** | Todo el centro: usuarios, estructura curricular, fichas, matrículas, asignaciones, proyectos; consulta y califica cualquier juicio; reportes, bitácora y configuración. |
| **Instructor** | Las fichas en las que tiene autoridad (líder, competencia asignada o seguimiento de etapa práctica). Califica solo los RAP que le corresponden; gestiona actividades, planes y evidencias de esos aprendices. |
| **Aprendiz** | Lo suyo: juicios, expediente, evidencias, planes, retroalimentación pública, proyecto y calendario de su ficha. |

## 3. Requisitos y trazabilidad

| Requisito | Descripción | Historias |
|---|---|---|
| **RF01** | Gestión de programas, competencias y resultados de aprendizaje | HU-10, HU-11, HU-12, HU-13 |
| **RF02** | Proyectos formativos asociados a fichas, con fases y actividades | HU-20, HU-21, HU-22 |
| **RF03** | Evaluación de RAP con juicio A (aprobado), D (aún no aprobado) o pendiente | HU-23, HU-24, HU-25, HU-26, HU-27 |
| **RF04** | Panel de progreso individual y grupal | HU-15, HU-29, HU-30, HU-33, HU-34, HU-35 |
| **RF05** | Reportes por instructor, ficha y competencia | HU-36, HU-37 |
| **RNF01** | Control de acceso por roles | HU-01 a HU-06, HU-18 |
| **RNF02** | Histórico de evaluaciones con trazabilidad | HU-25, HU-38 |
| **RNF03** | Exportación a PDF y Excel | HU-09, HU-19, HU-24, HU-36, HU-37 |
| RNF04 (complementario) | Uso desde teléfono móvil y modo oscuro | HU-43, HU-44 |
| RNF05 (complementario) | Seguridad de la información (OWASP) | HU-05, HU-06, HU-27, HU-45 · [SEGURIDAD.md](SEGURIDAD.md) |
| RNF06 (complementario) | Instalación reproducible y pruebas automáticas | HU-46, HU-47 |

## 4. Plan de sprints

| Sprint | Objetivo | Historias |
|---|---|---|
| 1 | Acceso seguro, usuarios y estructura curricular | HU-01 a HU-13 |
| 2 | Fichas, matrículas, asignaciones y proyecto formativo | HU-14 a HU-22 |
| 3 | Evaluación, evidencias, seguimiento y planes de mejoramiento | HU-23 a HU-32 |
| 4 | Analítica, reportes, auditoría, experiencia móvil, seguridad y operación | HU-33 a HU-47 |

Convenciones: cada historia sigue **Como / quiero / para**, con criterios de aceptación verificables y su trazabilidad (ruta → código). Estado ✅ = implementada y cubierta por pruebas.

---

## Épica 1 — Acceso y control de acceso (RNF01)

### HU-01 · Iniciar sesión según mi rol
> **Como** usuario **quiero** entrar con mi correo institucional y contraseña **para** ver solo lo que me corresponde.

1. Con credenciales correctas entro al panel de mi rol; con incorrectas veo un mensaje que no revela si falló el correo o la contraseña.
2. Las contraseñas se guardan con bcrypt; al entrar se regenera el identificador de sesión.
3. Una cuenta inactiva no entra; si la coordinación desactiva mi cuenta o cambia mi rol, se aplica en mi siguiente petición.
4. Cada pestaña tiene su propia sesión, y la sesión caduca tras 2 horas sin actividad.

`login.php` → `includes/auth.php` · Sprint 1 · Alta · ✅

### HU-02 · Acceder solo a lo que mi rol permite
> **Como** coordinador **quiero** que cada pantalla y cada operación declaren qué roles las alcanzan **para** que nadie opere fuera de su función.

1. La tabla de rutas declara los roles de cada pantalla y de cada acción ([RUTAS_Y_PERMISOS.md](RUTAS_Y_PERMISOS.md)).
2. Una operación no permitida, aunque se fuerce desde fuera de la interfaz, se rechaza, redirige al panel con un aviso y queda en la bitácora.
3. Una prueba automática falla si una escritura administrativa queda abierta a otro rol o si cambia el número de rutas sin revisarlo.

`config/rutas.php` · `Core\Router` · Sprint 1 · Alta · ✅

### HU-03 · Recuperar mi contraseña por correo
> **Como** usuario **quiero** restablecer mi contraseña **para** no depender de la coordinación si la olvido.

1. El mensaje es el mismo exista o no la cuenta (no revela correos registrados).
2. El enlace caduca en 30 minutos, sirve una sola vez y el token se guarda solo como hash.
3. La nueva contraseña cumple la misma política que en el perfil.

`recover.php` · Sprint 1 · Alta · ✅

### HU-04 · Cambiar la contraseña temporal
> **Como** usuario con cuenta nueva **quiero** que se me obligue a cambiar la clave temporal **para** que solo yo la conozca.

1. Mientras tenga clave temporal, cualquier pantalla me lleva al perfil (salvo cerrar sesión).
2. Debo escribir la actual; la nueva cumple la política (8+ caracteres, letras y números, sin mi usuario de correo) y no puede ser igual.
3. Al cambiarla se regenera la sesión y queda en la bitácora.

`/perfil` · `PerfilController::contrasena` · `PoliticaContrasena` · Sprint 1 · Alta · ✅

### HU-05 · Protección contra peticiones falsificadas
> **Como** usuario **quiero** que nadie pueda enviar formularios en mi nombre desde otro sitio **para** proteger mis operaciones.

1. Todo envío POST lleva un token CSRF y se rechaza sin él.
2. La aplicación no puede embeberse en otro sitio (clickjacking) y solo acepta formularios hacia sí misma.

`includes/session.php` · `Core\Support\Seguridad` · Sprint 1 · Alta · ✅

### HU-06 · Bloqueo temporal tras intentos fallidos
> **Como** coordinador **quiero** que los intentos repetidos de adivinar contraseñas se frenen **para** proteger las cuentas.

1. Tras 5 fallos con el mismo correo, o 20 desde la misma IP, en 15 minutos, el acceso se bloquea 15 minutos.
2. La recuperación de contraseña también está limitada.
3. Los intentos fallidos quedan en la bitácora.

`Core\Services\LimitadorIntentos` · Sprint 1 · Alta · ✅

## Épica 2 — Usuarios

### HU-07 · Administrar las cuentas
> **Como** coordinador **quiero** crear, editar, activar o desactivar cuentas y restablecer claves **para** gestionar quién usa el sistema.

1. Correo único (sin distinguir mayúsculas), nombre validado, rol y color de avatar de una paleta cerrada.
2. Crear o restablecer genera una clave temporal que se muestra una sola vez.
3. No puedo desactivarme ni quitarme el rol de coordinador; siempre queda al menos un coordinador activo.
4. Listado paginado con búsqueda y filtros por rol y estado.

`/usuarios` · `UsuariosService` · Sprint 1 · Alta · ✅

### HU-08 · Importar usuarios en dos pasos
> **Como** coordinador **quiero** cargar muchas cuentas desde CSV o Excel y revisar el resultado antes de guardar **para** no dejar datos a medias.

1. Acepta CSV, XLSX y XLS de hasta 5 MB; detecta separador y codificación.
2. La vista previa marca cada fila válida o con sus errores (correo repetido, rol inválido, filas duplicadas en el archivo).
3. Al confirmar se guardan solo las filas válidas y se muestran las claves temporales una vez.

`/usuarios/importar` · `ImportadorUsuarios` · [formato](FORMATOS_IMPORTACION.md) · Sprint 1 · Media · ✅

### HU-09 · Exportar usuarios
> **Como** coordinador **quiero** descargar el listado de cuentas **para** reportarlo o archivarlo.

1. Excel (.xlsx real) o CSV, con los filtros aplicados; las celdas que parecen fórmulas se neutralizan.

`/usuarios/exportar` · Sprint 1 · Baja · ✅

## Épica 3 — Estructura curricular (RF01)

### HU-10 · Gestionar programas de formación
> **Como** coordinador **quiero** registrar los programas **para** organizar competencias, fichas y proyectos.

1. Código único normalizado a mayúsculas, nombre y duración validados.
2. Un programa con fichas o competencias no se elimina; se desactiva.

`/programas` · `ProgramasService` · Sprint 1 · Alta · ✅

### HU-11 · Gestionar competencias y resultados de aprendizaje
> **Como** coordinador **quiero** mantener las competencias de cada programa y sus RAP **para** que el sistema sepa qué se evalúa.

1. Código único por programa; marca de etapa práctica en la competencia.
2. Al crear un RAP, cada aprendiz en formación del programa recibe su evaluación pendiente.
3. No se elimina una competencia con RAP, actividades o asignaciones, ni un RAP con juicios emitidos o evidencias.
4. El instructor consulta la estructura; solo coordinación la modifica.

`/competencias` · `/resultados-aprendizaje` · `CompetenciasService` · Sprint 1 · Alta · ✅

### HU-12 · Importar la estructura desde el PDF del programa
> **Como** coordinador **quiero** cargar el diseño curricular publicado en PDF **para** no transcribir competencias y RAP a mano.

1. Se analiza el PDF y se muestra lo que se creará antes de confirmar.

`/estructura/importar` · `EstructuraImportService` · Sprint 1 · Media · ✅

### HU-13 · Importar competencias y RAP desde CSV o Excel
> **Como** coordinador **quiero** cargar competencias y RAP desde una hoja de cálculo **para** actualizar la estructura masivamente.

1. Importación en dos pasos con vista previa; valida programa, código, horas y duplicados.

`/competencias/importar` · `/resultados-aprendizaje/importar` · Sprint 1 · Media · ✅

## Épica 4 — Fichas, matrículas y asignaciones

### HU-14 · Gestionar fichas de formación
> **Como** coordinador **quiero** crear fichas con su programa, proyecto formativo e instructor líder **para** organizar los grupos.

1. Número de ficha único; el líder debe ser un instructor activo; fechas coherentes.
2. No se cambia el programa si hay juicios emitidos, ni el proyecto si hay actividades en sus fases.
3. Una ficha con aprendices o actividades no se elimina; se pasa a «cierre».
4. Cambiar el líder pasa al nuevo las evaluaciones pendientes que le corresponden y le avisa.

`/fichas` · `FichasService` · Sprint 2 · Alta · ✅

### HU-15 · Ver el avance de una ficha
> **Como** instructor o coordinador **quiero** ver el estado de una ficha y de cada aprendiz **para** actuar a tiempo.

1. Aprendices activos, desempeño con semáforo, avance de RAP, RAP en D, planes abiertos y avance del proyecto formativo.
2. Lista de aprendices con sus cifras y semáforo, filtrable; el aprendiz ve solo su fila.
3. Una ficha sin juicios no aparece como crítica («sin juicios»).

`/fichas/ver` · `FichaModel` · Sprint 2 · Alta · ✅

### HU-16 · Matricular aprendices
> **Como** coordinador **quiero** matricular aprendices uno a uno o en bloque **para** preparar su seguimiento.

1. Se crea la cuenta con clave temporal y la evaluación pendiente de cada RAP del programa.
2. Documento según su tipo (CC, TI y CE solo dígitos), correo y documento únicos; una ficha en cierre no admite matrículas.
3. La matrícula masiva es en dos pasos con vista previa.

`/matriculas` · `/matriculas/importar` · `MatriculasService` · Sprint 2 · Alta · ✅

### HU-17 · Cambiar el estado o la ficha de una matrícula
> **Como** coordinador **quiero** actualizar el estado de un aprendiz o trasladarlo **para** reflejar su situación real.

1. Con juicios emitidos solo se traslada a otra ficha del mismo programa; el traslado mueve sus registros y completa su rejilla.
2. La etapa práctica exige instructor de seguimiento.
3. Retirar deja al aprendiz desertado y sin acceso, sin borrar su historial; reactivarlo le devuelve el acceso.

`/matriculas` (editar, retirar) · Sprint 2 · Alta · ✅

### HU-18 · Asignar instructores por competencia
> **Como** coordinador **quiero** decidir quién califica cada competencia en cada ficha **para** repartir la evaluación.

1. Solo competencias del programa de la ficha y que no sean de etapa práctica; instructor activo.
2. Asignar, cambiar o quitar mueve las evaluaciones pendientes al nuevo responsable (o de vuelta al líder).
3. El instructor ve las asignaciones de sus fichas; solo coordinación las cambia.

`/asignaciones` · `AsignacionesService` · Sprint 2 · Alta · ✅

### HU-19 · Exportar fichas y matrículas
> **Como** coordinador o instructor **quiero** descargar el listado de fichas, el detalle de una ficha y las matrículas **para** compartirlos.

1. Excel o CSV con los filtros aplicados y los indicadores de cada ficha.

`/fichas/exportar` · `/matriculas/exportar` · Sprint 2 · Media · ✅

## Épica 5 — Proyecto formativo (RF02)

### HU-20 · Gestionar proyectos formativos y sus fases
> **Como** coordinador **quiero** registrar el proyecto formativo de cada programa con sus fases **para** estructurar la formación de las fichas.

1. Código único, programa, objetivo; fases numeradas con fechas y estado.
2. El proyecto se asocia a las fichas; el aprendiz lo consulta.

`/proyectos` · `/fases` · Sprint 2 · Alta · ✅

### HU-21 · Planear actividades de aprendizaje por fase
> **Como** instructor **quiero** crear las actividades de mi ficha dentro de cada fase **para** organizar el trabajo del proyecto.

1. Solo en fichas donde tengo autoridad y en fases del proyecto de esa ficha; opcionalmente ligadas a una competencia.
2. Registro de avance coherente (completada = 100 %, pendiente = 0 %).

`/actividades` · `ActividadesService` · Sprint 2 · Alta · ✅

### HU-22 · Ver el avance del proyecto formativo de la ficha
> **Como** instructor, coordinador o aprendiz **quiero** saber cuánto avanza el proyecto de la ficha **para** compararlo con el cronograma.

1. Avance de cada fase y del proyecto = promedio de sus actividades (sin las canceladas), calculado al leer.
2. Las entregas aparecen en el calendario y las próximas a vencer en los paneles.

`/fases` · `/fichas/ver` · paneles · Sprint 2 · Media · ✅

## Épica 6 — Evaluación (RF03)

### HU-23 · Emitir el juicio de un RAP
> **Como** instructor **quiero** registrar A o D en los RAP que califico **para** dejar constancia del resultado.

1. Solo puedo calificar lo que me corresponde (asignación, líder o seguimiento de etapa práctica); la coordinación puede calificar cualquiera.
2. No se registran juicios nuevos a aprendices desertados.
3. La retroalimentación se muestra al aprendiz, que recibe un aviso.

`/evaluaciones` · `/seguimiento` · `JuiciosService` · Sprint 3 · Alta · ✅

### HU-24 · Consultar los juicios
> **Como** usuario **quiero** ver los juicios que me corresponden **para** conocer el estado de la evaluación.

1. Coordinación todo; instructor lo que califica; aprendiz lo suyo.
2. Cifras A/D/pendientes coherentes con el listado; filtros por ficha, concepto y búsqueda; paginación.
3. Exportación a Excel o CSV con los mismos filtros.

`/evaluaciones` · `/evaluaciones/exportar` · Sprint 3 · Alta · ✅

### HU-25 · Cambiar un juicio dejando constancia (RNF02)
> **Como** coordinador **quiero** que cada cambio de juicio quede registrado **para** poder auditarlo.

1. Cambiar un juicio emitido exige motivo; no se puede devolver a «pendiente» a mano.
2. Cada cambio guarda anterior, nuevo, motivo, quién y cuándo, venga de la pantalla, de una evidencia, de un plan o de una importación.
3. El detalle de cada juicio muestra su historial.

`EvaluacionService` · `historial_evaluaciones` · Sprint 3 · Alta · ✅

### HU-26 · Importar juicios desde Sofia Plus
> **Como** instructor **quiero** cargar el reporte de juicios de Sofia Plus **para** no transcribirlos.

1. Toma la ficha y el programa del reporte; la ficha debe existir y ser mía (o soy coordinación).
2. «NO APROBADO» entra como D; un «POR EVALUAR» no borra un juicio emitido; un cambio A↔D queda en el historial.
3. El instructor solo carga los RAP que califica; la estructura o aprendices que falten solo los crea la coordinación, con aviso en la vista previa.
4. Un aviso por aprendiz afectado.

`/evaluaciones/importar` · `ImportadorJuicios` · Sprint 3 · Alta · ✅

### HU-27 · Entregar y revisar evidencias
> **Como** aprendiz **quiero** enviar mis evidencias ligadas a un RAP **para** demostrar lo aprendido; **como** instructor **quiero** revisarlas y, si corresponde, registrar el juicio.

1. Archivo de hasta 10 MB en PDF, Office, imagen o texto; se comprueba el contenido real, no solo la extensión.
2. Solo el aprendiz, quien revisa y la coordinación descargan el archivo.
3. La revisión (aprobada, requiere ajustes, rechazada) lleva retroalimentación; registrar el juicio del RAP es una decisión aparte.
4. El aprendiz puede retirar una evidencia mientras no se haya revisado.

`/evidencias` · `EvidenciasService` · Sprint 3 · Alta · ✅

### HU-28 · Dar y consultar retroalimentación
> **Como** instructor **quiero** registrar fortalezas, aspectos a mejorar y recomendaciones, públicas o privadas **para** orientar al aprendiz y coordinar con el equipo.

1. Solo para aprendices con los que tengo relación; las privadas no las ve el aprendiz ni generan aviso.
2. El aprendiz ve su retroalimentación pública; el instructor la de sus aprendices.

`/retroalimentacion` · expediente de `/seguimiento` · `RetroalimentacionService` · Sprint 3 · Media · ✅

## Épica 7 — Seguimiento y nivelación (RF04)

### HU-29 · Consultar el expediente de un aprendiz
> **Como** instructor **quiero** ver en una sola pantalla los RAP de un aprendiz por competencia **para** hacer su seguimiento.

1. Cifras, semáforo y avance del aprendiz; RAP agrupados por competencia con su juicio, quién lo califica y si tiene plan.
2. Puedo calificar desde ahí solo los RAP que me corresponden; puedo registrar observaciones.
3. El aprendiz ve su propio expediente.

`/seguimiento` · `SeguimientoModel` · Sprint 3 · Alta · ✅

### HU-30 · Semáforo de riesgo académico
> **Como** instructor **quiero** que el sistema clasifique a los aprendices **para** priorizar a quién atender.

1. Crítico: menos del 60 % en A sobre lo evaluado o más de 2 RAP en D. Riesgo: menos del 80 % o algún D. Al día: el resto. Sin juicios: aún no hay evaluados.
2. La misma regla en fichas, expediente, paneles y reportes.

`Core\Support\Semaforo` · Sprint 3 · Alta · ✅

### HU-31 · Plan de mejoramiento de un RAP en D
> **Como** instructor **quiero** abrir, acompañar y cerrar el plan de mejoramiento de un RAP no aprobado **para** que el aprendiz lo nivele con constancia.

1. Se abre sobre un RAP en D, uno vigente por RAP, con actividades y fecha límite (hasta 180 días); el aprendiz recibe un aviso.
2. Pasa a «en curso» cuando el aprendiz entrega evidencia de ese RAP.
3. Cerrar como cumplido pasa el RAP a A con historial; no cumplido lo deja en D y permite abrir otro.
4. Exportación de planes.

`/mejoramiento` · `PlanesService` · Sprint 3 · Alta · ✅

### HU-32 · Detectar RAP en D sin plan y planes vencidos
> **Como** coordinador **quiero** ver qué no aprobados no tienen plan y qué planes vencieron **para** que nada quede sin atender.

1. Lista de RAP en D sin plan vigente (con búsqueda y filtro por ficha); cifras de vigentes, vencidos y cumplidos.
2. Los vencidos se resaltan en listados, paneles y calendario.

`/mejoramiento` · Sprint 3 · Media · ✅

## Épica 8 — Analítica por rol (RF04)

### HU-33 · Panel de coordinación
> **Como** coordinador **quiero** la situación del centro de un vistazo **para** decidir dónde intervenir.

1. Fichas activas, aprendices en formación, desempeño, avance, deserción, planes y evidencias por revisar.
2. Semáforo de aprendices, juicios por semana, desglose por programa, carga de cada instructor, competencias críticas, fichas con menor desempeño, aprendices en riesgo y actividades por vencer.
3. Todas las cifras calculadas en el momento, con las mismas definiciones que fichas y reportes ([ANALITICA.md](ANALITICA.md)).

`/dashboard` · `AnaliticaModel` · Sprint 4 · Alta · ✅

### HU-34 · Panel del instructor
> **Como** instructor **quiero** saber qué tengo pendiente y cómo van mis fichas **para** organizar mi trabajo.

1. RAP por calificar, D sin plan, planes vencidos, evidencias por revisar; desempeño y avance de mis fichas.
2. Mis fichas con su semáforo, mis aprendices en riesgo y las actividades por vencer.

`/dashboard` · Sprint 4 · Alta · ✅

### HU-35 · Panel del aprendiz
> **Como** aprendiz **quiero** ver mi progreso **para** saber qué me falta.

1. Mi avance frente al promedio de mi ficha, mi desempeño y semáforo, mis A, D y pendientes.
2. Progreso por competencia, mis planes con su plazo, próximas actividades y última retroalimentación.

`/dashboard` · Sprint 4 · Alta · ✅

## Épica 9 — Reportes (RF05, RNF03)

### HU-36 · Generar reportes
> **Como** coordinador o instructor **quiero** reportes en Excel, CSV y PDF **para** informar y archivar.

1. Juicios de una ficha, resumen por ficha, cumplimiento por instructor, por competencia, aprendices en riesgo e historial de cambios por fechas.
2. Excel real con las celdas del semáforo coloreadas; PDF con encabezado institucional; CSV seguro frente a fórmulas.
3. El instructor obtiene lo que califica; no puede sacar el reporte de una ficha ajena.

`/reportes` · `ReportesService` · Sprint 4 · Alta · ✅

### HU-37 · Exportar desde cada listado
> **Como** usuario **quiero** descargar lo que veo en pantalla **para** trabajarlo fuera del sistema.

1. Usuarios, fichas, matrículas, juicios, planes y bitácora, con sus filtros.

Sprint 4 · Media · ✅

## Épica 10 — Auditoría y herramientas transversales

### HU-38 · Bitácora de auditoría (RNF02)
> **Como** coordinador **quiero** consultar quién hizo qué y cuándo **para** auditar el uso del sistema.

1. Accesos (correctos y fallidos), denegaciones, creaciones, cambios, eliminaciones, importaciones y exportaciones, con usuario, módulo, registro e IP.
2. Filtros por acción, módulo, usuario y fechas; exportación.

`/logs` · `Core\Services\Auditoria` · Sprint 4 · Alta · ✅

### HU-39 · Calendario académico
> **Como** usuario **quiero** un calendario de mi ficha **para** anticipar fechas.

1. Inicio y fin de fichas, fases, entregas de actividades, planes que vencen, juicios propios y eventos.
2. Instructores y coordinación crean eventos para sus fichas (los aprendices reciben aviso); solo el autor o coordinación los eliminan.
3. En el móvil arranca en vista de agenda y se navega deslizando.

`/calendario` · Sprint 4 · Media · ✅

### HU-40 · Avisos internos
> **Como** usuario **quiero** enterarme de lo que me afecta sin buscarlo **para** reaccionar a tiempo.

1. Avisos por juicios, evidencias, planes, retroalimentación, asignaciones, liderazgo de ficha y eventos ([FLUJOS.md §16](FLUJOS.md#16-avisos-notificaciones)).
2. Contador en la campana; marcar como leídos; solo enlaces internos.

`Core\Services\Notificador` · Sprint 4 · Media · ✅

### HU-41 · Configuración institucional
> **Como** coordinador **quiero** ajustar el nombre del sistema y del centro **para** que los reportes salgan con los datos correctos.

1. Los valores aparecen en el encabezado de los PDF; la pantalla muestra además los parámetros reales del sistema (semáforo, límites, correo, base de datos, modo).

`/configuracion` · Sprint 4 · Baja · ✅

### HU-42 · Mi perfil
> **Como** usuario **quiero** actualizar mi nombre, mi color de avatar y mi contraseña **para** mantener mi cuenta.

`/perfil` · Sprint 4 · Media · ✅

## Épica 11 — Experiencia de uso

### HU-43 · Uso desde el teléfono
> **Como** usuario **quiero** usar el sistema desde el celular **para** consultarlo en cualquier lugar.

1. Ninguna pantalla desborda horizontalmente a 390 px; listados en tarjetas o con desplazamiento propio; formularios y modales usables con el dedo.
2. Instalable como aplicación (PWA).

Sprint 4 · Alta · ✅

### HU-44 · Modo oscuro
> **Como** usuario **quiero** alternar entre tema claro y oscuro **para** trabajar con comodidad.

1. Se recuerda mi elección y se aplica antes de pintar (sin parpadeo); gráficos y componentes legibles en ambos.

Sprint 4 · Baja · ✅

## Épica 12 — Seguridad y operación (RNF complementarios)

### HU-45 · Protección de la información
> **Como** coordinador **quiero** que el sistema resista los ataques habituales **para** proteger los datos de aprendices e instructores.

1. Consultas preparadas (inyección SQL), salida escapada y CSP sin scripts en línea (XSS), CSRF, control por rol y por dato (IDOR), subidas verificadas, descargas con permiso, archivos internos cerrados a la web.
2. Detalle y evidencias en [SEGURIDAD.md](SEGURIDAD.md).

Sprint 4 · Alta · ✅

### HU-46 · Instalación y actualización reproducibles
> **Como** administrador **quiero** instalar y actualizar con comandos **para** no depender de pasos manuales.

1. `bin/instalar.php` crea la base desde el esquema y aplica migraciones (con datos de demostración opcionales); `bin/migrar.php` actualiza; `bin/verificar-esquema.php` comprueba la coherencia.

Sprint 4 · Media · ✅

### HU-47 · Pruebas automáticas
> **Como** equipo de desarrollo **quiero** que cada cambio se pruebe solo **para** no romper lo que funciona.

1. 595 pruebas (unitarias, de integración y de seguridad) en cada envío al repositorio, sobre una instalación limpia ([PRUEBAS.md](PRUEBAS.md)).

Sprint 4 · Media · ✅

---

## 5. Cambios respecto de la versión 2.0

| Historia | Cambio |
|---|---|
| HU-06 Bloqueo por intentos | Ahora implementada (antes pendiente). |
| HU-40 Avisos | Ahora con todos sus disparadores (antes solo la infraestructura). |
| HU-31 Plan de mejoramiento | Era un listado de juicios en D; ahora es un ciclo completo con estados, plazo y cierre que actualiza el juicio. |
| HU-33 a HU-35 Paneles | Rehechos con cifras calculadas al leer (antes leían contadores sin recalcular y mostraban tendencias escritas a mano). |
| HU-21, HU-22 | Actividades ligadas a fases y avance del proyecto calculado. |
| HU-08, HU-13, HU-16, HU-26 | Importaciones en dos pasos con vista previa. |
| HU-09, HU-19, HU-24, HU-36, HU-37 | Exportación a Excel real (antes HTML con extensión .xls). |
| HU-27 | Descarga de evidencias con permiso (antes enlace directo sin control). |
| Nuevas | HU-09, HU-13, HU-19, HU-22, HU-32, HU-35, HU-37, HU-45, HU-46, HU-47. |
