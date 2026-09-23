<?php
declare(strict_types=1);
?>
    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"
            integrity="sha384-NrKB+u6Ts6AtkIhwPixiKTzgSKNblyhlk0Sohlgar9UHUBzai/sgnNNWWd291xqt" crossorigin="anonymous"></script>
    <!-- App JS -->
    <script src="<?= APP_URL ?>/assets/js/app.js?v=<?= filemtime(BASE_PATH . 'assets/js/app.js') ?>"></script>
    <!-- Searchable picker -->
    <script src="<?= APP_URL ?>/assets/js/searchable-picker.js?v=<?= filemtime(BASE_PATH . 'assets/js/searchable-picker.js') ?>"></script>
    <!-- Comportamientos declarativos (data-confirmar, data-modal, data-filtro...) -->
    <script src="<?= APP_URL ?>/assets/js/comportamientos.js?v=<?= filemtime(BASE_PATH . 'assets/js/comportamientos.js') ?>"></script>
    <?php
    // Scripts propios de la pantalla, declarados por la vista en
    // $scriptsVista (rutas relativas a assets/js). Van en archivos para que
    // la CSP no tenga que admitir código en línea.
    foreach (($scriptsVista ?? []) as $js):
        $rutaJs = BASE_PATH . 'assets/js/' . $js;
        if (preg_match('#^[a-z0-9/_\-]+\.js$#', $js) && is_file($rutaJs)): ?>
    <script src="<?= APP_URL ?>/assets/js/<?= e($js) ?>?v=<?= filemtime($rutaJs) ?>"></script>
    <?php endif; endforeach; ?>
</body>
</html>
