<?php
declare(strict_types=1);

// Script de diagnóstico de subida de archivos
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Diagnóstico de Carga de Archivos - SENA</title>
    <link rel="stylesheet" href="assets/css/theme.css">
    <style>
        body { font-family: sans-serif; margin: 40px; background: #f4f6f9; color: #333; }
        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); max-width: 600px; margin: 0 auto; }
        h1 { color: #39A900; margin-top: 0; }
        pre { background: #eee; padding: 15px; border-radius: 4px; overflow-x: auto; font-family: monospace; font-size: 13px; }
        .btn { background: #39A900; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-weight: bold; }
        .btn:hover { background: #2e8500; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input[type="file"] { display: block; width: 100%; padding: 8px; border: 1px dashed #ccc; border-radius: 4px; }
    </style>
</head>
<body>

<div class="card">
    <h1>Diagnóstico de Carga de Archivos</h1>
    <p>Usa este formulario para subir el archivo que falla y ver en detalle qué está recibiendo el servidor PHP.</p>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        ob_start();
        
        if (empty($_FILES)) {
            echo "¡ADVERTENCIA: La variable \$_FILES está vacía! Esto puede deberse a que el tamaño de la petición superó 'post_max_size' en php.ini.\n";
            echo "post_max_size actual: " . ini_get('post_max_size') . "\n";
            echo "Content-Length de la petición: " . ($_SERVER['CONTENT_LENGTH'] ?? 'Desconocido') . " bytes\n";
        } else {
            foreach ($_FILES as $inputName => $fileData) {
                echo "Campo del formulario: '$inputName'\n";
                echo "- Nombre original: " . $fileData['name'] . "\n";
                echo "- Tipo MIME enviado: " . $fileData['type'] . "\n";
                echo "- Tamaño reportado por navegador: " . $fileData['size'] . " bytes\n";
                echo "- Nombre temporal generado (tmp_name): " . $fileData['tmp_name'] . "\n";
                echo "- Código de error de subida: " . $fileData['error'] . " (";
                switch($fileData['error']) {
                    case UPLOAD_ERR_OK: echo "UPLOAD_ERR_OK - Sin errores"; break;
                    case UPLOAD_ERR_INI_SIZE: echo "UPLOAD_ERR_INI_SIZE - Excede upload_max_filesize"; break;
                    case UPLOAD_ERR_FORM_SIZE: echo "UPLOAD_ERR_FORM_SIZE - Excede MAX_FILE_SIZE en HTML"; break;
                    case UPLOAD_ERR_PARTIAL: echo "UPLOAD_ERR_PARTIAL - Archivo subido parcialmente"; break;
                    case UPLOAD_ERR_NO_FILE: echo "UPLOAD_ERR_NO_FILE - No se subió ningún archivo"; break;
                    case UPLOAD_ERR_NO_TMP_DIR: echo "UPLOAD_ERR_NO_TMP_DIR - Falta carpeta temporal"; break;
                    case UPLOAD_ERR_CANT_WRITE: echo "UPLOAD_ERR_CANT_WRITE - Error al escribir en disco"; break;
                    case UPLOAD_ERR_EXTENSION: echo "UPLOAD_ERR_EXTENSION - Una extensión detuvo la subida"; break;
                    default: echo "Desconocido";
                }
                echo ")\n";

                if ($fileData['error'] === UPLOAD_ERR_OK) {
                    $tmpPath = $fileData['tmp_name'];
                    $exists = file_exists($tmpPath);
                    echo "- ¿Existe en el directorio temporal al iniciar?: " . ($exists ? "SÍ" : "NO") . "\n";
                    if ($exists) {
                        echo "- Tamaño real en disco: " . filesize($tmpPath) . " bytes\n";
                        echo "- ¿Es legible?: " . (is_readable($tmpPath) ? "SÍ" : "NO") . "\n";
                        echo "- ¿Es archivo subido válido?: " . (is_uploaded_file($tmpPath) ? "SÍ" : "NO") . "\n";
                    }
                }
            }
        }
        
        $logContent = ob_get_clean();
        
        // Escribir a un archivo de registro en el servidor
        $logFile = __DIR__ . '/upload_diagnostic_log.txt';
        $timestamp = date('Y-m-d H:i:s');
        $fullLog = "=== Diagnóstico de Subida [$timestamp] ===\n" . $logContent . "\n\n";
        @file_put_contents($logFile, $fullLog, FILE_APPEND);
        
        echo "<h3>Resultados del Diagnóstico:</h3>";
        echo "<pre>" . htmlspecialchars($logContent) . "</pre>";
        echo "<p class='text-success'><strong>¡Éxito!</strong> Estos resultados también se han guardado en el archivo <code>upload_diagnostic_log.txt</code> en la raíz del proyecto para diagnóstico de Antigravity.</p>";
        echo "<hr>";
    }
    ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="excel_file">Selecciona el archivo Excel (.xls):</label>
            <input type="file" name="excel_file" id="excel_file" required>
        </div>
        <button type="submit" class="btn">Subir y Analizar</button>
    </form>
    
    <p style="margin-top:20px; font-size:12px; color:#666;">
        Carpeta temporal configurada en PHP: <code><?= ini_get('upload_tmp_dir') ?: 'No definida (usa la del sistema)' ?></code><br>
        Límite de subida de archivos: <code><?= ini_get('upload_max_filesize') ?></code>
    </p>
</div>

</body>
</html>
