<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';

requireAuth();

$pageTitle = 'Módulo en construcción · SENA';
$contentView = __FILE__;
if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}
?>
<div class="text-center py-5">
  <i class="bi bi-tools" style="font-size:3rem;color:var(--text-soft)"></i>
  <h2 class="mt-3">Módulo en construcción</h2>
  <p class="text-muted">Esta sección estará disponible próximamente.</p>
</div>
