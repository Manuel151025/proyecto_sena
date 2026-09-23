# Historial de versiones

**Sistema de Seguimiento de Proyectos Formativos — SENA**

Reconstruido a partir del historial real del repositorio: **121 commits** entre el 19 de mayo y el 11 de agosto de 2026. Las versiones agrupan el trabajo por objetivo, no por fecha arbitraria.

| Versión | Fecha | Commits | Objetivo |
|---|---|---:|---|
| [v3.1](#v31) | 23 sep 2026 | 1 | Suite de pruebas: 492 casos unitarios, de seguridad e integración |
| [v3.0](#v30) | 23 sep 2026 | 1 | Endurecimiento: entrada, errores, permisos y arquitectura |
| [v2.2](#v22) | 22 sep 2026 | 1 | Exportación real a PDF (RNF03) |
| [v2.1](#v21) | 22 sep 2026 | 1 | Trazabilidad real del juicio evaluativo (RNF02) |
| [v2.0.1](#v201) | 11 ago 2026 | 1 | Documentación de sustentación |
| [v2.0](#v20) | 10 ago 2026 | 5 | Interfaz móvil, contraste y paginación |
| [v1.1](#v11) | 28–31 jul 2026 | 10 | Unificación de UX y correo real |
| [v1.0](#v10) | 3–8 jul 2026 | 27 | Auditoría completa en cinco fases |
| [v0.4](#v04) | 30 jun–1 jul 2026 | 14 | Migración MVC completa y validación |
| [v0.3](#v03) | 18–22 jun 2026 | 46 | Despliegue, PWA y rediseño |
| [v0.2](#v02) | 2–3 jun 2026 | 13 | Enrutador, roles y calendario |
| [v0.1](#v01) | 19–27 may 2026 | 5 | Arranque del proyecto |

---

## v3.1

**23 de septiembre de 2026 · 1 commit**

El proyecto no tenía ninguna prueba ni dependencias de desarrollo. Ahora tiene **492 casos y 2.320 comprobaciones** sobre PHPUnit 11, repartidos en tres suites, más un flujo de CI que las ejecuta en cada push.

```
composer test              492 casos · 2.320 comprobaciones · 15 s
composer test:unit         196 casos (sin base de datos)
composer test:security     183 casos
composer test:integration  113 casos
```

### Tres fallos encontrados por las propias pruebas

Ninguno se había detectado en las revisiones anteriores.

**1. Comodines de `LIKE` sin escapar.** `%` y `_` son comodines de SQL, y los 19 buscadores del sistema no los escapaban. Buscar «%» devolvía la tabla entera: el buscador servía para saltarse el propio buscador y para forzar un recorrido completo de una tabla de 3.900 filas. Como efecto colateral, era imposible buscar un porcentaje literal («50%») o un nombre con guion bajo. Se añade `Validador::escaparLike()` y se aplica a las 19.

**2. Filtros manipulados que mostraban de más.** Un valor desconocido en el filtro de rol o de estado **se ignoraba en silencio**, de modo que `?rol=loquesea` devolvía TODOS los usuarios en lugar de ninguno: trastear con la URL enseñaba más de lo que ofrecía la pantalla. Ahora un valor que no existe no encaja con nada, que es la respuesta honesta.

**3. La mitigación del canal lateral de tiempo estaba al revés.** Para que el login no delatara qué cuentas existen, la v3.0 añadió un hash de relleno contra el que verificar cuando el correo no existe. El problema: era una cadena inventada con pinta de bcrypt, no un hash válido. Y `password_verify` contra un hash malformado tarda ~200 ms, frente a los ~50 ms de uno de coste 10. Resultado medido: la rama «esta cuenta no existe» era **cuatro veces más lenta** que la otra. La fuga seguía ahí, invertida. Con un bcrypt real del mismo coste, el ratio baja de 3,86 a **1,01**.

### Qué cubren las pruebas de seguridad

| Categoría | Qué se intenta |
|---|---|
| Autenticación | Fuerza bruta descartando cookies, barrido de cuentas desde una IP, rotación de `X-Forwarded-For`, enumeración por tiempo de respuesta |
| Autorización | IDOR sobre evaluaciones, evidencias, notificaciones y reportes; escalada de privilegios; aislamiento de los listados por rol |
| Inyección | 13 cargas SQL contra cada buscador, inyección temporal con `SLEEP`, 8 cargas de XSS, inyección de cabeceras SMTP, travesía de rutas |
| CSRF y sesión | Reutilización de tokens entre pestañas, comparación en tiempo constante, caducidad por inactividad y absoluta, `tabId` manipulado |
| Subida de archivos | 13 extensiones ejecutables, doble extensión, byte nulo, PHP disfrazado de PDF y de imagen, permisos de la carpeta |
| Fuga de información | Que no vuelvan el volcado de depuración del 403, los `getMessage()` en pantalla ni las vistas sin guarda |

### Decisiones de diseño de la suite

- **Contra la base real, sin dejar rastro.** Cada prueba corre dentro de una transacción que se revierte. No hace falta una base de pruebas aparte.
- **Con los datos que ya existen**, no con datos sembrados: así se comprueban las consultas contra la forma real que tienen los datos, que es donde aparecieron varios de los fallos. Cuando no hay datos suficientes, la prueba se omite en lugar de pasar en vacío — un caso que detectó una prueba de aislamiento de evidencias que no comprobaba nada porque la tabla estaba vacía.
- **Varias documentan un fallo pasado** y fallan si alguien lo reintroduce: el parche que aceptaba el token de cualquier pestaña, el 403 que imprimía el ID de sesión, el contador desnormalizado. `tests/README.md` las lista.
- `failOnWarning` y `failOnNotice` están activos: un aviso de PHP en una prueba suele ser el síntoma de un acceso a una clave que no existe.

### Lo que la suite NO cubre

Está documentado en `tests/README.md` para que un resultado verde no se lea como más de lo que es: **nada se ejecuta en un navegador**, así que no se comprueba que las cabeceras CSP lleguen ni que el guard de las vistas responda a una petición HTTP real. `MailService` no se prueba (exige SMTP), y el importador de Excel y el lector de PDF no se prueban de extremo a extremo por falta de archivos de ejemplo.

---

## v3.0

**23 de septiembre de 2026 · 1 commit**

Revisión completa de entrada, errores, permisos, consultas y arquitectura. **231 comprobaciones automáticas** nuevas, ejecutables con `php tests/ejecutar.php`.

### El hallazgo que condicionó el resto

`sql_mode` no incluía `STRICT_TRANS_TABLES`. Comprobado contra esta misma base: un ENUM con un valor inventado se guardaba como **cadena vacía** y una fecha ilegible como **`0000-00-00`**, sin lanzar ningún error. Catorce campos ENUM y once fechas viajaban de `$_POST` a la consulta sin lista blanca, así que cualquiera podía dejar una ficha en un estado que ninguna pantalla sabe dibujar. Se activa el modo estricto en la conexión —no en `my.cnf`, para que la garantía viaje con el proyecto— y se añade validación en PHP delante.

### Validación de entrada

`core/Support/Validador.php` acumula los errores en vez de detenerse en el primero, y cubre enteros, textos, enums, fechas, decimales, correos y listas de identificadores. Rechaza lo que un cast aceptaría: `"12abc"` no es 12, `?id[]=1` no es un escalar, `2026-02-30` encaja con el patrón pero no existe.

`core/Support/Enums.php` declara los valores de las 17 columnas ENUM en un solo sitio, y `migrations/verificar_enums.php` comprueba que sigan coincidiendo con el esquema.

### Fugas de información

- **`requireCsrf()` imprimía el ID de sesión y los tokens CSRF esperados** de todas las pestañas, bajo el rótulo «Diagnostic Info». Era depuración en producción: quien provocara un 403 se llevaba de vuelta los secretos.
- **49 bloques `catch` mostraban `$e->getMessage()`** en pantalla. Mezclaban mensajes de negocio con el texto de una `PDOException`, que incluye nombres de tabla y de columna. Ahora `ErrorDeNegocio` distingue los dos: lo escrito para el usuario se muestra; lo demás se registra y se responde con una referencia cruzable.
- **No existía ningún manejador global de errores.** Una excepción no capturada salía como traza de PHP con rutas absolutas del servidor. `ManejadorErrores` cubre excepciones, avisos y errores fatales, y distingue si la petición esperaba JSON.

### Acceso y abuso

- **El bloqueo por intentos fallidos vivía en `$_SESSION`**: se evadía descartando la cookie. Pasa a base de datos (`LimitadorIntentos`), contando por identidad **y** por IP. La recuperación de contraseña, que no tenía ningún límite, ahora lo tiene.
- **El login delataba qué cuentas existen** por el tiempo de respuesta: un correo inexistente respondía al instante y uno real tardaba lo que tarda bcrypt. Se verifica siempre contra un hash de relleno.
- **El token CSRF era por pestaña**, y eso obligó a un parche que recorría todas las pestañas aceptando cualquier coincidencia — anulando el aislamiento que lo justificaba. Pasa a ser un token por sesión, que es lo correcto, y el parche desaparece.
- **La sesión no caducaba nunca.** Se añaden inactividad máxima (2 h), duración máxima (12 h) y recolección de pestañas, que crecían sin tope.
- **IDOR en notificaciones**: `UPDATE notificaciones SET leida=1 WHERE id=?`, sin filtrar por usuario. Cualquiera podía silenciar los avisos de otro.
- **Cierre de sesión por GET y sin token.** Pasa a POST con token.
- **`install.php` empezaba con `DROP DATABASE` y era accesible por web**, sin autenticación. Ahora exige CLI y `--confirmar-borrado-total`.
- **`logs/password_resets.log` estaba versionado en git con enlaces de recuperación reales.** Se deja de versionar y solo se escribe el token en DEV_MODE.
- Sin cabeceras de seguridad ni SRI: se añaden CSP, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, HSTS bajo HTTPS, y `integrity` en los cuatro recursos de jsdelivr.
- **Las 36 vistas eran accesibles por URL directa.** Llevan guarda.
- **Inyección de fórmulas en los CSV**: un motivo que empiece por `=` se ejecutaba al abrir el reporte en Excel. El objetivo no era quien exporta, sino quien recibe el archivo.

### Auditoría

Había 17 `INSERT INTO logs_sistema` a mano en nueve archivos, y **ningún evento de seguridad**: ni accesos, ni fallos, ni cambios de contraseña, ni permisos denegados. La tabla tenía una fila. `Core\Services\Auditoria` centraliza el registro y cubre esos eventos; un acceso denegado es justo la señal que delata a quien prueba rutas a mano.

### Integridad de datos

- **`fichas.cantidad_aprendices` estaba desviado en 5 de las 7 fichas.** Era un contador con dos escritores en conflicto: cuatro puntos del código lo incrementaban y el formulario de ficha permitía teclearlo. Ahora el recuento se calcula al leer y los dos campos derivados (también `cumplimiento_porcentaje`) dejan de ser editables.
- **`configuraciones_sistema` no existía**, pero el modelo la consultaba y el `catch` devolvía un array vacío: el módulo aparentaba funcionar y descartaba lo guardado sin avisar.
- **Quién puede calificar dependía de un `LIKE` sobre texto libre** (`c.nombre LIKE '%ETAPA PRÁCTICA%'`). Una tilde o un espacio de más cambiaban el control de acceso en silencio. Pasa a `competencias.es_etapa_practica`: **36 ocurrencias** sustituidas.
- La regla de acceso del instructor tenía **cinco copias divergentes**; se consolidan en `InstructorAccessService`, que gana `tieneAccesoEvaluacion()` y expone la condición para los listados.

### Arquitectura y consultas

- **Las rutas ahora declaran quién entra.** El permiso vivía en el constructor de cada controlador, y nueve solo comprobaban que hubiera sesión sin mirar el rol. `index.php` se lee como la matriz de accesos: 31 de las 69 rutas son exclusivas de coordinación. La comprobación del controlador se mantiene como segunda capa.
- El despacho recorría las 69 rutas comparando cadenas; ahora son una búsqueda por clave. Se distingue **405** de 404.
- Revisadas las **85 consultas** de todos los modelos en los tres roles: ninguna hace escaneo completo de tabla. Se añaden dos índices compuestos, `aprendices(ficha_id, estado)` y `evaluaciones(ficha_id, concepto)`, que son las dos consultas cuyo coste crece con los datos.
- `attemptLogin` hacía dos viajes a la base para el mismo usuario; ahora uno, y rehashea la contraseña si sube el coste configurado.
- Doble escapado en los tres paneles: un nombre con apóstrofo se veía como `O&#039;Brien`.

---

## v2.2

**22 de septiembre de 2026 · 1 commit**

RNF03 pide «exportación de reportes en PDF y Excel». El Excel existía; el PDF era un botón que llamaba a `window.print()`, es decir, el diálogo de impresión del navegador sobre la propia página web. Eso dependía del navegador de cada quien, arrastraba la barra lateral y los botones al papel, y no producía ningún archivo que el sistema pudiera entregar. La propia interfaz lo admitía: «utiliza la función de impresión del navegador (Ctrl+P)».

### Qué se añade

Los cuatro reportes se exportan ahora en PDF generado por el servidor, con `dompdf`. Nuevo `core/Services/ReportePdfService.php`: encabezado institucional, subtítulo de contexto (ficha y programa), metadatos de generación, tabla con el semáforo de cumplimiento y numeración «Página X de Y».

### Tres problemas que hubo que resolver

**Memoria.** dompdf mantiene el mapa de celdas de cada `<table>` en memoria, y el reporte de la ficha más grande —1.683 filas— agotaba 512 MB y abortaba. Emitiendo una tabla independiente cada 22 filas, el mismo reporte baja a ~215 MB. Cada bloque repite el encabezado, así que el resultado impreso es indistinguible de una tabla continua.

**Páginas desperdiciadas.** Con el reparto de anchos automático de dompdf, la columna «RA Denominación» se quedaba estrecha y sus celdas crecían a tres líneas: 137 páginas para 1.683 filas, la mitad de ellas medio vacías. Calculando el ancho de cada columna a partir de lo que mide su contenido, y con una tipografía más ajustada, el mismo reporte baja a **78 páginas**. El tamaño de bloque, 22, sale de medir los cuatro reportes: es el mayor valor con el que cada bloque sigue cabiendo en una página.

**Caché de fuentes.** dompdf guardaba las métricas de DejaVu Sans dentro de `vendor/`, que composer reinstala en cada despliegue y que en un servidor endurecido es de solo lectura. Se traslada a `cache/dompdf/`.

### De paso

- El criterio de color pasa a `core/Services/SemaforoReporte.php`. Estaba escrito a mano dentro del `foreach` del export a Excel, y el PDF habría sido una segunda copia: dos copias divergen en cuanto alguien ajusta un umbral en una y olvida la otra.
- Los límites de memoria y de tiempo se elevan solo durante la generación y se restauran al terminar, para que el reporte más grande no dependa de los valores por defecto del servidor.
- El logo es opcional: incrustar el PNG obliga a la extensión GD, que no está activa en todos los entornos. Si falta, el encabezado se dibuja con CSS y el reporte sale igual, en lugar de convertir un adorno en un requisito de despliegue.

### Nota de despliegue

`dompdf/dompdf` se añade a `composer.json`. Como `vendor/` no se versiona, hace falta `composer install` en el servidor.

---

## v2.1

**22 de septiembre de 2026 · 1 commit**

RNF02 («histórico de evaluaciones con trazabilidad de cambios») estaba declarado como cumplido, pero la base de datos decía otra cosa: **987 juicios emitidos frente a 15 filas de historial**.

### La causa

Cuatro caminos distintos escribían `evaluaciones.concepto`, cada uno con garantías diferentes:

| Camino | Transacción | Historial |
|---|---|---|
| `SeguimientoModel::registrarEvaluacion` | no | sí |
| `EvaluacionesModel::actualizarEvaluacion` | no | sí |
| `EvidenciasModel::calificarEvidencia` | sí | **no** |
| `JuiciosImportService` | sí | solo al actualizar, **no al crear** |

Calificar una evidencia cambiaba la nota de un RAP sin dejar rastro de quién ni por qué, y el importador de Sofia Plus creaba miles de juicios sin registrar de dónde salían. Los dos caminos que sí escribían historial lo hacían en tres sentencias sueltas sin transacción: si fallaba el `INSERT` del historial, la nota quedaba cambiada sin constancia.

### El arreglo

- Nuevo `core/Services/EvaluacionService.php`, **única puerta de escritura** de `evaluaciones.concepto`. Los cuatro caminos pasan ahora por él y las firmas públicas de los modelos no cambian, así que los controladores quedan intactos.
- **Transacción siempre.** Si el llamador ya abrió una (evidencias e importación lo hacen), se participa en ella en lugar de anidar, que es lo que PDO no soporta.
- **`SELECT ... FOR UPDATE`** antes de leer el concepto anterior. Sin esto, dos instructores calificando el mismo RAP a la vez escribían un `concepto_anterior` falso.
- **Historial si y solo si el concepto cambia de verdad.** Una evaluación nueva parte de `'pendiente'`, de modo que crear un juicio `A` o `D` también deja constancia, y crear una fila `'pendiente'` no genera ruido.

### Efectos secundarios corregidos de paso

- La búsqueda de la evaluación existente iba por `(RAP, aprendiz, ficha)`, pero el índice `UNIQUE` real de la tabla es `(RAP, aprendiz)`. Tras un traslado de ficha no se encontraba la fila y el `INSERT` reventaba contra el índice.
- Un juicio `'pendiente'` recibía la fecha de hoy en `fecha_evaluacion`, así que los listados mostraban como «evaluado hoy» lo que nadie había mirado. Ahora queda en `NULL`.
- Editar solo el comentario movía la fecha de evaluación. Ahora la fecha solo cambia cuando cambia el juicio.
- El importador podía borrar comentarios escritos por un instructor, porque el reporte de Sofia Plus no los trae. Ahora un comentario ausente significa «no tocar».

### Reconstrucción del historial perdido

`migrations/backfill_historial_evaluaciones.php` crea una fila por cada juicio emitido que no tenía ninguna: **972 filas**, con lo que la cobertura de RNF02 pasa de 1,5 % a **100 %**.

No inventa datos. El autor sale de `evaluaciones.instructor_id` y la fecha de `fecha_evaluacion`, ambos reales; el `concepto_anterior` es `'pendiente'`, que es literalmente lo que había registrado antes. El `motivo` dice de forma explícita que la fila es una reconstrucción y no el registro de un cambio observado, lo que mantiene la operación auditable y reversible (`--revertir`). Por defecto hace un simulacro; hay que pasar `--aplicar` para que escriba.

---

## v2.0.1

**11 de agosto de 2026 · 1 commit**

Documentación reescrita a partir del código en producción.

- Se añaden `docs/HISTORIAS_USUARIO.md` (34 historias en nueve épicas, con criterios de aceptación y trazabilidad al código) y `docs/ARQUITECTURA.md` (componentes, modelo entidad-relación, casos de uso y flujo de evaluación).
- Se reescribe el `README.md`, que describía un proyecto distinto al real: listaba diez archivos CSS y seis de JavaScript que no existen en el repositorio, afirmaba «PHP procedural» cuando ya era MVC con clases, y daba por pendientes cuatro módulos terminados. Además contenía 27 bytes nulos por estar parcialmente en UTF-16, motivo por el que git lo trataba como binario.
- Se corrige una afirmación falsa que arrastraba la documentación anterior: **no existe** bloqueo por intentos fallidos de acceso. Queda declarado como pendiente en lugar de sostenerse.

---

## v2.0

**10 de agosto de 2026 · 5 commits**

Cuatro frentes, cada uno en su propio commit. Lo relevante no fue la estética: tres de los hallazgos eran **fallos silenciosos**, sin mensaje de error en pantalla.

### Interfaz móvil

- Patrón único `.page-header` aplicado a 29 vistas. Cada una escribía su encabezado a mano, así que en un teléfono el título y los botones se disputaban el mismo renglón.
- Capa móvil con corte en 640 px: densidad de botones, área táctil de 44 px, pestañas desplazables y KPIs de dos en dos.
- **Contenido inalcanzable, no solo apretado:** los contenedores de tabla usaban `overflow:hidden`, de modo que en usuarios, detalle de ficha y estructura las últimas columnas —incluida la de Acciones— quedaban recortadas sin forma de llegar a ellas.
- La miga de pan empujaba los controles de la barra superior fuera de la pantalla, y los menús desplegables tenían 360 px fijos en un teléfono de 390 px.

### Contraste en modo oscuro

- Las utilidades `.text-*` de Bootstrap **no** se aclaran en su modo oscuro: eran **172 usos** en las vistas con letra oscura sobre fondo oscuro. Se sobrescriben de forma centralizada.
- Los tokens semánticos hacían de relleno y de color de texto a la vez, lo que impedía aclararlos sin romper los botones. Se separa cada uno en un par `--x` / `--x-text`.
- Los banners de bienvenida llevaban `btn-light text-dark`: al remapear `.text-dark` al color del tema, en oscuro quedaba botón claro con letra casi blanca. Se introduce `.on-dark`.

### Sincronización de evaluaciones

- Se extrae `EvaluacionesSyncService` como única fuente de verdad de la regla «cada aprendiz en formación debe tener una fila por cada resultado de aprendizaje de su programa».
- La regla se aplicaba solo al matricular y estaba escrita dos veces. Eso dejaba **31 aprendices con cero evaluaciones (256 filas ausentes)** y hacía que un resultado creado después de la matrícula no llegara a nadie: invisible en pantalla e imposible de calificar.
- Se invoca ahora desde los cinco caminos que crean o mueven resultados de aprendizaje. Migración de recuperación idempotente, verificada sin alterar ninguno de los 987 juicios ya emitidos.
- Sale a la luz un fallo latente: `evaluaciones.instructor_id` es `NOT NULL` con clave foránea, pero el código pasaba nulo cuando la ficha no tenía instructor líder.
- Se retiran las guardias `function_exists` de los puntos de llamada: no evitaban el fallo, **lo ocultaban**.

### Paginación

- Dos consultas recortaban datos en silencio: `LIMIT 200` en el listado de juicios (con 3.873 registros, la coordinación veía 200 sin ninguna señal de que existieran los demás) y `LIMIT 100` en la bitácora de auditoría, que volvía inalcanzable todo el historial anterior.
- Mecanismo único `Paginator` más componente de vista compartido. En los modelos, el `FROM`+`WHERE` se comparte entre el listado y el conteo para que el total no pueda desalinearse de las filas.
- Consecuencia obligada: **la búsqueda pasa a SQL**. Se retiran los filtros de JavaScript que ocultaban filas ya renderizadas, porque con 25 filas por página solo habrían alcanzado a la visible.

**Reducción de altura de página en móvil:** evaluaciones 37.136 → 5.401 px, matrículas 16.365 → 3.310 px, usuarios 14.316 → 2.228 px, seguimiento 8.706 → 2.835 px.

---

## v1.1

**28 al 31 de julio de 2026 · 10 commits**

- **Seguridad:** mitigación de inyección SQL y XSS, con lista blanca de caracteres en la validación de entradas.
- **Unificación de UX:** un solo estilo de modal en todo el sistema (convivían tres), corrección del modo oscuro en tablas y componentes de Bootstrap mediante el puente de variables, y migración de todos los `select` nativos al selector con búsqueda.
- **Mayúsculas visuales:** los datos de la estructura curricular se muestran en mayúsculas de forma uniforme sin alterar lo que se guarda.
- Corrección del doble codificado de acentos y del calendario en móvil.
- **Importaciones:** previsualización antes de escribir, deduplicación en usuarios y competencias, y coherencia de formatos de archivo.
- **Correo real:** envío por SMTP con `MailService` y PHPMailer, y rediseño institucional del correo de recuperación de contraseña.
- Corrección del sidebar responsivo y de la dependencia de lectura de Excel.

---

## v1.0

**3 al 8 de julio de 2026 · 27 commits**

Auditoría completa del proyecto en cinco dimensiones —seguridad, lógica de negocio, SOLID, base de datos y experiencia de usuario— con **37 hallazgos** clasificados por severidad, corregidos en cinco fases y un commit por corrección.

### Fase 1 · Seguridad crítica

- Retirada del **acceso biométrico falso**: el cliente hacía WebAuthn real, pero la comprobación en el servidor era un HMAC con sal fija, no una verificación de firma.
- `install.php` (que recreaba la base completa sin autenticación) deja de estar versionado.
- `uploads/` bloquea la ejecución de código y valida el tipo real de archivo con `finfo`, con nombres aleatorios.
- Se sustituye la **contraseña fija por defecto** de las cuentas masivas por una temporal aleatoria por usuario, con cambio forzado al primer acceso.

> Durante esta fase se descubrió que `includes/functions.php` no se cargaba hasta que se renderizaba la plantilla, es decir, **después** de que la lógica del controlador ya hubiera corrido. Varias llamadas lo tapaban con guardias `function_exists`. Ese fue el origen de los 31 aprendices sin evaluaciones que se corrigieron en la v2.0.

### Fase 2 · Lógica y exposición de datos

- Inyección de cabecera `Host` en el enlace de recuperación.
- XSS en importaciones masivas y en los `json_encode()` embebidos en atributos HTML.
- **IDOR**: el detalle de ficha no comprobaba la propiedad para el rol instructor. Además, `denyAccess()` se invocaba en tres sitios sin estar definida en ninguno, lo que era un error fatal para cualquier aprendiz sin ficha.
- Borrado lógico en usuarios y matrículas: el borrado físico **fallaba el 100 % de las veces** por clave foránea en cuanto la persona tenía cualquier actividad registrada.
- El widget de últimas evaluaciones del panel estaba siempre vacío por un `JOIN` ausente.
- **Fuga de alcance:** el reporte de cumplimiento por instructor filtraba por ficha pero agregaba los totales de toda la ficha, así que un instructor a cargo de una competencia veía cifras de competencias ajenas.

### Fase 3 · SOLID

- `InstructorAccessService` unifica la regla de permisos del instructor, escrita en tres sitios con criterios divergentes: la retroalimentación solo reconocía al instructor líder e ignoraba las asignaciones por competencia.
- `requireRole`/`requireAuth` se mueven al constructor en 18 controladores.
- Se extraen 360 líneas de análisis de PDF del controlador a `EstructuraPdfParser`, y en el proceso se corrige un error que provocaba un fallo fatal cada vez que una importación confirmaba una fase o competencia realmente nueva.

### Fase 4 · Integridad de la base

- `CASCADE` → `RESTRICT` en la cadena programas → competencias → resultados de aprendizaje → evaluaciones. Con cascada, borrar un programa arrastraba en silencio **todos los juicios evaluativos asociados**.
- Unicidad en los códigos de competencia y de resultado de aprendizaje, con corrección posterior del alcance a `(programa_id, codigo)`.
- Al trasladar un aprendiz de ficha se sincroniza la ficha de sus evaluaciones y evidencias: sin eso, recalificar un resultado ya evaluado violaba la restricción de unicidad.
- **La retroalimentación era un hueco de escritura, no de lectura:** solo el módulo de evidencias insertaba en la tabla, de modo que los comentarios registrados desde seguimiento o evaluaciones nunca le llegaban al aprendiz.

### Fase 5 · Experiencia de usuario

- Corrección del *mojibake* de acentos y emojis en 16 vistas, decodificando solo las cadenas dañadas y no los archivos completos, porque varias vistas mezclaban texto correcto y roto.
- Sidebar inutilizable en móvil: no había forma de leer las opciones del menú. Se reconstruye como panel deslizable.
- Patrón de redirección posterior a escritura extendido a cuatro módulos más.
- Accesibilidad: asociación de etiquetas con campos en los formularios de mayor tráfico y alternativa textual en las gráficas del panel.
- Corrección de ocho errores de tipo en el bloque académico y de un `?>` huérfano en reportes.

---

## v0.4

**30 de junio al 1 de julio de 2026 · 14 commits**

- **Migración MVC completa:** seguimiento, actividades y el bloque académico (evaluaciones, evidencias, mejoramiento, retroalimentación) dejan de ser archivos monolíticos con SQL y HTML mezclados.
- **CSRF global** con inyector automático en formularios, y resolución de la asincronía del identificador de pestaña entre la sesión y el `POST`.
- Validación estricta cliente-servidor en todos los módulos, con límites de longitud y lista de caracteres permitidos.
- Retirada del lector biométrico simulado.
- Patrón de redirección posterior a escritura con mensajes de un solo uso.

---

## v0.3

**18 al 22 de junio de 2026 · 46 commits**

La versión con más movimiento del proyecto: se resolvió el despliegue y, en paralelo, la lectura de archivos Excel, que exigió varios intentos.

- **Despliegue:** `Dockerfile` y `docker-compose.yml` para el VPS, `APP_URL` configurable por entorno, y generación del autocargador PSR-4 en la construcción.
- **PWA:** manifiesto y *service worker* registrados.
- **Seguridad:** aplicación de SOLID, MVC, CSRF y control de acceso por rol en el módulo de usuarios; endurecimiento de la sesión bajo HTTPS.
- **Lectura de Excel:** cuatro aproximaciones sucesivas hasta dar con una que funcionara en el servidor —subida en base64 para evitar bloqueos del sistema operativo, conversión con LibreOffice en Linux, y finalmente lectura directa con SimpleXLS.
- **Importaciones:** resumen con tabla detallada, agrupación por aprendiz con filas desplegables y almacenamiento por pestaña.
- **Rediseño visual:** fondo pizarra, sidebar oscuro, banner de bienvenida, botones con micro-interacciones y mejoras de legibilidad en modo oscuro.
- Migración a MVC de los tres paneles (coordinador, instructor, aprendiz), fichas y evaluaciones.
- Autenticación biométrica con WebAuthn, retirada más adelante en la v1.0 al no tener verificación real en el servidor.

---

## v0.2

**2 y 3 de junio de 2026 · 13 commits**

- **Enrutador frontal** con clases base MVC y primeros modelos; matrículas es el primer módulo migrado a vistas limpias.
- **Instructor de seguimiento y etapa práctica:** nuevo rol de acompañamiento con su propio alcance.
- **Tabla `asignaciones`:** permite que un instructor responda por una competencia concreta de una ficha sin ser su líder. Es la base del control de acceso fino.
- Renombrado de «instructor» a «instructor líder» para ajustarse a la terminología del dominio.
- **Calendario académico** por rol, con API JSON.
- KPIs con gráficas y datos reales de la base.
- Ajuste de las consultas y del filtrado por rol en los módulos clave.

---

## v0.1

**19 al 27 de mayo de 2026 · 5 commits**

- Estructura inicial del proyecto y esquema de base de datos.
- Rol de coordinador con importación de archivos PDF y Excel, y rediseño del menú.
- Buscador en el módulo de seguimiento académico.

---

## Lectura del historial

Tres patrones que explican mejor el proyecto que la lista de funcionalidades:

**El pico de junio no fue avance, fue fricción.** Los 46 commits de la v0.3 son en buena parte intentos sucesivos de resolver dos problemas de entorno: el despliegue en el VPS y la lectura de archivos Excel. Hay commits de diagnóstico (`debug:`, `diagnose:`) que se retiran después. Es el coste real de trabajar contra un servidor que no se comporta como el entorno local.

**La auditoría encontró lo que la funcionalidad ocultaba.** Los 27 commits de la v1.0 no añadieron ninguna funcionalidad: corrigieron 37 fallos que ya estaban ahí. Varios eran de la clase peor —el borrado que fallaba siempre, el reporte que mezclaba competencias, la retroalimentación que no llegaba— porque no producían un error visible.

**Los fallos silenciosos aparecen midiendo, no suponiendo.** Los tres hallazgos de mayor valor de la v2.0 —columnas inalcanzables, listados recortados y una regla escrita dos veces— se encontraron verificando en un navegador real y contando filas en la base de datos, no revisando código.

---

*Elaborado a partir de `git log`. Las cifras de commits por versión suman exactamente los 121 del repositorio.*
