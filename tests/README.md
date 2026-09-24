# Pruebas

```bash
composer test                       # todas
composer test:unit                  # solo unitarias (sin base de datos)
composer test:security              # solo seguridad
composer test:integration           # solo integración

vendor/bin/phpunit --filter Validador       # una clase concreta
vendor/bin/phpunit --testdox                # con la descripción de cada caso
```

## Cómo están organizadas

| Suite | Qué comprueba | Necesita BD |
|---|---|---|
| `Unit/` | Clases aisladas: validación, paginación, semáforo, enrutador, manejo de errores | No |
| `Security/` | Intentos de abuso reales: inyección, escalada de privilegios, fuerza bruta, fuga de información, subida de archivos | Algunas |
| `Integration/` | Modelos, consultas, esquema y flujos completos contra la base real | Sí |

## Sobre la base de datos

Las pruebas corren **sobre la base de trabajo** y no dejan rastro: cada una
se ejecuta dentro de una transacción que se revierte al terminar
(`CasoConBaseDeDatos`). No hace falta una base de pruebas aparte.

Dos salvedades:

- `intentos_acceso` es una tabla de control de abuso, no de datos del
  negocio. Las pruebas de fuerza bruta la limpian en lugar de revertirla,
  porque el limitador escribe con su propia conexión.
- `EvaluacionService` y los servicios que escriben en bloque participan en
  la transacción de la prueba si ya hay una abierta (PDO no anida), así que
  también se revierten. `sinTransaccion()` queda para el caso raro de un
  código que necesite su propia conexión, y entonces la prueba limpia a mano.

Las pruebas trabajan con **datos que ya existen** en vez de sembrar los
suyos. Es deliberado: así se comprueban también las consultas contra la
forma real que tienen los datos, que es donde aparecieron varios de los
fallos. Cuando no hay datos suficientes, la prueba se marca como omitida
(`markTestSkipped`) en lugar de pasar en vacío.

Para una base limpia con datos en todas las tablas (lo que usa el CI):

```bash
DB_NAME=sena_prueba php bin/instalar.php --confirmar-borrado-total --demo
DB_NAME=sena_prueba vendor/bin/phpunit
```

## Qué NO cubren

Conviene saberlo antes de confiar en un resultado verde:

- **Nada se ejecuta en un navegador.** No se comprueba que las cabeceras
  CSP lleguen, que el formulario de cierre de sesión funcione, ni que el
  guard de las vistas responda a una petición HTTP real. Se prueba la
  lógica que las produce, no su efecto en el navegador. Eso se cubre con
  el recorrido manual en navegador descrito en `docs/PRUEBAS.md`.
- **`MailService`** no se prueba: exige un servidor SMTP.
- **`EstructuraPdfParser`** no se prueba de extremo a extremo: haría falta
  un PDF de diseño curricular de ejemplo. El importador de juicios de Sofia
  Plus sí (`ImportadorJuiciosTest`), con filas construidas en la prueba.
- **La cobertura de líneas** no se mide en local (falta Xdebug o PCOV);
  sí se recoge en CI.

## Pruebas que documentan un fallo pasado

Varias comprueban que no vuelva un problema concreto, y su nombre lo dice.
Si alguna falla, lo que hay que mirar es el fallo original:

| Prueba | Qué impide que vuelva |
|---|---|
| `CsrfYSesionTest::testNoAceptaTokenDeOtraPestana` | El parche que daba por bueno el token de cualquier pestaña |
| `FugaDeInformacionTest::testSinVolcadoDeDepuracionEnCsrf` | El 403 que imprimía el ID de sesión y los tokens |
| `EsquemaTest::testCoberturaDeTrazabilidad` | Un camino de escritura que se salte `EvaluacionService` |
| `BusquedaYFiltrosTest::testComodinesNoDevuelvenTodo` | Buscar `%` devolvía la tabla entera |
| `BusquedaYFiltrosTest::testFiltroManipuladoNoDevuelveTodo` | Un filtro inválido se ignoraba y mostraba todo |
| `ConsultasDeModelosTest::testRecuentoDeAprendicesCoherente` | El contador desnormalizado que se desviaba |
| `SeguridadTest::testScriptSrcSinUnsafeInline` | Que la CSP vuelva a permitir scripts en línea |
| `SeguridadTest::testSinCodigoEnLinea` | Un `<script>` o un `onclick=` en una vista (la CSP lo bloquearía) |
| `SeguridadTest::testJavascriptSinSumiderosHtml` | El XSS almacenado del calendario y de la campana (`innerHTML` con datos) |
| `SeguridadTest::testRecursosExternosConIntegridad` | Un recurso de CDN sin SRI |
| `ImportadorJuiciosTest` | «NO APROBADO» leído como A; «POR EVALUAR» borrando un juicio emitido |
| `GestionAcademicaTest::testSiempreQuedaUnCoordinador` | Quedarse sin ningún coordinador activo |
| `ConsultasDeModelosTest::testSemaforoSqlIgualQuePhp` | Que el panel y el expediente clasifiquen distinto al mismo aprendiz |
| `RutasYPermisosTest::testNumeroDeRutas` | Canario: obliga a revisar el permiso al añadir una ruta |
