<?php
// Limpiar cualquier salida previa y establecer headers
ob_clean();
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

$backupId = $_GET['backup_id'] ?? '';

if (empty($backupId)) {
    echo json_encode([
        'progress' => 0,
        'log' => '',
        'completed' => false,
        'error' => true,
        'error_message' => 'Backup ID no proporcionado'
    ]);
    exit;
}

/**
 * Función para detectar directorio de backups dinámicamente (misma que create_backup_files.php)
 */
function detectBackupDirectory() {
    $possibleDirs = array();
    
    // 1. Intentar directorio relativo al script (preferido)
    $scriptDir = __DIR__ . '/../backups';
    $possibleDirs[] = array('path' => $scriptDir, 'priority' => 10);
    
    // 2. Intentar desde DOL_DOCUMENT_ROOT si está definido
    if (defined('DOL_DOCUMENT_ROOT') && !empty(DOL_DOCUMENT_ROOT)) {
        $dolBackupDir = DOL_DOCUMENT_ROOT . '/custom/filemanager/backups';
        $possibleDirs[] = array('path' => $dolBackupDir, 'priority' => 9);
    }
    
    // 3. Intentar desde $_SERVER['DOCUMENT_ROOT'] si está disponible
    if (isset($_SERVER['DOCUMENT_ROOT']) && !empty($_SERVER['DOCUMENT_ROOT'])) {
        $docRootBackupDir = $_SERVER['DOCUMENT_ROOT'] . '/custom/filemanager/backups';
        $possibleDirs[] = array('path' => $docRootBackupDir, 'priority' => 8);
    }
    
    // 4. Intentar directorio temporal del sistema
    $tempDir = sys_get_temp_dir() . '/dolibarr_backups';
    $possibleDirs[] = array('path' => $tempDir, 'priority' => 1);
    
    // Ordenar por prioridad
    usort($possibleDirs, function($a, $b) {
        return $b['priority'] - $a['priority'];
    });
    
    // Probar cada directorio
    foreach ($possibleDirs as $dirInfo) {
        $dir = $dirInfo['path'];
        if (is_dir($dir)) {
            return $dir;
        }
    }
    
    return sys_get_temp_dir() . '/dolibarr_backups';
}

// Usar detección dinámica
$backupDir = detectBackupDirectory();
$tempBackupDir = sys_get_temp_dir() . '/dolibarr_backups';

// Asegurar que los directorios existen
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0755, true);
}
if (!is_dir($tempBackupDir)) {
    @mkdir($tempBackupDir, 0755, true);
}

$progressFile = $backupDir . DIRECTORY_SEPARATOR . 'progress_' . $backupId . '.txt';
$logFile = $backupDir . DIRECTORY_SEPARATOR . 'log_' . $backupId . '.txt';
$tempProgressFile = $tempBackupDir . DIRECTORY_SEPARATOR . 'progress_' . $backupId . '.txt';
$tempLogFile = $tempBackupDir . DIRECTORY_SEPARATOR . 'log_' . $backupId . '.txt';
$zipFileDB = $backupDir . DIRECTORY_SEPARATOR . 'db_dolibarr_' . $backupId . '.zip';
$zipFileFiles = $backupDir . DIRECTORY_SEPARATOR . 'files_dolibarr_' . $backupId . '.zip';
$zipFileComplete = $backupDir . DIRECTORY_SEPARATOR . 'full_backup_dolibarr_' . $backupId . '.zip';
// Buscar backups automáticos con diferentes formatos posibles
$zipFileAutomatic = $backupDir . DIRECTORY_SEPARATOR . 'automatic_backup_' . $backupId . '.zip';
$zipFileAutomaticNew = $backupDir . DIRECTORY_SEPARATOR . 'automatic_backup_dolibarr_' . $backupId . '.zip';
// También buscar con formato YYYYMMDD_HHMMSS (si el backup_id es solo la fecha sin guiones)
$backupIdFormatted = preg_replace('/(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', '$1$2$3_$4$5$6', $backupId);
$zipFileAutomaticFormatted = $backupDir . DIRECTORY_SEPARATOR . 'automatic_backup_dolibarr_' . $backupIdFormatted . '.zip';
$heartbeatFile = $backupDir . DIRECTORY_SEPARATOR . 'heartbeat_' . $backupId . '.txt';

$progress = 0;
$log = '';
$zipExists = false;

// Intentar leer desde el directorio principal primero
if (file_exists($progressFile)) {
    $progressContent = @file_get_contents($progressFile);
    if ($progressContent !== false) {
        $progress = (int)trim($progressContent);
    }
} elseif (file_exists($tempProgressFile)) {
    // Si no está en el directorio principal, buscar en /tmp
    $progressContent = @file_get_contents($tempProgressFile);
    if ($progressContent !== false) {
        $progress = (int)trim($progressContent);
    }
} else {
    // Si no existe, intentar buscar archivos alternativos o recientes
    $searchDirs = [$backupDir, $tempBackupDir];
    foreach ($searchDirs as $searchDir) {
        if (is_dir($searchDir)) {
            $allProgressFiles = glob($searchDir . DIRECTORY_SEPARATOR . 'progress_*.txt');
            if (!empty($allProgressFiles)) {
                // Ordenar por fecha de modificación (más reciente primero)
                usort($allProgressFiles, function($a, $b) {
                    return filemtime($b) - filemtime($a);
                });
                // Si hay un archivo muy reciente (últimos 5 minutos), usar ese
                foreach ($allProgressFiles as $altFile) {
                    if (time() - filemtime($altFile) < 300) {
                        $altContent = @file_get_contents($altFile);
                        if ($altContent !== false) {
                            $progress = (int)trim($altContent);
                            break 2; // Salir de ambos bucles
                        }
                    }
                }
            }
        }
    }
}

// Intentar leer desde el directorio principal primero
if (file_exists($logFile)) {
    $logContent = @file_get_contents($logFile);
    if ($logContent !== false) {
        $log = $logContent;
    }
} elseif (file_exists($tempLogFile)) {
    // Si no está en el directorio principal, buscar en /tmp
    $logContent = @file_get_contents($tempLogFile);
    if ($logContent !== false) {
        $log = $logContent;
    }
} else {
    // Si no existe, intentar buscar archivos alternativos o recientes
    $searchDirs = [$backupDir, $tempBackupDir];
    foreach ($searchDirs as $searchDir) {
        if (is_dir($searchDir)) {
            $allLogFiles = glob($searchDir . DIRECTORY_SEPARATOR . 'log_*.txt');
            if (!empty($allLogFiles)) {
                // Ordenar por fecha de modificación (más reciente primero)
                usort($allLogFiles, function($a, $b) {
                    return filemtime($b) - filemtime($a);
                });
                // Si hay un archivo muy reciente (últimos 5 minutos), usar ese
                foreach ($allLogFiles as $altFile) {
                    if (time() - filemtime($altFile) < 300) {
                        $altContent = @file_get_contents($altFile);
                        if ($altContent !== false) {
                            $log = $altContent;
                            break 2; // Salir de ambos bucles
                        }
                    }
                }
            }
        }
    }
}

// Verificar si existe el ZIP final (esto significa que el backup terminó)
if (file_exists($zipFileDB) || file_exists($zipFileFiles) || file_exists($zipFileComplete) || file_exists($zipFileAutomatic) || file_exists($zipFileAutomaticNew) || file_exists($zipFileAutomaticFormatted)) {
    // Determinar qué ZIP existe (prioridad: automático formateado > automático nuevo > automático > completo > archivos > base de datos)
    if (file_exists($zipFileAutomaticFormatted)) {
        $zipFileToCheck = $zipFileAutomaticFormatted;
    } elseif (file_exists($zipFileAutomaticNew)) {
        $zipFileToCheck = $zipFileAutomaticNew;
    } elseif (file_exists($zipFileAutomatic)) {
        $zipFileToCheck = $zipFileAutomatic;
    } elseif (file_exists($zipFileComplete)) {
        $zipFileToCheck = $zipFileComplete;
    } elseif (file_exists($zipFileFiles)) {
        $zipFileToCheck = $zipFileFiles;
    } else {
        $zipFileToCheck = $zipFileDB;
    }
    
    $zipFileSize = filesize($zipFileToCheck);
    
    // El ZIP existe y tiene contenido
    if ($zipFileSize > 1000) { // Más de 1KB para considerar válido
        $zipExists = true;
        // Si existe el ZIP con contenido y el progreso es 85 o más, marcar como completado
        if ($progress >= 85) {
            $progress = 100;
        }
    }
}

// Limpiar buffer antes de enviar respuesta
ob_clean();

// Detectar si hay error (progress = -1)
$hasError = ($progress < 0);
$errorMessage = '';
if ($hasError && file_exists($logFile)) {
    // Leer las últimas líneas del log para obtener el error
    $logLines = explode("\n", $log);
    $errorLines = array_filter($logLines, function($line) {
        return stripos($line, 'ERROR') !== false || stripos($line, 'error') !== false;
    });
    if (!empty($errorLines)) {
        $errorMessage = end($errorLines); // Última línea con error
    }
}

echo json_encode([
    'progress' => $progress,
    'log' => $log,
    'completed' => $progress >= 100 || $zipExists,
    'error' => $hasError,
    'error_message' => $errorMessage,
    'zip_exists' => $zipExists,
    'debug' => [
        'progress_file_exists' => file_exists($progressFile) || file_exists($tempProgressFile),
        'log_file_exists' => file_exists($logFile) || file_exists($tempLogFile),
        'progress_file_location' => file_exists($progressFile) ? 'main' : (file_exists($tempProgressFile) ? 'temp' : 'none'),
        'log_file_location' => file_exists($logFile) ? 'main' : (file_exists($tempLogFile) ? 'temp' : 'none'),
        'heartbeat_file_exists' => file_exists($heartbeatFile),
        'heartbeat_last_update' => file_exists($heartbeatFile) ? file_get_contents($heartbeatFile) : null,
        'zip_db_exists' => file_exists($zipFileDB),
        'zip_files_exists' => file_exists($zipFileFiles),
        'zip_complete_exists' => file_exists($zipFileComplete),
        'zip_automatic_exists' => file_exists($zipFileAutomatic) || file_exists($zipFileAutomaticNew) || file_exists($zipFileAutomaticFormatted),
        'backup_id' => $backupId,
        'backup_dir' => $backupDir,
        'temp_backup_dir' => $tempBackupDir,
        'backup_dir_writable' => is_writable($backupDir),
        'temp_backup_dir_writable' => is_writable($tempBackupDir),
        'log_file_size' => file_exists($logFile) ? filesize($logFile) : (file_exists($tempLogFile) ? filesize($tempLogFile) : 0),
        'progress_value' => file_exists($progressFile) ? file_get_contents($progressFile) : (file_exists($tempProgressFile) ? file_get_contents($tempProgressFile) : null)
    ]
]);
exit;
?>
