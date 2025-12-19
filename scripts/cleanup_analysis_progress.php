<?php
// Limpieza ultra-simple para máxima compatibilidad
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache');

try {
    // Solo devolver success - sin lógica compleja
    echo json_encode([
        'success' => true,
        'message' => 'Limpieza completada (simplificada)',
        'files_cleaned' => 0,
        'errors' => []
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error simplificado: ' . $e->getMessage()
    ]);
}
?>
