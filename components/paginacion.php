<?php
declare(strict_types=1);

/**
 * Control de paginación compartido por todos los listados.
 *
 * Uso desde una vista:
 *   <?php $paginador = $paginacion; require BASE_PATH . 'components/paginacion.php'; ?>
 *
 * Espera una variable `$paginador` de tipo Core\Services\Paginator.
 * Si no hay nada que paginar, se dibuja solo el resumen de totales, que
 * sigue siendo información útil ("32 registros").
 *
 * En móvil solo se muestran Anterior/Siguiente y el contador de página:
 * los números sueltos quedaban demasiado pequeños para el pulgar y se
 * desbordaban con muchas páginas.
 */

use Core\Services\Paginator;

/** @var Paginator|null $paginador */
if (!isset($paginador) || !$paginador instanceof Paginator) {
    return;
}

$etiqueta = $paginacionEtiqueta ?? 'registros';
?>
<nav class="paginacion" aria-label="Paginación de <?= htmlspecialchars($etiqueta) ?>">
  <p class="paginacion-resumen" aria-live="polite">
    <?php if ($paginador->totalItems() === 0): ?>
      Sin <?= htmlspecialchars($etiqueta) ?>
    <?php else: ?>
      Mostrando <strong><?= $paginador->primerItem() ?></strong>–<strong><?= $paginador->ultimoItem() ?></strong>
      de <strong><?= $paginador->totalItems() ?></strong> <?= htmlspecialchars($etiqueta) ?>
    <?php endif; ?>
  </p>

  <?php if ($paginador->tieneVariasPaginas()): ?>
    <ul class="paginacion-paginas">
      <li>
        <?php if ($paginador->hayAnterior()): ?>
          <a class="pag-link" href="<?= htmlspecialchars($paginador->urlPagina($paginador->currentPage() - 1)) ?>"
             rel="prev" aria-label="Página anterior">
            <i class="bi bi-chevron-left" aria-hidden="true"></i><span class="pag-texto">Anterior</span>
          </a>
        <?php else: ?>
          <span class="pag-link is-disabled" aria-disabled="true">
            <i class="bi bi-chevron-left" aria-hidden="true"></i><span class="pag-texto">Anterior</span>
          </span>
        <?php endif; ?>
      </li>

      <?php foreach ($paginador->ventanaPaginas() as $numero): ?>
        <li class="pag-numero">
          <?php if ($numero === null): ?>
            <span class="pag-salto" aria-hidden="true">…</span>
          <?php elseif ($numero === $paginador->currentPage()): ?>
            <span class="pag-link is-current" aria-current="page"><?= $numero ?></span>
          <?php else: ?>
            <a class="pag-link" href="<?= htmlspecialchars($paginador->urlPagina($numero)) ?>"
               aria-label="Página <?= $numero ?>"><?= $numero ?></a>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>

      <li class="pag-contador" aria-hidden="true">
        <?= $paginador->currentPage() ?> / <?= $paginador->totalPages() ?>
      </li>

      <li>
        <?php if ($paginador->haySiguiente()): ?>
          <a class="pag-link" href="<?= htmlspecialchars($paginador->urlPagina($paginador->currentPage() + 1)) ?>"
             rel="next" aria-label="Página siguiente">
            <span class="pag-texto">Siguiente</span><i class="bi bi-chevron-right" aria-hidden="true"></i>
          </a>
        <?php else: ?>
          <span class="pag-link is-disabled" aria-disabled="true">
            <span class="pag-texto">Siguiente</span><i class="bi bi-chevron-right" aria-hidden="true"></i>
          </span>
        <?php endif; ?>
      </li>
    </ul>
  <?php endif; ?>
</nav>
