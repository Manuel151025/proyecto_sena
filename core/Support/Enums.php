<?php
declare(strict_types=1);

namespace Core\Support;

/**
 * Valores admitidos por cada columna ENUM del esquema.
 *
 * Existen aquí porque los controladores los escribían a mano, cada uno en
 * su `in_array(...)`, y unos cuantos ni siquiera comprobaban: el valor
 * pasaba de `$_POST` a la consulta. Como MariaDB no tenía activado
 * STRICT_TRANS_TABLES, un estado inventado se guardaba como cadena vacía
 * sin error, y la ficha quedaba con un estado que ninguna pantalla sabe
 * dibujar.
 *
 * Tener la lista en un único sitio significa además que, al añadir un
 * valor al esquema, hay exactamente un archivo que actualizar.
 *
 * Las constantes siguen el orden declarado en la base de datos. Si cambia
 * el ENUM, hay que reflejarlo aquí: `php bin/verificar-esquema.php`
 * compara las dos listas y avisa si se han separado.
 */
final class Enums {
    public const ACTIVIDAD_ESTADO = ['pendiente', 'en_progreso', 'completada', 'cancelada'];

    public const APRENDIZ_ESTADO  = ['matriculado', 'suspendido', 'desertado', 'egresado', 'etapa_practica'];
    public const APRENDIZ_GENERO  = ['M', 'F', 'O'];
    public const TIPO_DOCUMENTO   = ['CC', 'TI', 'CE', 'PEP', 'PA'];

    public const COMPETENCIA_ESTADO = ['activo', 'inactivo'];
    public const RAP_ESTADO         = ['activo', 'inactivo'];

    public const CONCEPTO = ['A', 'D', 'pendiente'];

    public const EVIDENCIA_ESTADO = ['enviada', 'revisada', 'aprobada', 'rechazada'];

    public const FASE_ESTADO  = ['planeada', 'en_ejecucion', 'completada'];
    public const FICHA_ESTADO = ['planeacion', 'induccion', 'ejecucion', 'cierre'];

    public const PLAN_ESTADO = ['abierto', 'en_curso', 'cumplido', 'no_cumplido'];

    public const PROGRAMA_ESTADO = ['activo', 'inactivo', 'archivado'];
    public const PROYECTO_ESTADO = ['activo', 'inactivo', 'finalizado'];

    public const RETROALIMENTACION_TIPO = ['fortaleza', 'aspecto_mejorar', 'recomendacion'];

    public const USUARIO_ESTADO = ['activo', 'inactivo', 'bloqueado'];
    public const USUARIO_ROL    = ['coordinador', 'instructor', 'aprendiz'];

    /**
     * Correspondencia entre columna del esquema y constante, para que la
     * migración de verificación pueda comprobarlas todas sin listarlas
     * otra vez.
     *
     * @return array<string, string[]>  'tabla.columna' => valores
     */
    public static function mapaEsquema(): array {
        return [
            'actividades.estado'                    => self::ACTIVIDAD_ESTADO,
            'aprendices.estado'                     => self::APRENDIZ_ESTADO,
            'aprendices.genero'                     => self::APRENDIZ_GENERO,
            'aprendices.tipo_documento'             => self::TIPO_DOCUMENTO,
            'competencias.estado'                   => self::COMPETENCIA_ESTADO,
            'evaluaciones.concepto'                 => self::CONCEPTO,
            'evidencias.estado'                     => self::EVIDENCIA_ESTADO,
            'fases_proyecto.estado'                 => self::FASE_ESTADO,
            'fichas.estado'                         => self::FICHA_ESTADO,
            'historial_evaluaciones.concepto_anterior' => self::CONCEPTO,
            'historial_evaluaciones.concepto_nuevo' => self::CONCEPTO,
            'planes_mejoramiento.estado'            => self::PLAN_ESTADO,
            'programas.estado'                      => self::PROGRAMA_ESTADO,
            'proyectos.estado'                      => self::PROYECTO_ESTADO,
            'resultados_aprendizaje.estado'         => self::RAP_ESTADO,
            'retroalimentacion.tipo'                => self::RETROALIMENTACION_TIPO,
            'usuarios.estado'                       => self::USUARIO_ESTADO,
            'usuarios.rol'                          => self::USUARIO_ROL,
        ];
    }
}
