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
      if (isset($contentView) && file_exists($contentView)) {
          require $contentView;
      }
      ?>
    </section>
  </main>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>
