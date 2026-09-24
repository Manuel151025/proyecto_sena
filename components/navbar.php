<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

$user = getCurrentUser();
$breadcrumbs = getBreadcrumbs();

// Cargar notificaciones no leídas
$notifCount = 0;
$notificaciones = [];
if ($user) {
    try {
        $notifModel = new Core\Models\NotificacionesModel();
        $notifCount = $notifModel->contarNoLeidas((int) $user['id']);
        $notificaciones = $notifModel->noLeidas((int) $user['id']);
    } catch (Throwable $e) {
        // La campana es accesoria: un fallo aquí no debe tumbar la página.
        error_log('navbar notificaciones: ' . $e->getMessage());
    }
}

// Mapeo de tipo a icono y color
$tipoIconos = [
    'info'    => ['icon' => 'bi-info-circle-fill',          'color' => 'text-primary'],
    'success' => ['icon' => 'bi-check-circle-fill',         'color' => 'text-success'],
    'warning' => ['icon' => 'bi-exclamation-triangle-fill', 'color' => 'text-warning'],
    'danger'  => ['icon' => 'bi-exclamation-circle-fill',   'color' => 'text-danger'],
];
?>
<header class="navbar-top">
  <button type="button" class="toggle-sidebar" data-accion="menu" aria-label="Alternar menú">
    <i class="bi bi-list"></i>
  </button>
  <div class="breadcrumb-nav">
    <?php
    $total = count($breadcrumbs);
    foreach ($breadcrumbs as $index => $bc) {
        if ($index === $total - 1) {
            echo '<span class="current">' . htmlspecialchars((string)($bc['label'] ?? '')) . '</span>';
        } else {
            echo '<span>' . htmlspecialchars((string)($bc['label'] ?? '')) . '</span>';
            echo '<span class="sep">/</span>';
        }
    }
    ?>
  </div>
  <div class="nav-actions">
    <button type="button" class="icon-btn" data-accion="tema" aria-label="Cambiar tema">
      <i class="bi bi-moon-stars" data-theme-icon></i>
    </button>

    <!-- Notification Bell Dropdown -->
    <div class="dropdown">
      <button class="icon-btn position-relative" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Notificaciones" id="btnNotificaciones">
        <i class="bi bi-bell"></i>
        <?php if ($notifCount > 0): ?>
          <span class="dot" id="notifBadge"><?= $notifCount > 99 ? '99+' : $notifCount ?></span>
        <?php endif; ?>
      </button>
      <div class="dropdown-menu dropdown-menu-end shadow border-0 p-0 notif-menu" id="notifDropdown"
           data-api="<?= htmlspecialchars(APP_URL . '/index.php/api/notificaciones', ENT_QUOTES, 'UTF-8') ?>">
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
          <h6 class="mb-0 fw-semibold">Notificaciones</h6>
          <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" id="btnMarcarTodas" title="Marcar todas como leídas" <?= $notifCount === 0 ? 'hidden' : '' ?>>
            <i class="bi bi-check2-all me-1"></i>Marcar todas
          </button>
        </div>
        <div class="overflow-auto notif-lista" id="notifListContainer">
          <?php if (empty($notificaciones)): ?>
            <div class="text-center text-muted py-4" id="notifEmpty">
              <i class="bi bi-bell-slash fs-2 d-block mb-2"></i>
              No tienes notificaciones nuevas
            </div>
          <?php else: ?>
            <?php foreach ($notificaciones as $notif):
                $tipo = $tipoIconos[$notif['tipo']] ?? $tipoIconos['info'];
            ?>
              <a href="<?= htmlspecialchars(Core\Services\Notificador::urlSegura($notif['url'] ?? null) ?? '#', ENT_QUOTES, 'UTF-8') ?>"
                 class="dropdown-item d-flex gap-3 py-2 px-3 border-bottom notif-item"
                 data-notif-id="<?= (int)$notif['id'] ?>"
                 style="white-space:normal">
                <div class="flex-shrink-0 mt-1">
                  <i class="bi <?= $tipo['icon'] ?> <?= $tipo['color'] ?>"></i>
                </div>
                <div class="flex-grow-1 overflow-hidden">
                  <div class="fw-semibold small"><?= htmlspecialchars($notif['titulo']) ?></div>
                  <div class="text-muted small text-truncate"><?= htmlspecialchars(mb_substr($notif['mensaje'], 0, 80)) ?></div>
                  <div class="text-muted notif-tiempo"><?= htmlspecialchars(timeAgo((string)$notif['fecha_creacion'])) ?></div>
                </div>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="dropdown">
      <button class="icon-btn" style="width:auto;padding:0;border-radius:50%" data-bs-toggle="dropdown">
        <div class="avatar"><?= getInitials($user['nombre']) ?></div>
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="margin-top:10px">
        <li><h6 class="dropdown-header"><?= htmlspecialchars($user['nombre']) ?></h6></li>
        <li><a class="dropdown-item" href="<?= APP_URL ?>/index.php/perfil"><i class="bi bi-person me-2"></i>Mi perfil</a></li>
        <li><hr class="dropdown-divider"></li>
        <li>
          <form method="POST" action="<?= APP_URL ?>/index.php/logout" class="m-0">
            <?= csrfField() ?>
            <button type="submit" class="dropdown-item text-danger"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</button>
          </form>
        </li>
      </ul>
    </div>
  </div>
</header>

<script src="<?= APP_URL ?>/assets/js/notificaciones.js?v=<?= filemtime(BASE_PATH . 'assets/js/notificaciones.js') ?>" defer></script>
