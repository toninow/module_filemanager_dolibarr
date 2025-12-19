<?php
/**
 * UPDATE ANALYSIS PROGRESS - Actualiza el progreso del análisis de archivos
 * Compatible con entornos de hosting restringido
 */

// Headers para JSON y CORS
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

try {
    // Detectar directorio temporal
    $tempDir = sys_get_temp_dir();

    // Archivo de progreso del análisis
    $progressFile = $tempDir . '/analysis_progress_' . session_id() . '.json';

    // Obtener datos del POST
    $files = isset($_POST['files']) ? (int)$_POST['files'] : 0;
    $folders = isset($_POST['folders']) ? (int)$_POST['folders'] : 0;
    $size = isset($_POST['size']) ? (float)$_POST['size'] : 0;

    // Crear estructura de datos
    $progressData = [
        'running' => true, // Asumir que está corriendo si se está actualizando
        'stats' => [
            'total_files' => $files,
            'total_folders' => $folders,
            'total_size_mb' => $size
        ],
        'last_update' => time(),
        'partial' => false
    ];

    // Intentar guardar el archivo
    $jsonContent = json_encode($progressData, JSON_PRETTY_PRINT);
    if ($jsonContent !== false) {
        $result = @file_put_contents($progressFile, $jsonContent);
        if ($result !== false) {
            // Éxito
            echo json_encode([
                'success' => true,
                'message' => 'Progreso actualizado correctamente',
                'data' => $progressData
            ]);
        } else {
            // Error al escribir
            echo json_encode([
                'success' => false,
                'error' => 'No se pudo escribir el archivo de progreso'
            ]);
        }
    } else {
        // Error al codificar JSON
        echo json_encode([
            'success' => false,
            'error' => 'Error al codificar datos JSON'
        ]);
    }

} catch (Exception $e) {
    // Error crítico
    echo json_encode([
        'success' => false,
        'error' => 'Error interno: ' . $e->getMessage()
    ]);
}
?>

