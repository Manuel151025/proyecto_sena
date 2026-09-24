<?php
declare(strict_types=1);

namespace Core\Controllers;

use Core\BaseController;
use Core\Database;
use Core\Formularios\EvidenciaFormulario;
use Core\Importacion\LectorTabular;
use Core\Models\ConfiguracionModel;
use Core\Services\Auditoria;
use Core\Support\Actor;
use Core\Support\Configuracion;
use Core\Support\ErrorDeNegocio;
use Core\Support\PoliticaContrasena;
use Core\Support\Semaforo;
use Throwable;

/**
 * Configuración (solo coordinación).
 *
 *   GET  /configuracion
 *   POST /configuracion  action=guardar   nombre del sistema y centro de formación
 *
 * Lo demás se muestra como referencia, con datos reales del servidor: la
 * ficha técnica anterior estaba escrita a mano («MySQL 8.0», «15 MB») y no
 * coincidía con lo instalado.
 */
class ConfiguracionController extends BaseController {
    public function index(): void {
        $this->exigirRol(ROL_COORDINADOR);
        $db = Database::getConnection();
        $migraciones = 0;
        try {
            $migraciones = (int)$db->query("SELECT COUNT(*) FROM migraciones")->fetchColumn();
        } catch (Throwable) {
        }
        $this->render(BASE_PATH . 'modules/configuracion/views/index.view.php', [
            'valores' => array_map(static fn($k) => Configuracion::valor($k), array_combine(array_keys(Configuracion::CLAVES), array_keys(Configuracion::CLAVES))),
            'claves'  => Configuracion::CLAVES,
            'tecnica' => [
                'Semáforo' => sprintf('Al día desde %s %% en A sobre lo evaluado; crítico por debajo de %s %% o con más de %d RAP en D',
                    Semaforo::UMBRAL_AL_DIA, Semaforo::UMBRAL_RIESGO, Semaforo::MAX_D_RIESGO),
                'Contraseñas' => 'Mínimo ' . PoliticaContrasena::MIN . ' caracteres, con letras y números; bloqueo temporal tras intentos fallidos',
                'Evidencias' => 'Hasta ' . EvidenciaFormulario::MAX_MB . ' MB · ' . strtoupper(implode(', ', EvidenciaFormulario::EXTENSIONES)),
                'Importaciones' => 'CSV, XLSX o XLS de hasta ' . LectorTabular::MAX_MB . ' MB, con vista previa antes de guardar',
                'Correo saliente' => getenv('MAIL_USERNAME') && getenv('MAIL_PASSWORD') ? 'Configurado (' . (getenv('MAIL_HOST') ?: 'smtp.gmail.com') . ')' : 'Sin configurar: faltan MAIL_USERNAME y MAIL_PASSWORD en el entorno',
                'Base de datos' => (string)$db->getAttribute(\PDO::ATTR_SERVER_VERSION) . ' · ' . $migraciones . ' migraciones aplicadas',
                'PHP' => PHP_VERSION . ' · subida máxima ' . ini_get('upload_max_filesize'),
                'Zona horaria' => date_default_timezone_get(),
                'Modo' => defined('DEV_MODE') && DEV_MODE ? 'Desarrollo (muestra detalles de error: no usar en producción)' : 'Producción',
            ],
        ], 'Configuración · SENA');
    }

    public function guardar(): never {
        $this->exigirRol(ROL_COORDINADOR);
        $v = $this->entrada();
        $datos = [];
        foreach (Configuracion::CLAVES as $clave => [$etiqueta]) {
            $datos[$clave] = $v->nombre($clave, $etiqueta, 3, 120);
        }
        $this->siHayErrores($v, '/configuracion');
        $this->ejecutar(function () use ($datos) {
            $modelo = new ConfiguracionModel();
            foreach ($datos as $clave => $valor) {
                $modelo->save($clave, $valor);
            }
            Configuracion::olvidar();
            (new Auditoria())->operacion(Actor::actual(), 'Editar', 'Configuración', 'configuraciones_sistema', null,
                'Actualizó: ' . implode(' · ', $datos));
        }, '/configuracion', 'Configuración guardada.', 'No se pudo guardar la configuración');
    }
}
