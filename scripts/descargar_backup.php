<?php
/**
 * DESCARGA AUTOMÁTICA DE CHUNKS - API para FileManager
 *
 * Proporciona una API REST para descargar chunks automáticamente
 * desde la interfaz del módulo FileManager
 */

// Configuración crítica para evitar timeouts
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
set_time_limit(300); // 5 minutos máximo por chunk
ini_set('memory_limit', '256M');

// Headers de respuesta
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');

// Incluir Dolibarr
$mainPath = realpath(__DIR__ . '/../../../main.inc.php');
if (!$mainPath || !file_exists($mainPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'No se puede cargar Dolibarr']);
    exit;
}
require_once $mainPath;

// Función para obtener información de chunks
function getBackupInfo() {
    $backupDir = DOL_DOCUMENT_ROOT.'/custom/filemanager/backups';

    if (!is_dir($backupDir)) {
        return ['error' => 'Directorio de backups no encontrado'];
    }

    // Buscar archivos de chunks (patrón: chunk_*.zip)
    $chunkFiles = glob($backupDir . '/chunk_*.zip');

    if (empty($chunkFiles)) {
        return ['error' => 'No se encontraron chunks de backup'];
    }

    // Extraer información de los chunks
    $chunks = [];
    $totalSize = 0;

    foreach ($chunkFiles as $chunkFile) {
        $fileName = basename($chunkFile);

        // Parsear nombre del chunk: chunk_BACKUPID_NUMERO.zip
        if (preg_match('/chunk_([^_]+)_(\d+)\.zip$/', $fileName, $matches)) {
            $backupId = $matches[1];
            $chunkNumber = (int)$matches[2];
            $size = filesize($chunkFile);

            $chunks[] = [
                'numero' => $chunkNumber,
                'archivo' => $fileName,
                'tamano_mb' => round($size / 1024 / 1024, 1),
                'tamano_bytes' => $size
            ];

            $totalSize += $size;
        }
    }

    // Ordenar chunks por número
    usort($chunks, function($a, $b) {
        return $a['numero'] <=> $b['numero'];
    });

    return [
        'backup_id' => $backupId ?? 'unknown',
        'total_chunks' => count($chunks),
        'total_tamano_mb' => round($totalSize / 1024 / 1024, 1),
        'chunks' => $chunks,
        'directorio' => $backupDir
    ];
}

// Procesar acciones
$action = $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'info':
            // Devolver información del backup
            $info = getBackupInfo();
            if (isset($info['error'])) {
                http_response_code(404);
                echo json_encode(['error' => $info['error']]);
            } else {
                echo json_encode($info);
            }
            break;

        case 'descargar':
            // Descargar un chunk específico
            $chunkNum = intval($_GET['chunk'] ?? 0);

            if ($chunkNum <= 0) {
                http_response_code(400);
                echo json_encode(['error' => 'Número de chunk inválido']);
                break;
            }

            $info = getBackupInfo();

            if (isset($info['error'])) {
                http_response_code(404);
                echo json_encode(['error' => $info['error']]);
                break;
            }

            // Buscar el chunk solicitado
            $chunkData = null;
            foreach ($info['chunks'] as $chunk) {
                if ($chunk['numero'] === $chunkNum) {
                    $chunkData = $chunk;
                    break;
                }
            }

            if (!$chunkData) {
                http_response_code(404);
                echo json_encode(['error' => 'Chunk no encontrado']);
                break;
            }

            $chunkPath = $info['directorio'] . '/' . $chunkData['archivo'];

            if (!file_exists($chunkPath)) {
                http_response_code(404);
                echo json_encode(['error' => 'Archivo de chunk no existe']);
                break;
            }

            // Enviar headers de descarga
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $chunkData['archivo'] . '"');
            header('Content-Length: ' . $chunkData['tamano_bytes']);
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');

            // Enviar archivo
            readfile($chunkPath);
            break;

        default:
            http_response_code(400);
            echo json_encode(['error' => 'Acción no válida. Use: info, descargar']);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Error interno del servidor',
        'message' => $e->getMessage()
    ]);
}
?>

