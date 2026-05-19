<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

$user = getCurrentUser();
$breadcrumbs = getBreadcrumbs();
?>
<header class="navbar-top">
  <button class="toggle-sidebar" onclick="toggleSidebar()" aria-label="Alternar menú">
    <i class="bi bi-list"></i>
  </button>
  <div class="breadcrumb-nav">
    <?php
    $total = count($breadcrumbs);
    foreach ($breadcrumbs as $index => $bc) {
        if ($index === $total - 1) {
            echo '<span class="current">' . htmlspecialchars((string)($bc['title'] ?? '')) . '</span>';
        } else {
            echo '<span>' . htmlspecialchars((string)($bc['title'] ?? '')) . '</span>';
            echo '<span class="sep">/</span>';
        }
    }
    ?>
  </div>
  <div class="nav-actions">
    <button type="button" class="icon-btn" onclick="toggleTheme()" aria-label="Cambiar tema">
      <i class="bi bi-moon-stars" data-theme-icon></i>
    </button>
    <button class="icon-btn">
      <i class="bi bi-bell"></i>
      <span class="dot">3</span>
    </button>
    <div class="dropdown">
      <button class="icon-btn" style="width:auto;padding:0;border-radius:50%" data-bs-toggle="dropdown">
        <div class="avatar"><?= getInitials($user['nombre']) ?></div>
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" style="margin-top:10px">
        <li><h6 class="dropdown-header"><?= htmlspecialchars($user['nombre']) ?></h6></li>
        <li><a class="dropdown-item" href="<?= MODULES_PATH ?>/perfil/"><i class="bi bi-person me-2"></i>Mi perfil</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/includes/auth.php?action=logout"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a></li>
      </ul>
    </div>
  </div>
</header>
