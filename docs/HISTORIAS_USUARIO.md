# Historias de Usuario

**Sistema de Seguimiento de Proyectos Formativos — SENA**

| | |
|---|---|
| **Versión** | 2.0 |
| **Fecha** | 11 de agosto de 2026 |
| **Estado del sistema** | En producción |
| **Total de historias** | 34 (32 implementadas, 2 no implementadas) |

---

## 1. Contexto y problema que resuelve

El seguimiento de proyectos formativos en el centro se llevaba en hojas de cálculo repartidas entre instructores. Eso producía tres problemas concretos:

1. **No había una fuente única de verdad.** El juicio evaluativo de un aprendiz vivía en el archivo de su instructor, así que la coordinación no podía ver el cumplimiento real de una ficha sin pedir los archivos uno por uno.
2. **La trazabilidad se perdía.** Cambiar una nota no dejaba rastro de quién la cambió, cuándo ni por qué.
3. **La nivelación era manual.** Detectar qué aprendices tenían un resultado de aprendizaje en «D» y necesitaban plan de mejoramiento exigía revisar cada archivo a mano.

El sistema centraliza la estructura curricular, la matrícula, el juicio evaluativo y la nivelación en una sola plataforma con control de acceso por rol y bitácora de auditoría.

---

## 2. Actores del sistema

| Rol | Constante en código | Alcance |
|---|---|---|
| **Coordinador** | `ROL_COORDINADOR` | Acceso total. Administra usuarios, estructura curricular, fichas, matrículas y asignación de instructores. Consulta todos los reportes y la auditoría. |
| **Instructor** | `ROL_INSTRUCTOR` | Acceso limitado a las fichas donde es instructor líder, a las competencias que tiene asignadas y a los aprendices de los que hace seguimiento. Registra juicios evaluativos y retroalimentación. |
| **Aprendiz** | `ROL_APRENDIZ` | Acceso solo a su propia información: su ficha, su proyecto, sus evaluaciones, sus evidencias y sus planes de mejoramiento. |

El alcance del instructor no es una simple comprobación de rol: se resuelve en `Core\Services\InstructorAccessService`, que centraliza la regla «¿tiene este instructor autoridad sobre este aprendiz o resultado de aprendizaje?» considerando tres vías (asignación por competencia, ficha propia, y seguimiento de etapa práctica).

---

## 3. Convenciones de este documento

Cada historia sigue el formato **Como / quiero / para**, con criterios de aceptación verificables y trazabilidad al código que la implementa.

- **Estado `✅`** — implementada y verificada.
- **Estado `⚠️`** — implementada con una limitación documentada.
- **Estado `❌`** — no implementada (pendiente).
- **Prioridad** — `Alta` (imprescindible para operar), `Media` (mejora sustancial), `Baja` (deseable).

---

## Épica 1 — Autenticación y control de acceso

### HU-01 · Inicio de sesión por rol

> **Como** usuario del sistema (coordinador, instructor o aprendiz)
> **quiero** iniciar sesión con mi correo institucional y contraseña
> **para** acceder únicamente a la información que me corresponde según mi rol.

**Criterios de aceptación**

1. Dado un correo y contraseña correctos, el sistema me redirige al panel de mi rol.
2. Dadas credenciales incorrectas, se muestra un mensaje genérico que no revela si el fallo fue el correo o la contraseña.
3. Las contraseñas se almacenan cifradas con `bcrypt`; nunca en texto plano.
4. Al iniciar sesión se regenera el identificador de sesión, de modo que un identificador capturado antes del acceso queda inservible.
5. Un usuario con estado `inactivo` no puede iniciar sesión.

**Trazabilidad:** `login.php` → `includes/auth.php::attemptLogin()` → `index.php`
**Prioridad:** Alta · **Estado:** ✅

> **Nota de diseño.** El `session_unset()` + `session_regenerate_id(true)` en el acceso no es solo una medida contra fijación de sesión: también evita que el rol de una sesión anterior se arrastre a la nueva, que era un fallo real de cambio de rol.

---

### HU-02 · Restricción de módulos por rol

> **Como** coordinador académico
> **quiero** que cada rol vea y pueda usar solo sus módulos
> **para** garantizar la confidencialidad de la información académica.

**Criterios de aceptación**

1. El menú lateral se construye desde `config/navigation.php` según el rol; un rol no ve enlaces a módulos ajenos.
2. Ocultar el enlace no es la protección: cada controlador exige el rol en su constructor, así que escribir la URL a mano también queda bloqueado.
3. Un instructor que intente abrir una ficha que no le corresponde recibe una negación de acceso, no los datos.
4. Un aprendiz solo puede consultar sus propios registros, aunque manipule los identificadores de la URL.

**Trazabilidad:** `includes/session.php::requireRole()`, constructores de los 23 controladores, `Core\Services\InstructorAccessService`
**Prioridad:** Alta · **Estado:** ✅

---

### HU-03 · Recuperación de contraseña por correo

> **Como** usuario que olvidó su contraseña
> **quiero** recibir un enlace de recuperación en mi correo
> **para** restablecer el acceso sin depender del coordinador.

**Criterios de aceptación**

1. Solicito la recuperación indicando mi correo; el sistema responde siempre igual exista o no la cuenta, para no revelar qué correos están registrados.
2. Recibo un correo con diseño institucional y un enlace de un solo uso.
3. El enlace caduca a los **30 minutos** y queda invalidado tras usarse.
4. En la base solo se guarda el *hash* del token, no el token en claro.
5. Solicitar un enlace nuevo invalida los anteriores no usados.

**Trazabilidad:** `recover.php`, tabla `password_resets`, `Core\Services\MailService` (SMTP vía PHPMailer)
**Prioridad:** Media · **Estado:** ✅

---

### HU-04 · Cambio obligatorio de contraseña temporal

> **Como** coordinador
> **quiero** que las cuentas creadas de forma masiva exijan cambiar la contraseña en el primer acceso
> **para** que no queden cuentas activas con una contraseña que conoce quien hizo la importación.

**Criterios de aceptación**

1. Cada cuenta creada manualmente o por importación recibe una contraseña temporal **distinta y aleatoria**.
2. La contraseña temporal se muestra una única vez al terminar la importación y no se puede volver a consultar.
3. Mientras el usuario no la cambie, cualquier navegación lo redirige a su perfil.
4. La contraseña temporal excluye caracteres ambiguos (`0`/`O`, `1`/`l`/`I`) para poder dictarse sin error.

**Trazabilidad:** `includes/functions.php::generateTempPassword()`, columna `usuarios.debe_cambiar_password`, `includes/session.php::requirePasswordChangeIfPending()`
**Prioridad:** Alta · **Estado:** ✅

---

### HU-05 · Protección de formularios contra peticiones falsificadas

> **Como** responsable del sistema
> **quiero** que ninguna operación de escritura pueda dispararse desde un sitio externo
> **para** evitar que se creen o modifiquen datos sin que el usuario lo sepa.

**Criterios de aceptación**

1. Toda petición `POST` valida un token CSRF antes de llegar al controlador.
2. La validación es global en el enrutador, no opcional por formulario, de modo que un formulario nuevo queda protegido sin que el desarrollador tenga que acordarse.
3. El token es distinto por pestaña, para que trabajar en varias pestañas no invalide operaciones.

**Trazabilidad:** `core/Router.php::dispatch()` → `includes/session.php::requireCsrf()`
**Prioridad:** Alta · **Estado:** ✅

---

### HU-06 · Bloqueo temporal tras intentos fallidos

> **Como** responsable del sistema
> **quiero** que una cuenta se bloquee temporalmente tras varios intentos fallidos
> **para** frenar ataques de prueba de contraseñas por fuerza bruta.

**Criterios de aceptación**

1. Tras N intentos fallidos consecutivos, la cuenta rechaza intentos durante un periodo.
2. El bloqueo se registra en la bitácora de auditoría.

**Trazabilidad:** — sin implementar
**Prioridad:** Media · **Estado:** ❌

> **Nota.** Documentación anterior del proyecto afirmaba que esto existía («15 minutos de bloqueo tras 3 intentos»). Se verificó en `includes/auth.php` y **no está implementado**: no hay contador de intentos ni columna que lo soporte. Se declara aquí como pendiente para no sostener una afirmación falsa en la sustentación.

---

## Épica 2 — Gestión de usuarios

### HU-07 · Administrar usuarios del sistema

> **Como** coordinador
> **quiero** crear, consultar, editar y desactivar usuarios
> **para** mantener actualizado el directorio de personas del centro.

**Criterios de aceptación**

1. Puedo crear usuarios con rol coordinador, instructor o aprendiz.
2. El correo es único; si se repite, recibo un mensaje claro en lugar de un error técnico.
3. Los nombres admiten tildes y `ñ`, y se rechazan caracteres que puedan inyectar código.
4. «Eliminar» **desactiva** el usuario, no lo borra: conserva su historial de evaluaciones y auditoría.
5. El listado se pagina de 25 en 25 y muestra el total real de registros.
6. Puedo buscar por nombre o correo y filtrar por rol y estado; la búsqueda se resuelve en el servidor, así que alcanza a todo el directorio y no solo a la página visible.

**Trazabilidad:** `UsuarioController`, `UsuarioModel::getFilteredList()`, `modules/usuarios/`
**Prioridad:** Alta · **Estado:** ✅

> **Nota de diseño.** El borrado es lógico por necesidad, no por preferencia: `usuarios.id` está referenciado por `fichas`, `evaluaciones` y `logs_sistema` sin borrado en cascada, así que un `DELETE` físico fallaba en cuanto la persona tenía cualquier actividad registrada.

---

### HU-08 · Importación masiva de usuarios

> **Como** coordinador
> **quiero** cargar un archivo Excel o CSV con muchos usuarios
> **para** no tener que registrarlos uno por uno al iniciar trimestre.

**Criterios de aceptación**

1. Acepto archivos `.xlsx` y `.csv`, detectando automáticamente si el separador es `,` o `;`.
2. La importación es **idempotente**: los correos que ya existen se omiten e informan, no se duplican ni interrumpen la carga.
3. Al terminar veo un resumen de insertados y omitidos, y las contraseñas temporales generadas.
4. Las filas con datos inválidos se reportan indicando el número de línea.

**Trazabilidad:** `UsuarioController::import()`, `Core\XlsxParser`, `modules/usuarios/views/importar.view.php`
**Prioridad:** Media · **Estado:** ✅

---

## Épica 3 — Estructura curricular

### HU-09 · Gestionar programas de formación

> **Como** coordinador
> **quiero** registrar los programas de formación con su código y duración
> **para** que las fichas y competencias se cuelguen de una estructura correcta.

**Criterios de aceptación**

1. Puedo crear, editar y consultar programas.
2. No puedo eliminar un programa que tenga competencias o fichas asociadas; recibo un mensaje que explica por qué.
3. Los códigos y nombres de la estructura curricular se muestran en mayúsculas de forma uniforme, sin alterar el dato guardado.

**Trazabilidad:** `ProgramasController`, `ProgramasModel`, `modules/programas/`
**Prioridad:** Alta · **Estado:** ✅

---

### HU-10 · Gestionar competencias y resultados de aprendizaje

> **Como** coordinador o instructor
> **quiero** registrar las competencias de cada programa y sus resultados de aprendizaje (RAP)
> **para** disponer de las unidades sobre las que se emite juicio evaluativo.

**Criterios de aceptación**

1. Cada competencia pertenece a un programa; cada RAP pertenece a una competencia.
2. Los códigos de competencia y de RAP son únicos; un duplicado produce un mensaje comprensible.
3. Puedo importar competencias y RAP de forma masiva desde Excel o CSV.
4. Al crear o importar un RAP nuevo, **los aprendices ya matriculados en ese programa reciben automáticamente su registro de evaluación pendiente**.

**Trazabilidad:** `CompetenciasController`, `ResultadosAprendizajeController`, `Core\Services\EvaluacionesSyncService`
**Prioridad:** Alta · **Estado:** ✅

> **Nota de diseño.** El criterio 4 corrige un fallo real: la creación del registro de evaluación solo ocurría al matricular, así que un RAP creado después nunca llegaba a los aprendices existentes y quedaba imposible de calificar. La regla vive ahora en un único servicio invocado desde los cinco caminos que crean o mueven RAP.

---

### HU-11 · Importar la estructura curricular desde el PDF institucional

> **Como** coordinador
> **quiero** cargar el PDF de estructura curricular y el de proyecto formativo
> **para** poblar programas, competencias, RAP, proyectos y fases sin transcribirlos a mano.

**Criterios de aceptación**

1. Subo uno o ambos PDF y el sistema me muestra una **previsualización** de lo que interpretó.
2. Confirmo antes de escribir: nada se guarda hasta que apruebo la previsualización.
3. La escritura es atómica; si algo falla a mitad, no queda estructura a medias.
4. Los elementos que ya existen se actualizan en lugar de duplicarse.
5. Tras la importación, los aprendices ya matriculados reciben los registros de evaluación de los RAP nuevos.

**Trazabilidad:** `EstructuraController::import()`, `Core\Services\EstructuraPdfParser`
**Prioridad:** Media · **Estado:** ✅

---

## Épica 4 — Fichas y matrículas

### HU-12 · Gestionar fichas de formación

> **Como** coordinador
> **quiero** crear y administrar las fichas con su programa, proyecto, instructor líder y fechas
> **para** organizar los grupos de formación del centro.

**Criterios de aceptación**

1. El número de ficha es único.
2. Puedo asignar programa, proyecto formativo, instructor líder y rango de fechas.
3. La fecha de inicio no puede ser posterior a la de fin.
4. Al cambiar el programa de una ficha, sus aprendices reciben los registros de evaluación del nuevo conjunto de RAP, conservando el historial del programa anterior.

**Trazabilidad:** `FichaController`, `FichaModel`, `modules/fichas/`
**Prioridad:** Alta · **Estado:** ✅

---

### HU-13 · Consultar el detalle de una ficha

> **Como** coordinador o instructor
> **quiero** ver en un solo lugar los aprendices, la estructura curricular y las evaluaciones de una ficha
> **para** valorar su estado sin recorrer varios módulos.

**Criterios de aceptación**

1. La vista organiza la información en pestañas: información general, aprendices, estructura y evaluaciones.
2. Como instructor solo accedo a las fichas donde soy responsable; el sistema comprueba la propiedad, no solo el rol.
3. Puedo filtrar el listado de aprendices de la ficha.

**Trazabilidad:** `FichaController::view()`, `modules/fichas/views/ver.view.php`
**Prioridad:** Alta · **Estado:** ✅

---

### HU-14 · Matricular aprendices

> **Como** coordinador
> **quiero** matricular aprendices en una ficha, de forma individual o masiva
> **para** habilitarlos en el sistema y que puedan ser evaluados.

**Criterios de aceptación**

1. Al matricular se crean en una sola transacción: la cuenta de usuario, el registro de aprendiz, **los registros de evaluación pendientes de todos los RAP del programa** y el contador de la ficha.
2. El documento y el correo no pueden estar repetidos.
3. Puedo cargar un CSV para matricular a un grupo completo.
4. Las contraseñas temporales generadas se muestran una sola vez.
5. El listado se pagina y permite buscar por nombre, documento o correo y filtrar por ficha y estado.

**Trazabilidad:** `MatriculaController`, `AprendizModel::matricular()`, `modules/matriculas/`
**Prioridad:** Alta · **Estado:** ✅

---

### HU-15 · Gestionar el estado y traslado de una matrícula

> **Como** coordinador
> **quiero** cambiar el estado de un aprendiz y trasladarlo de ficha
> **para** reflejar deserciones, suspensiones, etapa práctica y egresos.

**Criterios de aceptación**

1. Los estados disponibles son: matriculado, suspendido, desertado, egresado y etapa práctica.
2. «Eliminar» una matrícula la marca como `desertado`; no borra el expediente.
3. Al trasladar un aprendiz de ficha, sus evaluaciones y evidencias se reasignan a la ficha nueva.
4. Puedo asignarle un instructor de seguimiento para la etapa práctica.

**Trazabilidad:** `AprendizModel::editarMatricula()`, `AprendizModel::eliminar()`
**Prioridad:** Media · **Estado:** ✅

> **Nota de diseño.** El criterio 3 resuelve un fallo real: sin reasignar la ficha de las evaluaciones, volver a calificar un RAP ya evaluado después de un traslado violaba la restricción de unicidad y fallaba.

---

### HU-16 · Asignar instructores a competencias

> **Como** coordinador
> **quiero** asignar qué instructor responde por cada competencia dentro de una ficha
> **para** que cada uno evalúe solo lo que le corresponde.

**Criterios de aceptación**

1. Puedo asignar un instructor a una combinación de ficha y competencia.
2. La asignación determina qué puede evaluar el instructor.
3. Si una competencia no tiene asignación específica, responde el instructor líder de la ficha.

**Trazabilidad:** `AsignacionesController`, tabla `asignaciones`, `Core\Services\InstructorAccessService`
**Prioridad:** Alta · **Estado:** ✅

---

## Épica 5 — Proyectos formativos

### HU-17 · Gestionar proyectos formativos y sus fases

> **Como** coordinador
> **quiero** registrar los proyectos formativos y sus fases
> **para** estructurar el desarrollo del aprendizaje por etapas.

**Criterios de aceptación**

1. Puedo crear proyectos con nombre, código y objetivo.
2. Puedo definir fases numeradas con nombre y descripción.
3. Las fases se muestran como línea de tiempo con su estado (completada, en curso, pendiente).

**Trazabilidad:** `ProyectosController`, `FasesController`, `modules/proyectos/`, `modules/fases/`
**Prioridad:** Media · **Estado:** ✅

---

### HU-18 · Gestionar actividades de aprendizaje

> **Como** instructor
> **quiero** registrar actividades asociadas a una competencia y una ficha
> **para** organizar el trabajo que deben desarrollar los aprendices.

**Criterios de aceptación**

1. Cada actividad se vincula a una ficha, una competencia y un responsable.
2. Puedo consultar las actividades de mis fichas.
3. El aprendiz ve las actividades de su ficha.

**Trazabilidad:** `ActividadesController`, `modules/actividades/`
**Prioridad:** Media · **Estado:** ✅

---

## Épica 6 — Evaluación

### HU-19 · Registrar juicio evaluativo por resultado de aprendizaje

> **Como** instructor
> **quiero** registrar el juicio evaluativo de cada RAP de mis aprendices
> **para** dejar constancia formal de su avance.

**Criterios de aceptación**

1. Los conceptos válidos son **A** (aprobado) y **D** (aún no competente), más el estado inicial `pendiente`, conforme a la normativa SENA.
2. Solo puedo evaluar los RAP sobre los que tengo autoridad (asignación, ficha propia o seguimiento).
3. Puedo acompañar el juicio con un comentario para el aprendiz.
4. Cada aprendiz tiene un único juicio vigente por RAP.
5. Al registrar el juicio, el comentario queda también en el historial de retroalimentación del aprendiz.

**Trazabilidad:** `EvaluacionesController`, `EvaluacionesModel`, `SeguimientoModel::registrarEvaluacion()`
**Prioridad:** Alta · **Estado:** ✅

---

### HU-20 · Consultar el listado completo de juicios evaluativos

> **Como** coordinador
> **quiero** consultar todos los juicios con filtros y paginación
> **para** auditar el estado evaluativo del centro sin perder registros.

**Criterios de aceptación**

1. Puedo filtrar por ficha y por concepto, y buscar por aprendiz o RAP.
2. El listado se pagina y **muestra el total real** de juicios que cumplen el filtro.
3. Lo que veo respeta mi rol: un instructor solo cuenta y ve lo que le corresponde.

**Trazabilidad:** `EvaluacionesModel::getEvaluaciones()` y `contarEvaluaciones()`
**Prioridad:** Alta · **Estado:** ✅

> **Nota de diseño.** Antes esta consulta tenía un `LIMIT 200` fijo. Con 3.873 juicios registrados, la coordinación veía los 200 más recientes **sin ninguna señal de que existieran los demás**: no era una medida de rendimiento, era un recorte silencioso de datos. La paginación lo sustituye y deja el total a la vista.

---

### HU-21 · Modificar un juicio dejando constancia

> **Como** instructor
> **quiero** poder corregir un juicio ya emitido indicando el motivo
> **para** rectificar errores sin perder la trazabilidad de lo ocurrido.

**Criterios de aceptación**

1. Al cambiar un concepto debo indicar un motivo.
2. El cambio queda registrado en `historial_evaluaciones` con el concepto anterior, el nuevo, el autor y la fecha.
3. El aprendiz ve el juicio vigente; el historial queda para auditoría.

**Trazabilidad:** `EvaluacionesModel::actualizarEvaluacion()`, tabla `historial_evaluaciones`
**Prioridad:** Alta · **Estado:** ✅

---

### HU-22 · Importar juicios evaluativos desde el reporte institucional

> **Como** coordinador
> **quiero** cargar el reporte de juicios evaluativos en Excel
> **para** incorporar de una vez las notas registradas en la plataforma institucional.

**Criterios de aceptación**

1. Subo el archivo y veo una **previsualización** agrupada por aprendiz antes de confirmar.
2. La previsualización indica, por cada juicio, si se va a crear o actualizar.
3. Si el aprendiz o el RAP no existen, se informa en lugar de fallar en bloque.
4. Al confirmar veo un resumen del resultado.

**Trazabilidad:** `EvaluacionesController::import()`, `Core\Services\JuiciosImportService`
**Prioridad:** Media · **Estado:** ✅

---

### HU-23 · Entregar y calificar evidencias

> **Como** aprendiz
> **quiero** subir los archivos que evidencian mi trabajo
> **para** que mi instructor pueda valorarlos.

**Criterios de aceptación**

1. Puedo subir archivos asociados a una evaluación.
2. El sistema valida el **tipo real** del archivo, no solo la extensión del nombre.
3. Los archivos se guardan con nombre aleatorio y en un directorio que no permite ejecutar código.
4. El instructor puede calificar la evidencia y dejar retroalimentación.

**Trazabilidad:** `EvidenciasController`, `EvidenciasModel::calificarEvidencia()`, `uploads/.htaccess`
**Prioridad:** Media · **Estado:** ✅

> **Nota de diseño.** Validar solo la extensión permitía subir un `.php` renombrado a `.jpg`. La validación por tipo real (`finfo`), el nombre aleatorio y el `.htaccess` que bloquea la ejecución son tres barreras independientes para el mismo riesgo.

---

### HU-24 · Consultar mi retroalimentación

> **Como** aprendiz
> **quiero** ver el historial de comentarios de mis instructores
> **para** saber qué debo corregir.

**Criterios de aceptación**

1. Veo los comentarios ordenados en el tiempo, con su autor y fecha.
2. Recibo la retroalimentación **sin importar por qué módulo la haya registrado el instructor**.

**Trazabilidad:** `RetroalimentacionController`, tabla `retroalimentacion`
**Prioridad:** Media · **Estado:** ✅

> **Nota de diseño.** El criterio 2 corrige un fallo de escritura, no de lectura: solo el módulo de evidencias insertaba en `retroalimentacion`, de modo que los comentarios hechos desde seguimiento o evaluaciones nunca le llegaban al aprendiz. Los tres caminos de calificación ahora escriben de forma consistente.

---

## Épica 7 — Seguimiento y nivelación

### HU-25 · Seguimiento del rendimiento por aprendiz

> **Como** instructor o coordinador
> **quiero** ver el avance de cada aprendiz de una ficha con su nivel de alerta
> **para** intervenir a tiempo con los que van rezagados.

**Criterios de aceptación**

1. Por cada aprendiz veo total de RAP, aprobados, en proceso, porcentaje de avance y nivel de alerta.
2. La alerta se clasifica en Crítico, Riesgo y Al Día según el avance y los RAP en «D».
3. Puedo filtrar por nivel de alerta y buscar un aprendiz concreto.
4. Puedo exportar el seguimiento a Excel.

**Trazabilidad:** `SeguimientoController`, `SeguimientoModel`, `modules/seguimiento/`
**Prioridad:** Alta · **Estado:** ✅

---

### HU-26 · Plan de mejoramiento automático

> **Como** instructor
> **quiero** que el sistema identifique solo los RAP en «D» que requieren nivelación
> **para** no tener que cruzar listados a mano.

**Criterios de aceptación**

1. Todo RAP con concepto «D» aparece como plan de mejoramiento pendiente.
2. El aprendiz ve sus propios planes; el instructor los de sus aprendices.
3. Puedo generar el **formato institucional imprimible** del plan de mejoramiento con los datos de la ficha, el aprendiz y los RAP a nivelar.

**Trazabilidad:** `MejoramientoController`, `MejoramientoModel::getPlanesMejoramiento()`
**Prioridad:** Alta · **Estado:** ✅

---

### HU-27 · Panel de indicadores por rol

> **Como** usuario del sistema
> **quiero** un panel de inicio con los indicadores propios de mi rol
> **para** saber de un vistazo qué requiere mi atención.

**Criterios de aceptación**

1. **Coordinador:** fichas activas, aprendices, instructores, retención promedio, cumplimiento por programa, estado de fichas, deserción, alertas críticas y evaluaciones recientes.
2. **Instructor:** evaluaciones pendientes de calificar, aprendices que requieren plan de mejoramiento, aprendices en etapa práctica y avance de sus fichas.
3. **Aprendiz:** avance formativo, alertas de RAP en «D» y nivel de progreso.
4. Las gráficas incluyen alternativa textual para lectores de pantalla.

**Trazabilidad:** `DashboardController`, `DashboardModel`, `InstructorDashboardModel`, `AprendizDashboardModel`
**Prioridad:** Alta · **Estado:** ✅

---

## Épica 8 — Reportes, auditoría y calendario

### HU-28 · Reportes exportables

> **Como** coordinador
> **quiero** generar reportes y exportarlos
> **para** presentar resultados y trabajarlos fuera del sistema.

**Criterios de aceptación**

1. Dispongo de cuatro reportes: evaluaciones por ficha, cumplimiento por instructor, cumplimiento por competencia e historial de cambios.
2. Cada reporte se exporta en **CSV y Excel**.
3. Los datos respetan el alcance de mi rol.

**Trazabilidad:** `ReportesController`, `ReportesModel`, `modules/reportes/`
**Prioridad:** Media · **Estado:** ✅

> **Nota de diseño.** El reporte de cumplimiento por instructor filtraba por ficha pero agregaba los totales de toda la ficha sin distinguir competencia, así que un instructor a cargo de una sola competencia veía cifras de competencias ajenas. Se corrigió agrupando por ficha y competencia.

---

### HU-29 · Bitácora de auditoría

> **Como** coordinador
> **quiero** consultar quién hizo cada operación y cuándo
> **para** garantizar la trazabilidad de la información académica.

**Criterios de aceptación**

1. Se registran las operaciones de creación, modificación, eliminación e importación con usuario, módulo, fecha y descripción.
2. Puedo buscar por texto y filtrar por tipo de acción.
3. La bitácora se pagina de 50 en 50 y **todo el historial es alcanzable**.

**Trazabilidad:** `LogsController`, `LogsModel`, tabla `logs_sistema`
**Prioridad:** Media · **Estado:** ✅

> **Nota de diseño.** La consulta tenía un `LIMIT 100` fijo. En una bitácora que por definición solo crece, eso volvía inalcanzable todo lo anterior a los últimos 100 apuntes: precisamente lo contrario de lo que debe garantizar una auditoría.

---

### HU-30 · Calendario académico

> **Como** usuario del sistema
> **quiero** ver en un calendario los hitos de formación
> **para** anticipar fechas relevantes.

**Criterios de aceptación**

1. El calendario muestra inicio y fin de ficha, fases, evaluaciones y eventos manuales, diferenciados por color con su leyenda.
2. Puedo crear y eliminar eventos propios.
3. Los eventos se cargan por API y respetan el alcance del rol.

**Trazabilidad:** `CalendarioController` (`index` y `apiEvents`), tabla `eventos_calendario`
**Prioridad:** Baja · **Estado:** ✅

---

### HU-31 · Notificaciones internas

> **Como** usuario del sistema
> **quiero** recibir avisos dentro de la plataforma
> **para** enterarme de lo que requiere mi atención sin revisar cada módulo.

**Criterios de aceptación**

1. Un indicador en la barra superior muestra los avisos no leídos.
2. Puedo marcar uno o todos como leídos.
3. El contador se actualiza periódicamente sin recargar la página.

**Trazabilidad:** `components/navbar.php`, `includes/api_notificaciones.php`, tabla `notificaciones`
**Prioridad:** Baja · **Estado:** ⚠️

> **Limitación.** La infraestructura funciona (tabla, API, interfaz), pero **aún no hay disparadores de negocio que generen avisos** automáticamente al calificar, matricular o vencer un plazo. Hoy la tabla está vacía en operación.

---

### HU-32 · Configuración institucional

> **Como** coordinador
> **quiero** ajustar los parámetros del sistema
> **para** adaptarlo a mi centro sin tocar código.

**Criterios de aceptación** — Puedo modificar nombres institucionales y parámetros académicos desde la interfaz.

**Trazabilidad:** `ConfiguracionController`, `ConfiguracionModel`
**Prioridad:** Baja · **Estado:** ✅

---

## Épica 9 — Experiencia de usuario y accesibilidad

### HU-33 · Uso desde teléfono móvil

> **Como** instructor que consulta el sistema desde el aula o el taller
> **quiero** que la interfaz sea usable en mi teléfono
> **para** registrar y consultar información sin volver a un computador.

**Criterios de aceptación**

1. Ninguna pantalla obliga a desplazarse en horizontal.
2. Los botones y controles alcanzan el área táctil mínima recomendada (44 px).
3. El menú lateral funciona como panel deslizable, no como barra fija de iconos.
4. Las tablas anchas se desplazan **dentro de su propia caja**, con todas sus columnas alcanzables.
5. Los encabezados apilan título y acciones en lugar de comprimirlos.

**Trazabilidad:** `assets/css/theme.css` (patrón `.page-header` y capa móvil), 29 vistas
**Prioridad:** Alta · **Estado:** ✅

> **Verificación.** Comprobado en navegador real a 390 px de ancho, en tema claro y oscuro, sobre las nueve pantallas principales: ninguna presenta desplazamiento horizontal. Las alturas de página se redujeron entre un 75 % y un 85 % en los listados.

---

### HU-34 · Modo oscuro legible

> **Como** usuario que trabaja en jornada nocturna
> **quiero** un modo oscuro donde todo el texto sea legible
> **para** no forzar la vista ni perder información.

**Criterios de aceptación**

1. Puedo alternar entre claro y oscuro; la preferencia se recuerda.
2. El tema se aplica antes de pintar la página, sin destello blanco.
3. Ningún texto queda con contraste insuficiente frente a su fondo.
4. Los componentes de Bootstrap (tablas, menús, alertas, formularios) respetan el tema sin parches por vista.

**Trazabilidad:** `assets/css/theme.css` (puente de variables Bootstrap y pares de tokens `--x` / `--x-text`), `assets/js/app.js`
**Prioridad:** Media · **Estado:** ✅

> **Nota de diseño.** Había dos causas de raíz. Las utilidades `.text-*` de Bootstrap **no** se aclaran en su modo oscuro (172 usos en las vistas quedaban con letra oscura sobre fondo oscuro), y los tokens semánticos hacían de relleno y de color de texto a la vez, lo que impedía aclararlos sin romper los botones. Se resolvió separando cada token en un par y sobrescribiendo las utilidades, no vista por vista.

---

## 4. Resumen de cobertura

| Épica | Historias | Implementadas |
|---|---|---|
| 1. Autenticación y control de acceso | 6 | 5 |
| 2. Gestión de usuarios | 2 | 2 |
| 3. Estructura curricular | 3 | 3 |
| 4. Fichas y matrículas | 5 | 5 |
| 5. Proyectos formativos | 2 | 2 |
| 6. Evaluación | 6 | 6 |
| 7. Seguimiento y nivelación | 3 | 3 |
| 8. Reportes, auditoría y calendario | 5 | 5 (una con limitación) |
| 9. Experiencia de usuario | 2 | 2 |
| **Total** | **34** | **32 completas · 1 con limitación · 1 pendiente** |

### Pendientes declarados

| Historia | Estado | Motivo |
|---|---|---|
| HU-06 · Bloqueo tras intentos fallidos | ❌ | Requiere contador de intentos y columna de bloqueo. Documentación anterior lo daba por hecho sin estarlo. |
| HU-31 · Notificaciones internas | ⚠️ | Infraestructura completa; faltan los disparadores de negocio que generen los avisos. |

---

## 5. Trazabilidad rápida: historia → módulo

| Módulo | Historias |
|---|---|
| `usuarios` | HU-07, HU-08 |
| `programas` | HU-09 |
| `competencias`, `resultados-aprendizaje` | HU-10 |
| `estructura` | HU-11 |
| `fichas` | HU-12, HU-13 |
| `matriculas` | HU-14, HU-15 |
| `asignaciones` | HU-16 |
| `proyectos`, `fases` | HU-17 |
| `actividades` | HU-18 |
| `evaluaciones` | HU-19, HU-20, HU-21, HU-22 |
| `evidencias` | HU-23 |
| `retroalimentacion` | HU-24 |
| `seguimiento` | HU-25 |
| `mejoramiento` | HU-26 |
| `dashboard` | HU-27 |
| `reportes` | HU-28 |
| `logs` | HU-29 |
| `calendario` | HU-30 |
| `configuracion` | HU-32 |
| `perfil` | HU-04 |
| Transversal (CSS/JS) | HU-33, HU-34 |
| Transversal (seguridad) | HU-01, HU-02, HU-03, HU-05, HU-06 |

---

*Documento generado a partir del código en producción. Cada criterio de aceptación es verificable en el módulo referenciado.*
