<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/session.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../core/Database.php';

use Core\Database;

requireRole(ROL_COORDINADOR);

// Redirigir a editar.php que maneja crear y editar
header('Location: ' . MODULES_PATH . '/fichas/editar.php');
exit;

$id = $_GET['id'] ?? null;
$pageTitle = $id ? 'Editar Ficha' : 'Nueva Ficha';
$contentView = __FILE__;

// Si no se ha incluido el layout, lo incluimos
if (!isset($app_included)) {
    $app_included = true;
    require_once BASE_PATH . 'layouts/app.php';
    exit;
}

// Mock data si estamos editando
$ficha = $id ? [
    'codigo' => '2845121', 'programa' => 'ADSO', 'instructor' => '2', 
    'estado' => 'Ejecución', 'jornada' => 'Diurna', 'modalidad' => 'Presencial',
    'fecha_inicio' => '2025-01-15', 'fecha_fin' => '2026-12-15'
] : [];
?>

<div class="content-header animate-fade-in-up">
    <div>
        <a href="index.php" class="auth-form-back mb-2" style="margin-bottom: var(--space-2) !important;"><i class="bi bi-arrow-left"></i> Volver a fichas</a>
        <h2><?= $id ? 'Editar Ficha: ' . $ficha['codigo'] : 'Crear Nueva Ficha' ?></h2>
        <p class="text-muted">Ingresa los datos del programa de formación para configurar el grupo.</p>
    </div>
</div>

<div class="row animate-fade-in-up" style="animation-delay: 0.1s">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h3>Información Básica</h3>
            </div>
            <div class="card-body p-5">
                <form action="index.php" method="POST">
                    
                    <div class="form-row">
                        <div class="form-group form-floating">
                            <input type="text" class="form-control" id="codigo" placeholder="Código de Ficha" value="<?= $ficha['codigo'] ?? '' ?>" required>
                            <label for="codigo">Código de Ficha <span class="required">*</span></label>
                        </div>

                        <div class="form-group form-floating">
                            <select class="form-select" id="programa" required>
                                <option value="" disabled <?= !$id ? 'selected' : '' ?>>Seleccionar Programa...</option>
                                <option value="ADSO" <?= ($ficha['programa']??'') == 'ADSO' ? 'selected' : '' ?>>Análisis y Desarrollo de Software</option>
                                <option value="Sistemas" <?= ($ficha['programa']??'') == 'Sistemas' ? 'selected' : '' ?>>Sistemas</option>
                                <option value="Multimedia" <?= ($ficha['programa']??'') == 'Multimedia' ? 'selected' : '' ?>>Integración de Contenidos Digitales</option>
                            </select>
                            <label for="programa">Programa de Formación <span class="required">*</span></label>
                        </div>
                    </div>

                    <div class="form-row mt-4">
                        <div class="form-group form-floating">
                            <select class="form-select" id="jornada" required>
                                <option value="Diurna" <?= ($ficha['jornada']??'') == 'Diurna' ? 'selected' : '' ?>>Diurna</option>
                                <option value="Nocturna" <?= ($ficha['jornada']??'') == 'Nocturna' ? 'selected' : '' ?>>Nocturna</option>
                                <option value="Mixta" <?= ($ficha['jornada']??'') == 'Mixta' ? 'selected' : '' ?>>Mixta</option>
                            </select>
                            <label for="jornada">Jornada</label>
                        </div>

                        <div class="form-group form-floating">
                            <select class="form-select" id="modalidad" required>
                                <option value="Presencial" <?= ($ficha['modalidad']??'') == 'Presencial' ? 'selected' : '' ?>>Presencial</option>
                                <option value="Virtual" <?= ($ficha['modalidad']??'') == 'Virtual' ? 'selected' : '' ?>>Virtual</option>
                                <option value="A Distancia" <?= ($ficha['modalidad']??'') == 'A Distancia' ? 'selected' : '' ?>>A Distancia</option>
                            </select>
                            <label for="modalidad">Modalidad</label>
                        </div>
                    </div>

                    <h4 class="mt-5 mb-4 text-sm text-muted fw-bold text-uppercase border-bottom pb-2">Fechas y Asignación</h4>

                    <div class="form-row">
                        <div class="form-group form-floating">
                            <input type="date" class="form-control" id="fecha_inicio" value="<?= $ficha['fecha_inicio'] ?? '' ?>" required>
                            <label for="fecha_inicio">Fecha de Inicio</label>
                        </div>
                        <div class="form-group form-floating">
                            <input type="date" class="form-control" id="fecha_fin" value="<?= $ficha['fecha_fin'] ?? '' ?>" required>
                            <label for="fecha_fin">Fecha de Fin</label>
                        </div>
                    </div>

                    <div class="form-group form-floating mt-4">
                        <select class="form-select" id="instructor">
                            <option value="">Sin asignar</option>
                            <option value="1">Carlos Andrés Martínez</option>
                            <option value="2" <?= ($ficha['instructor']??'') == '2' ? 'selected' : '' ?>>María Fernanda López</option>
                        </select>
                        <label for="instructor">Instructor Líder</label>
                        <div class="form-hint">Puedes asignar el instructor líder más tarde.</div>
                    </div>

                    <div class="form-row mt-4">
                        <div class="form-group form-floating">
                            <select class="form-select" id="estado">
                                <option value="Inducción" <?= ($ficha['estado']??'') == 'Inducción' ? 'selected' : '' ?>>Inducción</option>
                                <option value="Planeación" <?= ($ficha['estado']??'') == 'Planeación' ? 'selected' : '' ?>>Planeación</option>
                                <option value="Ejecución" <?= ($ficha['estado']??'') == 'Ejecución' ? 'selected' : '' ?>>Ejecución</option>
                                <option value="Cierre" <?= ($ficha['estado']??'') == 'Cierre' ? 'selected' : '' ?>>Cierre</option>
                            </select>
                            <label for="estado">Fase Actual</label>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-3 mt-5 pt-4 border-top">
                        <a href="index.php" class="btn btn-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary"><?= $id ? 'Guardar Cambios' : 'Crear Ficha' ?></button>
                    </div>

                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card bg-surface-secondary border-0">
            <div class="card-body p-4">
                <div class="d-flex align-items-center gap-3 mb-4">
                    <div class="avatar avatar-md bg-primary-light text-primary"><i class="bi bi-info-circle"></i></div>
                    <h4 class="m-0">Información</h4>
                </div>
                <p class="text-sm text-muted">Asegúrate de que el código de la ficha coincida exactamente con el registrado en Sofia Plus.</p>
                <p class="text-sm text-muted">Una vez creada la ficha, podrás realizar la importación masiva de aprendices desde un archivo Excel en la vista de detalles.</p>
            </div>
        </div>
    </div>
</div>
