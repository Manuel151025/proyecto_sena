<div class="d-flex justify-content-between align-items-center mb-3">
  <div>
    <h1 class="mb-1">Matrículas de Aprendices</h1>
    <p class="text-muted mb-0">Gestiona las admisiones de estudiantes y su asignación a fichas técnicas.</p>
  </div>
  <?php if (hasRole(ROL_COORDINADOR)): ?>
  <div class="d-flex gap-2">
    <button class="btn btn-soft text-primary" data-bs-toggle="modal" data-bs-target="#modalCargarCSV">
      <i class="bi bi-file-earmark-spreadsheet me-1"></i> Carga Masiva (CSV)
    </button>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalMatricular">
      <i class="bi bi-person-plus me-1"></i> Matricular Aprendiz
    </button>
  </div>
  <?php endif; ?>
</div>

<?php if (!empty($successMessage)): ?>
<div class="alert alert-success alert-dismissible fade show border-0 glass-card text-success" role="alert">
  <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($successMessage) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger alert-dismissible fade show border-0 glass-card text-danger" role="alert">
  <i class="bi bi-exclamation-triangle-fill me-2"></i>
  <ul class="mb-0 ps-3 d-inline-block">
    <?php foreach ($errors as $err): ?>
      <li><?= htmlspecialchars($err) ?></li>
    <?php endforeach; ?>
  </ul>
  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- Barra de filtros -->
<div class="card glass-card mb-4 border-0">
  <div class="card-body">
    <form method="GET" class="row g-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label text-muted small">Buscar aprendiz</label>
        <div class="input-group">
          <span class="input-group-text border-end-0"><i class="bi bi-search text-muted"></i></span>
          <input type="text" name="search" id="searchAprendizInput" class="form-control border-start-0 ps-0" placeholder="Nombre, correo o documento..." value="<?= htmlspecialchars($search) ?>">
        </div>
      </div>
      <div class="col-md-3">
        <label class="form-label text-muted small">Filtrar por Ficha</label>
        <select name="ficha_id" class="form-select" onchange="this.form.submit()"
                data-picker
                data-picker-label="Filtrar por ficha"
                data-picker-placeholder="Buscar ficha por número o programa...">
          <option value="0">Todas las fichas</option>
          <?php foreach ($fichas as $f): ?>
            <option value="<?= $f['id'] ?>" <?= $filter_ficha === (int)$f['id'] ? 'selected' : '' ?>
                    data-search="<?= htmlspecialchars($f['numero_ficha'] . ' ' . $f['programa']) ?>">
              Ficha #<?= htmlspecialchars($f['numero_ficha']) ?> — <?= htmlspecialchars($f['programa']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label text-muted small">Estado</label>
        <select name="estado" class="form-select">
          <option value="">Todos</option>
          <option value="matriculado" <?= $filter_estado === 'matriculado' ? 'selected' : '' ?>>Matriculado</option>
          <option value="suspendido" <?= $filter_estado === 'suspendido' ? 'selected' : '' ?>>Suspendido</option>
          <option value="desertado" <?= $filter_estado === 'desertado' ? 'selected' : '' ?>>Desertado</option>
          <option value="egresado" <?= $filter_estado === 'egresado' ? 'selected' : '' ?>>Egresado</option>
          <option value="etapa_practica" <?= $filter_estado === 'etapa_practica' ? 'selected' : '' ?>>Etapa Práctica</option>
        </select>
      </div>
      <div class="col-md-2 d-grid">
        <button type="submit" class="btn btn-soft">Filtrar</button>
      </div>
    </form>
  </div>
</div>

<!-- Tabla de matriculados -->
<div class="card glass-card border-0">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table mb-0 align-middle">
        <thead class="table-light-head" style="background: rgba(0,0,0,0.03);">
          <tr>
            <th class="ps-4">Documento</th>
            <th>Aprendiz</th>
            <th>Ficha Asignada</th>
            <th>Teléfono / Ciudad</th>
            <th>Estado</th>
            <th class="pe-4 text-end">Acciones</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($aprendices as $ap): ?>
          <tr class="aprendiz-row" data-search="<?= htmlspecialchars(strtolower($ap['nombre'] . ' ' . $ap['email'] . ' ' . $ap['numero_documento']), ENT_QUOTES, 'UTF-8') ?>">
            <td class="ps-4 font-monospace fw-bold text-muted">
              <span class="badge bg-light text-dark border"><?= htmlspecialchars($ap['tipo_documento']) ?></span> 
              <?= htmlspecialchars($ap['numero_documento']) ?>
            </td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <div class="avatar" style="width:36px; height:36px; font-size:0.9rem; background:<?= htmlspecialchars($ap['avatar_color']) ?>">
                  <?= strtoupper(substr($ap['nombre'], 0, 2)) ?>
                </div>
                <div>
                  <h6 class="mb-0 fw-semibold"><?= htmlspecialchars($ap['nombre']) ?></h6>
                  <small class="text-muted"><?= htmlspecialchars($ap['email']) ?></small>
                </div>
              </div>
            </td>
            <td>
              <?php if ($ap['numero_ficha']): ?>
                <div class="fw-bold text-dark">Ficha #<?= htmlspecialchars($ap['numero_ficha']) ?></div>
                <small class="text-muted text-wrap d-block" style="max-width:200px;"><?= htmlspecialchars($ap['programa_nombre']) ?></small>
              <?php else: ?>
                <span class="text-danger small d-block mb-1"><i class="bi bi-x-circle me-1"></i>Sin Ficha</span>
              <?php endif; ?>
              <?php if ($ap['instructor_seguimiento_nombre']): ?>
                <div class="mt-1"><span class="badge bg-soft-info text-info"><i class="bi bi-person-video3 me-1"></i>Seguimiento: <?= htmlspecialchars($ap['instructor_seguimiento_nombre']) ?></span></div>
              <?php endif; ?>
            </td>
            <td>
              <div><?= htmlspecialchars($ap['telefono'] ?: 'N/A') ?></div>
              <small class="text-muted"><?= htmlspecialchars($ap['ciudad'] ?: 'No asignado') ?></small>
            </td>
            <td>
              <span class="badge-soft <?= $estados_label[$ap['estado']][1] ?? 'secondary' ?>">
                <?= $estados_label[$ap['estado']][0] ?? 'N/A' ?>
              </span>
            </td>
            <td class="pe-4 text-end">
              <?php if (hasRole(ROL_COORDINADOR)): ?>
                <button class="btn btn-sm btn-soft me-1" 
                        onclick="mostrarEditarMatricula(this)"
                        data-id="<?= $ap['id'] ?>"
                        data-nombre="<?= htmlspecialchars($ap['nombre'], ENT_QUOTES, 'UTF-8') ?>"
                        data-email="<?= htmlspecialchars($ap['email'], ENT_QUOTES, 'UTF-8') ?>"
                        data-tipo-documento="<?= htmlspecialchars($ap['tipo_documento'], ENT_QUOTES, 'UTF-8') ?>"
                        data-numero-documento="<?= htmlspecialchars($ap['numero_documento'], ENT_QUOTES, 'UTF-8') ?>"
                        data-genero="<?= htmlspecialchars($ap['genero'], ENT_QUOTES, 'UTF-8') ?>"
                        data-fecha-nacimiento="<?= htmlspecialchars($ap['fecha_nacimiento'] ?: '', ENT_QUOTES, 'UTF-8') ?>"
                        data-telefono="<?= htmlspecialchars($ap['telefono'] ?: '', ENT_QUOTES, 'UTF-8') ?>"
                        data-ciudad="<?= htmlspecialchars($ap['ciudad'] ?: '', ENT_QUOTES, 'UTF-8') ?>"
                        data-ficha-id="<?= $ap['ficha_id'] ?>"
                        data-estado="<?= htmlspecialchars($ap['estado'], ENT_QUOTES, 'UTF-8') ?>"
                        data-instructor-seguimiento-id="<?= $ap['instructor_seguimiento_id'] ?: '' ?>"
                        data-bs-toggle="modal" 
                        data-bs-target="#modalEditarMatricula"
                        title="Gestionar Matrícula">
                  <i class="bi bi-pencil"></i>
                </button>


                 <button class="btn btn-sm btn-soft text-danger"
                        onclick="eliminarMatricula(<?= $ap['id'] ?>, <?= htmlspecialchars(json_encode($ap['nombre']), ENT_QUOTES, 'UTF-8') ?>)"
                        title="Eliminar Matrícula">
                  <i class="bi bi-trash"></i>
                </button>

              <?php else: ?>
                <span class="text-muted small">No acciones</span>
              <?php endif; ?>
            </td>

          </tr>
          <?php endforeach; ?>
          <?php if (empty($aprendices)): ?>
          <tr>
            <td colspan="6" class="text-center py-5 text-muted">
              <i class="bi bi-people d-block mb-2" style="font-size:2rem; opacity:0.5;"></i>
              No se encontraron aprendices registrados.
            </td>
          </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Modal Registrar Matrícula -->
<?php if (hasRole(ROL_COORDINADOR)): ?>
<div class="modal fade" id="modalMatricular" tabindex="-1" aria-labelledby="modalMatricularLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content glass-card border-0" style="background: rgba(255,255,255,0.98); backdrop-filter: blur(20px);">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold" id="modalMatricularLabel"><i class="bi bi-person-plus text-primary me-2"></i>Nueva Matrícula</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="matricular">
        <div class="modal-body">
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label text-muted small fw-semibold">Nombre Completo</label>
              <input type="text" name="nombre" class="form-control" placeholder="Ej. Carlos Mario Restrepo" required>
            </div>
            <div class="col-md-6">
              <label class="form-label text-muted small fw-semibold">Correo Electrónico Institucional</label>
              <input type="email" name="email" class="form-control" placeholder="Ej. carlos@soy.sena.edu.co" required>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label text-muted small fw-semibold">Tipo Documento</label>
              <select name="tipo_documento" class="form-select">
                <option value="CC">Cédula de Ciudadanía (CC)</option>
                <option value="TI">Tarjeta de Identidad (TI)</option>
                <option value="CE">Cédula de Extranjería (CE)</option>
                <option value="PEP">PEP</option>
                <option value="PA">Pasaporte</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label text-muted small fw-semibold">Número Documento</label>
              <input type="text" name="numero_documento" class="form-control" placeholder="Ej. 1045612378" required>
            </div>
            <div class="col-md-4">
              <label class="form-label text-muted small fw-semibold">Ficha de Destino</label>
              <select name="ficha_id" class="form-select" required
                      data-picker
                      data-picker-label="Seleccionar ficha"
                      data-picker-placeholder="Número de ficha o nombre del programa...">
                <option value="" disabled selected>Seleccionar Ficha...</option>
                <?php foreach ($fichas as $f): ?>
                  <option value="<?= $f['id'] ?>"
                          data-search="<?= htmlspecialchars($f['numero_ficha'] . ' ' . $f['programa']) ?>">
                    Ficha #<?= htmlspecialchars($f['numero_ficha']) ?> — <?= htmlspecialchars($f['programa']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-3">
              <label class="form-label text-muted small fw-semibold">Género</label>
              <select name="genero" class="form-select">
                <option value="M">Masculino</option>
                <option value="F">Femenino</option>
                <option value="O">Otro</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label text-muted small fw-semibold">Fecha Nacimiento</label>
              <input type="date" name="fecha_nacimiento" class="form-control">
            </div>
            <div class="col-md-3">
              <label class="form-label text-muted small fw-semibold">Teléfono</label>
              <input type="text" name="telefono" class="form-control" placeholder="Ej. 3127894512">
            </div>
            <div class="col-md-3">
              <label class="form-label text-muted small fw-semibold">Ciudad</label>
              <input type="text" name="ciudad" class="form-control" placeholder="Ej. Medellín">
            </div>
          </div>
          <div class="row g-3 mb-3">
            <div class="col-md-12">
              <label class="form-label text-muted small fw-semibold">Instructor de Seguimiento (Etapa Práctica)</label>
              <select name="instructor_seguimiento_id" class="form-select">
                <option value="">-- Sin asignar --</option>
                <?php foreach ($instructores as $inst): ?>
                  <option value="<?= $inst['id'] ?>"><?= htmlspecialchars($inst['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
        </div>
        <div class="modal-footer border-top-0 pt-0">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Matricular Aprendiz</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Carga Masiva CSV -->
<div class="modal fade" id="modalCargarCSV" tabindex="-1" aria-labelledby="modalCargarCSVLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content glass-card border-0" style="background: rgba(255,255,255,0.98); backdrop-filter: blur(20px);">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold" id="modalCargarCSVLabel"><i class="bi bi-file-earmark-spreadsheet text-primary me-2"></i>Carga Masiva de Aprendices</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" enctype="multipart/form-data">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="cargar_csv">
        <div class="modal-body">
          <p class="text-muted small mb-3">
            Sube un archivo delimitado por comas (<strong>.csv</strong>) con la información de los aprendices. El sistema registrará a los usuarios e inicializará automáticamente todas sus evaluaciones para la ficha seleccionada.
          </p>
          
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Ficha de Destino</label>
            <select name="ficha_id" class="form-select" required>
              <option value="" disabled selected>Seleccionar Ficha...</option>
              <?php foreach ($fichas as $f): ?>
                <option value="<?= $f['id'] ?>">
                  Ficha #<?= htmlspecialchars($f['numero_ficha']) ?> — <?= htmlspecialchars($f['programa']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Seleccionar Archivo CSV</label>
            <input type="file" name="file_csv" class="form-control" accept=".csv" required>
          </div>

          <div class="p-3 bg-light rounded-3 text-muted" style="font-size:0.8rem;">
            <div class="fw-bold mb-1"><i class="bi bi-info-circle me-1"></i> Formato de Columnas del CSV:</div>
            <code>nombre, email, tipo_documento, numero_documento, genero, telefono, ciudad</code>
            <div class="mt-2">
              * El tipo de documento debe ser uno de: <strong>CC, TI, CE, PEP, PA</strong>.<br>
              * La contraseña por defecto de los nuevos aprendices será <strong>Sena2026</strong>.
            </div>
          </div>
        </div>
        <div class="modal-footer border-top-0 pt-0">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Importar Aprendices</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- Modal Editar Matrícula -->
<?php if (hasRole(ROL_COORDINADOR)): ?>
<div class="modal fade" id="modalEditarMatricula" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content glass-card border-0" style="background: rgba(255,255,255,0.98); backdrop-filter: blur(20px);">
      <div class="modal-header border-bottom-0 pb-0">
        <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Editar Datos del Aprendiz</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="editar_matricula">
        <input type="hidden" name="aprendiz_id" id="edit_aprendiz_id">
        <div class="modal-body">
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label text-muted small fw-semibold">Nombre Completo</label>
              <input type="text" name="nombre" id="edit_nombre" class="form-control" placeholder="Ej. Carlos Mario Restrepo" required>
            </div>
            <div class="col-md-6">
              <label class="form-label text-muted small fw-semibold">Correo Electrónico Institucional</label>
              <input type="email" name="email" id="edit_email" class="form-control" placeholder="Ej. carlos@soy.sena.edu.co" required>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label text-muted small fw-semibold">Tipo Documento</label>
              <select name="tipo_documento" id="edit_tipo_documento" class="form-select">
                <option value="CC">Cédula de Ciudadanía (CC)</option>
                <option value="TI">Tarjeta de Identidad (TI)</option>
                <option value="CE">Cédula de Extranjería (CE)</option>
                <option value="PEP">PEP</option>
                <option value="PA">Pasaporte</option>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label text-muted small fw-semibold">Número Documento</label>
              <input type="text" name="numero_documento" id="edit_numero_documento" class="form-control" placeholder="Ej. 1045612378" required>
            </div>
            <div class="col-md-4">
              <label class="form-label text-muted small fw-semibold">Ficha de Formación</label>
              <select name="ficha_id" id="edit_ficha_id" class="form-select" required
                      data-picker
                      data-picker-label="Seleccionar ficha"
                      data-picker-placeholder="Número de ficha o nombre del programa...">
                <?php foreach ($fichas as $f): ?>
                  <option value="<?= $f['id'] ?>"
                          data-search="<?= htmlspecialchars($f['numero_ficha'] . ' ' . $f['programa']) ?>">
                    Ficha #<?= htmlspecialchars($f['numero_ficha']) ?> — <?= htmlspecialchars($f['programa']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-3">
              <label class="form-label text-muted small fw-semibold">Estado de Matrícula</label>
              <select name="estado" id="edit_estado" class="form-select" required>
                <option value="matriculado">Matriculado</option>
                <option value="suspendido">Suspendido</option>
                <option value="desertado">Desertado</option>
                <option value="egresado">Egresado</option>
                <option value="etapa_practica">Etapa Práctica</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label text-muted small fw-semibold">Género</label>
              <select name="genero" id="edit_genero" class="form-select">
                <option value="M">Masculino</option>
                <option value="F">Femenino</option>
                <option value="O">Otro</option>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label text-muted small fw-semibold">Fecha Nacimiento</label>
              <input type="date" name="fecha_nacimiento" id="edit_fecha_nacimiento" class="form-control">
            </div>
            <div class="col-md-3">
              <label class="form-label text-muted small fw-semibold">Teléfono</label>
              <input type="text" name="telefono" id="edit_telefono" class="form-control" placeholder="Ej. 3127894512">
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Ciudad</label>
            <input type="text" name="ciudad" id="edit_ciudad" class="form-control" placeholder="Ej. Medellín">
          </div>
          <div class="mb-3">
            <label class="form-label text-muted small fw-semibold">Instructor de Seguimiento (Etapa Práctica)</label>
            <select name="instructor_seguimiento_id" id="edit_instructor_seguimiento_id" class="form-select">
              <option value="">-- Sin asignar --</option>
              <?php foreach ($instructores as $inst): ?>
                <option value="<?= $inst['id'] ?>"><?= htmlspecialchars($inst['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer border-top-0 pt-0">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar Cambios</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<form id="formEliminarMatricula" method="POST" style="display:none;">
  <?= csrfField() ?>
  <input type="hidden" name="action" value="eliminar_matricula">
  <input type="hidden" name="aprendiz_id" id="eliminar_aprendiz_id">
</form>

<script>
function eliminarMatricula(id, nombre) {
    if (confirm('¿Estás seguro de que deseas desvincular y eliminar la matrícula del aprendiz ' + nombre + '? Su usuario también será desactivado.')) {
        document.getElementById('eliminar_aprendiz_id').value = id;
        document.getElementById('formEliminarMatricula').submit();
    }
}

function mostrarEditarMatricula(button) {
    const elId = document.getElementById('edit_aprendiz_id');
    const elNombre = document.getElementById('edit_nombre');
    const elEmail = document.getElementById('edit_email');
    const elTipoDoc = document.getElementById('edit_tipo_documento');
    const elNumDoc = document.getElementById('edit_numero_documento');
    const elFicha = document.getElementById('edit_ficha_id');
    const elEstado = document.getElementById('edit_estado');
    const elGenero = document.getElementById('edit_genero');
    const elFechaNac = document.getElementById('edit_fecha_nacimiento');
    const elTelefono = document.getElementById('edit_telefono');
    const elCiudad = document.getElementById('edit_ciudad');

    if (elId) elId.value = button.getAttribute('data-id') || '';
    if (elNombre) elNombre.value = button.getAttribute('data-nombre') || '';
    if (elEmail) elEmail.value = button.getAttribute('data-email') || '';
    if (elTipoDoc) elTipoDoc.value = button.getAttribute('data-tipo-documento') || 'CC';
    if (elNumDoc) elNumDoc.value = button.getAttribute('data-numero-documento') || '';
    if (elFicha) elFicha.value = button.getAttribute('data-ficha-id') || '';
    if (elEstado) elEstado.value = button.getAttribute('data-estado') || 'matriculado';
    if (elGenero) elGenero.value = button.getAttribute('data-genero') || 'O';
    if (elFechaNac) elFechaNac.value = button.getAttribute('data-fecha-nacimiento') || '';
    if (elTelefono) elTelefono.value = button.getAttribute('data-telefono') || '';
    if (elCiudad) elCiudad.value = button.getAttribute('data-ciudad') || '';
    const elInstSeg = document.getElementById('edit_instructor_seguimiento_id');
    if (elInstSeg) elInstSeg.value = button.getAttribute('data-instructor-seguimiento-id') || '';
}

document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchAprendizInput');
    if (searchInput) {
        searchInput.addEventListener('keyup', function(e) {
            const filter = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('.aprendiz-row');
            rows.forEach(row => {
                const text = row.getAttribute('data-search') || '';
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    }
});
</script>
