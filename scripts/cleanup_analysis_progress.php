<?php
// Limpieza de archivos de análisis para cancelación
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

try {
    $filesCleaned = 0;
    $errors = [];

    // 1. LIMPIAR archivo de progreso del análisis en temp
    $tempDir = sys_get_temp_dir();
    $progressFile = $tempDir . '/analysis_progress_' . session_id() . '.json';

    if (file_exists($progressFile)) {
        if (@unlink($progressFile)) {
            $filesCleaned++;
        } else {
            $errors[] = 'No se pudo eliminar: ' . basename($progressFile);
        }
    }

    // 2. LIMPIAR archivos de análisis en el directorio de backups
    $backupDir = dirname(__DIR__) . '/backups'; // Subir un nivel desde scripts/

    if (is_dir($backupDir)) {
        // Archivos a eliminar
        $filesToClean = [
            'pre_analyzed_files.json',           // Análisis preprocesado
            'pre_analyzed_files.json.gz',        // Comprimido
            'pre_analyzed_files.json.compressed', // Comprimido alternativo
        ];

        foreach ($filesToClean as $fileName) {
            $filePath = $backupDir . '/' . $fileName;
            if (file_exists($filePath)) {
                if (@unlink($filePath)) {
                    $filesCleaned++;
                } else {
                    $errors[] = 'No se pudo eliminar: ' . $fileName;
                }
            }
        }

        // 3. LIMPIAR archivos filelist antiguos (más de 1 hora)
        $filelistPattern = $backupDir . '/filelist_*.json*';
        $filelistFiles = glob($filelistPattern);

        if ($filelistFiles) {
            $oneHourAgo = time() - 3600; // 1 hora atrás

            foreach ($filelistFiles as $filePath) {
                // Solo eliminar si es más antiguo de 1 hora (para no eliminar análisis en curso)
                if (filemtime($filePath) < $oneHourAgo) {
                    if (@unlink($filePath)) {
                        $filesCleaned++;
                    } else {
                        $errors[] = 'No se pudo eliminar: ' . basename($filePath);
                    }
                }
            }
        }
    } else {
        $errors[] = 'Directorio de backups no encontrado: ' . $backupDir;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Limpieza completada',
        'files_cleaned' => $filesCleaned,
        'errors' => $errors
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error en limpieza: ' . $e->getMessage(),
        'files_cleaned' => 0,
        'errors' => [$e->getMessage()]
    ]);
}
?>
