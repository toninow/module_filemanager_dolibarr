<?php
/**
 * BACKUP POR CHUNKS - Procesa archivos en lotes para evitar timeouts
 *
 * ⚠️ SOLO LECTURA - NO modifica archivos originales ni base de datos
 * Funciona en cualquier hosting con requests cortos e independientes.
 *
 * Parámetros:
 * - action: 'init' | 'process' | 'finalize'
 * - backup_id: ID único del backup
 * - chunk_size: Cantidad de archivos por chunk
 */

// Configuración de errores
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Headers PRIMERO - antes de cualquier salida
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('Access-Control-Allow-Origin: *');


// Iniciar buffer de salida para capturar cualquier HTML
ob_start();

// ============================================================================
// DETECCIÓN DINÁMICA DE RUTAS - ANTES DE CUALQUIER COSA
// ============================================================================

// Función mejorada para detectar la raíz completa de Dolibarr
function getDolibarrFullRoot() {
    $dolibarrRoot = '';

    // MÉTODO PRIORITARIO: Usar DOL_DOCUMENT_ROOT si está disponible (desde autenticación)
    if (defined('DOL_DOCUMENT_ROOT') && !empty(DOL_DOCUMENT_ROOT)) {
        $testPath = @realpath(DOL_DOCUMENT_ROOT);
        if ($testPath && @is_dir($testPath) && @file_exists($testPath . '/main.inc.php')) {
            return $testPath;
        }
    }

    // MÉTODO 2: Desde la ubicación del script hacia arriba (método directo)
    $scriptDir = @realpath(__DIR__);
    if ($scriptDir) {
        // Ir directamente 3 niveles arriba (custom/filemanager/scripts -> raíz)
        $candidateRoot = @realpath($scriptDir . '/../../../');
        if ($candidateRoot && @is_dir($candidateRoot) && @file_exists($candidateRoot . '/main.inc.php')) {
            return $candidateRoot;
        }

        // También probar con htdocs
        $candidateRootHtdocs = @realpath($scriptDir . '/../../../htdocs');
        if ($candidateRootHtdocs && @is_dir($candidateRootHtdocs) && @file_exists($candidateRootHtdocs . '/main.inc.php')) {
            return $candidateRootHtdocs;
        }
    }

    // MÉTODO 3: Buscar recursivamente main.inc.php desde el directorio del script
    $currentDir = @realpath(__DIR__);
    $maxLevels = 8; // Límite de seguridad
    $level = 0;
    while ($currentDir && $currentDir !== '/' && $currentDir !== dirname($currentDir) && $level < $maxLevels) {
        // Probar el directorio actual
        if (@file_exists($currentDir . '/main.inc.php')) {
            return $currentDir;
        }
        // Probar htdocs si existe
        if (@is_dir($currentDir . '/htdocs') && @file_exists($currentDir . '/htdocs/main.inc.php')) {
            return $currentDir . '/htdocs';
        }
        $currentDir = dirname($currentDir);
        $level++;
    }

    // MÉTODO 4: Fallback a DOCUMENT_ROOT
    if (isset($_SERVER['DOCUMENT_ROOT'])) {
        $docRoot = @realpath($_SERVER['DOCUMENT_ROOT']);
        if ($docRoot && @file_exists($docRoot . '/main.inc.php')) {
            return $docRoot;
        }
    }

    return $dolibarrRoot;
}

// Detectar la raíz completa de Dolibarr ANTES de cualquier operación
$dolibarrRoot = getDolibarrFullRoot();

// Función para limpiar TODOS los archivos de un backup cuando hay error
function cleanupBackupFilesOnError($backupId, $backupDir) {
    if (empty($backupId)) {
        return;
    }
    
    $deleted = [];
    $errors = [];
    
    // 1. Archivos de progreso y estado
    $progressPatterns = [
        'progress_' . $backupId . '.txt',
        'backup_progress_' . $backupId . '.txt',
        'backup_progress_' . $backupId . '.json',
        'backup_state_' . $backupId . '.json',
        'state_' . $backupId . '.json',
        'chunk_state_' . $backupId . '.json', // Estado del chunk
    ];
    foreach ($progressPatterns as $pattern) {
        $file = $backupDir . '/' . $pattern;
        if (file_exists($file)) {
            if (@unlink($file)) {
                $deleted[] = $pattern;
            }
        }
    }
    
    // 2. Archivos de log
    $logPatterns = [
        'log_' . $backupId . '.txt',
        'backup_log_' . $backupId . '.txt',
        'backup_' . $backupId . '.log', // Log principal del backup
    ];
    foreach ($logPatterns as $pattern) {
        $file = $backupDir . '/' . $pattern;
        if (file_exists($file)) {
            if (@unlink($file)) {
                $deleted[] = $pattern;
            }
        }
    }
    
    // 3. Archivos ZIP (chunks y final)
    $zipPatterns = [
        'files_dolibarr_' . $backupId . '.zip',
        'chunk_' . $backupId . '_*.zip',
    ];
    foreach ($zipPatterns as $pattern) {
        if (strpos($pattern, '*') !== false) {
            // Usar glob para patrones con wildcard
            $files = glob($backupDir . '/' . $pattern);
            if ($files) {
                foreach ($files as $file) {
                    if (is_file($file)) {
                        if (@unlink($file)) {
                            $deleted[] = basename($file);
                        }
                    }
                }
            }
        } else {
            $file = $backupDir . '/' . $pattern;
            if (file_exists($file)) {
                if (@unlink($file)) {
                    $deleted[] = $pattern;
                }
            }
        }
    }
    
    // 4. Lista de archivos
    $filesListFile = $backupDir . '/filelist_' . $backupId . '.json';
    if (file_exists($filesListFile)) {
        if (@unlink($filesListFile)) {
            $deleted[] = 'filelist_' . $backupId . '.json';
        }
    }
    
    // 5. Cualquier otro archivo con el backup_id
    $allRelatedFiles = glob($backupDir . '/*' . $backupId . '*');
    if ($allRelatedFiles) {
        foreach ($allRelatedFiles as $file) {
            if (is_file($file)) {
                $basename = basename($file);
                if (!in_array($basename, $deleted)) {
                    // NO eliminar backups finales completados (solo temporales)
                    if (preg_match('/^files_dolibarr_\d\{14\}\.zip$/', $basename)) {
                        // Verificar si el ZIP está completo (más de 100MB sugiere que está completo)
                        $fileSize = @filesize($file);
                        if ($fileSize > 100 * 1024 * 1024) {
                            continue; // No eliminar backups completados grandes
                        }
                    }
                    if (@unlink($file)) {
                        $deleted[] = $basename;
                    }
                }
            }
        }
    }
    
    return ['deleted' => $deleted, 'count' => count($deleted)];
}

// Función helper para limpiar HTML y devolver JSON
function cleanOutputAndJson($data, $backupId = null, $backupDir = null) {
    // Si hay error y tenemos backup_id, limpiar archivos del backup
    if (isset($data['success']) && $data['success'] === false && !empty($backupId) && !empty($backupDir)) {
        // NO limpiar si es cancelación explícita (el usuario lo hizo a propósito)
        if (!isset($data['cancelled']) || $data['cancelled'] !== true) {
            $cleanupResult = cleanupBackupFilesOnError($backupId, $backupDir);
            $data['cleanup'] = $cleanupResult;
            $data['cleanup_message'] = 'Archivos del backup eliminados automáticamente debido al error';
        }
    }
    
    // Deshabilitar cualquier salida de errores
    @ini_set('display_errors', 0);
    
    // Limpiar TODO el buffer de salida (múltiples niveles)
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    
    // Asegurar headers JSON (sobrescribir cualquier header previo)
    if (!headers_sent()) {
        @header_remove(); // Limpiar headers previos
        header('Content-Type: application/json; charset=utf-8', true);
        header('Cache-Control: no-cache, must-revalidate', true);
        header('Access-Control-Allow-Origin: *', true);
    }
    
    // Devolver JSON (sin errores de encoding)
    $json = @json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) {
        // Si falla el encoding, devolver error simple
        $json = json_encode(['success' => false, 'error' => 'Error al generar respuesta JSON']);
    }
    echo $json;
    exit;
}

// OPTIMIZADO PARA MÁXIMA VELOCIDAD - límites adaptativos según entorno
// NO establecer límites altos aquí - se ajustarán después según detección de entorno
// Los límites se establecerán después de detectar si es entorno restringido
@ignore_user_abort(false); // Permitir detección de desconexión del usuario

// Desactivar compresión que puede causar problemas
@ini_set('zlib.output_compression', 'Off');

// Log de inicio para debug - USANDO RUTA DINÁMICA
$moduleDir = dirname(__DIR__); // Subir un nivel desde scripts/
$debugLogFile = $moduleDir . '/backups/debug_chunk.log';
$serverInfo = "Host: " . ($_SERVER['HTTP_HOST'] ?? 'unknown') . 
              " | PHP: " . phpversion() . 
              " | Memory: " . ini_get('memory_limit') . 
              " | MaxExec: " . ini_get('max_execution_time');
@file_put_contents($debugLogFile, date('[Y-m-d H:i:s]') . " INICIO - Action: " . ($_GET['action'] ?? 'none') . " | " . $serverInfo . "\n", FILE_APPEND);

// Registrar errores fatales - SIEMPRE devolver JSON
register_shutdown_function(function() use ($debugLogFile) {
    $error = error_get_last();
    
    // Limpiar TODO el buffer de salida
    while (ob_get_level() > 0) {
        $output = ob_get_clean();
        
        // Si hay HTML en la salida, es un error
        if (!empty($output) && (strpos($output, '<html') !== false || strpos($output, '<!DOCTYPE') !== false || strpos($output, '<body') !== false)) {
            if (!headers_sent()) {
                header('Content-Type: application/json; charset=utf-8', true);
            }
            echo json_encode(['success' => false, 'error' => 'Sesión expirada o error de autenticación. Recarga la página e inicia sesión.']);
            exit;
        }
    }
    
    if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE])) {
        @file_put_contents($debugLogFile, date('[Y-m-d H:i:s]') . " FATAL ERROR: " . $error['message'] . " en " . $error['file'] . ":" . $error['line'] . "\n", FILE_APPEND);
        
        // SIEMPRE enviar JSON, incluso si hay error fatal
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8', true);
            header('Cache-Control: no-cache', true);
        }
        
        $errorMsg = 'Error del servidor';
        if (strpos($error['message'], 'Maximum execution time') !== false) {
            $errorMsg = 'Timeout: El proceso tardó demasiado. El servidor tiene un límite de 30s. Contacta al administrador para aumentar el límite.';
        } elseif (strpos($error['message'], 'memory') !== false) {
            $errorMsg = 'Memoria insuficiente. Contacta al administrador.';
        } else {
            $errorMsg = 'Error: ' . substr($error['message'], 0, 200); // Limitar longitud
        }
        
        echo json_encode(['success' => false, 'error' => $errorMsg, 'fatal' => true]);
        exit;
    }
});

// ========== DETECCIÓN DE ENTORNO RESTRINGIDO ==========
function return_bytes($val) {
    if ($val == -1 || empty($val)) return -1;
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }
    return $val;
}

$memoryLimit = ini_get('memory_limit');
$memoryBytes = return_bytes($memoryLimit);
$maxExecTime = ini_get('max_execution_time');

// Detectar si es localhost o producción PRIMERO (tiene prioridad)
$isLocalhost = (strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false) || 
               (($_SERVER['SERVER_ADDR'] ?? '') === '127.0.0.1') ||
               (($_SERVER['REMOTE_ADDR'] ?? '') === '127.0.0.1') ||
               (($_SERVER['REMOTE_ADDR'] ?? '') === '::1');

// Detectar entorno ULTRA-restringido (128MB + 30s) SOLO en producción
// Localhost NUNCA se considera ultra-restringido
$isUltraRestricted = !$isLocalhost && ($memoryBytes <= 128 * 1024 * 1024 && $maxExecTime <= 30);

// Ajustar límites según entorno
if ($isLocalhost) {
    // LOCALHOST: Límites generosos para desarrollo/pruebas
    $maxTimePerRequest = 120;
    @set_time_limit(360);
    @ini_set('max_execution_time', 360);
    @ini_set('memory_limit', '512M');
} elseif ($isUltraRestricted) {
    // ENTORNO ULTRA-RESTRINGIDO: Límites muy conservadores
    $maxTimePerRequest = 15; // Dejar más margen (30s - 15s = 15s)
    @set_time_limit(18);
    @ini_set('max_execution_time', 18);
    // NO intentar cambiar memory_limit - el hosting lo controla estrictamente
} else {
    // Entorno normal de producción
    $maxTimePerRequest = 20;
    @set_time_limit(20);
    @ini_set('max_execution_time', 20);
}

$startRequestTime = time();

// OPTIMIZACIÓN: Solo cargar Dolibarr en INIT, luego usar token de sesión
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$backupId = $_GET['backup_id'] ?? $_POST['backup_id'] ?? '';

// Archivo de token de sesión para evitar conexiones a DB - USANDO RUTA DINÁMICA
$tokenFile = $moduleDir . '/backups/.auth_token_' . session_id() . '.json';

// Solo cargar Dolibarr completo en INIT o si no hay token válido
// PROTECCIÓN: No cargar Dolibarr durante finalización para no afectarlo
$needFullAuth = ($action === 'init' || $action === '' || !file_exists($tokenFile));
if ($action === 'finalize' && file_exists($tokenFile)) {
    // En finalización, intentar usar token primero para evitar cargar Dolibarr
    $tokenData = @json_decode(@file_get_contents($tokenFile), true);
    if ($tokenData && $tokenData['expires'] > time()) {
        $needFullAuth = false; // No cargar Dolibarr completo
    }
}

if ($needFullAuth) {
    // Capturar cualquier salida HTML antes de cargar Dolibarr
    ob_start();

    // Incluir Dolibarr SOLO cuando es necesario - USANDO DETECCIÓN DINÁMICA
    $mainPath = $dolibarrRoot . '/main.inc.php';
    if (!$dolibarrRoot || !@file_exists($mainPath)) {
        ob_end_clean();
        cleanOutputAndJson(['success' => false, 'error' => 'No se puede detectar/cargar Dolibarr. Raíz detectada: ' . ($dolibarrRoot ?: 'NINGUNA')]);
    }
    
    // Cargar Dolibarr capturando cualquier salida HTML
    require_once $mainPath;
    
    // Limpiar cualquier salida HTML que haya generado Dolibarr
    $htmlOutput = ob_get_clean();
    
    // Si hay salida HTML, es un error (login o error de Dolibarr)
    if (!empty($htmlOutput) && (strpos($htmlOutput, '<html') !== false || strpos($htmlOutput, '<!DOCTYPE') !== false)) {
        cleanOutputAndJson(['success' => false, 'error' => 'Sesión expirada. Por favor, recarga la página e inicia sesión.']);
    }
    
    // Verificar usuario
    if (!isset($user) || !is_object($user) || empty($user->admin)) {
        cleanOutputAndJson(['success' => false, 'error' => 'Acceso denegado. Se requiere permisos de administrador.']);
    }
    
    // Guardar token de autenticación para requests siguientes (válido 2 horas)
    $tokenData = [
        'user_id' => $user->id,
        'user_login' => $user->login,
        'created' => time(),
        'expires' => time() + 7200,
        'dolibarr_root' => DOL_DOCUMENT_ROOT
    ];
    @file_put_contents($tokenFile, json_encode($tokenData));
    @chmod($tokenFile, 0600);
    
    $dolibarrRootFromToken = DOL_DOCUMENT_ROOT;
} else {
    // Usar token existente - SIN conexión a base de datos
    $tokenData = @json_decode(@file_get_contents($tokenFile), true);
    
    if (!$tokenData || $tokenData['expires'] < time()) {
        @unlink($tokenFile);
        cleanOutputAndJson(['success' => false, 'error' => 'Sesión expirada. Recarga la página.']);
    }
    
    $dolibarrRootFromToken = $tokenData['dolibarr_root'];
    
    // NO cargamos main.inc.php - evitamos conexión a DB
}

// Obtener parámetros
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$backupId = $_GET['backup_id'] ?? $_POST['backup_id'] ?? '';

// OPTIMIZADO PARA MÁXIMA VELOCIDAD - chunks adaptativos según entorno
// Chunk size dinámico basado en el entorno y rendimiento
if ($isLocalhost) {
    // LOCALHOST: Chunks muy grandes para desarrollo rápido
    $defaultChunkSize = 5000;
    $maxChunk = 15000;
    $minChunk = 1000;
} elseif ($isUltraRestricted) {
    // ENTORNO RESTRINGIDO (128MB/30s): Balance entre velocidad y seguridad
    // - La mayoría de archivos son pequeños (PDFs, imágenes) = ~0.01s cada uno
    // - 300 archivos × 0.01s = 3s de procesamiento
    // - 5s para cierre de ZIP
    // - Total: ~8-10s por chunk (margen de 20s)
    $defaultChunkSize = 300;
    $maxChunk = 400; // Máximo 400 archivos (seguro)
    $minChunk = 150; // Mínimo 150 archivos
} else {
    // Entorno normal de producción: chunks grandes
    $defaultChunkSize = 1000;
    $maxChunk = 2000;
    $minChunk = 500;
}
$chunkSize = intval($_GET['chunk_size'] ?? $_POST['chunk_size'] ?? $defaultChunkSize);
$chunkSize = max($minChunk, min($maxChunk, $chunkSize));

// Directorios - Detectar dinámicamente desde módulo
$backupDir = $moduleDir . '/backups';
$backupDir = realpath($backupDir) ?: $backupDir;

// Si no existe, intentar crearlo
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0777, true);
    // Verificar nuevamente después de crear
    $backupDir = realpath($backupDir) ?: $backupDir;
}

// Verificar permisos
if (!is_dir($backupDir)) {
    cleanOutputAndJson([
        'success' => false,
        'error' => 'No se pueden crear los archivos necesarios para el backup. Contacta al administrador del servidor para configurar los permisos adecuados.'
    ]);
}

if (!is_writable($backupDir)) {
    // Intentar cambiar permisos de manera más agresiva
    $currentPerms = fileperms($backupDir);
    $currentOwner = fileowner($backupDir);
    $currentGroup = filegroup($backupDir);
    
    // Intentar cambiar permisos
    @chmod($backupDir, 0777);
    
    // Intentar cambiar owner si tenemos permisos (solo funciona si somos root o el owner actual)
    if (function_exists('posix_geteuid') && posix_geteuid() == 0) {
        // Somos root, podemos cambiar owner
        @chown($backupDir, 'www-data');
        @chgrp($backupDir, 'www-data');
    } elseif ($currentOwner == getmyuid()) {
        // Somos el owner actual, podemos cambiar permisos pero no owner
        @chmod($backupDir, 0777);
    }
    
    // Verificar nuevamente
    if (!is_writable($backupDir)) {
        $ownerName = 'unknown';
        if (function_exists('posix_getpwuid')) {
            $ownerInfo = @posix_getpwuid($currentOwner);
            $ownerName = $ownerInfo ? $ownerInfo['name'] : "uid:$currentOwner";
        }
        
        $groupName = 'unknown';
        if (function_exists('posix_getgrgid')) {
            $groupInfo = @posix_getgrgid($currentGroup);
            $groupName = $groupInfo ? $groupInfo['name'] : "gid:$currentGroup";
        }
        
        cleanOutputAndJson([
            'success' => false,
            'error' => 'No hay permisos suficientes para guardar los archivos del backup. Contacta al administrador del servidor para configurar los permisos adecuados.'
        ]);
    }
}

// Archivos de estado
$stateFile = $backupDir . '/chunk_state_' . $backupId . '.json';
// OPTIMIZACIÓN: Usar directamente el nombre final para evitar rename/copy al final
$zipFile = $backupDir . '/files_dolibarr_' . $backupId . '.zip';
$finalZipFile = $zipFile; // Mismo archivo, no hay que renombrar
$logFile = $backupDir . '/backup_' . $backupId . '.log';
$progressFile = $backupDir . '/backup_progress_' . $backupId . '.json';

// Función para log con soporte UTF-8 y niveles
function chunkLog($msg, $logFile, $level = 'INFO') {
    // Solo log básico: ERROR, WARN, INFO
    // Omitir DEBUG para mejorar rendimiento
    if ($level === 'DEBUG') {
        return; // No escribir logs de debug
    }

    $timestamp = date('[H:i:s]');
    $levelPrefix = '';
    switch ($level) {
        case 'ERROR': $levelPrefix = '[ERROR] '; break;
        case 'WARN': $levelPrefix = '[WARN] '; break;
        case 'INFO': $levelPrefix = '[INFO] '; break;
    }

    // Asegurar codificación UTF-8
    if (!mb_check_encoding($msg, 'UTF-8')) {
        $msg = mb_convert_encoding($msg, 'UTF-8', 'auto');
    }
    @file_put_contents($logFile, "$timestamp $levelPrefix$msg\n", FILE_APPEND | LOCK_EX);
}

// Función para actualizar progreso
function updateProgress($data, $progressFile) {
    @file_put_contents($progressFile, json_encode($data), LOCK_EX);
}

/**
 * FUNCIÓN ROBUSTA PARA DETECTAR DOL_DOCUMENT_ROOT EN CUALQUIER HOSTING
 * Prueba múltiples métodos para asegurar compatibilidad total
 */
function detectDolibarrRoot($dolibarrRootFromToken = '', $needFullAuth = false, $logFile = '') {
    $dolibarrRoot = '';
    $methodsTried = [];
    
    // Método 1: Desde el token de sesión (NO requiere DB) - MÁS RÁPIDO
    if (!empty($dolibarrRootFromToken)) {
        $testPath = @realpath($dolibarrRootFromToken);
        if ($testPath && @is_dir($testPath) && @file_exists($testPath . '/main.inc.php')) {
            $dolibarrRoot = $testPath;
            if ($logFile) chunkLog("Root detectado correctamente", $logFile, 'DEBUG');
            return $dolibarrRoot;
        }
        $methodsTried[] = "Token: " . ($testPath ?: 'no válido');
    }
    
    // Método 2: Variable global (solo si cargamos Dolibarr)
    if (empty($dolibarrRoot) && $needFullAuth) {
        global $dolibarr_main_document_root;
        if (!empty($dolibarr_main_document_root)) {
            $testPath = @realpath($dolibarr_main_document_root);
            if ($testPath && @is_dir($testPath) && @file_exists($testPath . '/main.inc.php')) {
                $dolibarrRoot = $testPath;
                if ($logFile) chunkLog("Root detectado correctamente", $logFile, 'DEBUG');
                return $dolibarrRoot;
            }
            $methodsTried[] = "Global: " . ($testPath ?: 'no válido');
        }
    }
    
    // Método 3: Constante DOL_DOCUMENT_ROOT
    if (empty($dolibarrRoot) && defined('DOL_DOCUMENT_ROOT')) {
        $testPath = @realpath(DOL_DOCUMENT_ROOT);
        if ($testPath && @is_dir($testPath) && @file_exists($testPath . '/main.inc.php')) {
            $dolibarrRoot = $testPath;
            if ($logFile) chunkLog("Root detectado correctamente", $logFile, 'DEBUG');
            return $dolibarrRoot;
        }
        $methodsTried[] = "Constante: " . ($testPath ?: 'no válido');
    }
    
    // Método 4: Desde la ubicación del script (más robusto)
    if (empty($dolibarrRoot)) {
        $scriptDir = @realpath(__DIR__);
        if ($scriptDir) {
            // Intentar diferentes niveles: ../../.., ../.., .., y también con htdocs
            $possiblePaths = [
                $scriptDir . '/../../../htdocs',  // custom/filemanager/scripts -> htdocs
                $scriptDir . '/../../htdocs',      // custom/filemanager/scripts -> htdocs (alternativa)
                $scriptDir . '/../../..',          // custom/filemanager/scripts -> raíz
                $scriptDir . '/../..',             // custom/filemanager/scripts -> custom
                dirname(dirname(dirname($scriptDir))) . '/htdocs',
                dirname(dirname(dirname($scriptDir))),
                dirname(dirname($scriptDir))
            ];
            
            foreach ($possiblePaths as $possiblePath) {
                $testPath = @realpath($possiblePath);
                if ($testPath && @is_dir($testPath) && @file_exists($testPath . '/main.inc.php')) {
                    $dolibarrRoot = $testPath;
                    if ($logFile) chunkLog("Root detectado correctamente", $logFile, 'DEBUG');
                    return $dolibarrRoot;
                }
            }
            $methodsTried[] = "Script paths: " . count($possiblePaths) . " intentados";
        }
    }
    
    // Método 5: Buscar desde el directorio actual hacia arriba (recursivo)
    if (empty($dolibarrRoot)) {
        $currentDir = @realpath(__DIR__);
        $maxLevels = 10; // Límite de seguridad
        $level = 0;
        while ($currentDir && $currentDir !== '/' && $currentDir !== dirname($currentDir) && $level < $maxLevels) {
            // Probar el directorio actual
            if (@file_exists($currentDir . '/main.inc.php')) {
                $dolibarrRoot = $currentDir;
                if ($logFile) chunkLog("Root detectado correctamente", $logFile, 'DEBUG');
                return $dolibarrRoot;
            }
            // También probar subdirectorio htdocs si existe
            if (@is_dir($currentDir . '/htdocs') && @file_exists($currentDir . '/htdocs/main.inc.php')) {
                $dolibarrRoot = $currentDir . '/htdocs';
                if ($logFile) chunkLog("Root detectado correctamente", $logFile, 'DEBUG');
                return $dolibarrRoot;
            }
            $currentDir = dirname($currentDir);
            $level++;
        }
        $methodsTried[] = "Recursivo: $level niveles";
    }
    
    // Método 6: Desde $_SERVER['DOCUMENT_ROOT'] (común en hostings)
    if (empty($dolibarrRoot) && isset($_SERVER['DOCUMENT_ROOT'])) {
        $docRoot = @realpath($_SERVER['DOCUMENT_ROOT']);
        if ($docRoot) {
            // Probar diferentes subdirectorios comunes
            $commonPaths = [
                $docRoot,
                $docRoot . '/htdocs',
                $docRoot . '/public_html',
                $docRoot . '/www',
                $docRoot . '/web',
                dirname($docRoot),
                dirname($docRoot) . '/htdocs'
            ];
            
            foreach ($commonPaths as $commonPath) {
                $testPath = @realpath($commonPath);
                if ($testPath && @is_dir($testPath) && @file_exists($testPath . '/main.inc.php')) {
                    $dolibarrRoot = $testPath;
                    if ($logFile) chunkLog("Root detectado correctamente", $logFile, 'DEBUG');
                    return $dolibarrRoot;
                }
            }
            $methodsTried[] = "DOCUMENT_ROOT: " . ($docRoot ?: 'no disponible');
        }
    }
    
    // Método 7: Desde $_SERVER['SCRIPT_FILENAME'] (ruta del script actual)
    if (empty($dolibarrRoot) && isset($_SERVER['SCRIPT_FILENAME'])) {
        $scriptFile = @realpath($_SERVER['SCRIPT_FILENAME']);
        if ($scriptFile) {
            $scriptDir = dirname($scriptFile);
            $maxLevels = 10;
            $level = 0;
            while ($scriptDir && $scriptDir !== '/' && $level < $maxLevels) {
                if (@file_exists($scriptDir . '/main.inc.php')) {
                    $dolibarrRoot = $scriptDir;
                    if ($logFile) chunkLog("Root detectado correctamente", $logFile, 'DEBUG');
                    return $dolibarrRoot;
                }
                if (@is_dir($scriptDir . '/htdocs') && @file_exists($scriptDir . '/htdocs/main.inc.php')) {
                    $dolibarrRoot = $scriptDir . '/htdocs';
                    if ($logFile) chunkLog("Root detectado correctamente", $logFile, 'DEBUG');
                    return $dolibarrRoot;
                }
                $scriptDir = dirname($scriptDir);
                $level++;
            }
            $methodsTried[] = "SCRIPT_FILENAME: $level niveles";
        }
    }
    
    // Si no se encontró, log conciso del error
    if (empty($dolibarrRoot) && $logFile) {
        chunkLog("No se pudo detectar DOL_DOCUMENT_ROOT automáticamente", $logFile, 'ERROR');
    }
    
    return $dolibarrRoot;
}

// Si aún no tenemos raíz detectada, usar la función robusta
// NOTA: Ya intentamos detectar antes, pero aquí podemos usar token/DB si está disponible
if (empty($dolibarrRoot)) {
    $dolibarrRoot = detectDolibarrRoot(
        isset($dolibarrRootFromToken) ? $dolibarrRootFromToken : '',
        isset($needFullAuth) ? $needFullAuth : false,
        '' // logFile se definirá después, por ahora vacío
    );
}

// ============================================================================
// EXCLUSIONES - Backups, papelera, archivos eliminados, base de datos
// ============================================================================
$excludeDirs = [
    // Backups del filemanager
    $dolibarrRoot . '/custom/filemanager/backups',
    // Papelera y archivos eliminados del filemanager
    $dolibarrRoot . '/custom/filemanager/Papelera',
    $dolibarrRoot . '/custom/filemanager/deletedfiles',
    $dolibarrRoot . '/documents/mpfilemanager/trash',
    // Base de datos MySQL (no incluir en backup de archivos)
    $dolibarrRoot . '/documents/admin/backup',
    $dolibarrRoot . '/documents/backup',
];

// Archivos específicos a excluir (backups antiguos conocidos)
$excludeFiles = [];

// Patrones por ruta - archivos temporales del filemanager
$excludePathPatterns = [];

// Excluir archivos grandes y de base de datos
$excludePatterns = [
    '*.zip' => 100 * 1024 * 1024,   // ZIPs > 100MB (backups antiguos)
    '*.sql' => 10 * 1024 * 1024,    // SQL > 10MB (dumps muy grandes)
    '*.sql.gz' => 10 * 1024 * 1024, // SQL comprimidos > 10MB
    '*.sql.bz2' => 10 * 1024 * 1024,// SQL comprimidos > 10MB
    '*.dump' => 10 * 1024 * 1024,   // Dumps > 10MB
    '*.tar' => 200 * 1024 * 1024,   // TAR > 200MB
    '*.tar.gz' => 200 * 1024 * 1024,
    '*.tgz' => 200 * 1024 * 1024,
    '*.gz' => 200 * 1024 * 1024,
];

// Función para verificar si un archivo debe ser excluido por tamaño/patrón
function shouldExcludeFile($filePath, $excludeFiles, $excludePatterns, $excludePathPatterns = []) {
    // Verificar exclusiones específicas por ruta completa
    foreach ($excludeFiles as $excludeFile) {
        if ($filePath === $excludeFile) {
            return true;
        }
    }
    
    $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $filename = basename($filePath);
    
    // Verificar patrones por ruta (ej: cualquier .zip dentro de /custom/filemanager/)
    foreach ($excludePathPatterns as $pathPrefix => $patterns) {
        if (strpos($filePath, $pathPrefix) === 0) {
            // Este archivo está dentro de una ruta con exclusiones específicas
            foreach ($patterns as $pattern) {
                // Convertir patrón glob simple (*.zip, chunk_*.json) a verificación
                if (strpos($pattern, '*') === 0) {
                    // Patrón tipo *.ext
                    $extPattern = substr($pattern, 2); // Quitar "*."
                    if ($extension === $extPattern) {
                        return true;
                    }
                } elseif (strpos($pattern, '*') !== false) {
                    // Patrón tipo prefix_*.ext - convertir a regex
                    $regex = '/^' . str_replace(['*', '.'], ['.*', '\\.'], $pattern) . '$/i';
                    if (preg_match($regex, $filename)) {
                        return true;
                    }
                } elseif ($filename === $pattern) {
                    // Coincidencia exacta
                    return true;
                }
            }
        }
    }
    
    // Verificar patrones por extensión y tamaño (para archivos muy grandes)
    foreach ($excludePatterns as $pattern => $maxSize) {
        // Convertir patrón glob simple a regex
        $patternExt = str_replace(['*.', '*'], ['', '.*'], $pattern);
        if ($extension === $patternExt || preg_match('/^' . str_replace('.', '\\.', $patternExt) . '$/', $extension)) {
            // Verificar tamaño solo si el archivo existe
            if (file_exists($filePath)) {
                $fileSize = @filesize($filePath);
                if ($fileSize !== false && $fileSize > $maxSize) {
                    return true;
                }
            }
        }
    }
    
    return false;
}

function getZipErrorMessage($errorCode) {
    $messages = [
        ZipArchive::ER_OK => 'Sin error',
        ZipArchive::ER_MULTIDISK => 'Multi-disco no soportado',
        ZipArchive::ER_RENAME => 'Error renombrando archivo temporal',
        ZipArchive::ER_CLOSE => 'Error cerrando archivo',
        ZipArchive::ER_SEEK => 'Error de búsqueda',
        ZipArchive::ER_READ => 'Error de lectura',
        ZipArchive::ER_WRITE => 'Error de escritura',
        ZipArchive::ER_CRC => 'Error CRC',
        ZipArchive::ER_ZIPCLOSED => 'Archivo ZIP cerrado',
        ZipArchive::ER_NOENT => 'Archivo no existe',
        ZipArchive::ER_EXISTS => 'Archivo ya existe',
        ZipArchive::ER_OPEN => 'No se puede abrir archivo',
        ZipArchive::ER_TMPOPEN => 'Error creando archivo temporal',
        ZipArchive::ER_ZLIB => 'Error ZLIB',
        ZipArchive::ER_MEMORY => 'Error de memoria',
        ZipArchive::ER_CHANGED => 'Archivo cambió mientras se leía',
        ZipArchive::ER_COMPNOTSUPP => 'Compresión no soportada',
        ZipArchive::ER_EOF => 'Final del archivo inesperado',
        ZipArchive::ER_INVAL => 'Argumento inválido',
        ZipArchive::ER_NOZIP => 'No es un archivo ZIP',
        ZipArchive::ER_INTERNAL => 'Error interno',
        ZipArchive::ER_INCONS => 'Archivo ZIP inconsistente',
        ZipArchive::ER_REMOVE => 'No se puede remover archivo',
        ZipArchive::ER_DELETED => 'Archivo borrado'
    ];
    return $messages[$errorCode] ?? "Error desconocido ($errorCode)";
}

// ============================================================
// ACCIÓN: INIT - Listar archivos y preparar
// ============================================================
chunkLog("🚀 ACCIÓN INIT INICIADA - Action: $action, BackupID: $backupId", $logFile);

if ($action === 'init' || $action === 'continue_listing') {
    chunkLog("📋 PROCESANDO ACTION: " . ($action === 'init' ? 'INIT' : 'CONTINUE_LISTING'), $logFile);

    if (empty($backupId)) {
        $backupId = date('YmdHis');
        chunkLog("🆔 BackupID generado: $backupId", $logFile);
        $stateFile = $backupDir . '/chunk_state_' . $backupId . '.json';
        // OPTIMIZACIÓN: Usar directamente el nombre final para evitar rename/copy
        $zipFile = $backupDir . '/files_dolibarr_' . $backupId . '.zip';
        $finalZipFile = $zipFile; // Mismo archivo, no hay que renombrar
        $logFile = $backupDir . '/backup_' . $backupId . '.log';
        $progressFile = $backupDir . '/backup_progress_' . $backupId . '.json';
    }
    
    // Verificar si hay un listado parcial para continuar
    $filesListFile = $backupDir . '/filelist_' . $backupId . '.json';
    $continueListing = false;
    $existingFiles = [];
    
    if ($action === 'continue_listing' && file_exists($stateFile)) {
        $existingState = @json_decode(@file_get_contents($stateFile), true);
        if ($existingState && isset($existingState['list_incomplete']) && $existingState['list_incomplete']) {
            $continueListing = true;
            $existingFiles = $existingState['files'] ?? [];
            
            chunkLog("Continuando listado de archivos - Backup ID: $backupId", $logFile, 'INFO');
        }
    }
    
    if (!$continueListing) {
        chunkLog("🚀 Iniciando backup por partes - ID: $backupId, Parte: $chunkSize archivos", $logFile, 'INFO');
    }
    
    // Verificar que la ruta es válida y tiene main.inc.php
    // Si no se detectó, intentar una vez más con el logFile disponible para mejor debugging
    if (empty($dolibarrRoot) || !@is_dir($dolibarrRoot) || !@file_exists($dolibarrRoot . '/main.inc.php')) {
        // Intentar detectar nuevamente con logFile disponible para mejor debugging
        if (empty($dolibarrRoot)) {
            chunkLog("⚠️ Root no detectado en primer intento, reintentando con logging...", $logFile);
            $dolibarrRoot = detectDolibarrRoot(
                isset($dolibarrRootFromToken) ? $dolibarrRootFromToken : '', 
                isset($needFullAuth) ? $needFullAuth : false, 
                $logFile
            );
        }
        
        // Si aún no se encontró, error detallado
        if (empty($dolibarrRoot) || !@is_dir($dolibarrRoot) || !@file_exists($dolibarrRoot . '/main.inc.php')) {
            $errorMsg = "Ruta de Dolibarr no válida o main.inc.php no encontrado";
            $errorDetails = "Root: " . ($dolibarrRoot ?: 'VACÍO') . " | is_dir: " . (@is_dir($dolibarrRoot) ? 'SÍ' : 'NO');
            if ($dolibarrRoot) {
                $errorDetails .= " | main.inc.php existe: " . (@file_exists($dolibarrRoot . '/main.inc.php') ? 'SÍ' : 'NO');
            }
            chunkLog("ERROR: $errorMsg - $errorDetails", $logFile, 'ERROR');
            cleanOutputAndJson(['success' => false, 'error' => $errorMsg . ' (' . $errorDetails . ')']);
        } else {
            chunkLog("Root detectado correctamente", $logFile, 'DEBUG');
        }
    }
    
    // Usar la detección de localhost ya realizada arriba
    
    // Límites dinámicos según entorno
    if ($isUltraRestricted) {
        // ENTORNO ULTRA-RESTRINGIDO: Optimizado para 128MB + 30s máximo
        // Con ~1500 archivos/s, 12s = ~18k archivos por pasada
        // Múltiples pasadas necesarias pero más estables
        $maxExecutionTime = 15; // Sin margen extra (límite real 15s)
        $timeLimit = 12; // 12s para listado (dentro del límite de 15s)
    } else {
        // Entorno normal
        $maxExecutionTime = $isLocalhost ? 320 : 20;
        $timeLimit = $isLocalhost ? 300 : 15;
    }
    
    // Verificar si ya existe un estado (para resumir o continuar listado)
    if (file_exists($stateFile)) {
        $existingState = @json_decode(@file_get_contents($stateFile), true);
        
        // Si el listado está incompleto, continuar desde donde quedó
        if ($existingState && isset($existingState['list_incomplete']) && $existingState['list_incomplete'] === true) {
            chunkLog("Continuando listado desde estado incompleto", $logFile, 'DEBUG');
            
            // IMPORTANTE: Cargar TODOS los archivos del archivo filelist (puede tener más archivos de listados anteriores)
            $filesListFile = $backupDir . '/filelist_' . $backupId . '.json';
            if (file_exists($filesListFile)) {
                $allFiles = @json_decode(@file_get_contents($filesListFile), true);
                if (!is_array($allFiles)) {
                    $allFiles = isset($existingState['files']) ? $existingState['files'] : [];
                }
            } else {
                $allFiles = isset($existingState['files']) ? $existingState['files'] : [];
            }
            
            $filesFromFile = count($allFiles);
            $filesFromState = isset($existingState['files']) ? count($existingState['files']) : 0;
            
            chunkLog("   Archivos en archivo: " . number_format($filesFromFile), $logFile);
            if ($filesFromFile > $filesFromState) {
                chunkLog("   ➕ Archivos adicionales de listados anteriores: " . number_format($filesFromFile - $filesFromState), $logFile);
            }
            chunkLog("   Directorios pendientes: " . (isset($existingState['dirs_pending']) ? count($existingState['dirs_pending']) : 0), $logFile);
            
            // Continuar desde el estado guardado
            $dirsToScan = isset($existingState['dirs_pending']) ? $existingState['dirs_pending'] : [$dolibarrRoot];
            $scannedDirs = isset($existingState['scanned_dirs']) ? array_flip($existingState['scanned_dirs']) : [];
            $dirsProcessed = isset($existingState['dirs_processed']) ? $existingState['dirs_processed'] : 0;
        } 
        // Si el backup ya está completo, reanudar procesamiento
        elseif ($existingState && isset($existingState['files']) && isset($existingState['processed'])) {
            chunkLog("📂 Estado existente encontrado, reanudando...", $logFile);
            chunkLog("   Archivos procesados: " . $existingState['processed'] . "/" . count($existingState['files']), $logFile);
            
            cleanOutputAndJson([
                'success' => true,
                'action' => 'resume',
                'backup_id' => $backupId,
                'total_files' => count($existingState['files']),
                'processed' => $existingState['processed'],
                'chunk_size' => $chunkSize,
                'message' => 'Reanudando backup existente'
            ]);
        } else {
            // Estado inválido, empezar de nuevo
            $allFiles = [];
            $dirsToScan = [$dolibarrRoot];
            $scannedDirs = [];
            $dirsProcessed = 0;
        }
    } else {
        // ═══════════════════════════════════════════════════════════
        // ANÁLISIS DINÁMICO RESUMIBLE PARA PRODUCCIÓN
        // ═══════════════════════════════════════════════════════════
        $preAnalyzedFile = $backupDir . '/pre_analyzed_files.json';
        $analysisCheckpointFile = $backupDir . '/analysis_checkpoint_' . $backupId . '.json';

        // VERIFICAR ANÁLISIS PREVIO Y CHECKPOINTS
        chunkLog("🔍 INICIANDO VERIFICACIÓN DE ANÁLISIS PREVIO", $logFile);
        chunkLog("   Archivo buscado: $preAnalyzedFile", $logFile);
        chunkLog("   Archivo existe: " . (file_exists($preAnalyzedFile) ? 'SÍ' : 'NO'), $logFile);

        if (file_exists($preAnalyzedFile)) {
            chunkLog("✅ Archivo de análisis previo ENCONTRADO", $logFile);
            chunkLog("📂 Buscando análisis previo dinámico...", $logFile);
            $preAnalyzedData = @json_decode(@file_get_contents($preAnalyzedFile), true);

            // Validar que el JSON se decodificó correctamente
            if ($preAnalyzedData === null) {
                chunkLog("⚠️ Error decodificando análisis previo JSON", $logFile);
                $preAnalyzedData = false;
            } elseif (!is_array($preAnalyzedData)) {
                chunkLog("⚠️ Análisis previo no es un array válido", $logFile);
                $preAnalyzedData = false;
            } elseif (!isset($preAnalyzedData['files']) || !is_array($preAnalyzedData['files'])) {
                chunkLog("⚠️ Análisis previo no contiene archivos válidos", $logFile);
                $preAnalyzedData = false;
            } else {
                chunkLog("   📊 Análisis previo válido: " . number_format(count($preAnalyzedData['files'])) . " archivos, partial=" . ($preAnalyzedData['partial'] ? 'true' : 'false'), $logFile);
            }
            
            // Verificar si hay checkpoint de continuación
            $hasValidCheckpoint = false;
            if (file_exists($analysisCheckpointFile)) {
                $checkpointData = @json_decode(@file_get_contents($analysisCheckpointFile), true);
                $hasValidCheckpoint = ($checkpointData && isset($checkpointData['scanned_dirs']));
            }

            if ($preAnalyzedData && isset($preAnalyzedData['partial']) && $preAnalyzedData['partial'] && $hasValidCheckpoint) {
                // ANÁLISIS PARCIAL CON CHECKPOINT - CONTINUAR DESDE DONDE QUEDÓ
                chunkLog("🔄 ANÁLISIS PARCIAL DETECTADO - Continuando desde checkpoint...", $logFile);
                chunkLog("   📊 Progreso previo: " . number_format($checkpointData['files_found'] ?? 0) . " archivos encontrados", $logFile);

                $allFiles = $preAnalyzedData['files'] ?? [];
                $dirsToScan = $checkpointData['pending_dirs'] ?? [];
                $scannedDirs = $checkpointData['scanned_dirs'] ?? [];

                // Ajustar límites para continuación en producción
                if (!$isLocalhost) {
                    $timeLimit = max($timeLimit, 30); // Al menos 30s para continuación en producción
                    chunkLog("⏱️ Límite extendido a {$timeLimit}s para continuación en producción", $logFile);
                }

                $dirsProcessed = count($scannedDirs);
                $startFromCheckpoint = true;

            } elseif ($preAnalyzedData && isset($preAnalyzedData['files']) && is_array($preAnalyzedData['files']) &&
                     (!isset($preAnalyzedData['partial']) || !$preAnalyzedData['partial'])) {
                // ANÁLISIS COMPLETO - USAR DIRECTAMENTE
                chunkLog("✅ ANÁLISIS COMPLETO ENCONTRADO - CONDICIÓN CUMPLIDA", $logFile);
                chunkLog("   📊 " . number_format(count($preAnalyzedData['files'])) . " archivos en JSON", $logFile);

                // Extraer rutas de archivos del análisis previo (que vienen como objetos)
                $allFiles = [];
                $validFiles = 0;
                $invalidFiles = 0;

                foreach ($preAnalyzedData['files'] as $index => $file) {
                    if (is_array($file) && isset($file['path']) && !empty($file['path'])) {
                        $allFiles[] = $file['path'];
                        $validFiles++;
                    } elseif (is_string($file) && !empty($file)) {
                        $allFiles[] = $file;
                        $validFiles++;
                    } else {
                        $invalidFiles++;
                        // Debug: mostrar primeros elementos inválidos
                        if ($invalidFiles <= 3) {
                            chunkLog("   ⚠️ Elemento inválido #$index: " . gettype($file), $logFile);
                        }
                    }
                }

                chunkLog("   ✅ Archivos válidos extraídos: " . number_format($validFiles), $logFile);
                if ($invalidFiles > 0) {
                    chunkLog("   ⚠️ Archivos inválidos ignorados: " . number_format($invalidFiles), $logFile);
                }
                $dirsToScan = []; // No scanear más
                $scannedDirs = $preAnalyzedData['scanned_dirs'] ?? [];
                $dirsProcessed = count($scannedDirs);

                // Copiar a lista específica del backup
                $filesListFile = $backupDir . '/filelist_' . $backupId . '.json';
                $saved = @file_put_contents($filesListFile, json_encode($allFiles));
                if ($saved !== false) {
                    chunkLog("   💾 Lista copiada para este backup: " . number_format(count($allFiles)) . " archivos", $logFile);
                } else {
                    chunkLog("   ❌ Error guardando lista de archivos", $logFile);
                }

            } elseif ($preAnalyzedData && isset($preAnalyzedData['files']) && $preAnalyzedData['partial']) {
                // ANÁLISIS PARCIAL SIN CHECKPOINT - CONTINUAR ESCANEO DESDE ANÁLISIS PARCIAL
                chunkLog("🔄 Análisis parcial encontrado - continuando escaneo dinámico...", $logFile);
                chunkLog("   📊 Base: " . number_format(count($preAnalyzedData['files'])) . " archivos ya analizados", $logFile);

                // Extraer rutas de archivos del análisis previo (que vienen como objetos)
                $allFiles = [];
                foreach ($preAnalyzedData['files'] as $file) {
                    if (is_array($file) && isset($file['path']) && !empty($file['path'])) {
                        $allFiles[] = $file['path'];
                    } elseif (is_string($file) && !empty($file)) {
                        $allFiles[] = $file;
                    }
                    // Ignorar elementos inválidos
                }
                chunkLog("   📊 Archivos base del análisis parcial: " . number_format(count($allFiles)), $logFile);
                $dirsToScan = [$dolibarrRoot]; // Continuar desde raíz
                $scannedDirs = $preAnalyzedData['scanned_dirs'] ?? [];
                $dirsProcessed = count($scannedDirs);

            } else {
                // ANÁLISIS CORRUPTO - EMPEZAR DE NUEVO
                chunkLog("⚠️ Análisis previo corrupto - iniciando análisis dinámico desde cero...", $logFile);
                chunkLog("   ❌ Condición análisis completo NO cumplida", $logFile);
                $allFiles = [];
                $dirsToScan = [$dolibarrRoot];
                $scannedDirs = [];
                $dirsProcessed = 0;
            }
        } else {
            // NO HAY ANÁLISIS PREVIO - EMPEZAR DE NUEVO
            chunkLog("📂 Iniciando análisis dinámico desde cero...", $logFile);
            chunkLog("   🎯 Se guardarán checkpoints para reanudación automática", $logFile);
            chunkLog("   ✅ EJECUTANDO ANÁLISIS DINÁMICO (DEBUG MODE)", $logFile);
            $allFiles = [];
            $dirsToScan = [$dolibarrRoot];
            $scannedDirs = [];
            $dirsProcessed = 0;
        }
    }
    
    // Solo listar si hay directorios pendientes (si no, ya tenemos la lista del análisis)
    if (!empty($dirsToScan)) {
    // Listar archivos OPTIMIZADO para evitar timeout (dinámico según entorno)
    chunkLog("📂 Listando archivos (modo " . ($isLocalhost ? "LOCAL" : "PRODUCCIÓN") . ", límite: {$timeLimit}s)...", $logFile);
    updateProgress(['status' => 'listing', 'message' => 'Listando archivos...', 'percent' => 5], $progressFile);
    
    $listStartTime = microtime(true);
    $filesFound = count($allFiles);
    $lastLogTime = $listStartTime;
    $lastLogFiles = $filesFound;
    if (!isset($dirsProcessed)) {
        $dirsProcessed = 0;
    }
    $logInterval = 30; // Log cada 30 segundos
    $filesLogInterval = 5000; // Log cada 5000 archivos nuevos
    
    // Continuar listado desde donde quedó
    while (!empty($dirsToScan) && (microtime(true) - $listStartTime) < $timeLimit) {
        $currentDir = array_shift($dirsToScan);
        
        // Verificar si ya fue escaneado (usar array_flip para búsqueda O(1))
        if (isset($scannedDirs[$currentDir])) continue;
        $scannedDirs[$currentDir] = true;
        
        // Verificar exclusiones
        $skip = false;
        foreach ($excludeDirs as $exclude) {
            if (strpos($currentDir, $exclude) === 0) {
                $skip = true;
                break;
            }
        }
        if ($skip || !is_dir($currentDir) || is_link($currentDir)) continue;
        
        $dirsProcessed++;
        $currentTime = microtime(true);
        $elapsedTime = $currentTime - $listStartTime;
        $timeSinceLastLog = $currentTime - $lastLogTime;
        $newFilesSinceLastLog = count($allFiles) - $lastLogFiles;
        
        // Log detallado cada X segundos o cada X archivos nuevos
        if ($timeSinceLastLog >= $logInterval || $newFilesSinceLastLog >= $filesLogInterval) {
            $filesInCurrentBatch = count($allFiles);
            $dirsRemaining = count($dirsToScan);
            $filesPerSecond = $filesInCurrentBatch > 0 ? round($filesInCurrentBatch / $elapsedTime, 1) : 0;
            $dirsPerSecond = $elapsedTime > 0 ? round($dirsProcessed / $elapsedTime, 1) : 0;
            
            // Calcular tiempo estimado restante
            $estimatedRemaining = 0;
            if ($filesPerSecond > 0 && $dirsRemaining > 0) {
                // Estimación basada en velocidad actual
                $estimatedRemaining = round(($dirsRemaining / max($dirsPerSecond, 0.1)), 0);
            }
            
            $elapsedMinutes = floor($elapsedTime / 60);
            $elapsedSeconds = round($elapsedTime % 60);
            $elapsedStr = $elapsedMinutes > 0 ? "{$elapsedMinutes}m {$elapsedSeconds}s" : "{$elapsedSeconds}s";
            
            $remainingMinutes = floor($estimatedRemaining / 60);
            $remainingSeconds = round($estimatedRemaining % 60);
            $remainingStr = $estimatedRemaining > 0 ? ($remainingMinutes > 0 ? "{$remainingMinutes}m {$remainingSeconds}s" : "{$remainingSeconds}s") : "calculando...";
            
            // Obtener nombre relativo del directorio actual
            $relativeDir = str_replace($dolibarrRoot, '', $currentDir);
            $relativeDir = $relativeDir ?: '/';
            if (strlen($relativeDir) > 60) {
                $relativeDir = '...' . substr($relativeDir, -57);
            }
            
            // LOG OPTIMIZADO: Solo cada 10 directorios o 5000 archivos para no ralentizar
            $shouldLog = ($dirsProcessed % 10 === 0) || ($filesInCurrentBatch > 5000 && $filesInCurrentBatch % 5000 === 0);
            if ($shouldLog) {
                chunkLog("📁 Procesando: {$relativeDir} | " . number_format($filesInCurrentBatch) . " archivos | {$elapsedStr} transcurrido", $logFile);
            }
            
            // Actualizar estado con información de progreso
            $scannedDirsList = is_array($scannedDirs) ? array_keys($scannedDirs) : [];
            $partialState = [
                'files' => $allFiles,
                'dirs_pending' => $dirsToScan,
                'scanned_dirs' => $scannedDirsList,
                'processed' => 0,
                'total' => $filesInCurrentBatch,
                'list_incomplete' => true,
                'started_at' => date('Y-m-d H:i:s'),
                'current_dir' => $relativeDir,
                'dirs_processed' => $dirsProcessed,
                'files_per_second' => $filesPerSecond,
                'elapsed_time' => round($elapsedTime, 1)
            ];
            @file_put_contents($stateFile, json_encode($partialState));
            
            $lastLogTime = $currentTime;
            $lastLogFiles = $filesInCurrentBatch;
        }
        
        $items = @scandir($currentDir);
        if (!$items) continue;
        
        foreach ($items as $item) {
            if ($item[0] === '.') continue; // Más rápido que comparar strings completos
            
            $fullPath = $currentDir . DIRECTORY_SEPARATOR . $item;
            
            // Verificar exclusiones (optimizado)
            $skipItem = false;
            foreach ($excludeDirs as $exclude) {
                if (strpos($fullPath, $exclude) === 0) {
                    $skipItem = true;
                    break;
                }
            }
            if ($skipItem) continue;
            
            if (is_dir($fullPath)) {
                if (!is_link($fullPath)) {
                    $dirsToScan[] = $fullPath;
                }
            } elseif (is_file($fullPath)) {
                // Verificación rápida de archivos de backup
                if (strpos($item, 'files_dolibarr_') !== false || 
                    strpos($item, 'incomplete_') !== false ||
                    strpos($item, 'chunk_state_') !== false) {
                    continue;
                }
                
                // Verificar si el archivo debe ser excluido (ZIPs grandes, backups antiguos, etc.)
                if (shouldExcludeFile($fullPath, $excludeFiles, $excludePatterns, $excludePathPatterns)) {
                    continue; // Saltar archivos muy grandes o específicamente excluidos
                }
                
                // Agregar archivo directamente (filesize se verifica después si es necesario)
                $allFiles[] = $fullPath;
                $filesFound++;

                // ═══════════════════════════════════════════════════════════
                // GUARDAR CHECKPOINT DURANTE ANÁLISIS DINÁMICO (PRODUCCIÓN)
                // ═══════════════════════════════════════════════════════════
                if (!$isLocalhost && count($allFiles) % 500 === 0 && !empty($dirsToScan)) {
                    $checkpointData = [
                        'timestamp' => date('Y-m-d H:i:s'),
                        'backup_id' => $backupId,
                        'scanned_dirs' => $scannedDirs,
                        'pending_dirs' => array_slice($dirsToScan, 0, 50), // Máximo 50 pendientes para no hacer archivo muy grande
                        'files_found' => count($allFiles),
                        'current_dir' => $currentDir,
                        'progress_percent' => $totalDirs > 0 ? round((count($scannedDirs) / $totalDirs) * 100, 1) : 0
                    ];

                    $checkpointResult = @file_put_contents($analysisCheckpointFile, json_encode($checkpointData));
                    if ($checkpointResult !== false) {
                        chunkLog("💾 Checkpoint guardado: " . number_format(count($allFiles)) . " archivos (" . count($dirsToScan) . " dirs pendientes)", $logFile);
                    }
                }
            }
        }
    }
    } // Cerrar el bloque if (!empty($dirsToScan))
    
    // Si no se hizo listado (porque usamos análisis previo), calcular tiempo como 0
    if (isset($listStartTime)) {
    $listTime = round(microtime(true) - $listStartTime, 2);
    } else {
        $listTime = 0; // No se hizo listado, usamos análisis previo
    }
    $totalFiles = count($allFiles);
    chunkLog("📊 TOTAL FILES FINAL: " . number_format($totalFiles), $logFile);
    $listInterrupted = !empty($dirsToScan); // Si quedan directorios, se interrumpió
    
    // Si se interrumpió el listado, guardar progreso y continuar después
    if ($listInterrupted) {
        $dirsPendingCount = count($dirsToScan);
        $elapsedTime = round(microtime(true) - $listStartTime, 1);
        $filesPerSecond = $elapsedTime > 0 ? round($totalFiles / $elapsedTime, 1) : 0;
        $dirsPerSecond = $elapsedTime > 0 ? round($dirsProcessed / $elapsedTime, 1) : 0;
        
        // Calcular tiempo estimado restante
        $estimatedRemaining = 0;
        if ($dirsPerSecond > 0 && $dirsPendingCount > 0) {
            $estimatedRemaining = round(($dirsPendingCount / max($dirsPerSecond, 0.1)), 0);
        }
        
        chunkLog("⚠️ Listado interrumpido por tiempo ({$timeLimit}s). Archivos encontrados hasta ahora: $totalFiles", $logFile);
        chunkLog("   Directorios pendientes: $dirsPendingCount", $logFile);
        chunkLog("   Directorios procesados: $dirsProcessed", $logFile);
        chunkLog("   Velocidad: {$filesPerSecond} archivos/s | {$dirsPerSecond} dirs/s", $logFile);
        chunkLog("   Tiempo transcurrido: {$elapsedTime}s", $logFile);
        chunkLog("   Modo: " . ($isLocalhost ? "LOCAL" : "PRODUCCIÓN"), $logFile);
        
        // IMPORTANTE: Guardar lista de archivos encontrados hasta ahora (aunque sea parcial)
        // Si ya existe un archivo, MERGEAR los archivos (no sobrescribir) para no perder archivos de listados anteriores
        $filesListFile = $backupDir . '/filelist_' . $backupId . '.json';
        $existingFiles = [];
        if (file_exists($filesListFile)) {
            $existingFiles = @json_decode(@file_get_contents($filesListFile), true);
            if (!is_array($existingFiles)) {
                $existingFiles = [];
            }
        }
        
        // Combinar archivos existentes con los nuevos (evitar duplicados)
        // Filtrar solo strings válidas antes del merge
        $validExistingFiles = array_filter($existingFiles, function($file) {
            return is_string($file) && !empty($file);
        });
        $validNewFiles = array_filter($allFiles, function($file) {
            return is_string($file) && !empty($file);
        });

        $mergedFiles = array_unique(array_merge($validExistingFiles, $validNewFiles));
        $mergedTotalFiles = count($mergedFiles);
        @file_put_contents($filesListFile, json_encode(array_values($mergedFiles)));

        $newFilesCount = $mergedTotalFiles - count($validExistingFiles);
        chunkLog("📄 Lista parcial guardada: " . number_format($mergedTotalFiles) . " archivos totales", $logFile);
        if ($newFilesCount > 0) {
            chunkLog("   ➕ Archivos nuevos en este listado: " . number_format($newFilesCount), $logFile);
        } elseif ($newFilesCount < 0) {
            chunkLog("   ⚠️ Archivos perdidos en merge: " . number_format(abs($newFilesCount)), $logFile);
        }
        chunkLog("   Ruta: $filesListFile", $logFile);
        
        // IMPORTANTE: Actualizar totalFiles con el número real después del merge
        $totalFiles = $mergedTotalFiles;
        
        // Guardar estado parcial (convertir scannedDirs de array asociativo a array simple)
        $scannedDirsList = is_array($scannedDirs) ? array_keys($scannedDirs) : [];
        $partialState = [
            'files' => $allFiles, // Solo los archivos nuevos de este listado
            'dirs_pending' => $dirsToScan,
            'scanned_dirs' => $scannedDirsList,
            'processed' => 0,
            'total' => $mergedTotalFiles, // TOTAL REAL después del merge
            'list_incomplete' => true,
            'started_at' => date('Y-m-d H:i:s'),
            'dirs_processed' => $dirsProcessed,
            'files_per_second' => $filesPerSecond,
            'dirs_per_second' => $dirsPerSecond,
            'elapsed_time' => $elapsedTime,
            'estimated_remaining' => $estimatedRemaining
        ];
        @file_put_contents($stateFile, json_encode($partialState));
        
        // Continuar listado en siguiente request
        cleanOutputAndJson([
            'success' => true,
            'action' => 'continue_listing',
            'list_incomplete' => true, // IMPORTANTE: Para que el frontend sepa que debe continuar
            'backup_id' => $backupId,
            'files_found' => $totalFiles,
            'total_files' => $totalFiles,
            'dirs_pending' => $dirsPendingCount,
            'dirs_processed' => $dirsProcessed,
            'files_per_second' => $filesPerSecond,
            'dirs_per_second' => $dirsPerSecond,
            'elapsed_time' => $elapsedTime,
            'estimated_remaining' => $estimatedRemaining,
            'message' => "Listado parcial: $totalFiles archivos. Continuando...",
            'environment' => $isLocalhost ? 'local' : 'production',
            'time_limit' => $timeLimit
        ]);
    }
    
    // Calcular estadísticas
    $totalChunksEstimate = ceil($totalFiles / $chunkSize);
    $estimatedTimeSec = $totalChunksEstimate * 10; // Estimado ~10s por chunk
    $estimatedTimeMin = floor($estimatedTimeSec / 60);
    $estimatedTimeSec2 = $estimatedTimeSec % 60;
    $estimatedTimeStr = $estimatedTimeMin > 0 ? "{$estimatedTimeMin}m {$estimatedTimeSec2}s" : "{$estimatedTimeSec}s";
    
    // Logs detallados
    chunkLog("", $logFile);
    chunkLog("╔═══════════════════════════════════════════════════════════╗", $logFile);
    chunkLog("║         ANÁLISIS DE ARCHIVOS COMPLETADO                   ║", $logFile);
    chunkLog("╠═══════════════════════════════════════════════════════════╣", $logFile);
    chunkLog("║                                                           ║", $logFile);
    chunkLog("║   📊 Total archivos encontrados: " . str_pad(number_format($totalFiles), 20) . "  ║", $logFile);
    chunkLog("║   ⏱️  Tiempo de análisis: " . str_pad("{$listTime}s", 27) . "  ║", $logFile);
    chunkLog("║   📦 Partes estimadas: " . str_pad("~{$totalChunksEstimate}", 29) . "  ║", $logFile);
    chunkLog("║   ⏳ Tiempo estimado: " . str_pad("~{$estimatedTimeStr}", 30) . "  ║", $logFile);
    chunkLog("║                                                           ║", $logFile);
    chunkLog("╚═══════════════════════════════════════════════════════════╝", $logFile);
    chunkLog("", $logFile);
    
    // Guardar lista de archivos en archivo separado (más eficiente)
    // IMPORTANTE: Si ya existe un archivo, MERGEAR los archivos (no sobrescribir)
    $filesListFile = $backupDir . '/filelist_' . $backupId . '.json';
    $existingFiles = [];
    if (file_exists($filesListFile)) {
        $existingFiles = @json_decode(@file_get_contents($filesListFile), true);
        if (!is_array($existingFiles)) {
            $existingFiles = [];
        }
    }
    
    // Combinar archivos existentes con los nuevos (evitar duplicados)
    // Filtrar solo strings válidas antes del merge
    $validExistingFiles = array_filter($existingFiles, function($file) {
        return is_string($file) && !empty($file);
    });
    $validNewFiles = array_filter($allFiles, function($file) {
        return is_string($file) && !empty($file);
    });

    $mergedFiles = array_unique(array_merge($validExistingFiles, $validNewFiles));
    @file_put_contents($filesListFile, json_encode(array_values($mergedFiles)));

    $finalTotal = count($mergedFiles);
    if (count($validExistingFiles) > 0) {
        $newFilesCount = $finalTotal - count($validExistingFiles);
        chunkLog("📄 Lista de archivos actualizada: " . number_format($finalTotal) . " archivos totales", $logFile);
        chunkLog("   ➕ Archivos nuevos agregados: " . number_format($newFilesCount), $logFile);
    } else {
        chunkLog("📄 Lista de archivos guardada: " . number_format($finalTotal) . " archivos", $logFile);
    }
    chunkLog("   Ruta: $filesListFile", $logFile);
    
    // Actualizar totalFiles con el número real de archivos (incluyendo los de listados anteriores)
    $totalFiles = $finalTotal;
    
    // Guardar estado inicial (SIN la lista de archivos, solo metadatos)
    $state = [
        'backup_id' => $backupId,
        'total' => $totalFiles,
        'processed' => 0,
        'bytes_added' => 0,
        'started_at' => date('Y-m-d H:i:s'),
        'last_update' => date('Y-m-d H:i:s'),
        'chunk_size' => $chunkSize,
        'dolibarr_root' => $dolibarrRoot,
        'chunk_zips' => [] // Array para guardar info de cada chunk ZIP
    ];
    
    @file_put_contents($stateFile, json_encode($state));
    chunkLog("💾 Estado inicial guardado (modo multi-ZIP)", $logFile);
    
    // NO crear ZIP principal aquí - se creará uno por cada chunk
    chunkLog("📦 Sistema de múltiples ZIPs activado (un ZIP por parte)", $logFile);
    chunkLog("", $logFile);
    chunkLog("═══════════════════════════════════════════════════════════", $logFile);
    chunkLog("🚀 INICIANDO COMPRESIÓN POR PARTES...", $logFile);
    chunkLog("═══════════════════════════════════════════════════════════", $logFile);
    chunkLog("", $logFile);
    
    updateProgress([
        'status' => 'ready',
        'message' => 'Listo para procesar',
        'percent' => 10,
        'total' => $totalFiles,
        'processed' => 0
    ], $progressFile);
    
    // Calcular estadísticas adicionales para el frontend
    $totalSize = 0;
    $totalFolders = 0;

    // Contar carpetas y calcular tamaño total aproximado (solo de primeros 1000 archivos para performance)
    $count = 0;
    foreach ($allFiles as $filePath) {
        if ($count >= 1000) break; // Solo procesar primeros 1000 para performance

        if (is_string($filePath) && !empty($filePath)) {
            if (is_dir($filePath)) {
                $totalFolders++;
            } else {
                // Estimación aproximada del tamaño (no precisa pero suficiente para UI)
                $fileSize = @filesize($filePath);
                if ($fileSize !== false) {
                    $totalSize += $fileSize;
                }
            }
        }
        $count++;
    }

    $totalSizeMB = round($totalSize / 1024 / 1024, 2);

    // ACTUALIZAR ARCHIVO DE PROGRESO PARA QUE EL POLLING LO LEA
    $tempDir = sys_get_temp_dir();
    $progressFile = $tempDir . '/analysis_progress_' . session_id() . '.json';

    $progressData = [
        'running' => false, // Análisis completado
        'stats' => [
            'total_files' => $totalFiles,
            'total_folders' => $totalFolders,
            'total_size_mb' => $totalSizeMB
        ],
        'last_update' => time(),
        'partial' => false
    ];

    $jsonContent = json_encode($progressData, JSON_PRETTY_PRINT);
    @file_put_contents($progressFile, $jsonContent);

    cleanOutputAndJson([
        'success' => true,
        'action' => 'init',
        'backup_id' => $backupId,
        'stats' => [
            'total_files' => $totalFiles,
            'total_folders' => $totalFolders,
            'total_size_mb' => $totalSizeMB
        ],
        'processed' => 0,
        'chunk_size' => $chunkSize,
        'estimated_chunks' => $totalChunksEstimate,
        'estimated_time' => $estimatedTimeStr,
        'list_time' => $listTime,
        'message' => "Listados " . number_format($totalFiles) . " archivos en {$listTime}s"
    ]);
}

// ============================================================
// ACCIÓN: PROCESS - Procesar un chunk de archivos
// ============================================================
if ($action === 'process') {
    if (empty($backupId)) {
        cleanOutputAndJson(['success' => false, 'error' => 'backup_id requerido'], null, null);
    }
    
    // Cargar estado
    if (!file_exists($stateFile)) {
        cleanupBackupFilesOnError($backupId, $backupDir);
        cleanOutputAndJson(['success' => false, 'error' => 'Estado no encontrado. Inicie el backup primero.'], $backupId, $backupDir);
    }
    
    $state = @json_decode(@file_get_contents($stateFile), true);
    if (!$state || !isset($state['total'])) {
        cleanupBackupFilesOnError($backupId, $backupDir);
        cleanOutputAndJson(['success' => false, 'error' => 'Estado corrupto'], $backupId, $backupDir);
    }
    
    // Cargar lista de archivos desde archivo separado
    $filesListFile = $backupDir . '/filelist_' . $backupId . '.json';
    if (!file_exists($filesListFile)) {
        chunkLog("❌ Lista de archivos no encontrada: $filesListFile", $logFile);
        cleanupBackupFilesOnError($backupId, $backupDir);
        cleanOutputAndJson(['success' => false, 'error' => 'Lista de archivos no encontrada'], $backupId, $backupDir);
    }

    $allFiles = @json_decode(@file_get_contents($filesListFile), true);
    if (!$allFiles || !is_array($allFiles)) {
        chunkLog("❌ Lista de archivos corrupta o vacía", $logFile);
        cleanupBackupFilesOnError($backupId, $backupDir);
        cleanOutputAndJson(['success' => false, 'error' => 'Lista de archivos corrupta'], $backupId, $backupDir);
    }

    chunkLog("📂 Lista de archivos cargada: " . number_format(count($allFiles)) . " archivos", $logFile);
    
    // ========== NO EXCLUIR ARCHIVOS - PROCESAR TODOS ==========
    // Todos los archivos se incluyen en el backup, sin importar su tamaño
    // ZipArchive::addFile() lee directamente del disco sin cargar todo en memoria
    
    // IMPORTANTE: Usar el total REAL del archivo filelist, no el del estado (puede estar desactualizado)
    $realTotalFiles = count($allFiles);
    $stateTotal = $state['total'] ?? 0;
    
    // Si el total real es diferente al del estado, actualizar el estado
    if ($realTotalFiles != $stateTotal) {
        chunkLog("⚠️ Total de archivos desactualizado en estado. Actualizando: {$stateTotal} → {$realTotalFiles}", $logFile);
        $state['total'] = $realTotalFiles;
        @file_put_contents($stateFile, json_encode($state));
    }
    
    $processed = $state['processed'];
    $totalFiles = $realTotalFiles; // Usar el total REAL del archivo
    $bytesAdded = $state['bytes_added'] ?? 0;
    $dolibarrRoot = $state['dolibarr_root'];

    // ========== CARGAR CHUNKSIZE REDUCIDO SI HUBO TIMEOUT ANTERIOR ==========
    if (isset($state['chunk_size_reduced']) && $state['chunk_size_reduced']) {
        $chunkSize = max(500, $chunkSize - 1000); // Reducir significativamente para evitar futuros timeouts
        chunkLog("📦 ChunkSize reducido por timeout anterior: {$chunkSize} archivos por chunk", $logFile);
        chunkLog("   → Razón: " . ($state['chunk_size_reduced_reason'] ?? 'Timeout en chunk anterior'), $logFile);
    }

    // ========== AJUSTE DINÁMICO DEL CHUNK SEGÚN ENTORNO ==========
    // Ya tenemos $isLocalhost e $isUltraRestricted definidos globalmente
    // Solo re-ajustar el chunkSize si es necesario
    
    if ($isLocalhost) {
        // LOCALHOST: Chunks grandes para desarrollo rápido
        $chunkSize = max($chunkSize, 3000);
        chunkLog("🏠 LOCALHOST detectado: chunk={$chunkSize} archivos (desarrollo)", $logFile);
    } elseif ($isUltraRestricted) {
        // ENTORNO ULTRA-RESTRINGIDO: MÁXIMA SEGURIDAD
        $chunkSize = min($chunkSize, 150); // Solo 150 archivos por chunk (muy seguro)
        $maxMbPerChunk = 20; // Máximo 20MB por chunk
        chunkLog("🔒 ENTORNO ULTRA-RESTRINGIDO detectado: {$memoryLimit} / {$maxExecTime}s", $logFile);
        chunkLog("   → Modo ULTRA-SEGURO: chunk={$chunkSize} archivos, max={$maxMbPerChunk}MB", $logFile);
    } else {
        // Entorno normal: Ajuste dinámico según tamaño del ZIP
        // Calcular tamaño total de todos los chunks procesados hasta ahora
        $currentZipSize = 0;
        foreach ($state['chunk_zips'] ?? [] as $chunkInfo) {
            $currentZipSize += ($chunkInfo['size'] ?? 0);
        }
        $currentZipSizeMB = $currentZipSize / 1024 / 1024;
        
        $originalChunkSize = $chunkSize;
        if ($currentZipSizeMB > 10000) {
            $chunkSize = min($chunkSize, 4000);
            if ($chunkSize < $originalChunkSize) {
                chunkLog("⚡ ZIP muy grande ({$currentZipSizeMB}MB): Reduciendo chunk a $chunkSize", $logFile);
            }
        } elseif ($currentZipSizeMB > 5000) {
            $chunkSize = min($chunkSize, 5000);
        } elseif ($currentZipSizeMB > 3000) {
            $chunkSize = min($chunkSize, 6000);
        } elseif ($currentZipSizeMB > 1000) {
            $chunkSize = min($chunkSize, 8000);
        }
    }
    
    // Verificar si ya terminó (con verificación robusta)
    $chunksExist = count($state['chunk_zips'] ?? []);

    // LÓGICA CORREGIDA: Si todos los archivos están procesados, el backup está completo (independientemente del número de chunks)
    $backupReallyComplete = ($processed >= $totalFiles && $chunksExist > 0);

    if ($backupReallyComplete) {
        chunkLog("✅ Backup ya completado - todos los archivos procesados y chunks creados", $logFile);
        ob_clean();
        cleanOutputAndJson([
            'success' => true,
            'action' => 'complete',
            'backup_id' => $backupId,
            'total_files' => $totalFiles,
            'processed' => $processed,
            'chunks_created' => $chunksExist,
            'message' => 'Backup ya completado'
        ]);
    } elseif ($processed < $totalFiles && $chunksExist < $expectedChunksTotal) {
        chunkLog("⏸️ Backup pausado - faltan archivos y chunks", $logFile);
        chunkLog("   → Archivos: $processed/$totalFiles ❌", $logFile);
        chunkLog("   → Chunks: $chunksExist/$expectedChunksTotal ❌", $logFile);
        // No retornar 'complete' - continuar procesando
    }
    
    // ========== ENVOLVER TODO EL PROCESAMIENTO EN TRY-CATCH ==========
    try {
    // VERIFICAR SI HAY CHUNKS ADICIONALES PENDIENTES (GENERADOS POR TIMEOUT)
    $processingAdditionalChunk = false;
    if (!empty($state['additional_chunks'])) {
        // Tomar el primer chunk adicional pendiente
        $additionalChunk = array_shift($state['additional_chunks']);
        $chunkStart = $additionalChunk['start_index'];
        $chunkEnd = $additionalChunk['end_index'];
        $chunkSize = $additionalChunk['chunk_size']; // Usar el chunkSize reducido

        $processingAdditionalChunk = true;
        chunkLog("🔄 Procesando chunk adicional generado por timeout (índices: {$chunkStart}-{$chunkEnd})", $logFile, 'INFO');

        // Guardar el estado actualizado (sin este chunk adicional)
        @file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));
    } else {
        // Procesar chunk normal
        $chunkStart = $processed;
        $chunkEnd = min($processed + $chunkSize, $totalFiles);
    }

    // Para chunks adicionales, necesitamos rastrear qué archivos ya fueron procesados
    // para evitar procesar los mismos archivos múltiples veces
    $alreadyProcessedFiles = $state['processed_files'] ?? [];
    $chunkProcessed = 0;
    $chunkBytes = 0;
    $errors = 0;
    
    // Inicializar tamaño del ZIP (se calcula más adelante, pero necesita valor inicial)
    $zipSizeMB = 0;
    $totalZipSize = 0;
    foreach ($state['chunk_zips'] ?? [] as $chunkInfo) {
        $totalZipSize += ($chunkInfo['size'] ?? 0);
    }
    $zipSizeMB = round($totalZipSize / 1024 / 1024, 2);
    
    // CORRECCIÓN CRÍTICA: Usar contador secuencial en lugar de cálculo matemático
    // El cálculo matemático falla cuando chunkSize cambia dinámicamente
    // Ejemplo: Si chunkSize cambia de 5000 a 3500, el cálculo floor(10000/3500)+1 = 3
    // pero si chunkSize cambia a 2000, floor(13500/2000)+1 = 7 (salta números)
    $existingChunks = count($state['chunk_zips'] ?? []);
    $chunkNumber = $existingChunks + 1; // Contador secuencial: 1, 2, 3, 4, 5... (siempre consecutivo)
    $totalChunks = ceil($totalFiles / $chunkSize); // Solo para logging/estimación, no crítico
    
    // Crear nombre de ZIP para este chunk
    $chunkZipFile = $backupDir . '/chunk_' . $backupId . '_' . str_pad($chunkNumber, 6, '0', STR_PAD_LEFT) . '.zip';
    
    // Crear nuevo ZIP para este chunk
    $zip = new ZipArchive();
    $zipResult = $zip->open($chunkZipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if ($zipResult !== true) {
        $errorMsg = 'Error creando ZIP de chunk: código ' . $zipResult;
        chunkLog("❌ $errorMsg", $logFile);
        cleanupBackupFilesOnError($backupId, $backupDir);
        ob_clean();
        cleanOutputAndJson(['success' => false, 'error' => $errorMsg], $backupId, $backupDir);
    }
    
    chunkLog("📦 Creando ZIP para parte #$chunkNumber: " . basename($chunkZipFile), $logFile);
    
    // Detectar carpeta actual para el log
    $currentFolder = '';
    $foldersInChunk = [];
    
    $startTime = microtime(true);
    
    // LOG DETALLADO: Inicio del chunk
    chunkLog("", $logFile);
    chunkLog("═══════════════════════════════════════════════════════════", $logFile);
    chunkLog("📦 PARTE #$chunkNumber de ~$totalChunks", $logFile);
    chunkLog("   Archivos: " . number_format($chunkStart + 1) . " al " . number_format($chunkEnd) . " de " . number_format($totalFiles), $logFile);
    chunkLog("   Tamaño de la parte: $chunkSize archivos", $logFile);
    chunkLog("═══════════════════════════════════════════════════════════", $logFile);
    
        // OPTIMIZACIÓN: Procesar archivos con límite de tiempo según entorno
        // Ya tenemos $isLocalhost e $isUltraRestricted definidos globalmente
        
        if ($isLocalhost) {
            // LOCALHOST: Tiempo generoso para desarrollo
            $maxProcessingTime = ($chunkNumber == 1) ? 120 : 150;
        } elseif ($isUltraRestricted) {
            // ENTORNO RESTRINGIDO: 20-22 segundos (dejando 8-10s para cierre de ZIP)
            // El cierre del ZIP tarda ~2-5 segundos con 400 archivos
            $maxProcessingTime = 20;
        } else {
            // Entorno normal: Tiempo suficiente para procesar muchos archivos
            $maxProcessingTime = ($chunkNumber == 1) ? 90 : 110;
        }
        $chunkStartTime = microtime(true);
        $filesProcessedInBatch = 0;
        $elapsedTime = 0; // Para rastrear tiempo transcurrido

        // Variable para rastrear si se necesita reducir chunkSize por timeout
        $chunkSizeNeedsReduction = false;

        // Verificar si el backup fue cancelado
        $stopFile = $backupDir . '/stop_backup_' . $backupId . '.txt';
        
        // Variable para rastrear si se canceló
        $backupCancelled = false;
        
        // ========== VERIFICACIÓN DE MEMORIA ANTES DE PROCESAR ==========
        // Verificar memoria disponible antes de iniciar el procesamiento del chunk
        $currentMemory = memory_get_usage(true);
        $availableMemory = $memoryBytes - $currentMemory;
        $minRequiredMemory = 25 * 1024 * 1024; // 25MB mínimo necesario
        
        if ($availableMemory < $minRequiredMemory) {
            chunkLog("⚠️ Memoria insuficiente antes de procesar parte: " . round($availableMemory / 1024 / 1024, 1) . "MB disponibles (mínimo: 25MB)", $logFile);
            chunkLog("   Memoria actual: " . round($currentMemory / 1024 / 1024, 1) . "MB / " . round($memoryBytes / 1024 / 1024) . "MB", $logFile);
            
            // Forzar cierre de ZIP y liberar memoria
            if (isset($zip) && $zip instanceof ZipArchive) {
                @$zip->close();
                unset($zip);
                gc_collect_cycles();
                chunkLog("💾 ZIP cerrado por memoria baja - liberando memoria", $logFile);
            }
            
            // Retornar para que el siguiente chunk intente de nuevo
            cleanOutputAndJson([
                'success' => true,
                'action' => 'process',
                'backup_id' => $backupId,
                'processed' => $chunkStart,
                'total' => $totalFiles,
                'chunk_processed' => 0,
                'warning' => 'Memoria insuficiente - chunk pausado. Se reintentará automáticamente en el siguiente request.',
                'memory_available_mb' => round($availableMemory / 1024 / 1024, 1)
            ]);
            exit;
        }
        
    for ($i = $chunkStart; $i < $chunkEnd; $i++) {
            // PARA CHUNKS ADICIONALES: Verificar si este archivo ya fue procesado
            if ($processingAdditionalChunk && isset($alreadyProcessedFiles[$i])) {
                // Este archivo ya fue procesado, saltarlo
                continue;
            }

            // Verificar memoria cada 50 archivos en entorno restringido
            if ($isUltraRestricted && $filesProcessedInBatch % 50 == 0) {
                $currentMemory = memory_get_usage(true);
                $availableMemory = $memoryBytes - $currentMemory;
                
                // Si queda menos de 15MB, detener inmediatamente
                if ($availableMemory < 15 * 1024 * 1024) {
                    chunkLog("⚠️ Memoria crítica durante procesamiento: " . round($availableMemory / 1024 / 1024, 1) . "MB disponibles - Deteniendo parte", $logFile);
                    break; // Salir del loop para guardar progreso
                }
            }
            
            // Verificar cancelación según entorno
            $checkInterval = $isUltraRestricted ? 50 : 200; // Cada 50 archivos (balance velocidad/seguridad)
            if ($filesProcessedInBatch % $checkInterval == 0) {
                // Verificar si el usuario canceló el backup
                if (file_exists($stopFile)) {
                    chunkLog("🛑 Backup cancelado por el usuario - Deteniendo inmediatamente", $logFile);
                    @unlink($stopFile); // Eliminar archivo de stop
                    $backupCancelled = true;
                    break;
                }
                
                // Verificar tiempo transcurrido
                $elapsedTime = microtime(true) - $chunkStartTime;
                if ($elapsedTime > $maxProcessingTime) {
                    $progressPercent = $chunkSize > 0 ? (($i - $chunkStart) / $chunkSize * 100) : 0;

                    if ($progressPercent > 80) {
                        // CHUNK >80% COMPLETO: Permitir que termine para NO SALTAR ARCHIVOS
                        chunkLog("⏱️ Timeout pero chunk {$progressPercent}% completo - Completando chunk (NO SALTAR)", $logFile);
                        $chunkSizeNeedsReduction = true; // Marcar para reducir chunkSize en siguientes iteraciones
                        // NO HACER BREAK - continuar procesando hasta completar el chunk
                        chunkLog("📦 ChunkSize se reducirá en siguiente iteración para evitar futuros timeouts", $logFile);
                    } else {
                        // CHUNK <80% COMPLETO: GENERAR CHUNKS ADICIONALES AUTOMÁTICAMENTE
                        chunkLog("⏱️ Timeout detectado en {$progressPercent}% - Generando chunks adicionales", $logFile);

                        // Calcular archivos restantes en este chunk
                        $filesRemainingInChunk = $chunkEnd - $i;
                        $newChunkSize = max(500, intval($chunkSize * 0.6)); // Reducir a 60% pero mínimo 500

                        // Generar múltiples chunks del trabajo restante
                        $additionalChunks = ceil($filesRemainingInChunk / $newChunkSize);
                        chunkLog("📦 Generando {$additionalChunks} chunks adicionales (tamaño: {$newChunkSize})", $logFile);

                        // Guardar información de chunks adicionales en el estado
                        $state['additional_chunks'] = $state['additional_chunks'] ?? [];
                        $state['additional_chunks'][] = [
                            'start_index' => $i,
                            'end_index' => $chunkEnd,
                            'chunk_size' => $newChunkSize,
                            'generated_at' => date('Y-m-d H:i:s'),
                            'reason' => 'timeout_detection'
                        ];

                        @file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));
                        chunkLog("💾 Chunks adicionales guardados en estado - Se procesarán en siguientes iteraciones", $logFile);

                        // Detener este chunk aquí
                        chunkLog("🛑 Deteniendo chunk actual para procesar chunks adicionales", $logFile);
                        break;
                    }
                }
                
                // Verificar conexión del usuario (si se desconectó, detener)
                if (connection_aborted()) {
                    chunkLog("🔌 Conexión del usuario perdida - Deteniendo backup", $logFile);
                    break;
                }
            }
            $filesProcessedInBatch++;
            
            // Verificar cancelación solo cada 200 archivos (máxima velocidad)
            // La verificación dentro del loop cada checkInterval es suficiente
            
        $filePath = $allFiles[$i];

        // Validar que el elemento sea una ruta válida
        if (!is_string($filePath) || empty($filePath)) {
            chunkLog("⚠️ Elemento inválido en posición {$i}: " . gettype($filePath) . " - omitiendo", $logFile);
            $chunkProcessed++; // Contar como procesado para no bloquear el progreso
            continue;
        }

        $fileName = basename($filePath);

        // ========== VERIFICAR EXCLUSIONES POR TAMAÑO/PATRÓN ==========
        // Excluir archivos muy grandes que causan timeout (ZIPs > 500MB, backups antiguos, etc.)
        if (shouldExcludeFile($filePath, $excludeFiles, $excludePatterns, $excludePathPatterns)) {
            $chunkProcessed++; // Contar como procesado para avanzar
            continue; // Sin log para no ralentizar
        }
        
        // OPTIMIZACIÓN: Calcular ruta relativa PRIMERO (más rápido)
        $relativePath = str_replace($dolibarrRoot . DIRECTORY_SEPARATOR, '', $filePath);
        $relativePath = ltrim($relativePath, '/\\');
        
        // Verificación mínima: solo is_file
        if (!@is_file($filePath)) {
            $errors++;
            // Solo loggear errores cada 1000 archivos para máxima velocidad
            if ($errors % 1000 == 0) {
                chunkLog("   ⚠️ Archivos no encontrados: " . $errors . " hasta ahora", $logFile);
            }
            continue;
        }
        
        // Obtener tamaño para estadísticas (opcional, no bloquea)
        $stat = @stat($filePath);
        $fileSize = ($stat && isset($stat['size'])) ? $stat['size'] : 0;
        
        // Agregar al ZIP usando addFile() - lee directamente del disco sin cargar en memoria
        // Esto funciona incluso con archivos muy grandes (GB) porque ZipArchive lee por chunks
        // IMPORTANTE: addFile() puede tardar con archivos grandes, pero no consume memoria
        $addStartTime = microtime(true);
        $addResult = @$zip->addFile($filePath, $relativePath);
        $addTime = microtime(true) - $addStartTime;
        
        if ($addResult) {
            $chunkBytes += $fileSize;
            $chunkProcessed++;

            // MARCAR ARCHIVO COMO PROCESADO EN ESTADO GLOBAL (para chunks adicionales)
            if ($processingAdditionalChunk) {
                $state['processed_files'][$i] = true;
            }
            // Loggear archivos grandes para seguimiento
            // No loggear archivos grandes para mejorar rendimiento
        } else {
            $errors++;
            // Solo loggear errores cada 1000 archivos para no ralentizar
            if ($errors % 1000 == 0) {
                chunkLog("   ⚠️ " . $errors . " errores de archivos hasta ahora", $logFile);
            }
        }
        
        // Liberar memoria periódicamente - más frecuente en entorno restringido
        if ($isUltraRestricted) {
            // ENTORNO ULTRA-RESTRINGIDO: Liberar memoria cada 100 archivos
            $gcInterval = 100;
        } else {
            // Entorno normal: Solo cuando el ZIP es extremadamente grande
        $gcInterval = ($zipSizeMB > 10000) ? 2000 : 3000;
        }
        if ($chunkProcessed % $gcInterval == 0 && function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
        }
        
        // Verificar si se canceló el backup DESPUÉS del loop
        $backupCancelled = file_exists($stopFile);
        if ($backupCancelled) {
            chunkLog("🛑 Backup cancelado por el usuario - Deteniendo inmediatamente", $logFile);
            @unlink($stopFile); // Eliminar archivo de stop
            
            // Cerrar ZIP si está abierto para guardar lo procesado hasta ahora
            if (isset($zip) && $zip instanceof ZipArchive) {
                @$zip->close();
            }
            
            // Guardar estado de cancelación
            $state['cancelled'] = true;
            $state['cancelled_at'] = date('Y-m-d H:i:s');
            @file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));
            
            // Retornar error de cancelación
            cleanOutputAndJson([
                'success' => false,
                'error' => 'Backup cancelado por el usuario',
                'cancelled' => true,
                'processed' => $chunkStart + $chunkProcessed,
                'total' => $totalFiles
            ]);
            exit;
        }
        
        // Si se interrumpió por tiempo, actualizar el índice procesado
        if ($i < $chunkEnd) {
            chunkLog("⚠️ Parte interrumpida por tiempo - Procesados: {$chunkProcessed} de " . ($chunkEnd - $chunkStart) . " archivos", $logFile);
        }
    
    // Calcular si este es el último chunk
    $newProcessed = $chunkStart + $chunkProcessed;

    // VERIFICACIÓN MÁS ROBUSTA: Solo marcar como completo si REALMENTE terminó
    // No solo archivos procesados, sino que todos los chunks están guardados
    $basicComplete = ($newProcessed >= $totalFiles);
    $chunksCreated = count($state['chunk_zips'] ?? []) + 1; // +1 porque este chunk aún no está en el estado

    // LÓGICA DINÁMICA: Si todos los archivos están procesados, los chunks están completos
    if ($basicComplete) {
        $chunksComplete = true;
        $expectedChunks = $chunksCreated; // El número esperado es el número real creado
    } else {
        $expectedChunks = ceil($totalFiles / $chunkSize);
        $chunksComplete = ($chunksCreated >= $expectedChunks);
    }

    $isComplete = ($basicComplete && $chunksComplete);

    if ($basicComplete && !$chunksComplete) {
        chunkLog("⏸️ Backup pausado - esperando que todos los chunks se guarden correctamente", $logFile);
        chunkLog("   → Archivos: $newProcessed/$totalFiles (OK)", $logFile);
        chunkLog("   → Chunks: $chunksCreated/$expectedChunks (INCOMPLETO)", $logFile);
        $isComplete = false;
    }
    
    // Verificar cancelación ANTES de cerrar el ZIP
    if (file_exists($stopFile)) {
        chunkLog("🛑 Backup cancelado por el usuario - Deteniendo antes de cerrar ZIP", $logFile);
        @unlink($stopFile);
        
        // Cerrar ZIP del chunk si está abierto
        if (isset($zip) && $zip instanceof ZipArchive) {
            @$zip->close();
        }
        
        // Guardar estado de cancelación
        $state['cancelled'] = true;
        $state['cancelled_at'] = date('Y-m-d H:i:s');
        @file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));
        
        // Retornar error de cancelación
        cleanOutputAndJson([
            'success' => false,
            'error' => 'Backup cancelado por el usuario',
            'cancelled' => true,
            'processed' => $newProcessed,
            'total' => $totalFiles
        ]);
        exit;
    }
    
    // ========== SIEMPRE CERRAR ZIP DEL CHUNK ==========
    // En modo multi-ZIP, cada chunk se cierra siempre para mantener velocidad
        
        // Verificar tamaño del chunk ZIP antes de cerrar
        clearstatcache(true, $chunkZipFile);
        $chunkSizeBeforeClose = file_exists($chunkZipFile) ? @filesize($chunkZipFile) : 0;
        $chunkSizeMBBeforeClose = round($chunkSizeBeforeClose / 1024 / 1024, 2);
        $isLargeChunk = ($chunkSizeBeforeClose > 100 * 1024 * 1024); // Más de 100MB
        
        if ($isLargeChunk) {
            chunkLog("⚠️ Parte ZIP GRANDE detectada ({$chunkSizeMBBeforeClose} MB) - cerrando con cuidado...", $logFile);
            // Liberar memoria antes de cerrar chunk grande
            gc_collect_cycles();
            if ($isUltraRestricted && function_exists('gc_mem_caches')) {
                @gc_mem_caches();
            }
        }
        
        $closeStartTime = microtime(true);
        
    // Cerrar ZIP del chunk (guarda los cambios)
    $closeResult = @$zip->close();

    $closeTime = round(microtime(true) - $closeStartTime, 2);

    // DIAGNÓSTICO CRÍTICO: Verificar que el chunk se guardó correctamente
    chunkLog("🔍 DIAGNÓSTICO POST-CIERRE CHUNK #$chunkNumber:", $logFile);
    chunkLog("   → Resultado close(): " . ($closeResult ? "SUCCESS" : "FAILED"), $logFile);
    chunkLog("   → Tiempo de cierre: {$closeTime}s", $logFile);
    chunkLog("   → Ruta esperada: $chunkZipFile", $logFile);

    // Verificar existencia del archivo - CRÍTICO para evitar chunks fantasma
    if (!file_exists($chunkZipFile)) {
        chunkLog("   ❌ ERROR CRÍTICO: ZIP no se creó físicamente", $logFile);
        chunkLog("   → close() retornó SUCCESS pero el archivo no existe", $logFile);
        $closeResult = false; // Forzar error para manejar como fallo

        // Verificar permisos del directorio
        $dirPerms = is_writable(dirname($chunkZipFile)) ? "escribible" : "no escribible";
        chunkLog("   → Directorio $dirPerms: " . dirname($chunkZipFile), $logFile);

        // Listar archivos en el directorio para ver qué hay
        $dirFiles = scandir(dirname($chunkZipFile));
        $zipFiles = array_filter($dirFiles, function($f) { return pathinfo($f, PATHINFO_EXTENSION) === 'zip'; });
        chunkLog("   → Archivos ZIP en directorio: " . count($zipFiles), $logFile);
        if (!empty($zipFiles)) {
            chunkLog("   → Lista: " . implode(', ', array_slice($zipFiles, 0, 5)), $logFile);
        }
    } else {
        chunkLog("   ✅ Archivo existe físicamente", $logFile);
        $chunkZipSize = @filesize($chunkZipFile);
        $chunkZipSizeMB = round($chunkZipSize / 1024 / 1024, 2);
        chunkLog("   → Tamaño: {$chunkZipSizeMB} MB ({$chunkZipSize} bytes)", $logFile);

        if ($chunkZipSize > 0) {
            chunkLog("   ✅ Archivo tiene contenido (>0 bytes)", $logFile);

            // Verificar que es un ZIP válido
            if (is_readable($chunkZipFile)) {
                chunkLog("   ✅ Archivo es legible", $logFile);

                // Intentar abrir para verificar que es un ZIP válido
                $testZip = new ZipArchive();
                $testResult = $testZip->open($chunkZipFile);
                if ($testResult === true) {
                    $numFiles = $testZip->numFiles;
                    $testZip->close();
                    chunkLog("   ✅ ZIP válido con $numFiles archivos internos", $logFile);
                } else {
                    chunkLog("   ❌ ZIP corrupto o inválido (código: $testResult)", $logFile);
                }
                unset($testZip);
            } else {
                chunkLog("   ❌ Archivo no es legible", $logFile);
            }
        } else {
            chunkLog("   ❌ Archivo está vacío (0 bytes)", $logFile);
        }
    }

    // Verificar que tenemos el tamaño correcto para el estado
    $chunkZipSize = @filesize($chunkZipFile);
    $chunkZipSizeMB = round($chunkZipSize / 1024 / 1024, 2);

    if ($closeResult === false) {
        chunkLog("❌ ERROR: Fallo al cerrar ZIP de la parte (tiempo: {$closeTime}s)", $logFile);
        // Intentar liberar y reintentar
        unset($zip);
        gc_collect_cycles();

        // Reintentar apertura y cierre
        $zip2 = new ZipArchive();
        if ($zip2->open($chunkZipFile) === true) {
            $zip2->close();
            chunkLog("✅ ZIP de la parte recuperado en segundo intento", $logFile);
            $chunkZipSize = @filesize($chunkZipFile);
            $chunkZipSizeMB = round($chunkZipSize / 1024 / 1024, 2);
        }
    } else {
        if ($closeTime > 1) {
            chunkLog("💾 ZIP de la parte guardado: {$closeTime}s (ZIP: {$chunkZipSizeMB}MB)", $logFile);
        }
    }

    // Liberar memoria
    unset($zip);
    gc_collect_cycles();
    
    // Calcular tiempo del chunk ANTES de usarlo
    $chunkTime = round(microtime(true) - $startTime, 2);

    // Solo guardar chunk si se creó correctamente
    $chunkCreatedSuccessfully = $closeResult && file_exists($chunkZipFile) && $chunkZipSize > 0;

    if ($chunkCreatedSuccessfully) {
        // Guardar información del chunk ZIP en el estado
        $state['chunk_zips'][] = [
            'number' => $chunkNumber,
            'file' => basename($chunkZipFile),
            'size' => $chunkZipSize,
            'files' => $chunkProcessed,
            'bytes' => $chunkBytes,
            'time' => $chunkTime
        ];

        chunkLog("✅ ZIP de parte #$chunkNumber creado: " . basename($chunkZipFile), $logFile);
        chunkLog("📦 Tamaño: {$chunkZipSizeMB} MB | Archivos: $chunkProcessed", $logFile);
    } else {
        chunkLog("❌ ERROR CRÍTICO: Chunk #$chunkNumber no se pudo crear correctamente", $logFile);
        chunkLog("   → closeResult: " . ($closeResult ? "true" : "false"), $logFile);
        chunkLog("   → archivo existe: " . (file_exists($chunkZipFile) ? "sí" : "no"), $logFile);
        chunkLog("   → tamaño: {$chunkZipSize} bytes", $logFile);

        // Limpiar archivos temporales que pudieron quedar
        if (file_exists($chunkZipFile)) {
            @unlink($chunkZipFile);
            chunkLog("   → Archivo temporal eliminado", $logFile);
        }

        // Cleanup y error
        cleanupBackupFilesOnError($backupId, $backupDir);
        ob_clean();
        cleanOutputAndJson(['success' => false, 'error' => "Error al crear chunk #$chunkNumber"], $backupId, $backupDir);
    }
    
    // Limpiar archivos temporales del ZIP del chunk que pudieron quedar
    $tempFiles = glob($chunkZipFile . '.*');
    foreach ($tempFiles as $tempFile) {
        @unlink($tempFile);
    }
    $newBytesAdded = $bytesAdded + $chunkBytes;
    // PORCENTAJE EXACTO: 0-100% basado en archivos procesados
    $percent = $totalFiles > 0 ? round(($newProcessed / $totalFiles) * 100, 1) : 0;
    $percent = min(100, max(0, $percent)); // Asegurar que esté entre 0 y 100
    $chunkBytesMB = round($chunkBytes / 1024 / 1024, 2);
    $speed = $chunkTime > 0 ? round($chunkProcessed / $chunkTime) : 0;
    
    // Calcular tamaño total de todos los chunks procesados hasta ahora
    $totalZipSize = 0;
    foreach ($state['chunk_zips'] ?? [] as $chunkInfo) {
        $totalZipSize += ($chunkInfo['size'] ?? 0);
    }
    $totalZipSize += $chunkZipSize; // Agregar el chunk actual
    $zipSizeMB = round($totalZipSize / 1024 / 1024, 2);
    
    // Info de memoria para diagnóstico
    $memUsed = round(memory_get_usage(true) / 1024 / 1024, 1);
    $memPeak = round(memory_get_peak_usage(true) / 1024 / 1024, 1);
    $memLimit = ini_get('memory_limit');
    $memLimitBytes = return_bytes($memLimit);
    $memAvailable = $memLimitBytes > 0 ? round(($memLimitBytes - memory_get_usage(true)) / 1024 / 1024, 1) : 0;
    $memPercent = $memLimitBytes > 0 ? round((memory_get_usage(true) / $memLimitBytes) * 100, 1) : 0;
    
    // Calcular velocidad promedio real (de todos los chunks procesados)
    $totalChunksProcessed = count($state['chunk_zips'] ?? []) + 1;
    $totalTimeElapsed = isset($state['started_at']) ? (time() - strtotime($state['started_at'])) : 0;
    $avgSpeedReal = ($totalTimeElapsed > 0 && $newProcessed > 0) ? round($newProcessed / $totalTimeElapsed) : 0;
    
    // Calcular tiempo promedio por chunk
    $avgChunkTime = $totalChunksProcessed > 0 ? round($totalTimeElapsed / $totalChunksProcessed, 2) : $chunkTime;
    
    // Calcular velocidad promedio ANTES de usarla en serverResources
    $remainingFiles = $totalFiles - $newProcessed;
    $speedHistory = $state['speed_history'] ?? [];
    $speedHistory[] = $speed;
    if (count($speedHistory) > 10) {
        array_shift($speedHistory);
    }
    $avgSpeed = count($speedHistory) > 0 ? array_sum($speedHistory) / count($speedHistory) : $speed;
    if ($avgSpeed < 10 && $speed > 10) {
        $avgSpeed = $speed;
    }
    
    // Información de recursos del servidor en tiempo real (DETALLADA)
    $serverResources = [
        'php_memory' => [
            'used_mb' => $memUsed,
            'peak_mb' => $memPeak,
            'available_mb' => $memAvailable,
            'limit_mb' => round($memLimitBytes / 1024 / 1024, 1),
            'limit_string' => $memLimit,
            'usage_percent' => $memPercent,
            'usage_detail' => "{$memUsed}MB usados de {$memLimit} límite PHP"
        ],
        'server_info' => [
            'max_execution_time' => ini_get('max_execution_time'),
            'max_execution_time_used' => $chunkTime,
            'max_execution_time_remaining' => ini_get('max_execution_time') > 0 ? max(0, ini_get('max_execution_time') - $chunkTime) : 0,
            'time_limit_detail' => ini_get('max_execution_time') > 0 ? 
                round($chunkTime, 1) . "s usados de " . ini_get('max_execution_time') . "s límite" : 
                "Sin límite de tiempo"
        ],
        'zip_info' => [
            'size_mb' => $zipSizeMB,
            'files_in_zip' => $newProcessed,
            'chunk_mb' => $chunkBytesMB,
            'chunks_count' => $totalChunksProcessed,
            'total_chunks_estimated' => $totalChunks,
            'chunks_detail' => "{$totalChunksProcessed} de ~{$totalChunks} chunks completados"
        ],
        'performance' => [
            'speed_files_sec' => $speed,
            'avg_speed_files_sec' => round($avgSpeed, 1),
            'avg_speed_real' => $avgSpeedReal,
            'chunk_time_sec' => round($chunkTime, 2),
            'avg_chunk_time_sec' => $avgChunkTime,
            'total_time_elapsed_sec' => $totalTimeElapsed,
            'performance_detail' => "Velocidad actual: {$speed} arch/s | Promedio: " . round($avgSpeed, 1) . " arch/s | Real: {$avgSpeedReal} arch/s"
        ]
    ];
    
    // Calcular ETA basado en velocidad promedio (ya calculada arriba)
    $etaSeconds = $avgSpeed > 0 ? round($remainingFiles / $avgSpeed) : 0;
    $etaMinutes = floor($etaSeconds / 60);
    $etaSecondsRest = $etaSeconds % 60;
    $etaStr = $etaMinutes > 0 ? "{$etaMinutes}m {$etaSecondsRest}s" : "{$etaSeconds}s";
    
    // Guardar historial de velocidades en el estado
    $state['speed_history'] = $speedHistory;
    $state['avg_speed'] = round($avgSpeed, 1);
    
    // Actualizar estado (chunk_zips ya se actualizó arriba al cerrar el ZIP)
    $state['processed'] = $newProcessed;
    $state['bytes_added'] = $newBytesAdded;
    $state['last_update'] = date('Y-m-d H:i:s');

    // GUARDAR INFORMACIÓN DE REDUCCIÓN DE CHUNKSIZE SI HUBO TIMEOUT
    if (isset($chunkSizeNeedsReduction) && $chunkSizeNeedsReduction) {
        $state['chunk_size_reduced'] = true;
        $state['chunk_size_reduced_reason'] = "Timeout en chunk #{$chunkNumber} ({$elapsedTime}s)";
        $state['chunk_size_reduced_at'] = date('Y-m-d H:i:s');
        chunkLog("💾 Información de reducción de chunkSize guardada en estado", $logFile);
    }

    @file_put_contents($stateFile, json_encode($state, JSON_PRETTY_PRINT));
    
    // Actualizar progreso
    updateProgress([
        'status' => 'processing',
        'message' => "Procesando archivos...",
        'percent' => $percent,
        'total' => $totalFiles,
        'processed' => $newProcessed,
        'zip_size_mb' => $zipSizeMB,
        'bytes_added' => $newBytesAdded,
        'chunks_count' => count($state['chunk_zips'] ?? [])
    ], $progressFile);
    
    // LOG DETALLADO: Resumen del chunk
    chunkLog("───────────────────────────────────────────────────────────", $logFile);
    chunkLog("✅ PARTE #$chunkNumber COMPLETADA", $logFile);
    chunkLog("   📊 Archivos procesados: $chunkProcessed", $logFile);
    chunkLog("   💾 Datos agregados: {$chunkBytesMB} MB", $logFile);
    chunkLog("   ⏱️  Tiempo: {$chunkTime}s ({$speed} archivos/seg)", $logFile);
    chunkLog("   🗂️  Carpetas procesadas: " . count($foldersInChunk), $logFile);
    if (!empty($foldersInChunk)) {
        foreach (array_slice($foldersInChunk, 0, 5) as $f) {
            chunkLog("      📂 $f", $logFile);
        }
        if (count($foldersInChunk) > 5) {
            chunkLog("      ... y " . (count($foldersInChunk) - 5) . " carpetas más", $logFile);
        }
    }
    if ($errors > 0) {
        chunkLog("   ⚠️  Errores: $errors archivos", $logFile);
    }
    chunkLog("───────────────────────────────────────────────────────────", $logFile);
    chunkLog("📈 PROGRESO TOTAL: $percent%", $logFile);
    chunkLog("   Archivos: " . number_format($newProcessed) . " / " . number_format($totalFiles), $logFile);
    chunkLog("   Restantes: " . number_format($remainingFiles) . " archivos", $logFile);
    chunkLog("   ZIP actual: {$zipSizeMB} MB", $logFile);
    chunkLog("   Tiempo estimado restante: ~$etaStr", $logFile);
    chunkLog("   Memoria: {$memUsed}MB (pico: {$memPeak}MB)", $logFile);
    chunkLog("═══════════════════════════════════════════════════════════", $logFile);
    
    // $isComplete ya se calculó arriba para cerrar el ZIP correctamente
    
    if ($isComplete) {
        chunkLog("", $logFile);
        chunkLog("🎉🎉🎉 TODOS LOS ARCHIVOS PROCESADOS 🎉🎉🎉", $logFile);
        chunkLog("   Total archivos: " . number_format($totalFiles), $logFile);
        chunkLog("   Tamaño ZIP: {$zipSizeMB} MB", $logFile);
        chunkLog("   Finalizando backup...", $logFile);
        chunkLog("", $logFile);
    }
    
    // Limpiar cualquier salida no deseada antes de enviar JSON
    ob_clean();
    
    // Calcular tiempo transcurrido desde el inicio
    $elapsedSeconds = isset($state['started_at']) ? (time() - strtotime($state['started_at'])) : 0;
    
    cleanOutputAndJson([
        'success' => true,
        'action' => $isComplete ? 'complete' : 'continue',
        'backup_id' => $backupId,
        'total_files' => $totalFiles,
        'processed' => $newProcessed,
        'percent' => $percent, // PORCENTAJE EXACTO 0-100%
        'chunk_time' => $chunkTime,
        'chunk_processed' => $chunkProcessed,
        'chunk_size_used' => $chunkSize,
        'chunk_number' => $chunkNumber,
        'total_chunks' => $totalChunks,
        'errors' => $errors,
        'zip_size_mb' => $zipSizeMB,
        'chunk_size_mb' => $chunkBytesMB,
        'speed_files_sec' => $speed,
        'avg_speed_files_sec' => round($avgSpeed, 1), // Velocidad promedio (más estable)
        'eta_seconds' => $etaSeconds,
        'eta_str' => $etaStr, // TIEMPO RESTANTE EXACTO (basado en promedio móvil)
        'time' => [
            'elapsed_seconds' => $elapsedSeconds,
            'estimated_remaining_seconds' => $etaSeconds,
            'estimated_remaining_str' => $etaStr
        ],
        'folders_processed' => count($foldersInChunk),
        'memory_mb' => $memUsed,
        'memory_peak_mb' => $memPeak,
        'server_resources' => $serverResources, // Recursos del servidor en tiempo real
        'message' => $isComplete ? 'Procesamiento completo' : "Chunk #$chunkNumber: $chunkProcessed archivos en {$chunkTime}s"
    ]);
    
    } catch (Exception $e) {
        // ========== CAPTURAR CUALQUIER ERROR EN EL CHUNK ==========
        $errorDetails = [
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
            'chunk_number' => $chunkNumber ?? 'desconocido',
            'chunk_start' => $chunkStart ?? 'desconocido',
            'chunk_end' => $chunkEnd ?? 'desconocido',
            'processed_before_error' => $chunkProcessed ?? 0,
            'backup_id' => $backupId,
            'zip_file' => $zipFile ?? 'desconocido',
            'zip_exists' => isset($zipFile) && file_exists($zipFile) ? 'SI' : 'NO',
            'memory_used' => round(memory_get_usage(true) / 1024 / 1024, 2) . 'MB',
            'memory_peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . 'MB'
        ];
        
        // Log detallado del error
        chunkLog("", $logFile);
        chunkLog("═══════════════════════════════════════════════════════════", $logFile);
        chunkLog("❌❌❌ ERROR CRÍTICO EN PARTE #" . ($chunkNumber ?? 'desconocido') . " ❌❌❌", $logFile);
        chunkLog("═══════════════════════════════════════════════════════════", $logFile);
        chunkLog("Mensaje: " . $e->getMessage(), $logFile);
        chunkLog("Archivo: " . $e->getFile(), $logFile);
        chunkLog("Línea: " . $e->getLine(), $logFile);
        chunkLog("Parte: " . ($chunkNumber ?? 'desconocido') . " (archivos " . ($chunkStart ?? '?') . "-" . ($chunkEnd ?? '?') . ")", $logFile);
        chunkLog("Procesados antes del error: " . ($chunkProcessed ?? 0), $logFile);
        chunkLog("ZIP existe: " . (isset($zipFile) && file_exists($zipFile) ? 'SÍ' : 'NO'), $logFile);
        chunkLog("Memoria: " . round(memory_get_usage(true) / 1024 / 1024, 2) . "MB (pico: " . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . "MB)", $logFile);
        chunkLog("", $logFile);
        chunkLog("Stack trace:", $logFile);
        chunkLog($e->getTraceAsString(), $logFile);
        chunkLog("═══════════════════════════════════════════════════════════", $logFile);
        
        // Intentar cerrar el ZIP si está abierto
        if (isset($zip) && $zip instanceof ZipArchive) {
            try {
                @$zip->close();
                chunkLog("✅ ZIP cerrado después del error", $logFile);
            } catch (Exception $zipError) {
                chunkLog("⚠️ Error al cerrar ZIP: " . $zipError->getMessage(), $logFile);
            }
        }
        
        // Guardar estado parcial si es posible
        if (isset($chunkProcessed) && isset($chunkStart)) {
            try {
                $partialProcessed = $chunkStart + $chunkProcessed;
                $state['processed'] = $partialProcessed;
                $state['last_error'] = date('Y-m-d H:i:s') . ' - ' . $e->getMessage();
                @file_put_contents($stateFile, json_encode($state));
                chunkLog("💾 Estado parcial guardado: $partialProcessed archivos", $logFile);
            } catch (Exception $stateError) {
                chunkLog("⚠️ Error al guardar estado: " . $stateError->getMessage(), $logFile);
            }
        }
        
        // Limpiar salida y devolver error JSON
        ob_clean();
        cleanOutputAndJson([
            'success' => false,
            'error' => 'Error en chunk #' . ($chunkNumber ?? 'desconocido') . ': ' . $e->getMessage(),
            'error_details' => $errorDetails,
            'backup_id' => $backupId,
            'chunk_number' => $chunkNumber ?? null,
            'processed_before_error' => $chunkProcessed ?? 0,
            'can_retry' => true
        ]);
        
    } catch (Error $e) {
        // ========== CAPTURAR ERRORES FATALES (PHP 7+) ==========
        $errorDetails = [
            'type' => 'Fatal Error',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'chunk_number' => $chunkNumber ?? 'desconocido',
            'backup_id' => $backupId
        ];
        
        chunkLog("", $logFile);
        chunkLog("═══════════════════════════════════════════════════════════", $logFile);
        chunkLog("💥💥💥 ERROR FATAL EN PARTE #" . ($chunkNumber ?? 'desconocido') . " 💥💥💥", $logFile);
        chunkLog("═══════════════════════════════════════════════════════════", $logFile);
        chunkLog("Tipo: Error Fatal", $logFile);
        chunkLog("Mensaje: " . $e->getMessage(), $logFile);
        chunkLog("Archivo: " . $e->getFile(), $logFile);
        chunkLog("Línea: " . $e->getLine(), $logFile);
        chunkLog("═══════════════════════════════════════════════════════════", $logFile);
        
        ob_clean();
        cleanOutputAndJson([
            'success' => false,
            'error' => 'Error fatal en chunk: ' . $e->getMessage(),
            'error_details' => $errorDetails,
            'backup_id' => $backupId,
            'fatal' => true
        ]);
    }
}

// ============================================================
// ACCIÓN: FINALIZE - Renombrar ZIP y limpiar
// ============================================================
if ($action === 'finalize') {
    if (empty($backupId)) {
        echo json_encode(['success' => false, 'error' => 'backup_id requerido']);
        exit;
    }

    chunkLog("═══════════════════════════════════════════════════════════", $logFile);
    chunkLog("🏁 FINALIZANDO BACKUP - SISTEMA MULTI-CHUNK", $logFile);

    // ═══════════════════════════════════════════════════════════
    // CARGA Y VALIDACIÓN INICIAL DEL ESTADO
    // ═══════════════════════════════════════════════════════════
    chunkLog("📋 Cargando estado del backup...", $logFile);

    if (!file_exists($stateFile)) {
        chunkLog("❌ ERROR: Archivo de estado no encontrado: $stateFile", $logFile);
        cleanupBackupFilesOnError($backupId, $backupDir);
        ob_clean();
        cleanOutputAndJson(['success' => false, 'error' => 'Estado del backup no encontrado'], $backupId, $backupDir);
    }

    $state = @json_decode(@file_get_contents($stateFile), true);
    if (!$state || !isset($state['chunk_zips'])) {
        chunkLog("❌ ERROR: Estado del backup corrupto o incompleto", $logFile);
        cleanupBackupFilesOnError($backupId, $backupDir);
        ob_clean();
        cleanOutputAndJson(['success' => false, 'error' => 'Estado del backup corrupto'], $backupId, $backupDir);
    }

    $chunkZips = $state['chunk_zips'] ?? [];
    $totalChunksExpected = count($chunkZips);

    chunkLog("✅ Estado cargado: $totalChunksExpected chunks esperados", $logFile);

    // VALIDAR que TODOS los chunks existen físicamente
    chunkLog("🔍 Validando existencia física de chunks...", $logFile);
    $missingChunks = [];
    foreach ($chunkZips as $chunkInfo) {
        $chunkPath = $backupDir . '/' . $chunkInfo['file'];
        if (!file_exists($chunkPath)) {
            $missingChunks[] = $chunkInfo['file'];
            chunkLog("❌ Chunk faltante: {$chunkInfo['file']}", $logFile);
        }
    }

    if (!empty($missingChunks)) {
        chunkLog("❌ ERROR CRÍTICO: " . count($missingChunks) . " chunks faltantes - no se puede continuar", $logFile);
        ob_clean();
        cleanOutputAndJson([
            'success' => false,
            'error' => 'Chunks faltantes: ' . implode(', ', $missingChunks),
            'missing_chunks' => $missingChunks
        ]);
    }

    chunkLog("✅ Todos los chunks existen físicamente", $logFile);

    // ═══════════════════════════════════════════════════════════
    // SISTEMA MULTI-CHUNK: MANTENER CHUNKS SEPARADOS PARA DESCARGA INDIVIDUAL
    // ═══════════════════════════════════════════════════════════

    // Verificar que todos los chunks individuales están completos y válidos
    chunkLog("🔍 Verificando integridad de chunks individuales...", $logFile);

    $chunksValid = [];
    $chunksInvalid = [];
    $totalSize = 0;

    foreach ($chunkZips as $chunkInfo) {
        $chunkPath = $backupDir . '/' . $chunkInfo['file'];

        if (!file_exists($chunkPath)) {
            $chunksInvalid[] = $chunkInfo['number'];
            chunkLog("❌ Chunk #{$chunkInfo['number']} no existe: {$chunkInfo['file']}", $logFile);
            continue;
        }

        $chunkSize = filesize($chunkPath);
        $chunkSizeMB = round($chunkSize / 1024 / 1024, 2);

        // Verificar que es un ZIP válido
        $testZip = new ZipArchive();
        $isValidZip = $testZip->open($chunkPath, ZipArchive::CHECKCONS) === true;
        if ($isValidZip) {
            $testZip->close();
            $chunksValid[] = [
                'number' => $chunkInfo['number'],
                'file' => $chunkInfo['file'],
                'size_mb' => $chunkSizeMB,
                'files' => $chunkInfo['files'] ?? 0
            ];
            $totalSize += $chunkSizeMB;
            chunkLog("✅ Chunk #{$chunkInfo['number']} válido: {$chunkSizeMB} MB", $logFile);
        } else {
            $chunksInvalid[] = $chunkInfo['number'];
            chunkLog("❌ Chunk #{$chunkInfo['number']} corrupto: {$chunkInfo['file']}", $logFile);
        }
    }

    if (!empty($chunksInvalid)) {
        chunkLog("❌ ERROR: " . count($chunksInvalid) . " chunks inválidos encontrados", $logFile);
        ob_clean();
        cleanOutputAndJson([
            'success' => false,
            'error' => 'Chunks inválidos encontrados: ' . implode(', ', $chunksInvalid)
        ]);
        exit;
    }

    chunkLog("✅ Todos los chunks válidos - Backup listo para descarga por partes", $logFile);
    chunkLog("📊 Total: " . count($chunksValid) . " chunks, {$totalSize} MB", $logFile);

    // Backup completado - devolver información de chunks individuales
    ob_clean();
    cleanOutputAndJson([
        'success' => true,
        'action' => 'finalized',
        'backup_id' => $backupId,
        'chunks' => $chunksValid,
        'total_chunks' => count($chunksValid),
        'total_size_mb' => $totalSize,
        'message' => "Backup completado: " . count($chunksValid) . " chunks listos para descarga individual ({$totalSize} MB total)"
    ]);
    exit;
// ============================================================
// ACCIÓN: STATUS - Verificar estado actual
// ============================================================
if ($action === 'status') {
    if (empty($backupId)) {
        echo json_encode(['success' => false, 'error' => 'backup_id requerido']);
        exit;
    }
    
    if (!file_exists($stateFile)) {
        echo json_encode([
            'success' => true,
            'exists' => false,
            'message' => 'No hay backup en progreso'
        ]);
        exit;
    }
    
    $state = @json_decode(@file_get_contents($stateFile), true);
    
    // Calcular tamaño total de todos los chunks procesados
    $totalZipSize = 0;
    foreach ($state['chunk_zips'] ?? [] as $chunkInfo) {
        $totalZipSize += ($chunkInfo['size'] ?? 0);
    }
    
    $response = [
        'success' => true,
        'exists' => true,
        'backup_id' => $backupId,
        'total_files' => $state['total'] ?? 0,
        'processed' => $state['processed'] ?? 0,
        'percent' => ($state['total'] ?? 0) > 0 ? round(($state['processed'] / $state['total']) * 100) : 0,
        'zip_size_mb' => round($totalZipSize / 1024 / 1024, 2),
        'chunks_count' => count($state['chunk_zips'] ?? []),
        'started_at' => $state['started_at'] ?? '',
        'last_update' => $state['last_update'] ?? ''
    ];
    
    // Si el listado está incompleto, incluir información de progreso
    if (isset($state['list_incomplete']) && $state['list_incomplete'] === true) {
        $response['files_found'] = $state['total'] ?? 0;
        $response['dirs_pending'] = is_array($state['dirs_pending'] ?? null) ? count($state['dirs_pending']) : 0;
        $response['dirs_processed'] = $state['dirs_processed'] ?? 0;
        $response['files_per_second'] = $state['files_per_second'] ?? 0;
        $response['dirs_per_second'] = $state['dirs_per_second'] ?? 0;
        $response['elapsed_time'] = $state['elapsed_time'] ?? 0;
        $response['estimated_remaining'] = $state['estimated_remaining'] ?? 0;
        $response['current_dir'] = $state['current_dir'] ?? '';
        $response['listing_in_progress'] = true;
    }
    
    echo json_encode($response);
    exit;
}
}

// Acción no reconocida
echo json_encode([
    'success' => false,
    'error' => 'Acción no válida. Use: init, process, finalize, status'
]);
?>
