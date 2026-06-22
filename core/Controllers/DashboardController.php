<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Models\DashboardModel;
use Exception;

class DashboardController extends BaseController {
    private DashboardModel $dashboardModel;

    public function __construct(?DashboardModel $dashboardModel = null) {
        // Exigir autenticación y cualquiera de los roles válidos
        requireRole(ROL_COORDINADOR, ROL_INSTRUCTOR, ROL_APRENDIZ);
        $this->dashboardModel = $dashboardModel ?? new DashboardModel();
    }

    /**
     * Muestra el dashboard según el rol del usuario autenticado
     */
    public function index(): void {
        $role = getCurrentRole();
        $user = getCurrentUser();

        // Si es instructor o aprendiz, delegar temporalmente al panel legacy
        if ($role === ROL_INSTRUCTOR) {
            $this->redirect(MODULES_PATH . '/dashboard/instructor.php');
        }
        if ($role === ROL_APRENDIZ) {
            $this->redirect(MODULES_PATH . '/dashboard/aprendiz.php');
        }

        // Si es coordinador, cargar las métricas y la nueva vista
        $nombreUsuario = htmlspecialchars($user['nombre']);

        // Obtener KPIs e información agregada del modelo
        $kpis = $this->dashboardModel->getKpiMetrics();
        $sparklineData = $this->dashboardModel->getSparklineData();
        $fichasCriticas = $this->dashboardModel->getCriticasFichas(5);
        $cumplimientoProgramas = $this->dashboardModel->getCumplimientoPorPrograma();
        $statsProgramas = $this->dashboardModel->getStatsProgramasDesercion(5);
        $topInstructores = $this->dashboardModel->getTopInstructores(5);
        $recentEvaluations = $this->dashboardModel->getRecentEvaluations(5);

        // Desestructurar datos para la vista
        $this->render(
            BASE_PATH . 'modules/dashboard/views/coordinador.view.php',
            [
                'nombreUsuario' => $nombreUsuario,
                'fichasActivas' => $kpis['fichas_activas'],
                'aprendicesMatriculados' => $kpis['aprendices_matriculados'],
                'instructoresActivos' => $kpis['instructores_activos'],
                'retencioPromedio' => $kpis['retencion_promedio'],
                'fichasCriticas' => $fichasCriticas,
                'cumplimientoProgramas' => $cumplimientoProgramas,
                'statsProgramas' => $statsProgramas,
                'topInstructores' => $topInstructores,
                'recentEvaluations' => $recentEvaluations,
                'fichasEstadosMap' => $sparklineData['fichas_estados'],
                'aprendicesEstadosMap' => $sparklineData['aprendices_estados'],
                'instructoresEstadosMap' => $sparklineData['instructores_estados'],
                'fichasCumplimientoData' => $sparklineData['fichas_cumplimiento']
            ],
            'Dashboard Coordinador · SENA'
        );
    }
}
