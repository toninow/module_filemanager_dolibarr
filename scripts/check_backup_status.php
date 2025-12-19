<?php
/**
 * Script para verificar si hay un backup en ejecución
 * Retorna JSON con el estado del backup
 */

// Detectar directorio de backups dinámicamente
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

$backupDir = detectBackupDirectory();

// Verificar locks
$manualLockFile = $backupDir . DIRECTORY_SEPARATOR . 'manual_backup.lock';
$autoLockFile = $backupDir . DIRECTORY_SEPARATOR . 'auto_backup.lock';

$isRunning = false;
$lockType = null;
$lockInfo = null;

/**
 * Función para verificar si un PID existe (multiplataforma)
 */
function isProcessRunning($pid) {
    if (empty($pid) || !is_numeric($pid)) {
        return false;
    }
    
    // En Windows
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $output = array();
        exec("tasklist /FI \"PID eq $pid\" 2>NUL", $output);
        return count($output) > 1;
    }
    
    // En Linux/Unix
    if (function_exists('posix_kill')) {
        // Usar posix_kill con señal 0 (no mata, solo verifica)
        return @posix_kill($pid, 0);
    }
    
    // Fallback: verificar /proc/$pid (solo Linux)
    if (file_exists("/proc/$pid")) {
        return true;
    }
    
    // Fallback final: usar ps
    $output = array();
    @exec("ps -p $pid 2>/dev/null", $output);
    return count($output) > 1;
}

// Verificar lock manual
if (file_exists($manualLockFile)) {
    $lockTime = filemtime($manualLockFile);
    $lockAge = time() - $lockTime;
    $lockContent = @file_get_contents($manualLockFile);
    
    // Extraer PID del contenido del lock
    $pid = null;
    if (preg_match('/PID:\s*(\d+)/', $lockContent, $matches)) {
        $pid = (int)$matches[1];
    }
    
    // Verificar si el proceso realmente existe
    $processExists = false;
    if ($pid) {
        $processExists = isProcessRunning($pid);
    }
    
    // Si el lock tiene menos de 2 horas Y el proceso existe, considerarlo activo
    if ($lockAge < 7200) {
        // Si hay un PID y el proceso NO existe, el backup murió
        if ($pid && !$processExists) {
            // Lock huérfano - el proceso murió
            @unlink($manualLockFile);
            $isRunning = false;
        } else {
            // Verificar heartbeat como confirmación adicional
            $heartbeatFiles = glob($backupDir . DIRECTORY_SEPARATOR . 'heartbeat_*.txt');
            $hasRecentHeartbeat = false;
            
            if (!empty($heartbeatFiles)) {
                $latestHeartbeat = 0;
                foreach ($heartbeatFiles as $hbFile) {
                    $mtime = filemtime($hbFile);
                    if ($mtime > $latestHeartbeat) {
                        $latestHeartbeat = $mtime;
                    }
                }
                
                // Si el heartbeat se actualizó en los últimos 5 minutos
                if (time() - $latestHeartbeat <= 300) {
                    $hasRecentHeartbeat = true;
                }
            }
            
            // El backup está activo si:
            // 1. El proceso existe Y tiene heartbeat reciente, O
            // 2. No se pudo verificar el proceso pero tiene heartbeat reciente, O
            // 3. El lock es muy reciente (menos de 2 minutos) y no hay PID
            if (($processExists && $hasRecentHeartbeat) || 
                (!$pid && $hasRecentHeartbeat) || 
                ($lockAge < 120 && !$pid)) {
                $isRunning = true;
                $lockType = 'manual';
                $lockInfo = $lockContent;
            } else {
                // Lock sin heartbeat reciente - probablemente murió
                @unlink($manualLockFile);
                $isRunning = false;
            }
        }
    } else {
        // Lock muy antiguo (>2 horas), eliminarlo
        @unlink($manualLockFile);
        $isRunning = false;
    }
}

// Verificar lock automático
if (!$isRunning && file_exists($autoLockFile)) {
    $lockTime = filemtime($autoLockFile);
    $lockAge = time() - $lockTime;
    
    if ($lockAge < 7200) {
        $isRunning = true;
        $lockType = 'automatic';
        $lockInfo = @file_get_contents($autoLockFile);
    } else {
        @unlink($autoLockFile);
    }
}

// Verificar archivos de progreso activos
if (!$isRunning) {
    $progressFiles = glob($backupDir . DIRECTORY_SEPARATOR . 'progress_*.txt');
    foreach ($progressFiles as $progressFile) {
        $progress = @file_get_contents($progressFile);
        $progress = trim($progress);
        
        // Si hay un progreso entre 0 y 99, hay un backup en ejecución
        if (is_numeric($progress) && $progress >= 0 && $progress < 100) {
            $fileTime = filemtime($progressFile);
            $fileAge = time() - $fileTime;
            
            // Si el archivo se actualizó en los últimos 5 minutos, hay un backup activo
            if ($fileAge < 300) {
                $isRunning = true;
                $lockType = 'progress';
                $lockInfo = "Backup en progreso: $progress%";
                break;
            }
        }
    }
}

// ========== BUSCAR CHECKPOINTS INCOMPLETOS PARA RESUME ==========
$incompleteBackup = null;

// Primero buscar checkpoints de LISTADO (fase más temprana)
$listingCheckpointFiles = glob($backupDir . '/listing_checkpoint_*.json');
if (!empty($listingCheckpointFiles) && !$isRunning) {
    usort($listingCheckpointFiles, function($a, $b) {
        return filemtime($b) - filemtime($a);
    });
    
    $latestListingCheckpoint = $listingCheckpointFiles[0];
    $listingCheckpointData = @json_decode(file_get_contents($latestListingCheckpoint), true);
    
    if ($listingCheckpointData && isset($listingCheckpointData['backup_id'])) {
        $backupId = $listingCheckpointData['backup_id'];
        $dirsProcessed = count($listingCheckpointData['dirs_processed'] ?? []);
        $totalDirs = $listingCheckpointData['total_dirs'] ?? 0;
        $filesFound = count($listingCheckpointData['files'] ?? []);
        
        $incompleteBackup = array(
            'backup_id' => $backupId,
            'files_added' => 0,
            'total_files' => $filesFound,
            'zip_size_mb' => 0,
            'last_update' => $listingCheckpointData['last_update'] ?? 'Desconocido',
            'phase' => 'listing',
            'has_zip' => false,
            'has_files_list' => false,
            'has_listing_checkpoint' => true,
            'dirs_processed' => $dirsProcessed,
            'total_dirs' => $totalDirs,
            'percent' => $totalDirs > 0 ? round(($dirsProcessed / $totalDirs) * 30, 1) : 0
        );
    }
}

// Si no hay checkpoint de listado, buscar checkpoints normales
if (!$incompleteBackup) {
    $checkpointFiles = glob($backupDir . '/checkpoint_*.json');
    
    if (!empty($checkpointFiles) && !$isRunning) {
        // Ordenar por fecha de modificación (más reciente primero)
        usort($checkpointFiles, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        
        // Verificar el checkpoint más reciente
        $latestCheckpoint = $checkpointFiles[0];
        $checkpointData = @json_decode(file_get_contents($latestCheckpoint), true);
        
        if ($checkpointData && 
            isset($checkpointData['status']) && 
            $checkpointData['status'] === 'in_progress') {
            
            $backupId = $checkpointData['backup_id'] ?? '';
            $incompleteZip = $backupDir . '/incomplete_files_dolibarr_' . $backupId . '.zip';
            $filesListFile = $checkpointData['files_list_file'] ?? ($backupDir . '/filelist_' . $backupId . '.json');
            $listingCheckpoint = $backupDir . '/listing_checkpoint_' . $backupId . '.json';
            
            // Un backup se considera reanudable si:
            // 1. Tiene un ZIP incompleto con datos, O
            // 2. Tiene la lista de archivos guardada (fase ready_to_zip), O
            // 3. Tiene un checkpoint de listado (fase listing)
            $hasIncompleteZip = file_exists($incompleteZip) && filesize($incompleteZip) > 1000;
            $hasFilesList = file_exists($filesListFile) && filesize($filesListFile) > 100;
            $hasListingCheckpoint = file_exists($listingCheckpoint);
            $phase = $checkpointData['phase'] ?? 'unknown';
            
            if ($hasIncompleteZip || $hasFilesList || $hasListingCheckpoint || $phase === 'listing') {
                $incompleteBackup = array(
                    'backup_id' => $backupId,
                    'files_added' => $checkpointData['files_added'] ?? 0,
                    'total_files' => $checkpointData['total_files'] ?? 0,
                    'zip_size_mb' => $checkpointData['zip_size_mb'] ?? 0,
                    'last_update' => $checkpointData['last_update'] ?? 'Desconocido',
                    'phase' => $phase,
                    'has_zip' => $hasIncompleteZip,
                    'has_files_list' => $hasFilesList,
                    'has_listing_checkpoint' => $hasListingCheckpoint,
                    'percent' => isset($checkpointData['files_added'], $checkpointData['total_files']) && $checkpointData['total_files'] > 0
                        ? round(($checkpointData['files_added'] / $checkpointData['total_files']) * 100, 1)
                        : 0
                );
            }
        }
    }
}

// Headers JSON
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Retornar estado
echo json_encode(array(
    'is_running' => $isRunning,
    'lock_type' => $lockType,
    'lock_info' => $lockInfo,
    'backup_dir' => $backupDir,
    'incomplete_backup' => $incompleteBackup
));
