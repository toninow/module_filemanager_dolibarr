<?php
/**
 * GET ANALYSIS PROGRESS - Devuelve el progreso actual del análisis de archivos
 * Compatible con entornos de hosting restringido
 */

// Headers para JSON y CORS
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Detectar directorio temporal
$tempDir = sys_get_temp_dir();

// Archivo de progreso del análisis
$progressFile = $tempDir . '/analysis_progress_' . session_id() . '.json';

// Valores por defecto
$defaultResponse = [
    'success' => true,
    'running' => false,
    'stats' => [
        'total_files' => 0,
        'total_folders' => 0,
        'total_size_mb' => 0
    ],
    'last_update' => time(),
    'partial' => false
];

try {
    // Intentar leer el archivo de progreso
    if (file_exists($progressFile)) {
        $progressContent = @file_get_contents($progressFile);
        if ($progressContent !== false) {
            $progressData = json_decode($progressContent, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($progressData)) {
                // Combinar con valores por defecto para asegurar que todos los campos existan
                $response = array_merge($defaultResponse, $progressData);
                $response['success'] = true;
            } else {
                // Archivo corrupto, usar valores por defecto
                $response = $defaultResponse;
            }
        } else {
            // No se pudo leer, usar valores por defecto
            $response = $defaultResponse;
        }
    } else {
        // Archivo no existe, usar valores por defecto
        $response = $defaultResponse;
    }

    // Agregar timestamp actual
    $response['timestamp'] = time();

    // Enviar respuesta
    echo json_encode($response);

} catch (Exception $e) {
    // Error crítico, devolver valores por defecto
    $defaultResponse['success'] = false;
    $defaultResponse['error'] = 'Error interno: ' . $e->getMessage();
    echo json_encode($defaultResponse);
}
?>



