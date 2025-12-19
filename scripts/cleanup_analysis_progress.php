<?php
/**
 * Limpiar progreso del análisis de archivos
 */

// Configurar manejo de errores
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Función para manejar errores y devolver JSON
function returnJsonError($message, $code = 500) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

function returnJsonSuccess($message, $data = null) {
    header('Content-Type: application/json');
    $response = ['success' => true, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit;
}

// Configurar headers para JSON
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

// Incluir entorno de Dolibarr
try {
    require_once '../../main.inc.php';
} catch (Exception $e) {
    returnJsonError('Error al cargar Dolibarr: ' . $e->getMessage());
}

// Verificar que las constantes necesarias existan
if (!defined('DOL_DOCUMENT_ROOT') || !defined('DOL_DATA_ROOT')) {
    returnJsonError('Constantes de Dolibarr no definidas');
}

// Verificar permisos
if (!$user->admin) {
    returnJsonError('Acceso denegado: solo administradores');
}

// Función para limpiar archivos temporales de análisis
function cleanupAnalysisFiles() {
    $filesCleaned = 0;
    $errors = [];

    // Archivos a limpiar (usando rutas seguras)
    $tempFiles = [];

    // Archivos en documents
    if (is_dir(DOL_DOCUMENT_ROOT . '/documents')) {
        $tempFiles[] = DOL_DOCUMENT_ROOT . '/documents/admin/temp/analysis_progress.json';
        $tempFiles[] = DOL_DOCUMENT_ROOT . '/documents/admin/temp/backup_analysis_progress.json';
    }

    // Archivos en data root
    if (is_dir(DOL_DATA_ROOT)) {
        $tempFiles[] = DOL_DATA_ROOT . '/admin/temp/analysis_progress.json';
        $tempFiles[] = DOL_DATA_ROOT . '/admin/temp/backup_analysis_progress.json';
    }

    foreach ($tempFiles as $file) {
        if (file_exists($file)) {
            if (@unlink($file)) {
                $filesCleaned++;
            } else {
                $errors[] = "No se pudo eliminar: " . basename($file);
            }
        }
    }

    return ['files_cleaned' => $filesCleaned, 'errors' => $errors];
}

try {
    $result = cleanupAnalysisFiles();
    returnJsonSuccess('Limpieza completada', [
        'files_cleaned' => $result['files_cleaned'],
        'errors' => $result['errors']
    ]);

} catch (Exception $e) {
    returnJsonError('Error en limpieza: ' . $e->getMessage());
}
?>
