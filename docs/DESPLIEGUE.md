# Despliegue y operación

**Sistema de Seguimiento de Proyectos Formativos — SENA** · versión 3.2

Cómo instalar, configurar, actualizar y mantener el sistema. Para la seguridad del despliegue ver también [SEGURIDAD.md](SEGURIDAD.md).

---

## 1. Requisitos

| Componente | Versión | Notas |
|---|---|---|
| PHP | 8.2 o superior | Extensiones: `pdo_mysql`, `mbstring`, `zip`, `dom`, `fileinfo`, `openssl`. `gd` es opcional (logo en los PDF). |
| Base de datos | MariaDB 10.4+ o MySQL 8.0+ | `utf8mb4`. El sistema activa el modo estricto en cada conexión. |
| Servidor web | Apache 2.4 | `mod_rewrite` y `mod_headers`; **`AllowOverride All`** en la carpeta del proyecto (sin eso se ignoran los `.htaccess` que cierran los archivos internos). |
| Composer | 2.x | Dependencias: PHPMailer, dompdf, SimpleXLS. |
| Límites de PHP | `upload_max_filesize ≥ 10M`, `post_max_size ≥ 12M`, `memory_limit ≥ 256M` | Los PDF grandes elevan el límite solo mientras se generan. |

## 2. Variables de entorno

Se leen del archivo `.env` en la raíz (copiar de `.env.example`) o del entorno real, que tiene prioridad (Docker, Dokploy o `VAR=x php bin/...`).

| Variable | Obligatoria | Descripción |
|---|---|---|
| `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` | Sí | Conexión a la base. |
| `DEV_MODE` | Sí | **`false` en producción.** En `true` muestra la causa técnica de los errores y el enlace de recuperación en pantalla. |
| `APP_URL` | Según el caso | Carpeta desde la que se sirve, sin barra final (`/proyecto_sena` en XAMPP). Vacía si se sirve desde la raíz del dominio (Docker). |
| `APP_HOST` | Sí en producción | Dominio real (sin `https://`). Los enlaces de los correos se construyen con él y no con la cabecera `Host`, que controla el cliente. |
| `MAIL_HOST`, `MAIL_PORT`, `MAIL_ENCRYPTION` | Para enviar correo | SMTP. Gmail: `smtp.gmail.com`, `587`, `tls`. |
| `MAIL_USERNAME`, `MAIL_PASSWORD` | Para enviar correo | Con Gmail, una **contraseña de aplicación** (exige verificación en dos pasos). Si están vacías, no se envía nada y el motivo queda en `logs/mail.log`. |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | Para enviar correo | Remitente. |
| `MAIL_TIMEOUT`, `MAIL_DEBUG` | No | Espera al conectar (15 s) y volcado del diálogo SMTP en el registro. |

> El `.env` nunca se versiona ni entra en la imagen Docker (`.gitignore`, `.dockerignore`) y la web no lo sirve (`.htaccess`).

## 3. Instalación en un servidor (Apache)

```bash
git clone https://github.com/Manuel151025/proyecto_sena.git
cd proyecto_sena
composer install --no-dev --optimize-autoloader

cp .env.example .env          # editar: DB_*, DEV_MODE=false, APP_URL, APP_HOST, MAIL_*

# Base de datos nueva (BORRA la base DB_NAME si existe)
php bin/instalar.php --confirmar-borrado-total

# Comprobar
php bin/verificar-esquema.php

# Permisos: el usuario de Apache escribe solo en estas tres carpetas
chown -R www-data:www-data uploads logs cache
chmod -R 750 uploads logs cache
```

`bin/instalar.php` crea la base desde `database/esquema.sql` y aplica las migraciones (en una base nueva solo se registran y siembran la configuración inicial). La base nace sin usuarios; la primera cuenta de coordinación se crea desde la consola:

```bash
php bin/crear-coordinador.php coordinacion@sena.edu.co "NOMBRE COMPLETO"
```

Muestra una contraseña temporal una sola vez y exige cambiarla al entrar. Desde ahí, el resto de cuentas se crean en **Usuarios**. El mismo comando sirve para recuperar el acceso si la coordinación pierde su cuenta.

### Datos de demostración

```bash
php bin/instalar.php --confirmar-borrado-total --demo
```

Siembra un centro completo y sintético (programas, fichas, aprendices, juicios, evidencias, planes, eventos). Todas las cuentas usan la contraseña `Demo2026*`:

| Rol | Correo |
|---|---|
| Coordinador | `coordinador@sena.edu.co` |
| Instructor | `instructor@sena.edu.co` … `instructor5@sena.edu.co` |
| Aprendiz | `aprendiz@sena.edu.co`, `aprendiz2@sena.edu.co` … |

> **Nunca** instalar la demostración en producción, o cambiar esas contraseñas de inmediato.

### Desarrollo local con XAMPP

1. Clonar en `C:\xampp\htdocs\proyecto_sena`.
2. `composer install` (con dependencias de desarrollo, para las pruebas).
3. `.env` con `DB_USER=root`, `DB_PASS=`, `DEV_MODE=true`, `APP_URL=/proyecto_sena`.
4. `php bin/instalar.php --confirmar-borrado-total --demo`.
5. Abrir `http://localhost/proyecto_sena`.

## 4. Instalación con Docker

```bash
cp .env.example .env
# en .env:  DB_HOST=db  DB_USER=sena_user  DB_PASS=sena_password  APP_URL=  DEV_MODE=false
docker compose up -d --build
docker compose exec app composer install --no-dev --optimize-autoloader
docker compose exec app php bin/migrar.php
docker compose exec app php bin/crear-coordinador.php coordinacion@sena.edu.co "NOMBRE COMPLETO"
```

- En el primer arranque (volumen vacío) la base se crea desde `database/esquema.sql`; `bin/migrar.php` registra las migraciones y siembra la configuración.
- La aplicación queda en `http://servidor:8090`.
- **Antes del primer arranque en un servidor real**, cambiar las contraseñas de la base en `docker-compose.yml` y en `.env`.
- La imagen (`Dockerfile`) ya viene endurecida: `AllowOverride All`, sin listados, `ServerTokens Prod`, `expose_php Off`, errores solo al registro y límites de subida coherentes con la aplicación.

## 5. Actualizar una instalación

```bash
git pull
composer install --no-dev --optimize-autoloader
php bin/migrar.php            # aplica las migraciones pendientes (idempotentes)
php bin/verificar-esquema.php
```

> **El despliegue actualiza el código, no la base.** Hay que ejecutar `bin/migrar.php` después de cada actualización. `php bin/migrar.php --estado` lista las aplicadas y las pendientes sin cambiar nada.

Una base anterior a la versión 3 (sin tabla `migraciones`) se pone al día con el mismo comando: cada migración comprueba si su cambio ya existe antes de aplicarlo.

## 6. Lista de comprobación de producción

- [ ] `DEV_MODE=false`.
- [ ] `APP_HOST` con el dominio real.
- [ ] HTTPS con certificado válido (la cookie de sesión pasa a `Secure` y se envía HSTS automáticamente). Detrás de un proxy, que envíe `X-Forwarded-Proto: https`.
- [ ] `AllowOverride All` activo: `https://dominio/.env`, `https://dominio/.git/config` y `https://dominio/uploads/` deben responder **403**.
- [ ] Contraseñas propias en la base (no las de `docker-compose.yml` de ejemplo) y **contraseña de aplicación de Gmail rotada** si la anterior estuvo en un `.env` expuesto.
- [ ] Sin datos de demostración, o con sus contraseñas cambiadas.
- [ ] `install.php` y volcados `.sql` fuera de la carpeta web (el `.htaccess` los niega, pero no deben estar).
- [ ] Copias de seguridad programadas (§7).
- [ ] Detrás de un proxy inverso: `mod_remoteip` para que el limitador de intentos vea la IP real.

## 7. Copias de seguridad

Lo que hay que respaldar: **la base de datos** y **`uploads/evidencias/`** (los archivos de las evidencias). El resto se reconstruye desde el repositorio.

```bash
# Diario, conservando 30 días
fecha=$(date +%F)
mysqldump --single-transaction --routines -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" | gzip > /respaldos/sena-$fecha.sql.gz
tar czf /respaldos/evidencias-$fecha.tar.gz -C /ruta/proyecto_sena uploads/evidencias
find /respaldos -name 'sena-*' -mtime +30 -delete
find /respaldos -name 'evidencias-*' -mtime +30 -delete
```

Guardar las copias fuera del servidor y probar una restauración de vez en cuando:

```bash
gunzip < sena-AAAA-MM-DD.sql.gz | mysql -u root -p sena_seguimiento
php bin/migrar.php
```

## 8. Registros

| Archivo | Contenido |
|---|---|
| `logs/error.log` | Errores técnicos con la referencia que se mostró al usuario (para cruzarlos). |
| `logs/mail.log` | Envíos de correo y sus fallos. |
| Tabla `logs_sistema` | Bitácora de auditoría, consultable en **Bitácora** (`/logs`) por la coordinación. |

`logs/` no es accesible por web. Conviene rotar los archivos con `logrotate`.

## 9. Mantenimiento

| Tarea | Frecuencia | Cómo |
|---|---|---|
| Aplicar migraciones | En cada actualización | `php bin/migrar.php` |
| Verificar el esquema | En cada actualización | `php bin/verificar-esquema.php` |
| Regenerar la documentación derivada | Tras cambiar rutas, tablas o importaciones | `php bin/generar-docs.php` |
| Regenerar `database/esquema.sql` | Tras crear una migración (en desarrollo) | `php bin/volcar-esquema.php` |
| Limpiar vistas previas de importación caducadas | Automático al importar | `cache/importaciones/` (1 hora de vigencia) |
| Revisar la bitácora | Semanal | Filtrar por «Acceso denegado» y «Acceso fallido» |

## 10. Rotación de credenciales

1. **Gmail**: en la cuenta de Google → Seguridad → Contraseñas de aplicación, revocar la anterior y crear una nueva; ponerla en `MAIL_PASSWORD`.
2. **Base de datos**: `ALTER USER 'usuario'@'host' IDENTIFIED BY 'nueva';` y actualizar `DB_PASS`.
3. **Cuentas de usuario**: la coordinación puede restablecer la contraseña de cualquier cuenta desde **Usuarios**; se genera una temporal que se debe cambiar al entrar.
