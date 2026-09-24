# Pruebas

**Sistema de Seguimiento de Proyectos Formativos — SENA** · versión 3.2

Cómo se verifica el sistema: pruebas automáticas, integración continua y recorridos en navegador. La guía práctica para ejecutarlas está en [tests/README.md](../tests/README.md).

---

## 1. Resumen

| Suite | Casos | Comprobaciones | Base de datos |
|---|---:|---:|---|
| Unitarias (`tests/Unit`) | 232 | 831 | No |
| Seguridad (`tests/Security`) | 190 | 1.992 | Algunas |
| Integración (`tests/Integration`) | 172 | 1.658 | Sí |
| **Total** | **594** | **4.481** | |

```bash
composer test                 # todas
composer test:unit            # unitarias
composer test:security        # seguridad
composer test:integration     # integración
vendor/bin/phpunit --testdox  # con la descripción de cada caso
```

Las pruebas con base de datos corren dentro de una transacción que se revierte: no dejan rastro en la base de trabajo. `failOnWarning` y `failOnNotice` están activos.

## 2. Qué cubre cada clase

### Unitarias

| Clase | Casos | Qué garantiza |
|---|---:|---|
| `ValidadorTest` | 76 | Enteros, textos, fechas, decimales, correos, enums, identificadores y colores: rechaza lo que un cast aceptaría (`12abc`, `?id[]=1`, `2026-02-30`, CSS en un color) |
| `SemaforoReporteTest` | 35 | Colores por umbral en los reportes; neutralización de fórmulas en celdas |
| `PaginatorTest` | 19 | Páginas fuera de rango, tamaños manipulados, desplazamientos |
| `RouterTest` | 16 | Normalización de la ruta, sin travesía de directorios, registro por método, roles por defecto |
| `SemaforoTest` | 16 | Bordes de la regla del semáforo (59,9 / 60 / 79,9 / 80 %, 0–3 D, sin juicios) |
| `ErrorDeNegocioTest` | 14 | Qué mensajes se muestran al usuario y cuáles se ocultan (errores de base de datos) |
| `ManejadorErroresTest` | 12 | Errores y excepciones no capturadas: referencia al usuario, detalle al registro, JSON si la petición lo espera |
| `SeguridadTest` | 22 | CSP sin scripts en línea, ninguna vista con código en línea, SRI en todo recurso externo, JavaScript sin `innerHTML` con datos, detección de HTTPS |
| `PoliticaContrasenaTest` | 10 | Longitud, letras y números, 72 bytes, caracteres de control, usuario del correo; la clave temporal siempre cumple |
| `EnumsTest` | 9 | Listas blancas de estados y conceptos |
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
| `FugaDeInformacionTest` | 16 | Volcado de depuración en el 403, `getMessage()` en pantalla, vistas sin guarda, registros de recuperación |
| `SuperficieWebTest` | 6 | Toda carpeta de la raíz es pública a propósito o está negada; ningún script de consola se ejecuta por URL |

### Integración

| Clase | Casos | Qué garantiza |
|---|---:|---|
| `EsquemaTest` | 38 | Modo estricto, llaves foráneas, índices, únicos, trazabilidad completa de los juicios |
| `EvaluacionServiceTest` | 24 | Única puerta de escritura del juicio: historial solo si cambia, transacción, bloqueo de fila, motivo obligatorio |
| `ModelosRestantesTest` | 19 | Consultas de todos los modelos en los tres roles; conteos que cuadran |
| `GestionAcademicaTest` | 17 | Reglas de fichas, matrículas, traslados, asignaciones y cuentas de coordinación |
| `ConsultasDeModelosTest` | 15 | Analítica por rol, semáforo SQL = PHP, progreso del aprendiz |
| `ImportadorJuiciosTest` | 14 | Importación de Sofia Plus: conceptos, permisos del instructor, estructura faltante, historial |
| `ProyectoFormativoTest` | 11 | Proyectos, fases, actividades y avance calculado (RF02) |
| `AuditoriaYReportesTest` | 10 | Eventos de la bitácora; cada reporte en PDF y Excel; volumen grande |
| `EvidenciasTest` | 9 | Envío ligado a un RAP propio, descarga con permiso, revisión y juicio separados |
| `RutasYPermisosTest` | 8 | Matriz de accesos; canario del número de rutas (111) |
| `PlanesMejoramientoTest` | 7 | Ciclo del plan: apertura sobre un D, en curso, cierre cumplido (A) o no cumplido |

## 3. Integración continua

`.github/workflows/pruebas.yml` se ejecuta en cada envío a `main`, `feat/**`, `fix/**`, `docs/**`, `refactor/**` y `release/**`, y en cada solicitud de cambios hacia `main`:

1. PHP 8.2 con las extensiones de producción y MariaDB 10.4.
2. `composer validate --strict` e instalación de dependencias.
3. Comprobación de sintaxis de todo el PHP.
4. **Instalación desde cero** con `bin/instalar.php --confirmar-borrado-total --demo`: prueba que el esquema y las 18 migraciones producen una base válida.
5. `bin/verificar-esquema.php`: sin migraciones pendientes y enums del código iguales a los de la base.
6. Las tres suites. Si una falla, sus casos se publican como anotaciones del commit (`bin/anotar-junit.php`).

## 4. Pruebas en navegador

Las pruebas automáticas no ejecutan un navegador, así que cada módulo se recorrió además con **Playwright** (Microsoft Edge) en los tres roles:

| Qué se comprobó | Resultado |
|---|---|
| Las 51 pantallas de los tres roles cargan sin errores de JavaScript ni bloqueos de la CSP | Sin incidencias |
| Ninguna pantalla desborda horizontalmente a **390 px** (teléfono), en tema claro y oscuro | Sin incidencias |
| Flujos completos: crear y editar registros, importar en dos pasos, calificar, enviar y revisar evidencias, abrir y cerrar planes, exportar en los tres formatos | Correctos |
| Permisos: un rol que fuerza una URL o una acción ajena es redirigido al panel con aviso | Correcto |
| Botón de tema, menú móvil, modales, confirmaciones y autoenvío de filtros (JavaScript en archivos, sin código en línea) | Correctos |

Tras cada recorrido se eliminaron los datos de prueba creados.

## 5. Verificación de una instalación nueva

```bash
DB_NAME=sena_verificacion php bin/instalar.php --confirmar-borrado-total --demo
DB_NAME=sena_verificacion php bin/verificar-esquema.php
DB_NAME=sena_verificacion vendor/bin/phpunit
```

Las 594 pruebas pasan tanto sobre la base de trabajo como sobre una instalación limpia con datos de demostración.

## 6. Cómo añadir una prueba

- Una regla de negocio nueva va con su prueba de integración en el servicio (`tests/Integration`).
- Una corrección de seguridad va con una prueba en `tests/Security` que falle si el fallo vuelve, y su nombre debe decir qué impide.
- Una ruta nueva obliga a actualizar el canario de `RutasYPermisosTest` y a revisar sus roles; después, `php bin/generar-docs.php` regenera [RUTAS_Y_PERMISOS.md](RUTAS_Y_PERMISOS.md).
