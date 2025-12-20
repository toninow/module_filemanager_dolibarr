<?php
/**
 * LIMPIEZA DE CHUNKS - Eliminar chunks temporales después del backup
 */

// Configuración crítica
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
set_time_limit(60); // 1 minuto máximo
ini_set('memory_limit', '128M');

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

// Procesar acciones
$action = $_GET['action'] ?? '';
$backupId = $_GET['backup_id'] ?? '';
$fileName = $_GET['filename'] ?? $_POST['filename'] ?? '';

try {
    if ($action === 'delete') {
        // Eliminar chunks
        $backupDir = DOL_DOCUMENT_ROOT.'/custom/filemanager/backups';

        if (!is_dir($backupDir)) {
            echo json_encode(['success' => false, 'message' => 'Directorio de backups no encontrado']);
            exit;
        }

        $spaceFreed = 0;
        $chunksDeleted = 0;

        // Si se especifica un archivo específico, eliminar solo ese
        if (!empty($fileName)) {
            $chunkFile = $backupDir . '/' . $fileName;
            if (is_file($chunkFile)) {
                $size = filesize($chunkFile);
                if (@unlink($chunkFile)) {
                    $chunksDeleted = 1;
                    $spaceFreed = $size;
                }
            }
        }
        // Si se especifica backup_id, eliminar todos los chunks de ese backup
        elseif (!empty($backupId)) {
            $chunkPattern = $backupDir . '/chunk_' . $backupId . '_*.zip';
            $chunkFiles = glob($chunkPattern);

            foreach ($chunkFiles as $chunkFile) {
                if (is_file($chunkFile)) {
                    $size = filesize($chunkFile);
                    if (@unlink($chunkFile)) {
                        $chunksDeleted++;
                        $spaceFreed += $size;
                    }
                }
            }
        }

        $spaceFreedMB = round($spaceFreed / 1024 / 1024, 2);

        if ($chunksDeleted > 0) {
            echo json_encode([
                'success' => true,
                'chunks_deleted' => $chunksDeleted,
                'space_freed_mb' => $spaceFreedMB,
                'message' => "Eliminados {$chunksDeleted} chunks, liberados {$spaceFreedMB} MB"
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'No se encontraron chunks para eliminar'
            ]);
        }

    } elseif ($action === 'list') {
        // Listar chunks disponibles
        $backupDir = DOL_DOCUMENT_ROOT.'/custom/filemanager/backups';

        $chunks = [];

        if (is_dir($backupDir)) {
            $chunkFiles = glob($backupDir . '/chunk_*.zip');

            foreach ($chunkFiles as $chunkFile) {
                $fileName = basename($chunkFile);

                // Parsear información del chunk
                if (preg_match('/chunk_([^_]+)_(\d+)\.zip$/', $fileName, $matches)) {
                    $chunkBackupId = $matches[1];
                    $chunkNumber = (int)$matches[2];
                    $size = filesize($chunkFile);
                    $modified = filemtime($chunkFile);

                    // Intentar obtener información detallada del archivo de estado
                    $fileCount = 0;
                    $chunkStateFile = $backupDir . '/chunk_state_' . $chunkBackupId . '.json';

                    if (file_exists($chunkStateFile)) {
                        $chunkState = json_decode(file_get_contents($chunkStateFile), true);
                        if ($chunkState && isset($chunkState['chunk_zips'])) {
                            // Buscar este chunk específico en el estado
                            foreach ($chunkState['chunk_zips'] as $chunkInfo) {
                                if ($chunkInfo['number'] == $chunkNumber) {
                                    $fileCount = $chunkInfo['files'] ?? 0;
                                    break;
                                }
                            }
                        }
                    }

                    $chunks[] = [
                        'backup_id' => $chunkBackupId,
                        'chunk_number' => $chunkNumber,
                        'file_name' => $fileName,
                        'size_bytes' => $size,
                        'size_mb' => round($size / 1024 / 1024, 2),
                        'file_count' => $fileCount,
                        'modified' => $modified,
                        'modified_formatted' => date('Y-m-d H:i:s', $modified)
                    ];
                }
            }

            // Ordenar por backup_id y luego por chunk_number
            usort($chunks, function($a, $b) {
                if ($a['backup_id'] !== $b['backup_id']) {
                    return strcmp($b['backup_id'], $a['backup_id']); // Más reciente primero
                }
                return $a['chunk_number'] <=> $b['chunk_number'];
            });
        }

        echo json_encode([
            'success' => true,
            'chunks' => $chunks,
            'total_chunks' => count($chunks),
            'total_size_mb' => round(array_sum(array_column($chunks, 'size_mb')), 2)
        ]);

    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Acción no válida. Use: delete o list']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error interno del servidor',
        'message' => $e->getMessage()
    ]);
}
?>


