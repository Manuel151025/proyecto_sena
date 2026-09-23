<?php
declare(strict_types=1);

namespace Core\Support;

/**
 * Cabeceras de seguridad de la respuesta.
 *
 * El proyecto no enviaba ninguna: ni CSP, ni X-Frame-Options, ni
 * X-Content-Type-Options. En la práctica eso significaba que cualquier
 * sitio podía embeber la aplicación en un iframe y montar un clickjacking
 * sobre acciones que ya van autenticadas por cookie.
 *
 * La CSP es deliberadamente explícita sobre de dónde puede venir cada
 * cosa. Hoy la aplicación carga Bootstrap, Bootstrap Icons y Chart.js
 * desde jsdelivr y las fuentes desde Google, así que esos orígenes se
 * declaran uno a uno en vez de abrir `*`.
 *
 * `'unsafe-inline'` sigue permitido para scripts y estilos porque las 36
 * vistas llevan `<script>` y `style=` incrustados; quitarlo exige moverlos
 * a archivos o firmarlos con nonce, y romper la interfaz entera de golpe
 * sería peor que la mejora. Queda anotado como el siguiente paso real de
 * endurecimiento: mientras esté, la CSP limita el origen de los scripts
 * pero no protege frente a un XSS incrustado.
 */
final class Seguridad {
    /** Orígenes desde los que la aplicación carga scripts y estilos. */
    private const CDN = 'https://cdn.jsdelivr.net';
    private const FUENTES_CSS = 'https://fonts.googleapis.com';
    private const FUENTES_FILES = 'https://fonts.gstatic.com';

    public static function cabeceras(): void {
        if (headers_sent()) {
            return;
        }

        // La versión exacta de PHP solo le sirve a quien busca un exploit
        // para ella.
        header_remove('X-Powered-By');

        // Evita que el navegador "adivine" el tipo de un archivo subido y
        // acabe ejecutando como HTML algo que se sirvió como texto.
        header('X-Content-Type-Options: nosniff');

        // Sin esto la aplicación se puede embeber en un iframe ajeno.
        header('X-Frame-Options: DENY');

        // No filtrar la URL completa (que lleva ids de ficha y de aprendiz)
        // al navegar a un sitio externo.
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // La aplicación no usa cámara, micrófono ni geolocalización.
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');

        header('Content-Security-Policy: ' . self::csp());

        // HSTS solo bajo HTTPS: enviarlo por HTTP no hace nada y en un
        // entorno local mal configurado deja el dominio inaccesible.
        if (self::esHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    /**
     * Cabeceras para respuestas que no deben quedar en caché: cualquier
     * página autenticada. Sin esto, el botón "atrás" tras cerrar sesión
     * puede mostrar datos del usuario anterior en un equipo compartido.
     */
    public static function sinCache(): void {
        if (headers_sent()) {
            return;
        }
        header('Cache-Control: no-store, no-cache, must-revalidate, private');
        header('Pragma: no-cache');
        header('Expires: 0');
    }

    private static function csp(): string {
        $cdn    = self::CDN;
        $fCss   = self::FUENTES_CSS;
        $fFiles = self::FUENTES_FILES;

        return implode('; ', [
            "default-src 'self'",
            // Ver nota de la cabecera sobre 'unsafe-inline'.
            "script-src 'self' 'unsafe-inline' {$cdn}",
            "style-src 'self' 'unsafe-inline' {$cdn} {$fCss}",
            "font-src 'self' {$cdn} {$fFiles} data:",
            // data: por los gráficos de Chart.js; blob: por la descarga de
            // exportaciones generadas en el navegador.
            "img-src 'self' data: blob:",
            "connect-src 'self'",
            // La aplicación no incrusta nada de terceros ni debe ser
            // incrustada: equivale a X-Frame-Options para navegadores modernos.
            "frame-ancestors 'none'",
            "frame-src 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            // Los formularios solo pueden enviarse a la propia aplicación.
            "form-action 'self'",
        ]);
    }

    public static function esHttps(): bool {
        if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }
        // Detrás de un proxy inverso (el despliegue previsto en VPS), el
        // esquema real llega en esta cabecera.
        return strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }
}
