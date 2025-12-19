<?php
/**
 * LIMPIEZA DE ARCHIVOS DE PROGRESO DE ANÁLISIS
 *
 * Limpia archivos temporales y de progreso del análisis de archivos
 * antes de iniciar un nuevo análisis para asegurar estado limpio.
 */

// Configuración de seguridad
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Headers de respuesta
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');

// Función helper para logging seguro
function cleanupLog($message) {
    error_log("CLEANUP_ANALYSIS: " . $message);
}

// Función para eliminar archivos de forma segura
function safeUnlink($filePath) {
    if (file_exists($filePath) && is_writable($filePath)) {
        if (unlink($filePath)) {
            cleanupLog("Archivo eliminado: " . basename($filePath));
            return true;
        } else {
            cleanupLog("Error eliminando archivo: " . basename($filePath));
            return false;
        }
    }
    return true; // Ya no existe o no se puede escribir
}

try {
    $deletedFiles = [];
    $errors = [];

    // 1. LIMPIAR ARCHIVOS DE CONTINUACIÓN EN /tmp
    cleanupLog("Iniciando limpieza de archivos de continuación...");
    $tempDir = sys_get_temp_dir();
    $continuationPattern = $tempDir . '/analyze_continuation_*.json';

    // Usar glob para encontrar archivos de continuación
    $continuationFiles = glob($continuationPattern);
    if ($continuationFiles) {
        foreach ($continuationFiles as $file) {
            if (safeUnlink($file)) {
                $deletedFiles[] = 'continuation: ' . basename($file);
            } else {
                $errors[] = 'No se pudo eliminar: ' . basename($file);
            }
        }
    }

    // 2. LIMPIAR ARCHIVOS FILELIST EN DIRECTORIO BACKUPS
    cleanupLog("Limpieza de archivos filelist...");
    $backupDir = __DIR__ . '/../backups';

    if (is_dir($backupDir)) {
        // Archivos filelist normales
        $filelistPattern = $backupDir . '/filelist_*.json';
        $filelistFiles = glob($filelistPattern);

        if ($filelistFiles) {
            foreach ($filelistFiles as $file) {
                if (safeUnlink($file)) {
                    $deletedFiles[] = 'filelist: ' . basename($file);
                } else {
                    $errors[] = 'No se pudo eliminar filelist: ' . basename($file);
                }
            }
        }

        // Archivos filelist comprimidos
        $compressedPattern = $backupDir . '/filelist_*.json.gz';
        $compressedFiles = glob($compressedPattern);

        if ($compressedFiles) {
            foreach ($compressedFiles as $file) {
                if (safeUnlink($file)) {
                    $deletedFiles[] = 'compressed: ' . basename($file);
                } else {
                    $errors[] = 'No se pudo eliminar comprimido: ' . basename($file);
                }
            }
        }

        // Archivos indicadores de compresión
        $indicatorPattern = $backupDir . '/filelist_*.compressed';
        $indicatorFiles = glob($indicatorPattern);

        if ($indicatorFiles) {
            foreach ($indicatorFiles as $file) {
                if (safeUnlink($file)) {
                    $deletedFiles[] = 'indicator: ' . basename($file);
                } else {
                    $errors[] = 'No se pudo eliminar indicador: ' . basename($file);
                }
            }
        }
    } else {
        $errors[] = 'Directorio backups no encontrado: ' . $backupDir;
    }

    // 3. LIMPIEZA DE OTROS ARCHIVOS TEMPORALES DE ANÁLISIS (si existen)
    $otherPatterns = [
        $tempDir . '/analysis_progress_*.tmp',
        $tempDir . '/file_scan_*.tmp',
        $backupDir . '/analysis_progress_*.json',
        $backupDir . '/scan_checkpoint_*.json'
    ];

    foreach ($otherPatterns as $pattern) {
        $tempFiles = glob($pattern);
        if ($tempFiles) {
            foreach ($tempFiles as $file) {
                if (safeUnlink($file)) {
                    $deletedFiles[] = 'temp: ' . basename($file);
                } else {
                    $errors[] = 'No se pudo eliminar temp: ' . basename($file);
                }
            }
        }
    }

    // Respuesta de éxito
    $response = [
        'success' => true,
        'message' => 'Limpieza de archivos de progreso completada',
        'timestamp' => date('Y-m-d H:i:s'),
        'files_deleted' => $deletedFiles,
        'errors' => $errors,
        'total_deleted' => count($deletedFiles),
        'total_errors' => count($errors),
        'cleanup_details' => [
            'temp_dir' => $tempDir,
            'backup_dir' => $backupDir,
            'patterns_cleaned' => [
                'continuation_files',
                'filelist_json',
                'compressed_files',
                'indicator_files',
                'temp_files'
            ]
        ]
    ];

    cleanupLog("Limpieza completada - Eliminados: " . count($deletedFiles) . ", Errores: " . count($errors));

} catch (Exception $e) {
    // Error crítico
    $response = [
        'success' => false,
        'error' => 'Error en limpieza de progreso: ' . $e->getMessage(),
        'error_type' => 'cleanup_exception',
        'timestamp' => date('Y-m-d H:i:s'),
        'diagnostics' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'temp_dir' => sys_get_temp_dir(),
            'backup_dir' => __DIR__ . '/../backups'
        ]
    ];

    cleanupLog("ERROR en limpieza: " . $e->getMessage());
}

// Enviar respuesta JSON
echo json_encode($response, JSON_PRETTY_PRINT);
?>
