<?php
declare(strict_types=1);

/**
 * MI PERFIL — modules/perfil/index.php
 *
 * Permite a cualquier usuario autenticado:
 *   - Actualizar su nombre y color de avatar
 *   - Cambiar su contraseña (verificando la actual)
 *
 * NO permite cambiar email ni rol (eso lo hace el coordinador desde el módulo
 * de usuarios, para mantener trazabilidad y evitar privilege escalation).
 */

require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../core/Database.php';

use Core\Database;

requireAuth();

$user_id = (int)getCurrentUser()['id'];
$errors  = [];
$success = '';

// Colores válidos del avatar (paleta institucional + variaciones)
$colores_validos = [
    '#39A900', // SENA verde
    '#2E7D32', // verde oscuro
    '#1976D2', // azul
    '#7B1FA2', // morado
    '#F59E0B', // ámbar
    '#E53935', // rojo
    '#00897B', // teal
    '#5D4037', // marrón
];

// =====================================================================
// POST: Actualizar datos personales
// =====================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_profile') {

    $nombre = trim($_POST['nombre'] ?? '');
    $color  = trim($_POST['avatar_color'] ?? '');

    if (mb_strlen($nombre) < 3) {
        $errors[] = 'El nombre debe tener al menos 3 caracteres.';
    }
    if (mb_strlen($nombre) > 150) {
        $errors[] = 'El nombre no puede exceder 150 caracteres.';
    }
    if (!in_array($color, $colores_validos, true)) {
        $errors[] = 'Color de avatar no válido.';
    }

    if (empty($errors)) {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("
                UPDATE usuarios
                SET nombre = ?, avatar_color = ?, fecha_actualizacion = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$nombre, $color, $user_id]);

            // Refrescar sesión de esta pestaña para que el cambio se vea de inmediato
            $_SESSION['tabs'][getTabId()]['user_nombre']       = $nombre;
            $_SESSION['tabs'][getTabId()]['user_avatar_color'] = $color;

            $success = 'Datos personales actualizados correctamente.';
        } catch (Exception $e) {
            $errors[] = 'No se pudieron guardar los cambios.';
        }
    }
}

// =====================================================================
// POST: Cambiar contraseña
// =====================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {

    $actual    = $_POST['password_actual'] ?? '';
    $nueva     = $_POST['password_nueva'] ?? '';
    $confirmar = $_POST['password_confirmar'] ?? '';

    if (empty($actual)) {
        $errors[] = 'Debes ingresar tu contraseña actual.';
    }
    if (strlen($nueva) < 8) {
        $errors[] = 'La nueva contraseña debe tener al menos 8 caracteres.';
    }
    if (!preg_match('/[A-Za-z]/', $nueva) || !preg_match('/[0-9]/', $nueva)) {
        $errors[] = 'La nueva contraseña debe contener letras y números.';
    }
    if ($nueva !== $confirmar) {
        $errors[] = 'La confirmación no coincide con la nueva contraseña.';
    }
    if ($nueva === $actual && empty($errors)) {
        $errors[] = 'La nueva contraseña no puede ser igual a la actual.';
    }

    if (empty($errors)) {
        try {
            $db = Database::getConnection();

            // Verificar contraseña actual
            $stmt = $db->prepare("SELECT password FROM usuarios WHERE id = ?");
            $stmt->execute([$user_id]);
            $hash_actual = $stmt->fetchColumn();

            if (!$hash_actual || !password_verify($actual, $hash_actual)) {
                $errors[] = 'La contraseña actual es incorrecta.';
            } else {
                $nuevo_hash = password_hash($nueva, PASSWORD_DEFAULT);
                $stmt = $db->prepare("
                    UPDATE usuarios
                    SET password = ?, fecha_actualizacion = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$nuevo_hash, $user_id]);

                $success = 'Contraseña actualizada correctamente.';
            }
        } catch (Exception $e) {
            $errors[] = 'No se pudo cambiar la contraseña.';
        }
    }
}

// =====================================================================
// Cargar datos frescos de BD (no de sesión, por si recién se actualizó)
// =====================================================================

$user = ['nombre' => '', 'email' => '', 'rol' => '', 'avatar_color' => '#39A900', 'fecha_creacion' => ''];
try {
    $db = Database::getConnection();
    $stmt = $db->prepare("SELECT nombre, email, rol, avatar_color, fecha_creacion FROM usuarios WHERE id = ?");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();
    if ($row) {
        $user = $row;
    }
} catch (Exception $e) {
    $errors[] = 'Error al cargar tu perfil.';
}

$pageTitle = 'Mi Perfil · SENA';
$contentView = __FILE__;
if (!isset($app_included)) {
    $app_included = true;
    require_once __DIR__ . '/../../layouts/app.php';
    exit;
}
?>

<h1 class="mb-1">Mi perfil</h1>
<p class="text-muted">Actualiza tus datos personales y credenciales de acceso.</p>

<?php if (!empty($success)): ?>
  <div class="alert-flat success mb-3">
    <i class="bi bi-check-circle"></i>
    <div><?= htmlspecialchars($success) ?></div>
  </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
  <div class="alert-flat danger mb-3">
    <i class="bi bi-exclamation-circle"></i>
    <div>
      <?php foreach ($errors as $err): ?>
        <div><?= htmlspecialchars($err) ?></div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<div class="row g-3">

  <!-- ===== Datos personales ===== -->
  <div class="col-lg-7">
    <div class="card mb-3">
      <div class="card-header">Datos personales</div>
      <div class="card-body">

        <div class="d-flex align-items-center gap-3 mb-4">
          <div class="avatar lg" style="background: <?= htmlspecialchars($user['avatar_color']) ?>">
            <?= getInitials($user['nombre']) ?>
          </div>
          <div>
            <div class="fw-semibold"><?= htmlspecialchars($user['nombre']) ?></div>
            <div class="small text-muted">
              Miembro desde
              <?= !empty($user['fecha_creacion']) ? date('M Y', strtotime($user['fecha_creacion'])) : '—' ?>
            </div>
          </div>
        </div>

        <form method="POST" action="">
          <input type="hidden" name="action" value="update_profile">

          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Nombres y apellidos</label>
              <input type="text" name="nombre" class="form-control"
                     value="<?= htmlspecialchars($user['nombre']) ?>"
                     minlength="3" maxlength="150" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Correo institucional</label>
              <input type="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" disabled>
              <div class="small text-muted mt-1">Para cambiar el correo contacta al coordinador.</div>
            </div>
            <div class="col-md-6">
              <label class="form-label">Rol</label>
              <input type="text" class="form-control" value="<?= ucfirst(htmlspecialchars($user['rol'])) ?>" disabled>
            </div>
            <div class="col-md-6">
              <label class="form-label">Color de avatar</label>
              <div class="d-flex flex-wrap gap-2">
                <?php foreach ($colores_validos as $color): ?>
                  <label class="d-inline-block" style="cursor:pointer">
                    <input type="radio" name="avatar_color" value="<?= $color ?>"
                           class="d-none"
                           <?= $user['avatar_color'] === $color ? 'checked' : '' ?>>
                    <span class="d-inline-block rounded-circle"
                          style="width:32px;height:32px;background:<?= $color ?>;
                                 border:3px solid <?= $user['avatar_color'] === $color ? '#1f2937' : 'transparent' ?>;
                                 transition:border-color .15s"></span>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <div class="mt-4 text-end">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-check2 me-1"></i>Guardar cambios
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ===== Cambiar contraseña ===== -->
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header">Cambiar contraseña</div>
      <div class="card-body">
        <form method="POST" action="">
          <input type="hidden" name="action" value="change_password">

          <div class="mb-3">
            <label class="form-label">Contraseña actual</label>
            <div class="position-relative">
              <input type="password" name="password_actual" id="pw-cur"
                     class="form-control pe-5" required>
              <button type="button" class="btn btn-link position-absolute end-0 top-0 text-muted"
                      data-pw-toggle="#pw-cur" style="height:100%">
                <i class="bi bi-eye"></i>
              </button>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Nueva contraseña</label>
            <input type="password" name="password_nueva" class="form-control"
                   data-pw-strength required minlength="8">
            <div class="pw-strength mt-2"><span></span><span></span><span></span><span></span></div>
            <div class="mt-2">
              <div class="pw-req" data-req="len"><i class="bi bi-circle"></i> Mínimo 8 caracteres</div>
              <div class="pw-req" data-req="letter"><i class="bi bi-circle"></i> Contiene letras</div>
              <div class="pw-req" data-req="num"><i class="bi bi-circle"></i> Contiene números</div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Confirmar nueva contraseña</label>
            <input type="password" name="password_confirmar" class="form-control"
                   required minlength="8">
          </div>

          <button type="submit" class="btn btn-primary w-100">
            <i class="bi bi-shield-check me-1"></i>Actualizar contraseña
          </button>
        </form>
      </div>
    </div>
  </div>

</div>