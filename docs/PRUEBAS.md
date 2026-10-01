# Pruebas

**Sistema de Seguimiento de Proyectos Formativos — SENA** · versión 3.4

Cómo se verifica el sistema: pruebas automáticas de PHPUnit, recorridos automáticos en navegador e integración continua. La guía práctica para ejecutarlas está en [tests/README.md](../tests/README.md) y, para el navegador, en [tests/e2e/README.md](../tests/e2e/README.md).

---

## 1. Resumen

| Suite | Casos | Comprobaciones | Base de datos |
|---|---:|---:|---|
| Unitarias (`tests/Unit`) | 409 | 1.160 | No |
| Seguridad (`tests/Security`) | 191 | 2.014 | Algunas |
| Integración (`tests/Integration`) | 222 | 1.775 | Sí |
| **Total PHPUnit** | **822** | **4.949** | |
| Navegador (`tests/e2e`, Playwright) | 46 recorridos | | Propia (`sena_e2e`) |

```bash
composer test                 # todas las de PHPUnit
composer test:unit            # unitarias
composer test:security        # seguridad
composer test:integration     # integración
vendor/bin/phpunit --testdox  # con la descripción de cada caso
cd tests/e2e && npx playwright test   # recorridos en navegador
```

Las pruebas con base de datos corren dentro de una transacción que se revierte: no dejan rastro en la base de trabajo. `failOnWarning` y `failOnNotice` están activos. Ninguna prueba envía correo: el arranque de PHPUnit y el servidor de los recorridos anulan las credenciales SMTP del `.env`.

## 2. Qué cubre cada clase

### Unitarias

| Clase | Casos | Qué garantiza |
|---|---:|---|
| `ValidadorTest` | 76 | Enteros, textos, fechas, decimales, correos, enums, identificadores y colores: rechaza lo que un cast aceptaría (`12abc`, `?id[]=1`, `2026-02-30`, CSS en un color) |
| `PersonasFormularioTest` | 44 | Usuarios, matrículas, perfil y contraseñas: normalización, topes exactos, documento según su tipo, edad de 14 a 90 años (también nacidos antes del 2000) |
| `FormacionFormularioTest` | 41 | Programas, competencias, RAP, fichas, proyectos, fases y actividades: códigos, horas, rangos de fechas, avance coherente con el estado |
| `NotificadorUrlTest` | 35 | Los enlaces de los avisos solo apuntan dentro de la aplicación: sin esquemas, dominios, `//`, saltos de línea ni travesías |
| `SemaforoReporteTest` | 35 | Colores por umbral en los reportes; neutralización de fórmulas en celdas |
| `SeguimientoFormularioTest` | 27 | Juicios (solo A o D), retroalimentación, evidencias y planes de mejoramiento (plazo de hasta 180 días) |
| `SeguridadTest` | 24 | CSP sin scripts ni estilos en línea (salvo nonce), ninguna vista con código o `style=` en línea, SRI en todo recurso externo, JavaScript sin `innerHTML` con datos, detección de HTTPS |
| `LectorTabularTest` | 23 | CSV (separadores, BOM, Windows-1252, comillas) y Excel: ida y vuelta con el propio exportador, tipos de celda, fechas de Excel, primera hoja real, bomba zip |
| `PaginatorTest` | 19 | Páginas fuera de rango, tamaños manipulados, desplazamientos |
| `RouterTest` | 16 | Normalización de la ruta, sin travesía de directorios, registro por método, roles por defecto |
| `SemaforoTest` | 16 | Bordes de la regla del semáforo (59,9 / 60 / 79,9 / 80 %, 0–3 D, sin juicios) |
| `ErrorDeNegocioTest` | 14 | Qué mensajes se muestran al usuario y cuáles se ocultan (errores de base de datos) |
| `ManejadorErroresTest` | 12 | Errores y excepciones no capturadas: referencia al usuario, detalle al registro, JSON si la petición lo espera |
| `PoliticaContrasenaTest` | 11 | Longitud, letras y números, 72 bytes, caracteres de control, usuario del correo; la clave temporal siempre cumple |
| `EnumsTest` | 9 | Listas blancas de estados y conceptos |
| `ExportacionCompletaTest` | 4 | Ningún archivo sale cortado en silencio: listados, Excel y CSV avisan pasadas las 20.000 filas, y el PDF pasadas las 2.000 (antes agotaba la memoria) |
| `ArranqueTest` | 3 | El entorno de pruebas carga y aísla la sesión entre casos |

### Seguridad

| Clase | Casos | Qué se intenta |
|---|---:|---|
| `InyeccionTest` | 59 | 13 cargas SQL contra cada buscador (incluida inyección temporal con `SLEEP`), XSS, inyección de cabeceras SMTP, travesía de rutas |
| `CsrfYSesionTest` | 28 | Token de otra pestaña, comparación en tiempo constante, `tabId` manipulado, caducidad por inactividad y absoluta, recolección de pestañas |
| `SubidaDeArchivosTest` | 26 | 13 extensiones ejecutables, doble extensión, byte nulo, PHP disfrazado de PDF o imagen, carpeta de subidas cerrada |
| `AutorizacionTest` | 20 | IDOR sobre evaluaciones, evidencias, notificaciones y reportes; escalada de privilegios; aislamiento por rol |
| `BusquedaYFiltrosTest` | 19 | Comodines `%` y `_`, filtros manipulados que mostraban de más |
| `AutenticacionTest` | 16 | Fuerza bruta sin cookies, barrido de cuentas, `X-Forwarded-For` rotado, enumeración por tiempo, cuentas inactivas, contraseñas hasheadas |
| `FugaDeInformacionTest` | 17 | Volcado de depuración en el 403, `getMessage()` en pantalla (también envuelto en un `ErrorDeNegocio`), vistas sin guarda, registros de recuperación fuera de git |
| `SuperficieWebTest` | 6 | Toda carpeta de la raíz es pública a propósito o está negada; ningún script de consola se ejecuta por URL |

### Integración

| Clase | Casos | Qué garantiza |
|---|---:|---|
| `EsquemaTest` | 39 | Modo estricto, misma hora en PHP y en la base, llaves foráneas, índices, únicos, trazabilidad completa de los juicios |
| `EvaluacionServiceTest` | 24 | Única puerta de escritura del juicio: historial solo si cambia, transacción, bloqueo de fila, motivo obligatorio |
| `GestionAcademicaTest` | 23 | Fichas (programa activo, cambio de programa sin arrastrar RAP del anterior), matrículas, traslados, retiros que cierran planes, reintegros que completan los RAP, asignaciones y cuentas de coordinación |
| `ModelosRestantesTest` | 19 | Consultas de todos los modelos en los tres roles; conteos que cuadran |
| `SoporteTest` | 16 | Transacciones anidadas y revertidas, traducción de errores reales de la base, configuración |
| `ConsultasDeModelosTest` | 15 | Analítica por rol, semáforo SQL = PHP, progreso del aprendiz |
| `ImportadorJuiciosTest` | 14 | Importación de Sofia Plus: conceptos, permisos del instructor, estructura faltante, historial |
| `EstructuraCurricularTest` | 12 | Competencias y RAP: códigos transversales, pendientes al crear un RAP, etapa práctica y quién responde, asignaciones, borrado sin perder historial |
| `ProyectoFormativoTest` | 11 | Proyectos, fases, actividades y avance calculado (RF02) |
| `AuditoriaYReportesTest` | 10 | Eventos de la bitácora; cada reporte en PDF y Excel; volumen grande |
| `EvidenciasTest` | 10 | Envío ligado a un RAP propio, descarga con permiso, revisión y juicio separados, sin juicios nuevos a un desertado |
| `CuentasDeAprendizTest` | 9 | El rol de aprendiz va con la matrícula: Usuarios no lo da ni lo quita, y una cuenta de aprendiz sin ficha se completa al matricularla |
| `PlanesMejoramientoTest` | 10 | Ciclo del plan: apertura sobre un D, en curso, cierre cumplido (A) o no cumplido; el plazo no se mueve al pasado; se cierra solo si el RAP se aprueba por otra vía y pasa a quien califica el RAP |
| `RutasYPermisosTest` | 8 | Matriz de accesos; canario del número de rutas (111) |
| `AuditoriaContrasenasTest` | 2 | Detecta las cuentas con contraseñas conocidas (también si comparten hash) y las anula con temporal y bitácora |

## 3. Integración continua

`.github/workflows/pruebas.yml` se ejecuta en cada envío a `main`, `feat/**`, `fix/**`, `docs/**`, `refactor/**` y `release/**`, y en cada solicitud de cambios hacia `main`. Tiene dos trabajos, cada uno con su MariaDB 10.4:

**pruebas**

1. PHP 8.2 con las extensiones de producción.
2. `composer validate --strict` e instalación de dependencias.
3. Comprobación de sintaxis de todo el PHP.
4. **Instalación desde cero** con `bin/instalar.php --confirmar-borrado-total --demo`: prueba que el esquema y las 19 migraciones producen una base válida.
5. `bin/verificar-esquema.php`: sin migraciones pendientes y enums del código iguales a los de la base.
6. `bin/generar-docs.php` y comparación con lo versionado: falla si alguien cambió rutas, tablas o importaciones sin regenerar la documentación.
7. Las tres suites de PHPUnit. Si una falla, sus casos se publican como anotaciones del commit (`bin/anotar-junit.php`).

**navegador**

1. PHP 8.2, Node 22, Playwright y Chromium.
2. Los 46 recorridos de `tests/e2e` contra el servidor integrado de PHP y su propia base, reinstalada con los datos de demostración.
3. Cada fallo se publica como anotación del commit; el informe HTML, las capturas y las trazas quedan como artefacto durante 7 días.

## 4. Recorridos en navegador

PHPUnit no ejecuta un navegador. Los recorridos de `tests/e2e` usan la aplicación como lo haría cada rol, con Playwright, y se repiten en cada envío:

| Archivo | Qué recorre |
|---|---|
| `01-publico` | Inicio de sesión con CSP estricta, credenciales incorrectas con mensaje genérico, cierre de sesión por rol, recuperación de contraseña de punta a punta |
| `02-permisos` | Pantallas y acciones de otro rol (URL forzada o formulario enviado a mano), formulario sin token CSRF, expediente ajeno |
| `03-coordinador` | Programa → competencia → RAP → cuenta con clave temporal y cambio obligatorio → ficha → matrícula → asignación → exportaciones; duplicados rechazados |
| `04-importaciones` | Los archivos de `docs/ejemplos`: vista previa, confirmación y reimportación sin duplicar, en CSV y Excel, y el reporte de Sofia Plus |
| `05-ciclo-formativo` | Juicio A → cambio a D con motivo e historial → aviso → plan de mejoramiento → evidencia (un PHP disfrazado de PDF se rechaza) → revisión → cierre cumplido |
| `06-reportes` | Los seis reportes en Excel, PDF y CSV, su alcance por rol, el límite del historial y el CSV sin fórmulas |
| `07-pantallas` | Todas las pantallas de cada rol en escritorio y en el teléfono (390 px, tema oscuro): sin errores de JavaScript, sin bloqueos de la CSP y sin desborde horizontal |
| `08-transversales` | Calendario y su aviso (y que otra ficha no lo vea), perfil, configuración con bitácora y campana de avisos |

Además de detectar regresiones, los recorridos encontraron fallos reales que se corrigieron en la v3.4: el aviso que faltaba al negar un acceso, el enlace de recuperación que volvía al primer paso tras un error, el icono que faltaba en cada página y la importación del propio Excel.

## 5. Verificación de una instalación nueva

```bash
DB_NAME=sena_verificacion php bin/instalar.php --confirmar-borrado-total --demo
DB_NAME=sena_verificacion php bin/verificar-esquema.php
DB_NAME=sena_verificacion vendor/bin/phpunit
```

Las 822 pruebas pasan tanto sobre la base de trabajo como sobre una instalación limpia con datos de demostración.

## 6. Cómo añadir una prueba

- Una regla de negocio nueva va con su prueba de integración en el servicio (`tests/Integration`).
- Una corrección de seguridad va con una prueba en `tests/Security` que falle si el fallo vuelve, y su nombre debe decir qué impide.
- Una ruta nueva obliga a actualizar el canario de `RutasYPermisosTest` y a revisar sus roles; después, `php bin/generar-docs.php` regenera [RUTAS_Y_PERMISOS.md](RUTAS_Y_PERMISOS.md).
