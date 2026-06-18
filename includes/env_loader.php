<?php
/**
 * env_loader.php — Cargador ligero de variables de entorno (.env)
 * Sistema de Seguimiento de Proyectos Formativos SENA
 */

if (!function_exists('loadEnv')) {
    /**
     * Carga variables de entorno desde un archivo .env
     *
     * @param string $path Ruta absoluta al archivo .env
     */
    function loadEnv(string $path): void {
        if (!file_exists($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            
            // Ignorar comentarios y líneas vacías
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }

            // Dividir en clave y valor por el primer signo '='
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // Quitar comillas simples o dobles que envuelvan el valor
                if (preg_match('/^([\'"])(.*)\1$/', $value, $matches)) {
                    $value = $matches[2];
                }

                // Registrar en getenv(), $_ENV y $_SERVER
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
}

// Ejecutar automáticamente la carga desde la raíz del proyecto
loadEnv(dirname(__DIR__) . '/.env');
