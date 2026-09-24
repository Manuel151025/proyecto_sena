# Sistema de Seguimiento de Proyectos Formativos — SENA

Plataforma web para planear, evaluar y hacer seguimiento a los proyectos formativos de las fichas del **Centro Tecnológico de la Amazonia**. Reúne la estructura curricular, las fichas y matrículas, el proyecto formativo con sus fases y actividades, el juicio evaluativo por resultado de aprendizaje con su historial, las evidencias, los planes de mejoramiento y la analítica por rol, con permisos por rol y por dato y bitácora de auditoría.

![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?logo=php&logoColor=white)
![MariaDB](https://img.shields.io/badge/MariaDB-10.4-003545?logo=mariadb&logoColor=white)
![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap&logoColor=white)
![Pruebas](https://img.shields.io/badge/pruebas-594-39A900)
![Versión](https://img.shields.io/badge/versión-3.2-39A900)

---

## El problema

El seguimiento se llevaba en hojas de cálculo de cada instructor y en los reportes de Sofia Plus:

- **Sin fuente única.** La coordinación no conocía el avance real de una ficha sin pedir los archivos uno a uno.
- **Sin trazabilidad.** Cambiar un juicio no dejaba constancia de quién, cuándo ni por qué.
- **Nivelación manual.** Detectar RAP en «D» y dar seguimiento a su plan dependía de revisar cada archivo.
- **Proyecto desconectado.** Las fases y actividades del proyecto formativo no estaban ligadas al avance de la ficha.

## La solución

| Requisito | Qué hace el sistema |
|---|---|
| **RF01** Estructura curricular | Programas, competencias y RAP; importación desde el PDF del programa o desde hoja de cálculo |
| **RF02** Proyecto formativo | Proyectos por programa, fases con fechas, actividades por ficha y fase, avance calculado |
| **RF03** Evaluación | Juicio A / D / pendiente por RAP; importación del reporte de Sofia Plus en dos pasos; evidencias ligadas al RAP |
| **RF04** Progreso | Paneles por rol con semáforo de riesgo; expediente de cada aprendiz; planes de mejoramiento con plazo y cierre |
| **RF05** Reportes | Por ficha, instructor, competencia, aprendices en riesgo e historial de cambios |
| **RNF01** Roles | Coordinador, instructor y aprendiz; permisos por ruta y por dato |
| **RNF02** Trazabilidad | Historial de cada cambio de juicio y bitácora de todas las operaciones |
| **RNF03** Exportación | Excel (.xlsx real), CSV y PDF |
| Móvil | Todas las pantallas usables a 390 px; instalable como aplicación; modo oscuro |

---

## Documentación

| Documento | Contenido |
|---|---|
| [Historias de usuario](docs/HISTORIAS_USUARIO.md) | 47 historias en 12 épicas con criterios de aceptación y trazabilidad a RF/RNF y al código |
| [Arquitectura](docs/ARQUITECTURA.md) | Capas, secuencia de una petición, control de acceso, dominio, decisiones de diseño |
| [Flujos](docs/FLUJOS.md) | 17 diagramas de flujo y de estados: acceso, evaluación, importación, evidencias, planes, avisos… |
| [Analítica](docs/ANALITICA.md) | Indicadores de cada rol, fórmulas, semáforo y catálogo de reportes |
| [Seguridad](docs/SEGURIDAD.md) | Revisión OWASP: 36 hallazgos corregidos, controles por capa, límites de entrada y salida, riesgos residuales |
| [Manual de usuario](docs/MANUAL_USUARIO.md) | Uso paso a paso para aprendiz, instructor y coordinador |
| [Despliegue](docs/DESPLIEGUE.md) | Instalación (Apache y Docker), variables de entorno, actualización, copias de seguridad |
| [Pruebas](docs/PRUEBAS.md) | 594 pruebas automáticas, integración continua y recorridos en navegador |
| [Datos](docs/DATOS.md) · [Rutas y permisos](docs/RUTAS_Y_PERMISOS.md) · [Formatos de importación](docs/FORMATOS_IMPORTACION.md) | Generados desde el código con `php bin/generar-docs.php` |
| [Historial de versiones](CHANGELOG.md) | Qué cambió en cada versión y por qué |

---

## Arquitectura

Monolito PHP sin framework, en capas, con **punto de entrada único**:

```text
Navegador
   │
index.php ── sesión por pestaña, CSRF en todo POST
   │
Router ───── config/rutas.php: cada ruta declara qué roles la alcanzan
   │
Controlador ── orquesta y responde (PRG); no contiene reglas ni SQL
   │
Formulario ─── Validador: tipos, rangos y listas blancas
   │
Servicio ───── reglas de negocio y permiso por dato (recibe el Actor)
   │
Modelo ─────── SQL preparado, acotado por rol en el WHERE
   │
MariaDB (modo estricto)
```

Principios:

1. **Cada regla en un solo sitio.** Quién califica (`InstructorAccessService`), cómo se escribe un juicio (`EvaluacionService`), cómo se clasifica el riesgo (`Semaforo`), cómo se importa (`Importador`) y cómo se exporta (`Exportador`).
2. **Las cifras se calculan al leer.** Nada de contadores guardados que se desvían.
3. **La autorización se comprueba en el servidor, dos veces:** por rol en la ruta y por dato en el servicio y la consulta.

Detalle en [docs/ARQUITECTURA.md](docs/ARQUITECTURA.md).

### Cifras

| | | | |
|---|---|---|---|
| **111** rutas y acciones | **25** controladores | **22** modelos | **26** servicios |
| **12** formularios | **5** importaciones | **22** tablas | **18** migraciones |
| **27** vistas | **594** pruebas | **≈24.700** líneas de PHP | **≈1.800** líneas de JS |

---

## Módulos por rol

| Módulo | Coordinador | Instructor | Aprendiz |
|---|:---:|:---:|:---:|
| Panel de indicadores | Centro | Sus fichas y su carga | Su progreso |
| Calendario | ✅ | Sus fichas | Su ficha |
| Usuarios | ✅ | — | — |
| Estructura curricular | ✅ | Consulta | — |
| Fichas | ✅ | Sus fichas | La suya |
| Matrículas | ✅ | Consulta | — |
| Asignación de instructores | ✅ | Consulta | — |
| Proyectos, fases y actividades | ✅ | ✅ en sus fichas | Consulta |
| Seguimiento (expediente) | ✅ | Sus aprendices | El suyo |
| Evaluaciones | ✅ | Lo que califica | Las suyas |
| Evidencias | ✅ | Revisa las de sus aprendices | Envía las suyas |
| Retroalimentación | ✅ | ✅ | La pública suya |
| Plan de mejoramiento | ✅ | ✅ | Los suyos |
| Reportes | Centro | Lo que califica | — |
| Configuración y bitácora | ✅ | — | — |

Matriz completa, ruta por ruta, en [docs/RUTAS_Y_PERMISOS.md](docs/RUTAS_Y_PERMISOS.md).

---

## Seguridad

Revisión completa contra el OWASP Top 10 con **36 hallazgos corregidos** (3 críticos: `.env` y `.git` descargables por web, `.htaccess` ignorados en Docker, instalador con `DROP DATABASE` accesible por URL). Controles vigentes:

| Control | Implementación |
|---|---|
| Inyección SQL | Sentencias preparadas en todas las consultas; identificadores dinámicos por lista blanca |
| XSS | Escape en toda salida; CSP sin `'unsafe-inline'` en scripts; JavaScript sin `innerHTML` con datos |
| CSRF | Token en todo POST, comparación en tiempo constante |
| Acceso | Roles por ruta, permiso por dato en servicio y consulta |
| Sesión | Por pestaña, `HttpOnly`, `SameSite`, caducidad por inactividad (2 h) y absoluta (12 h) |
| Contraseñas | bcrypt, política única, clave temporal obligatoria de cambiar, bloqueo tras intentos fallidos |
| Archivos | Extensión, tamaño, firma y MIME real; fuera de la web; descarga con permiso |
| Importación / exportación | Límites de tamaño, filas, columnas y descompresión; neutralización de fórmulas |
| Auditoría | Bitácora de accesos, fallos, denegaciones y operaciones |

Informe completo y acciones pendientes del responsable en [docs/SEGURIDAD.md](docs/SEGURIDAD.md).

---

## Instalación rápida

Requisitos: PHP 8.2 (`pdo_mysql`, `mbstring`, `zip`, `dom`, `fileinfo`), MariaDB 10.4+ o MySQL 8, Apache con `AllowOverride All`, Composer.

```bash
git clone https://github.com/Manuel151025/proyecto_sena.git
cd proyecto_sena
composer install
cp .env.example .env                                    # credenciales, DEV_MODE, APP_URL, APP_HOST
php bin/instalar.php --confirmar-borrado-total --demo   # base nueva con datos de demostración
```

Cuentas de demostración (contraseña `Demo2026*`): `coordinador@sena.edu.co`, `instructor@sena.edu.co`, `aprendiz@sena.edu.co`.

Para producción (sin demostración, primera cuenta, Docker, HTTPS, copias de seguridad) ver **[docs/DESPLIEGUE.md](docs/DESPLIEGUE.md)**.

### Comandos

| Comando | Para qué |
|---|---|
| `php bin/instalar.php --confirmar-borrado-total [--demo]` | Base nueva desde el esquema (borra la existente) |
| `php bin/migrar.php [--estado]` | Aplicar migraciones pendientes tras actualizar |
| `php bin/crear-coordinador.php correo "NOMBRE"` | Primera cuenta de coordinación (o recuperar el acceso) |
| `php bin/verificar-esquema.php` | Comprobar que la base coincide con el código |
| `php bin/volcar-esquema.php` | Regenerar `database/esquema.sql` tras una migración |
| `php bin/generar-docs.php` | Regenerar rutas, diccionario de datos y formatos de importación |
| `composer test` | Las 594 pruebas |

---

## Estructura del proyecto

```text
proyecto_sena/
├── index.php            Punto de entrada único
├── login.php · recover.php
├── config/              app, database, navigation, rutas (matriz de accesos)
├── core/                Núcleo (PSR-4 Core\)
│   ├── Router.php · BaseController.php · Database.php
│   ├── Controllers/     25 controladores
│   ├── Formularios/     12 formularios (validación de entrada)
│   ├── Services/        26 servicios (reglas de negocio)
│   ├── Models/          22 modelos (SQL)
│   ├── Importacion/     Importación en dos pasos (CSV, XLSX, XLS)
│   ├── Exportacion/     Exportador XLSX y CSV
│   └── Support/         Actor, Validador, Semaforo, Seguridad, Descarga…
├── includes/            Sesión, autenticación, utilidades
├── modules/*/views/     Vistas por módulo
├── layouts/ · components/
├── assets/              CSS (theme.css), JS por módulo, imágenes
├── database/            esquema.sql, migraciones/, semillas/
├── bin/                 Comandos de consola
├── tests/               Unit, Security, Integration
├── docs/                Documentación
└── uploads/ · logs/ · cache/   Escritura del servidor (cerradas a la web)
```

---

## Convenciones de desarrollo

1. **Una acción, una ruta.** Declarada en `config/rutas.php` con sus roles; el controlador valida con un `Formulario`, delega en un servicio y responde con PRG.
2. **Nada de SQL fuera de los modelos**, y todo modelo acota por el `Actor`.
3. **Nada de JavaScript en línea.** Comportamientos con atributos `data-*` (`comportamientos.js`); datos para scripts en `<script type="application/json">`; scripts propios en `$scriptsVista`.
4. **Interfaz:** `page-header` para el encabezado, tokens de color de `theme.css` (nunca colores literales), `data-picker` en todo `<select>`, un solo modal por formulario.
5. **Listados:** paginación y búsqueda en SQL, nunca filtrando en JavaScript.
6. **Esquema:** todo cambio es una migración nueva en `database/migraciones/`; después `bin/volcar-esquema.php` y `bin/generar-docs.php`.
7. **Cada corrección con su prueba.**

---

## Licencia y autoría

Proyecto formativo desarrollado para el Servicio Nacional de Aprendizaje (SENA), Centro Tecnológico de la Amazonia.

**Repositorio:** [github.com/Manuel151025/proyecto_sena](https://github.com/Manuel151025/proyecto_sena)
