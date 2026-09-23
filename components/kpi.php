<?php
// Tarjeta de indicador. Espera en $kpi: etiqueta, valor, icono y, opcional,
// clase (color del valor), nota (texto pequeño), enlace (ruta interna).
if (!defined('VISTA_PERMITIDA')) {
    http_response_code(404);
    exit('404 - No encontrado');
}
$contenido = '<div class="kpi h-100"><div class="kpi-content">'
    . '<div class="icon-bg"><i class="bi ' . e($kpi['icono']) . '"></i></div>'
    . '<div class="label">' . e($kpi['etiqueta']) . '</div>'
    . '<div class="value ' . e($kpi['clase'] ?? '') . '">' . e((string)$kpi['valor']) . '</div>'
    . (($kpi['nota'] ?? '') !== '' ? '<div class="small text-muted">' . e($kpi['nota']) . '</div>' : '')
    . '</div></div>';
if (!empty($kpi['enlace'])) {
    echo '<a class="text-reset text-decoration-none d-block h-100" href="' . e(APP_URL . '/index.php' . $kpi['enlace']) . '">' . $contenido . '</a>';
} else {
    echo $contenido;
}
