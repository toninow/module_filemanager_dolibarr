<?php
// Actualizar archivo de progreso directamente desde el frontend

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
}

// Archivo de log para debug
$debugLogFile = __DIR__ . '/../backups/debug_analyze.log';

/**
 * FUNCIÓN ROBUSTA PARA ENCONTRAR main.inc.php EN CUALQUIER HOSTING
 */
function findMainIncFile($logFile = '') {
    $possiblePaths = [];
    
    $scriptDir = @realpath(__DIR__);
    if ($scriptDir) {
        $possiblePaths = array_merge($possiblePaths, [
            $scriptDir . '/../../../main.inc.php',
            $scriptDir . '/../../../htdocs/main.inc.php',
            $scriptDir . '/../../main.inc.php',
            $scriptDir . '/../../htdocs/main.inc.php',
        ]);
    }
    
    if (isset($_SERVER['DOCUMENT_ROOT'])) {
        $docRoot = @realpath($_SERVER['DOCUMENT_ROOT']);
        if ($docRoot) {
            $possiblePaths = array_merge($possiblePaths, [
                $docRoot . '/main.inc.php',
                $docRoot . '/htdocs/main.inc.php',
            ]);
        }
    }
    
    foreach ($possiblePaths as $path) {
        $realPath = @realpath($path);
        if ($realPath && @file_exists($realPath)) {
            return $realPath;
        }
    }
    
    return null;
}

// Incluir Dolibarr
$mainPath = findMainIncFile($debugLogFile);
if (!$mainPath || !file_exists($mainPath)) {
    echo json_encode(['success' => false, 'message' => 'Error: No se puede cargar Dolibarr.']);
    exit;
}

try {
    ob_start();
    require_once $mainPath;
    $htmlOutput = ob_get_clean();
    
    if (!empty($htmlOutput)) {
        $isHtml = (strpos($htmlOutput, '<html') !== false || strpos($htmlOutput, '<!DOCTYPE') !== false);
        if ($isHtml) {
            echo json_encode(['success' => false, 'message' => 'Sesión expirada.']);
            exit;
        }
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    exit;
}

try {
    // Verificar usuario
    if (!isset($user) || !is_object($user)) {
        echo json_encode(['success' => false, 'message' => 'Usuario no autenticado.']);
        exit;
    }
    
    // Obtener datos del POST
    $files = isset($_POST['files']) ? intval($_POST['files']) : 0;
    $folders = isset($_POST['folders']) ? intval($_POST['folders']) : 0;
    $size = isset($_POST['size']) ? floatval($_POST['size']) : 0;
    
    // Archivo de progreso
    $progressFile = __DIR__ . '/../backups/analysis_progress.json';
    $backupsDir = dirname($progressFile);
    
    @file_put_contents($debugLogFile, date('[Y-m-d H:i:s]') . " 🔵 [UPDATE_PROGRESS] INICIO - files=$files, folders=$folders, size=$size\n", FILE_APPEND);
    @file_put_contents($debugLogFile, date('[Y-m-d H:i:s]') . " 🔵 [UPDATE_PROGRESS] Archivo: $progressFile\n", FILE_APPEND);
    @file_put_contents($debugLogFile, date('[Y-m-d H:i:s]') . " 🔵 [UPDATE_PROGRESS] Directorio: $backupsDir\n", FILE_APPEND);
    
    // Asegurar que el directorio existe
    if (!is_dir($backupsDir)) {
        @mkdir($backupsDir, 0777, true);
        @file_put_contents($debugLogFile, date('[Y-m-d H:i:s]') . " 📁 [UPDATE_PROGRESS] Directorio creado\n", FILE_APPEND);
    }
    
    // Verificar permisos del directorio - FORZAR permisos de escritura
    $currentPerms = substr(sprintf('%o', fileperms($backupsDir)), -4);
    @file_put_contents($debugLogFile, date('[Y-m-d H:i:s]') . " 🔧 [UPDATE_PROGRESS] Permisos actuales del directorio: $currentPerms\n", FILE_APPEND);
    
    if (!is_writable($backupsDir)) {
        @chmod($backupsDir, 0777);
        @file_put_contents($debugLogFile, date('[Y-m-d H:i:s]') . " 🔧 [UPDATE_PROGRESS] Permisos del directorio cambiados a 0777\n", FILE_APPEND);
    }
    
    // Verificar si el directorio es escribible después del cambio
    if (!is_writable($backupsDir)) {
        @file_put_contents($debugLogFile, date('[Y-m-d H:i:s]') . " ❌ [UPDATE_PROGRESS] Directorio NO es escribible después de cambiar permisos\n", FILE_APPEND);
        echo json_encode([
            'success' => false,
            'message' => 'Directorio no escribible: ' . $backupsDir . ' (permisos: ' . substr(sprintf('%o', fileperms($backupsDir)), -4) . ')'
        ]);
        exit;
    }
    
    // Actualizar archivo de progreso
    $currentTime = time();
    $progressData = [
        'current_path' => '',
        'total_files' => $files,
        'total_folders' => $folders,
        'total_size_mb' => $size,
        'recent_files' => [],
        'last_update' => $currentTime
    ];
    
    $jsonData = json_encode($progressData, JSON_PRETTY_PRINT);
    @file_put_contents($debugLogFile, date('[Y-m-d H:i:s]') . " 📝 [UPDATE_PROGRESS] JSON generado, tamaño: " . strlen($jsonData) . " bytes\n", FILE_APPEND);
    
    // Intentar cambiar permisos si el archivo existe
    if (file_exists($progressFile)) {
        $oldPerms = substr(sprintf('%o', fileperms($progressFile)), -4);
        @file_put_contents($debugLogFile, date('[Y-m-d H:i:s]') . " 🔧 [UPDATE_PROGRESS] Permisos actuales del archivo: $oldPerms\n", FILE_APPEND);
        @chmod($progressFile, 0666);
        $newPerms = substr(sprintf('%o', fileperms($progressFile)), -4);
        @file_put_contents($debugLogFile, date('[Y-m-d H:i:s]') . " 🔧 [UPDATE_PROGRESS] Permisos del archivo cambiados a: $newPerms\n", FILE_APPEND);
    }
    
    // Escribir archivo - intentar múltiples métodos
    $result = false;
    
    // Método 1: file_put_contents con LOCK_EX
    @file_put_contents($debugLogFile, date('[Y-m-d H:i:s]') . " 📝 [UPDATE_PROGRESS] Intentando escribir con file_put_contents...\n", FILE_APPEND);
    $result = @file_put_contents($progressFile, $jsonData, LOCK_EX);
    
    if ($result === false) {
        $lastError = error_get_last();
        @file_put_contents($debugLogFile, date('[Y-m-d H:i:s]') . " ⚠️ [UPDATE_PROGRESS] Método 1 falló: " . ($lastError['message'] ?? 'desconocido') . "\n", FILE_APPEND);
        @file_put_contents($debugLogFile, date('[Y-m-d H:i:s]') . " ⚠️ [UPDATE_PROGRESS] Intentando método 2 (fopen)...\n", FILE_APPEND);
        
        // Método 2: fopen + fwrite
        $fp = @fopen($progressFile, 'w');
        if ($fp) {
            @flock($fp, LOCK_EX);
            $result = @fwrite($fp, $jsonData);
            @fflush($fp);
            @flock($fp, LOCK_UN);
            @fclose($fp);
            @file_put_contents($debugLogFile, date('[Y-m-d H:i:s]') . " 📝 [UPDATE_PROGRESS] Método 2 usado, bytes: $result\n", FILE_APPEND);
        } else {
            $lastError = error_get_last();
            @file_put_contents($debugLogFile, date('[Y-m-d H:i:s]') . " ❌ [UPDATE_PROGRESS] Método 2 también falló: " . ($lastError['message'] ?? 'desconocido') . "\n", FILE_APPEND);
        }
    } else {
        @file_put_contents($debugLogFile, date('[Y-m-d H:i:s]') . " ✅ [UPDATE_PROGRESS] Método 1 exitoso, bytes: $result\n", FILE_APPEND);
    }
    
    if ($result !== false) {
        @file_put_contents($debugLogFile, date('[Y-m-d H:i:s]') . " ✅ [UPDATE_PROGRESS] Actualizado desde frontend - files=$files, folders=$folders, size=$size MB, bytes=$result\n", FILE_APPEND);
        clearstatcache(true, $progressFile);
        echo json_encode([
            'success' => true,
            'message' => 'Progreso actualizado correctamente',
            'files' => $files,
            'folders' => $folders,
            'size' => $size
        ]);
    } else {
        $lastError = error_get_last();
        @file_put_contents($debugLogFile, date('[Y-m-d H:i:s]') . " ❌ [UPDATE_PROGRESS] Error escribiendo: " . ($lastError['message'] ?? 'desconocido') . "\n", FILE_APPEND);
        echo json_encode([
            'success' => false,
            'message' => 'Error escribiendo archivo: ' . ($lastError['message'] ?? 'desconocido')
        ]);
    }
    
} catch (Exception $e) {
    @file_put_contents($debugLogFile, date('[Y-m-d H:i:s]') . " ❌ [UPDATE_PROGRESS] Excepción: " . $e->getMessage() . "\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    exit;
}

