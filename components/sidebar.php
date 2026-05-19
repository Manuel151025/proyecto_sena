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
    <div class="brand-mark">S</div>
    <div class="brand-text">
      <strong>SENA</strong>
      <small>Proyecto Formativo</small>
    </div>
  </div>
  <nav class="sidebar-nav">
    <?php foreach ($menu as $groupName => $items): ?>
      <div class="sidebar-section"><?= htmlspecialchars($groupName) ?></div>
      <?php foreach ($items as $item): ?>
        <?php 
        $activeClass = isActiveMenu($item['url']) ? 'active' : ''; 
        $itemUrl = $item['url'];
        ?>
        <a href="<?= $itemUrl ?>" class="sidebar-link <?= $activeClass ?>">
          <i class="<?= htmlspecialchars($item['icon']) ?>"></i>
          <span><?= htmlspecialchars($item['title']) ?></span>
          <?php if (isset($item['badge'])): ?>
            <span class="badge-side"><?= htmlspecialchars((string)$item['badge']) ?></span>
          <?php endif; ?>
        </a>
      <?php endforeach; ?>
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
