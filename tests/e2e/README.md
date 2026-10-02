# Pruebas en navegador por rol

Recorridos reales de la aplicación con [Playwright](https://playwright.dev): coordinación, instructor y aprendiz inician sesión y usan el sistema como lo harían a diario. Complementan las pruebas de PHPUnit (`composer test`), que no ejecutan un navegador.

## Qué se prueba

| Archivo | Recorrido |
|---|---|
| `01-publico` | Inicio de sesión (CSP estricta, credenciales incorrectas con mensaje genérico), cierre de sesión por rol, recuperación de contraseña de punta a punta (enlace de un solo uso, política, enlace usado) |
| `02-permisos` | Pantallas y acciones de otro rol (forzando la URL o enviando el formulario a mano), formulario sin token CSRF, expediente ajeno |
| `03-coordinador` | Programa → competencia → RAP → cuenta de instructora con clave temporal y cambio obligatorio → ficha → matrícula → asignación → exportaciones; duplicados rechazados; el formulario de cuentas solo ofrece lo que se puede guardar (sin crear aprendices ni activar a un desertado) |
| `04-importaciones` | Los archivos de `docs/ejemplos`: vista previa sin guardar, confirmación, reimportación sin duplicar; CSV y Excel; reporte de Sofia Plus |
| `05-ciclo-formativo` | Juicio A → cambio a D con motivo obligatorio e historial → aviso al aprendiz → plan de mejoramiento → evidencia (y un PHP disfrazado de PDF rechazado) → revisión → cierre cumplido → RAP aprobado |
| `06-reportes` | Los seis reportes en Excel, PDF y CSV; alcance por rol; límite del historial; CSV sin fórmulas |
| `07-pantallas` | Todas las pantallas de cada rol en escritorio y en el teléfono (390 px, tema oscuro): responden, sin errores de JavaScript ni bloqueos de la CSP, sin desborde horizontal |
| `08-transversales` | Evento de calendario y su aviso (y que no lo vea otra ficha), perfil, configuración y bitácora, campana de avisos |

## Cómo ejecutarlas

Requisitos: PHP y MariaDB/MySQL como para la aplicación, y Node.js 20 o superior.

```bash
cd tests/e2e
npm install
npx playwright install chromium     # una vez (o usar Edge: E2E_CANAL=msedge)
npx playwright test                 # todas
npx playwright test specs/05-ciclo-formativo.spec.js
npx playwright show-report informe  # informe HTML de la última ejecución
```

- Las pruebas levantan su propio servidor (`php -S 127.0.0.1:8081`) contra la base **`sena_e2e`**, que `global-setup.js` **borra y reinstala** con los datos de demostración antes de cada ejecución. Nunca tocan la base de trabajo: el script se niega a instalar sobre una base que no empiece por `sena_e2e`.
- Usan las cuentas de la demostración (contraseña `Demo2026*`), descritas en `helpers.js`.
- Variables: `E2E_DB` (base), `E2E_PUERTO` (8081), `E2E_PHP` (ruta de PHP), `E2E_CANAL` (`msedge` o `chrome` para usar un navegador instalado).
- Corren en serie (un solo trabajador): varios pasos dependen de lo que creó el anterior.

En el CI (`.github/workflows/pruebas.yml`, trabajo «navegador») se ejecutan en cada envío; si fallan, el informe queda como artefacto.
