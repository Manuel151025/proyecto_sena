<?php
declare(strict_types=1);

namespace Core\Support;

/**
 * Validación y normalización de la entrada del usuario.
 *
 * Por qué existe: los controladores leían `$_POST` directamente y lo
 * pasaban al modelo. Eso dejaba tres huecos comprobados:
 *
 *  1. Los enum (`estado`, `tipo`, `concepto`...) viajaban sin lista blanca.
 *     MariaDB, sin `STRICT_TRANS_TABLES`, guardaba un valor inventado como
 *     cadena vacía sin avisar; la ficha quedaba con un estado que ninguna
 *     pantalla sabe representar.
 *  2. Las fechas iban tal cual. `fecha_inicio=no-es-fecha` se almacenaba
 *     como '0000-00-00'.
 *  3. Las longitudes se comprobaban a mano, distinto en cada controlador,
 *     y a veces después de haber usado ya el valor.
 *
 * Acumula los errores en lugar de lanzar al primero, para que el
 * formulario pueda mostrarlos todos de una vez.
 *
 * Uso típico:
 *
 *     $v = new Validador($_POST);
 *     $nombre = $v->texto('nombre', 'Nombre', min: 3, max: 100);
 *     $estado = $v->enum('estado', 'Estado', ['activo', 'inactivo']);
 *     $desde  = $v->fecha('fecha_inicio', 'Fecha de inicio', obligatorio: false);
 *     if ($v->hayErrores()) { ... $v->errores() ... }
 */
final class Validador {
    private array $datos;
    private array $errores = [];

    public function __construct(?array $datos = null) {
        $this->datos = $datos ?? $_POST;
    }

    // -----------------------------------------------------------------
    // TIPOS
    // -----------------------------------------------------------------

    /**
     * Entero dentro de un rango. Devuelve 0 si no es válido, para que el
     * llamador nunca reciba null donde espera int.
     */
    public function entero(string $campo, string $etiqueta, int $min = 0, int $max = PHP_INT_MAX, bool $obligatorio = true): int {
        $bruto = $this->bruto($campo);

        if ($bruto === '' || $bruto === null) {
            if ($obligatorio) {
                $this->error($etiqueta . ' es obligatorio.');
            }
            return 0;
        }

        // filter_var rechaza "12abc" y " 12 ", que un (int) aceptaría como 12.
        $n = filter_var(trim((string)$bruto), FILTER_VALIDATE_INT);
        if ($n === false) {
            $this->error($etiqueta . ' debe ser un número entero.');
            return 0;
        }
        if ($n < $min || $n > $max) {
            $this->error(sprintf('%s debe estar entre %d y %d.', $etiqueta, $min, $max));
            return 0;
        }
        return $n;
    }

    /** Identificador de registro: entero positivo. */
    public function id(string $campo, string $etiqueta, bool $obligatorio = true): int {
        return $this->entero($campo, $etiqueta, 1, PHP_INT_MAX, $obligatorio);
    }

    /**
     * Texto con longitud acotada.
     *
     * `$limpiarHtml` aplica strip_tags: se usa en campos de texto libre que
     * luego se muestran. No sustituye al escapado en la vista, solo evita
     * almacenar marcado que nadie va a querer.
     */
    public function texto(string $campo, string $etiqueta, int $min = 0, int $max = 255, bool $obligatorio = true, bool $limpiarHtml = true): string {
        $valor = trim((string)($this->bruto($campo) ?? ''));

        if ($limpiarHtml) {
            $valor = strip_tags($valor);
        }

        // Los caracteres de control (salvo tabulador y salto de línea)
        // no aportan nada y descuadran los listados y las exportaciones.
        $valor = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $valor) ?? '';

        if ($valor === '') {
            if ($obligatorio) {
                $this->error($etiqueta . ' es obligatorio.');
            }
            return '';
        }

        $largo = mb_strlen($valor, 'UTF-8');
        if ($largo < $min) {
            $this->error(sprintf('%s debe tener al menos %d caracteres.', $etiqueta, $min));
        }
        if ($largo > $max) {
            $this->error(sprintf('%s no puede exceder los %d caracteres.', $etiqueta, $max));
        }
        return $valor;
    }

    /**
     * Nombres de entidades (programas, proyectos, fases, actividades...):
     * letras de cualquier idioma, números, espacios y la puntuación que
     * aparece en los nombres reales del SENA ("Fase 2: Análisis (ADSO)").
     * Excluye `<` y `>`, y todo carácter de control.
     */
    public const PATRON_NOMBRE = '/^[\p{L}\p{M}\p{N}\s\-_.,()\/:;#°\'"&+]+$/u';

    /** Nombres de persona: letras, espacios, apóstrofo, punto y guion. */
    public const PATRON_PERSONA = '/^[\p{L}\p{M}\s\'.\-]+$/u';

    /** Códigos institucionales: letras, números, guion, punto y guion bajo. */
    public const PATRON_CODIGO = '/^[A-Z0-9][A-Z0-9\-_.]*$/';

    /**
     * Texto que además debe ajustarse a un patrón (lista blanca).
     *
     * @param string $descripcion Qué se admite, para el mensaje de error
     *        ("solo letras y espacios").
     */
    public function patron(string $campo, string $etiqueta, string $regex, string $descripcion,
                           int $min = 0, int $max = 255, bool $obligatorio = true): string {
        $valor = $this->texto($campo, $etiqueta, $min, $max, $obligatorio);
        if ($valor !== '' && !preg_match($regex, $valor)) {
            $this->error("$etiqueta solo admite $descripcion.");
            return '';
        }
        return $valor;
    }

    /** Nombre de una entidad del dominio (ver PATRON_NOMBRE). */
    public function nombre(string $campo, string $etiqueta, int $min = 3, int $max = 150, bool $obligatorio = true): string {
        return $this->patron($campo, $etiqueta, self::PATRON_NOMBRE,
            'letras, números, espacios y signos de puntuación comunes', $min, $max, $obligatorio);
    }

    /** Código institucional, normalizado a mayúsculas (ver PATRON_CODIGO). */
    public function codigo(string $campo, string $etiqueta, int $min = 2, int $max = 30, bool $obligatorio = true): string {
        $bruto = $this->bruto($campo);
        if (is_string($bruto)) {
            $this->datos[$campo] = mb_strtoupper(trim($bruto), 'UTF-8');
        }
        return $this->patron($campo, $etiqueta, self::PATRON_CODIGO,
            'letras, números, guion, punto y guion bajo', $min, $max, $obligatorio);
    }

    /** Color hexadecimal #RRGGBB (avatares, eventos). */
    public function colorHex(string $campo, string $etiqueta, string $porDefecto): string {
        $v = trim((string)($this->bruto($campo) ?? ''));
        if ($v === '') {
            return $porDefecto;
        }
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $v)) {
            $this->error("$etiqueta no es un color válido.");
            return $porDefecto;
        }
        return strtoupper($v);
    }

    /**
     * Valor de una lista cerrada. Es la defensa de los enum de la base de
     * datos: lo que no esté en `$permitidos` no llega a la consulta.
     */
    public function enum(string $campo, string $etiqueta, array $permitidos, ?string $porDefecto = null, bool $obligatorio = true): string {
        $valor = trim((string)($this->bruto($campo) ?? ''));

        if ($valor === '') {
            if ($porDefecto !== null) {
                return $porDefecto;
            }
            if ($obligatorio) {
                $this->error($etiqueta . ' es obligatorio.');
            }
            return '';
        }

        if (!in_array($valor, $permitidos, true)) {
            // No se repite el valor recibido en el mensaje: sería reflejarlo
            // de vuelta al navegador sin necesidad.
            $this->error($etiqueta . ' no tiene un valor válido.');
            return $porDefecto ?? '';
        }
        return $valor;
    }

    /**
     * Fecha en formato ISO (YYYY-MM-DD), que es lo que emite <input type="date">
     * y lo que espera MySQL. Devuelve null si está vacía y no es obligatoria.
     *
     * Se comprueba que la fecha exista de verdad: '2026-02-30' encaja con el
     * patrón pero no es un día del calendario.
     */
    public function fecha(string $campo, string $etiqueta, bool $obligatorio = true): ?string {
        $valor = trim((string)($this->bruto($campo) ?? ''));

        if ($valor === '') {
            if ($obligatorio) {
                $this->error($etiqueta . ' es obligatoria.');
            }
            return null;
        }

        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
        if ($d === false || $d->format('Y-m-d') !== $valor) {
            $this->error($etiqueta . ' no es una fecha válida (formato AAAA-MM-DD).');
            return null;
        }

        // Cota de cordura: fuera de este rango es un error de tecleo o una
        // prueba, nunca una fecha académica real.
        $anio = (int)$d->format('Y');
        if ($anio < 2000 || $anio > 2100) {
            $this->error($etiqueta . ' está fuera del rango admitido (2000-2100).');
            return null;
        }
        return $valor;
    }

    /**
     * Comprueba que un rango de fechas tenga sentido. Se llama aparte
     * porque necesita las dos ya validadas.
     */
    public function rangoFechas(?string $desde, ?string $hasta, string $etiqueta = 'La fecha de fin'): void {
        if ($desde !== null && $hasta !== null && $hasta < $desde) {
            $this->error($etiqueta . ' no puede ser anterior a la de inicio.');
        }
    }

    /** Decimal acotado, para porcentajes y horas. */
    public function decimal(string $campo, string $etiqueta, float $min = 0, float $max = PHP_FLOAT_MAX, bool $obligatorio = true): float {
        $bruto = $this->bruto($campo);

        if ($bruto === '' || $bruto === null) {
            if ($obligatorio) {
                $this->error($etiqueta . ' es obligatorio.');
            }
            return 0.0;
        }

        // Se admite la coma decimal: es lo que teclea un usuario en español.
        $n = filter_var(str_replace(',', '.', trim((string)$bruto)), FILTER_VALIDATE_FLOAT);
        if ($n === false) {
            $this->error($etiqueta . ' debe ser un número.');
            return 0.0;
        }
        if ($n < $min || $n > $max) {
            $this->error(sprintf('%s debe estar entre %s y %s.', $etiqueta, $min, $max));
            return 0.0;
        }
        return $n;
    }

    /** Correo electrónico normalizado a minúsculas. */
    public function email(string $campo, string $etiqueta = 'El correo', bool $obligatorio = true): string {
        $valor = mb_strtolower(trim((string)($this->bruto($campo) ?? '')), 'UTF-8');

        if ($valor === '') {
            if ($obligatorio) {
                $this->error($etiqueta . ' es obligatorio.');
            }
            return '';
        }
        if (mb_strlen($valor) > 150) {
            $this->error($etiqueta . ' es demasiado largo.');
            return '';
        }
        if (!filter_var($valor, FILTER_VALIDATE_EMAIL)) {
            $this->error($etiqueta . ' no tiene un formato válido.');
            return '';
        }
        return $valor;
    }

    /** Casilla de verificación: presente equivale a 1. */
    public function booleano(string $campo): int {
        return isset($this->datos[$campo]) && $this->datos[$campo] !== '' && $this->datos[$campo] !== '0' ? 1 : 0;
    }

    /**
     * Lista de identificadores (checkboxes múltiples, selección masiva).
     * Descarta lo que no sea un entero positivo en vez de fallar entero.
     *
     * @return int[]
     */
    public function listaIds(string $campo, string $etiqueta, int $maximo = 500): array {
        $bruto = $this->datos[$campo] ?? [];
        if (!is_array($bruto)) {
            $this->error($etiqueta . ' no tiene un formato válido.');
            return [];
        }
        if (count($bruto) > $maximo) {
            $this->error(sprintf('%s no puede incluir más de %d elementos.', $etiqueta, $maximo));
            return [];
        }

        $ids = [];
        foreach ($bruto as $v) {
            $n = filter_var(trim((string)$v), FILTER_VALIDATE_INT);
            if ($n !== false && $n > 0) {
                $ids[] = $n;
            }
        }
        return array_values(array_unique($ids));
    }

    // -----------------------------------------------------------------
    // BÚSQUEDA
    // -----------------------------------------------------------------

    /**
     * Prepara un término para usarlo dentro de un `LIKE ?`.
     *
     * En SQL, `%` y `_` son comodines: buscar «%» devolvía la tabla entera
     * y buscar «a_b» encontraba «axb». No es inyección —el parámetro sigue
     * ligado— pero sí permite saltarse el filtro de un listado y forzar un
     * recorrido completo de la tabla, y además hace imposible buscar un
     * porcentaje literal («50%») o un nombre con guion bajo.
     *
     * La barra invertida se escapa primero, o escaparía a los escapes que
     * se añaden después. El `LIKE` de la consulta debe declarar
     * `ESCAPE '\\'`, que es el valor por defecto de MariaDB.
     *
     * @param string $termino Texto tal cual lo escribió el usuario.
     * @return string Término listo para envolver entre `%`.
     */
    public static function escaparLike(string $termino): string {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $termino);
    }

    /**
     * Término de búsqueda saneado pero SIN escapar para LIKE: para volver a
     * pintarlo en la caja de búsqueda y para los modelos que escapan ellos
     * mismos con escaparLike(). Nunca se mete directo en un LIKE.
     */
    public function busquedaCruda(string $campo = 'search', int $max = 100): string {
        $termino = trim(strip_tags((string)($this->bruto($campo) ?? '')));
        $termino = preg_replace('/[\x00-\x1F\x7F]/u', '', $termino) ?? '';
        return mb_substr($termino, 0, $max, 'UTF-8');
    }

    /**
     * Término de búsqueda ya saneado y escapado, listo para `LIKE ?`.
     * Devuelve '' si no hay nada que buscar.
     */
    public function busqueda(string $campo = 'search', int $max = 100): string {
        $termino = trim((string)($this->bruto($campo) ?? ''));
        if ($termino === '') {
            return '';
        }
        if (mb_strlen($termino, 'UTF-8') > $max) {
            $termino = mb_substr($termino, 0, $max, 'UTF-8');
        }
        return self::escaparLike(strip_tags($termino));
    }

    // -----------------------------------------------------------------
    // ERRORES
    // -----------------------------------------------------------------

    /** Añade un error de negocio, comprobado fuera de esta clase. */
    public function agregarError(string $mensaje): void {
        $this->error($mensaje);
    }

    public function hayErrores(): bool {
        return $this->errores !== [];
    }

    /** @return string[] */
    public function errores(): array {
        return $this->errores;
    }

    /** Primer error, para los flujos que solo muestran uno. */
    public function primerError(): string {
        return $this->errores[0] ?? '';
    }

    private function error(string $mensaje): void {
        // Sin duplicados: dos campos con el mismo fallo no deben producir
        // dos veces la misma línea en pantalla.
        if (!in_array($mensaje, $this->errores, true)) {
            $this->errores[] = $mensaje;
        }
    }

    private function bruto(string $campo): mixed {
        $v = $this->datos[$campo] ?? null;
        // Un array donde se espera un escalar es siempre manipulación:
        // `?id[]=1` convierte $_GET['id'] en array y rompe los cast.
        return is_array($v) ? null : $v;
    }
}
