<?php
declare(strict_types=1);

require_once __DIR__ . '/header.php';
?>
<div class="app-shell">
  <?php require_once __DIR__ . '/../components/sidebar.php'; ?>
  <main class="main">
    <?php require_once __DIR__ . '/../components/navbar.php'; ?>
    <section class="content">
      <?php
      $flashMessages = getFlashMessages();
      foreach ($flashMessages as $flash):
          $icon = 'bi-info-circle';
          if ($flash['tipo'] === 'success') $icon = 'bi-check-circle';
          if ($flash['tipo'] === 'danger') $icon = 'bi-exclamation-circle';
          if ($flash['tipo'] === 'warning') $icon = 'bi-exclamation-triangle';
      ?>
      <div class="alert-flat <?= htmlspecialchars($flash['tipo']) ?> mb-3">
        <i class="bi <?= $icon ?>"></i>
        <div><?= htmlspecialchars($flash['mensaje']) ?></div>
      </div>
      <?php endforeach; ?>
      <?php
      if (isset($contentView) && file_exists($contentView)) {
          require $contentView;
      }
      ?>
    </section>
  </main>
</div>
<?php if (getCurrentRole() === ROL_COORDINADOR): ?>
  <?php require_once __DIR__ . '/../components/modal_crear_usuario.php'; ?>
  <?php require_once __DIR__ . '/../components/modal_editar_usuario.php'; ?>
<?php endif; ?>
<?php require_once __DIR__ . '/footer.php'; ?>
