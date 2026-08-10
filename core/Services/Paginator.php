<?php
declare(strict_types=1);

namespace Core\Services;

/**
 * Cálculo de paginación, compartido por todos los listados.
 *
 * Antes cada listado grande resolvía el problema del volumen con un tope
 * fijo en la consulta (`LIMIT 200` en evaluaciones, `LIMIT 100` en la
 * auditoría). Eso no solo alargaba la página —37.000 px de alto en un
 * teléfono— sino que recortaba datos en silencio: con 3.873 evaluaciones,
 * el coordinador veía 200 sin ninguna señal de que existieran las demás.
 * Con paginación real el total queda a la vista y todo es alcanzable.
 *
 * Es un objeto de solo lectura: recibe el total de filas y la página
 * pedida, y calcula el resto. No toca la base de datos ni la petición;
 * quien consulta es el modelo, usando `offset()` y `perPage()`.
 */
final class Paginator {
    /** Tamaño de página por defecto: cabe en pantalla sin scroll infinito. */
    public const POR_PAGINA = 25;

    /** Cota superior de `?por_pagina=` para que nadie pida 1.000.000 de filas. */
    private const POR_PAGINA_MAX = 100;

    private int $totalItems;
    private int $perPage;
    private int $currentPage;
    private int $totalPages;

    public function __construct(int $totalItems, int $currentPage = 1, int $perPage = self::POR_PAGINA) {
        $this->totalItems = max(0, $totalItems);
        $this->perPage = max(1, min($perPage, self::POR_PAGINA_MAX));
        $this->totalPages = max(1, (int)ceil($this->totalItems / $this->perPage));
        // Se sujeta la página al rango válido: un `?pagina=999` a mano, o un
        // enlace guardado que ya no existe porque se filtró la lista, debe
        // mostrar la última página en vez de una tabla vacía.
        $this->currentPage = max(1, min($currentPage, $this->totalPages));
    }

    /**
     * Construye el paginador leyendo la página de la URL.
     */
    public static function desdePeticion(int $totalItems, int $perPage = self::POR_PAGINA, string $param = 'pagina'): self {
        $pagina = (int)($_GET[$param] ?? 1);
        $porPagina = isset($_GET['por_pagina']) ? (int)$_GET['por_pagina'] : $perPage;
        return new self($totalItems, $pagina, $porPagina);
    }

    public function offset(): int {
        return ($this->currentPage - 1) * $this->perPage;
    }

    public function perPage(): int {
        return $this->perPage;
    }

    public function currentPage(): int {
        return $this->currentPage;
    }

    public function totalPages(): int {
        return $this->totalPages;
    }

    public function totalItems(): int {
        return $this->totalItems;
    }

    public function tieneVariasPaginas(): bool {
        return $this->totalPages > 1;
    }

    public function hayAnterior(): bool {
        return $this->currentPage > 1;
    }

    public function haySiguiente(): bool {
        return $this->currentPage < $this->totalPages;
    }

    /** Número de la primera fila mostrada, en base 1 (para "Mostrando 26–50 de 162"). */
    public function primerItem(): int {
        return $this->totalItems === 0 ? 0 : $this->offset() + 1;
    }

    /** Número de la última fila mostrada. */
    public function ultimoItem(): int {
        return min($this->offset() + $this->perPage, $this->totalItems);
    }

    /**
     * Números de página a dibujar alrededor de la actual.
     *
     * Con 155 páginas no se pueden listar todas, así que se muestra una
     * ventana centrada en la página actual. El `null` representa un salto
     * ("…") para que la vista lo dibuje sin calcular nada.
     *
     * @return array<int|null>
     */
    public function ventanaPaginas(int $radio = 1): array {
        if ($this->totalPages <= 1) {
            return [1];
        }

        $paginas = [1];
        $desde = max(2, $this->currentPage - $radio);
        $hasta = min($this->totalPages - 1, $this->currentPage + $radio);

        if ($desde > 2) {
            $paginas[] = null;
        }
        for ($i = $desde; $i <= $hasta; $i++) {
            $paginas[] = $i;
        }
        if ($hasta < $this->totalPages - 1) {
            $paginas[] = null;
        }
        $paginas[] = $this->totalPages;

        return $paginas;
    }

    /**
     * URL de una página conservando los demás parámetros de la petición
     * (búsqueda y filtros): sin esto, pasar de página perdía el filtro y
     * devolvía al usuario a la lista completa.
     */
    public function urlPagina(int $pagina, string $param = 'pagina'): string {
        $query = $_GET;
        $query[$param] = $pagina;
        return '?' . http_build_query($query);
    }
}
