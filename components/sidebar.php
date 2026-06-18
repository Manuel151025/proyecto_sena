<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';

$role = getCurrentRole();
$menuConfig = require __DIR__ . '/../config/navigation.php';
$menu = $menuConfig[$role] ?? [];
$user = getCurrentUser();
?>
<aside class="sidebar">
  <div class="sidebar-brand">
    <div class="brand-mark" style="background: transparent;">
      <img src="<?= APP_URL ?>/assets/img/sena_logo.png" alt="SENA Logo" style="width: 38px; height: 38px; object-fit: contain;">
    </div>
    <div class="brand-text">
      <strong>SENA</strong>
      <small>Proyecto Formativo</small>
    </div>
  </div>
  <nav class="sidebar-nav">
    <?php foreach ($menu as $groupName => $group): ?>
      <?php 
      $items = $group['items'] ?? [];
      $icon = $group['icon'] ?? 'bi bi-folder';
      
      // Determinar si el grupo está activo (cualquiera de sus sub-elementos está activo)
      $isGroupActive = false;
      foreach ($items as $item) {
          if (isActiveMenu($item['url']) === 'active') {
              $isGroupActive = true;
              break;
          }
      }
      $activeClass = $isGroupActive ? 'active' : '';
      ?>
      
      <?php if (count($items) === 1): ?>
        <?php 
        $singleItem = $items[0];
        $itemUrl = $singleItem['url'];
        ?>
        <a href="<?= $itemUrl ?>" class="sidebar-link <?= $activeClass ?>">
          <i class="<?= htmlspecialchars($icon) ?>"></i>
          <span><?= htmlspecialchars($singleItem['title']) ?></span>
        </a>
      <?php elseif (count($items) > 1): ?>
        <div class="sidebar-link sidebar-dropdown <?= $activeClass ?>">
          <i class="<?= htmlspecialchars($icon) ?>"></i>
          <span class="dropdown-menu-custom">
            <div class="dropdown-header"><?= htmlspecialchars($groupName) ?></div>
            <div class="dropdown-links">
              <?php foreach ($items as $item): ?>
                <?php $subActive = (isActiveMenu($item['url']) === 'active') ? 'active' : ''; ?>
                <a href="<?= $item['url'] ?>" class="dropdown-link <?= $subActive ?>">
                  <?= htmlspecialchars($item['title']) ?>
                </a>
              <?php endforeach; ?>
            </div>
          </span>
        </div>
      <?php endif; ?>
    <?php endforeach; ?>
  </nav>
  <div class="sidebar-user">
    <div class="avatar"><?= getInitials($user['nombre']) ?></div>
    <div class="meta">
      <strong><?= htmlspecialchars($user['nombre']) ?></strong>
      <small><?= ucfirst($user['rol']) ?></small>
    </div>
  </div>
</aside>
