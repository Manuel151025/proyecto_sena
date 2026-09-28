<?php
declare(strict_types=1);

namespace Core\Support;

use Core\Models\ConfiguracionModel;
use Throwable;

/**
 * Parámetros institucionales editables desde /configuracion.
 *
 * Solo los que el sistema usa de verdad: el nombre del sistema y el centro
 * de formación (encabezado de los reportes PDF). Antes la pantalla también
 * guardaba un «porcentaje mínimo de aprobación» que contradecía el
 * semáforo (y que nada leía) y un «servidor SMTP» sin efecto: el correo se
 * configura en el entorno (MAIL_*), no en la base.
 */
final class Configuracion {
    public const CLAVES = [
        'system_title' => ['Nombre del sistema', 'SENA · Seguimiento de Proyectos Formativos'],
        'regional'     => ['Centro de formación', 'Centro Tecnológico de la Amazonia · Regional Caquetá'],
    ];

    /** @var array<string,string>|null */
    private static ?array $cache = null;

    public static function valor(string $clave): string {
        if (self::$cache === null) {
            try {
                self::$cache = (new ConfiguracionModel())->getAll();
            } catch (Throwable) {
                self::$cache = [];
            }
        }
        $v = trim((string)(self::$cache[$clave] ?? ''));
        return $v !== '' ? $v : (self::CLAVES[$clave][1] ?? '');
    }

    /** Tras guardar, para que la misma petición lea lo nuevo. */
    public static function olvidar(): void {
        self::$cache = null;
    }
}
