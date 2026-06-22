<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Models\DashboardModel;
use Core\Models\InstructorDashboardModel;
use Core\Models\AprendizDashboardModel;
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

        // Si es instructor, cargar métricas de su panel y renderizar la vista del instructor
        if ($role === ROL_INSTRUCTOR) {
            $instructorModel = new InstructorDashboardModel();
            $instructorId = (int)$user['id'];

            $kpis = $instructorModel->getKpis($instructorId);
            $fichasInstructor = $instructorModel->getFichasAsignadas($instructorId);
            $pendientesPlanes = $instructorModel->getRecentDeficiencies($instructorId, 10);
            $evalConceptos = $instructorModel->getConceptDistribution($instructorId);
            $aprendicesSeguimientoLista = $instructorModel->getAprendicesSeguimiento($instructorId);

            $this->render(
                BASE_PATH . 'modules/dashboard/views/instructor.view.php',
                [
                    'nombreUsuario' => htmlspecialchars($user['nombre']),
                    'kpis' => $kpis,
                    'fichasInstructor' => $fichasInstructor,
                    'pendientesPlanes' => $pendientesPlanes,
                    'evalConceptos' => $evalConceptos,
                    'aprendicesSeguimientoLista' => $aprendicesSeguimientoLista
                ],
                'Dashboard Instructor · SENA'
            );
            return;
        }

        // Si es aprendiz, cargar métricas académicas de su panel y renderizar su vista
        if ($role === ROL_APRENDIZ) {
            $aprendizModel = new AprendizDashboardModel();
            $aprendizInfo = $aprendizModel->getAprendizInfo((int)$user['id']);

            $progreso = ['total_ra' => 0, 'aprobados' => 0, 'reprobados' => 0, 'pendientes' => 0];
            $progresoCompetencias = [];
            $fasesProyecto = [];
            $evaluacionesRecientes = [];
            $alertasD = [];

            if ($aprendizInfo !== null) {
                $aprendizId = (int)$aprendizInfo['id'];
                $progreso = $aprendizModel->getProgresoGlobal($aprendizId);
                $progresoCompetencias = $aprendizModel->getProgresoCompetencias((int)$aprendizInfo['ficha_id'], $aprendizId);
                if ((int)$aprendizInfo['proyecto_id'] > 0) {
                    $fasesProyecto = $aprendizModel->getFasesProyecto((int)$aprendizInfo['proyecto_id']);
                }
                $evaluacionesRecientes = $aprendizModel->getRecentEvaluations($aprendizId, 6);
                $alertasD = $aprendizModel->getAlertasD($aprendizId, 3);
            }

            $this->render(
                BASE_PATH . 'modules/dashboard/views/aprendiz.view.php',
                [
                    'nombreUsuario' => htmlspecialchars($user['nombre']),
                    'aprendiz' => $aprendizInfo,
                    'progreso' => $progreso,
                    'progresoCompetencias' => $progresoCompetencias,
                    'fasesProyecto' => $fasesProyecto,
                    'evaluacionesRecientes' => $evaluacionesRecientes,
                    'alertasD' => $alertasD
                ],
                'Dashboard Aprendiz · SENA'
            );
            return;
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
