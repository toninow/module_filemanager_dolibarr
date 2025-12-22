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
        // Listar chunks y archivos relacionados disponibles
        $backupDir = DOL_DOCUMENT_ROOT.'/custom/filemanager/backups';

        $files = [];

    if (is_dir($backupDir)) {
        // Buscar todos los archivos relevantes
        $allFiles = glob($backupDir . '/*');
        error_log("DEBUG: Archivos encontrados en $backupDir: " . count($allFiles));

        foreach ($allFiles as $filePath) {
                if (!is_file($filePath)) continue;

                $fileName = basename($filePath);
                $size = filesize($filePath);
                $modified = filemtime($filePath);

                $fileInfo = [
                    'file_name' => $fileName,
                    'size_bytes' => $size,
                    'size_mb' => round($size / 1024 / 1024, 2),
                    'modified' => $modified,
                    'modified_formatted' => date('Y-m-d H:i:s', $modified)
                ];

                // Clasificar archivos por tipo y extraer backup_id
                if (preg_match('/chunk_([^_]+)_(\d+)\.zip$/', $fileName, $matches)) {
                    // Chunks ZIP
                    $fileInfo['type'] = 'chunk';
                    $fileInfo['backup_id'] = $matches[1];
                    $fileInfo['chunk_number'] = (int)$matches[2];

                    // Obtener información detallada del archivo de estado
                    $fileCount = 0;
                    $chunkStateFile = $backupDir . '/chunk_state_' . $fileInfo['backup_id'] . '.json';
                    if (file_exists($chunkStateFile)) {
                        $chunkState = json_decode(file_get_contents($chunkStateFile), true);
                        if ($chunkState && isset($chunkState['chunk_zips'])) {
                            foreach ($chunkState['chunk_zips'] as $chunkInfo) {
                                if ($chunkInfo['number'] == $fileInfo['chunk_number']) {
                                    $fileCount = $chunkInfo['files'] ?? 0;
                                    break;
                                }
                            }
                        }
                    }
                    $fileInfo['file_count'] = $fileCount;

                } elseif (preg_match('/chunk_state_([^_]+)\.json$/', $fileName, $matches)) {
                    // Estados de chunks
                    $fileInfo['type'] = 'chunk_state';
                    $fileInfo['backup_id'] = $matches[1];

                } elseif (preg_match('/backup_progress_([^_]+)\.json$/', $fileName, $matches)) {
                    // Progreso de backup
                    $fileInfo['type'] = 'backup_progress';
                    $fileInfo['backup_id'] = $matches[1];

                } elseif (preg_match('/filelist_([^_]+)\.json/', $fileName, $matches)) {
                    // Lista de archivos
                    $fileInfo['type'] = 'filelist';
                    $fileInfo['backup_id'] = $matches[1];

                } elseif (preg_match('/backup_([^_]+)\.log$/', $fileName, $matches)) {
                    // Logs de backup
                    $fileInfo['type'] = 'backup_log';
                    $fileInfo['backup_id'] = $matches[1];

                } elseif (preg_match('/\.auth_token_[^_]*\.json$/', $fileName)) {
                    // Tokens de autenticación
                    $fileInfo['type'] = 'auth_token';
                    $fileInfo['backup_id'] = 'system'; // No asociado a backup específico

                } elseif (preg_match('/debug_.*\.log$/', $fileName)) {
                    // Logs de debug
                    $fileInfo['type'] = 'debug_log';
                    $fileInfo['backup_id'] = 'system'; // No asociado a backup específico

                } else {
                    // Otros archivos no clasificados
                    $fileInfo['type'] = 'other';
                    $fileInfo['backup_id'] = 'unknown';
                }

                $files[] = $fileInfo;
            }

            // Ordenar por tipo, luego por backup_id, luego por nombre
            usort($files, function($a, $b) {
                // Primero por tipo (chunks primero)
                $typeOrder = ['chunk' => 1, 'chunk_state' => 2, 'backup_progress' => 3,
                             'filelist' => 4, 'backup_log' => 5, 'debug_log' => 6,
                             'auth_token' => 7, 'other' => 8];
                $aOrder = $typeOrder[$a['type']] ?? 9;
                $bOrder = $typeOrder[$b['type']] ?? 9;

                if ($aOrder !== $bOrder) {
                    return $aOrder <=> $bOrder;
                }

                // Luego por backup_id (más reciente primero)
                if ($a['backup_id'] !== $b['backup_id']) {
                    if ($a['backup_id'] === 'system') return 1;
                    if ($b['backup_id'] === 'system') return -1;
                    return strcmp($b['backup_id'], $a['backup_id']);
                }

                // Finalmente por nombre
                return strcmp($a['file_name'], $b['file_name']);
            });
        }

        // Debug: Mostrar resumen de archivos procesados
        error_log("DEBUG: Total files processed: " . count($files));
        $typesCount = array_count_values(array_column($files, 'type'));
        error_log("DEBUG: Files by type: " . json_encode($typesCount));
        $backupIds = array_unique(array_filter(array_column($files, 'backup_id')));
        error_log("DEBUG: Backup IDs found: " . json_encode($backupIds));
        $chunks = array_filter($files, function($f) { return $f['type'] === 'chunk'; });
        error_log("DEBUG: Chunks found: " . count($chunks));
        $chunksByBackup = [];
        foreach ($chunks as $chunk) {
            $backupId = $chunk['backup_id'];
            if (!isset($chunksByBackup[$backupId])) {
                $chunksByBackup[$backupId] = 0;
            }
            $chunksByBackup[$backupId]++;
        }
        error_log("DEBUG: Chunks by backup: " . json_encode($chunksByBackup));

        echo json_encode([
            'success' => true,
            'files' => $files, // Cambiar 'chunks' por 'files'
            'total_files' => count($files),
            'total_size_mb' => round(array_sum(array_column($files, 'size_mb')), 2)
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


