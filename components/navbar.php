<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/notificaciones.php';

$user = getCurrentUser();
$breadcrumbs = getBreadcrumbs();

// Cargar notificaciones no leídas
$notifCount = 0;
$notificaciones = [];
if ($user) {
    $notifCount = contarNotificacionesNoLeidas((int) $user['id']);
    $notificaciones = getNotificacionesNoLeidas((int) $user['id']);
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
  <button class="toggle-sidebar" onclick="toggleSidebar()" aria-label="Alternar menú">
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
    <button type="button" class="icon-btn" onclick="toggleTheme()" aria-label="Cambiar tema">
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
      <div class="dropdown-menu dropdown-menu-end shadow border-0 p-0" style="width:360px;max-height:480px;margin-top:10px" id="notifDropdown">
        <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom">
          <h6 class="mb-0 fw-semibold">Notificaciones</h6>
          <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" id="btnMarcarTodas" title="Marcar todas como leídas" <?= $notifCount === 0 ? 'style="display:none"' : '' ?>>
            <i class="bi bi-check2-all me-1"></i>Marcar todas
          </button>
        </div>
        <div class="overflow-auto" style="max-height:380px" id="notifListContainer">
          <?php if (empty($notificaciones)): ?>
            <div class="text-center text-muted py-4" id="notifEmpty">
              <i class="bi bi-bell-slash fs-2 d-block mb-2"></i>
              No tienes notificaciones nuevas
            </div>
          <?php else: ?>
            <?php foreach ($notificaciones as $notif):
                $tipo = $tipoIconos[$notif['tipo']] ?? $tipoIconos['info'];
            ?>
              <a href="<?= htmlspecialchars($notif['url'] ?? '#') ?>"
                 class="dropdown-item d-flex gap-3 py-2 px-3 border-bottom notif-item"
                 data-notif-id="<?= (int)$notif['id'] ?>"
                 style="white-space:normal">
                <div class="flex-shrink-0 mt-1">
                  <i class="bi <?= $tipo['icon'] ?> <?= $tipo['color'] ?>"></i>
                </div>
                <div class="flex-grow-1 overflow-hidden">
                  <div class="fw-semibold small"><?= htmlspecialchars($notif['titulo']) ?></div>
                  <div class="text-muted small text-truncate"><?= htmlspecialchars(mb_substr($notif['mensaje'], 0, 80)) ?></div>
                  <div class="text-muted" style="font-size:.7rem"><?= timeAgo($notif['fecha_creacion']) ?></div>
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
        <li><a class="dropdown-item" href="<?= MODULES_PATH ?>/perfil/"><i class="bi bi-person me-2"></i>Mi perfil</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-danger" href="<?= APP_URL ?>/includes/auth.php?action=logout"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a></li>
      </ul>
    </div>
  </div>
</header>

<script>
(function() {
  const API_URL = '<?= APP_URL ?>/includes/api_notificaciones.php';
  const badge = document.getElementById('notifBadge');
  const btnTodas = document.getElementById('btnMarcarTodas');
  const listContainer = document.getElementById('notifListContainer');

  // Marcar una notificación como leída al hacer clic
  document.getElementById('notifDropdown')?.addEventListener('click', function(e) {
    const item = e.target.closest('.notif-item');
    if (!item) return;

    const id = item.getAttribute('data-notif-id');
    if (!id) return;

    // Enviar petición para marcar como leída (fire-and-forget)
    fetch(API_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=marcar_leida&id=' + encodeURIComponent(id) + '&_tab=' + encodeURIComponent(window.__tabId || 'default') + '&csrf_token=' + encodeURIComponent('<?= getCsrfToken() ?>')
    });

    // Eliminar visualmente
    item.remove();
    updateBadgeCount(-1);
  });

  // Marcar todas como leídas
  btnTodas?.addEventListener('click', function() {
    fetch(API_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'action=marcar_todas&_tab=' + encodeURIComponent(window.__tabId || 'default') + '&csrf_token=' + encodeURIComponent('<?= getCsrfToken() ?>')
    }).then(() => {
      listContainer.innerHTML = '<div class="text-center text-muted py-4" id="notifEmpty"><i class="bi bi-bell-slash fs-2 d-block mb-2"></i>No tienes notificaciones nuevas</div>';
      updateBadgeCount(0, true);
      btnTodas.style.display = 'none';
    });
  });

  function updateBadgeCount(delta, absolute) {
    let current = badge ? parseInt(badge.textContent || '0', 10) : 0;
    let newCount = absolute ? delta : Math.max(0, current + delta);

    if (newCount <= 0) {
      badge?.remove();
      if (btnTodas) btnTodas.style.display = 'none';
      // Si no quedan items, mostrar mensaje vacío
      if (!listContainer.querySelector('.notif-item')) {
        listContainer.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-bell-slash fs-2 d-block mb-2"></i>No tienes notificaciones nuevas</div>';
      }
    } else if (badge) {
      badge.textContent = newCount > 99 ? '99+' : String(newCount);
    }
  }

  // Refresco periódico cada 60 segundos
  setInterval(function() {
    fetch(API_URL + '?_tab=' + encodeURIComponent(window.__tabId || 'default'))
      .then(r => r.json())
      .then(data => {
        if (!data.ok) return;

        // Actualizar badge
        const btn = document.getElementById('btnNotificaciones');
        let b = document.getElementById('notifBadge');

        if (data.count > 0) {
          if (!b) {
            b = document.createElement('span');
            b.className = 'dot';
            b.id = 'notifBadge';
            btn?.appendChild(b);
          }
          b.textContent = data.count > 99 ? '99+' : String(data.count);
          if (btnTodas) btnTodas.style.display = '';
        } else if (b) {
          b.remove();
          if (btnTodas) btnTodas.style.display = 'none';
        }

        // Reconstruir lista
        if (data.notificaciones.length === 0) {
          listContainer.innerHTML = '<div class="text-center text-muted py-4"><i class="bi bi-bell-slash fs-2 d-block mb-2"></i>No tienes notificaciones nuevas</div>';
        } else {
          const iconMap = {
            info:    { icon: 'bi-info-circle-fill',          color: 'text-primary' },
            success: { icon: 'bi-check-circle-fill',         color: 'text-success' },
            warning: { icon: 'bi-exclamation-triangle-fill', color: 'text-warning' },
            danger:  { icon: 'bi-exclamation-circle-fill',   color: 'text-danger' }
          };
          let html = '';
          data.notificaciones.forEach(n => {
            const t = iconMap[n.tipo] || iconMap.info;
            const msg = n.mensaje.length > 80 ? n.mensaje.substring(0, 80) + '…' : n.mensaje;
            html += `<a href="${n.url || '#'}" class="dropdown-item d-flex gap-3 py-2 px-3 border-bottom notif-item" data-notif-id="${n.id}" style="white-space:normal">
              <div class="flex-shrink-0 mt-1"><i class="bi ${t.icon} ${t.color}"></i></div>
              <div class="flex-grow-1 overflow-hidden">
                <div class="fw-semibold small">${n.titulo}</div>
                <div class="text-muted small text-truncate">${msg}</div>
                <div class="text-muted" style="font-size:.7rem">${n.tiempo_relativo}</div>
              </div>
            </a>`;
          });
          listContainer.innerHTML = html;
        }
      })
      .catch(() => {});
  }, 60000);
})();
</script>
