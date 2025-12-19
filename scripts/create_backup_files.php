<?php
// Backup de Archivos de Dolibarr
// Mantener errores visibles para diagnóstico (pero sin mostrar en pantalla)
error_reporting(E_ALL);
ini_set('display_errors', 0); // No mostrar errores en pantalla
ini_set('log_errors', 1); // Pero sí loguearlos

// ========== OPTIMIZACIÓN PARA SERVIDORES CON RECURSOS LIMITADOS ==========
// Configurar límites pero permitir extender cuando sea necesario
if (!function_exists('safeExtendExecutionTime')) {
    function safeExtendExecutionTime($seconds = 0)
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit($seconds);
        }
        if ($seconds === 0) {
            @ini_set('max_execution_time', 0);
        } elseif ($seconds > 0) {
            @ini_set('max_execution_time', $seconds);
        }
    }
}

safeExtendExecutionTime(0); // Tiempo ilimitado controlado por lógica propia

// Detectar y registrar límites del servidor
$originalMaxExec = ini_get('max_execution_time');
$newMaxExec = ini_get('max_execution_time');
error_log("FILEMANAGER BACKUP: max_execution_time original=$originalMaxExec, después de set_time_limit(0)=$newMaxExec");

// Verificar si set_time_limit está deshabilitado (algunos hostings lo bloquean)
if ($newMaxExec > 0 && $newMaxExec == $originalMaxExec) {
    error_log("FILEMANAGER BACKUP: ⚠️ ADVERTENCIA: set_time_limit puede estar deshabilitado por el hosting");
}

// Detectar memoria disponible y usar un límite razonable
$current_memory = ini_get('memory_limit');
$current_bytes = return_bytes($current_memory);
$max_allowed = 256 * 1024 * 1024; // 256MB máximo
if ($current_bytes > $max_allowed || $current_bytes == -1) {
    ini_set('memory_limit', '256M');
}

// Función auxiliar para convertir límites a bytes
function return_bytes($val) {
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

// Número máximo de archivos a procesar por lote (para evitar timeout)
define('BACKUP_BATCH_SIZE', 100);
// Pausa entre lotes en microsegundos (para no saturar CPU)
define('BACKUP_BATCH_PAUSE', 10000); // 10ms

// Cargar traducciones
require_once dirname(__FILE__) . '/../lib/backup_translations.lib.php';
$backupLang = isset($_GET['lang']) ? $_GET['lang'] : (isset($_POST['lang']) ? $_POST['lang'] : detectBackupLanguage());

// IMPORTANTE: Crear archivos de progreso ANTES de cerrar la conexión con el cliente
// Soportar tanto parámetros HTTP (GET/POST) como CLI (argv)
if (php_sapi_name() === 'cli') {
    // Desde línea de comandos, leer de argv
    parse_str(implode('&', array_slice($argv, 1)), $cliParams);
    $tipo = $cliParams['backup_type'] ?? 'files_only';
    $backupId = $cliParams['backup_id'] ?? date('YmdHis');
    $isAutomatic = isset($cliParams['automatic']) ? (int)$cliParams['automatic'] : 0;
} else {
    // Desde HTTP - POR DEFECTO SIEMPRE MANUAL (0) a menos que se especifique explícitamente como automático
$tipo = $_GET['backup_type'] ?? $_POST['backup_type'] ?? 'files_only';
$backupId = $_GET['backup_id'] ?? $_POST['backup_id'] ?? date('YmdHis'); // Sin guiones bajos para coincidir con JS
    // IMPORTANTE: Si viene desde la interfaz web, SIEMPRE es MANUAL (0)
    // Solo es automático si viene explícitamente del cron con el parámetro from_cron=1
    // Esto asegura que los backups iniciados desde la interfaz siempre sean MANUALES
    $isAutomatic = (isset($_GET['from_cron']) && $_GET['from_cron'] == 1) ? 1 : 0;
}
$fecha = $backupId;

/**
 * Función para detectar y configurar el directorio de backups dinámicamente
 * Detecta el entorno y encuentra el mejor directorio disponible
 */
function detectBackupDirectory() {
    // 1. Directorio relativo al script (dentro de filemanager/backups)
    $scriptDir = realpath(__DIR__ . '/..') . '/backups';
    
    // Si existe y es escribible, usarlo
    if (is_dir($scriptDir) && is_writable($scriptDir)) {
        return $scriptDir;
    }
    
    // Intentar crear el directorio
    $parentDir = dirname($scriptDir);
    if (is_dir($parentDir) && is_writable($parentDir)) {
        if (@mkdir($scriptDir, 0755, true)) {
            return $scriptDir;
        }
    }
    
    // 2. Fallback: Directorio temporal del sistema
    $tempDir = sys_get_temp_dir() . '/filemanager_backups';
    if (!is_dir($tempDir)) {
        @mkdir($tempDir, 0755, true);
    }
    
    return is_dir($tempDir) && is_writable($tempDir) ? $tempDir : $scriptDir;
}

// Detectar directorio de backups dinámicamente
$backupDir = detectBackupDirectory();

// Forzar que ZipArchive y cualquier función temporal use nuestro directorio persistente
if (is_dir($backupDir) && is_writable($backupDir)) {
    if (function_exists('ini_set')) {
        @ini_set('sys_temp_dir', $backupDir);
    }
    @putenv('TMPDIR=' . $backupDir);
    @putenv('TEMP=' . $backupDir);
    @putenv('TMP=' . $backupDir);
    // Se registrará más adelante cuando tengamos logFile, por ahora solo informar por error_log
    error_log("FILEMANAGER BACKUP: sys_temp_dir forzado a $backupDir");
}

/**
 * Función para diagnosticar límites del sistema de archivos
 * Detecta problemas con inodos, espacio en disco, permisos, etc.
 */
function checkFilesystemLimits($directory, $logFile = null) {
    $issues = array();
    
    // 1. Verificar espacio en disco
    $freeSpace = @disk_free_space($directory);
    $totalSpace = @disk_total_space($directory);
    if ($freeSpace !== false && $totalSpace !== false) {
        $percentFree = ($freeSpace / $totalSpace) * 100;
        if ($percentFree < 10) {
            $issues[] = "⚠️ ESPACIO EN DISCO BAJO: " . round($percentFree, 1) . "% libre (" . round($freeSpace / (1024*1024*1024), 2) . " GB)";
        }
        if ($logFile) {
            logMsg("   • Espacio disponible: " . round($freeSpace / (1024*1024*1024), 2) . " GB (" . round($percentFree, 1) . "%)", $logFile);
        }
    }
    
    // 2. Verificar inodos (solo en Linux/Unix)
    if (PHP_OS_FAMILY === 'Linux' || PHP_OS_FAMILY === 'Darwin') {
        $output = array();
        $returnVar = 0;
        @exec("df -i " . escapeshellarg($directory) . " 2>/dev/null | tail -1", $output, $returnVar);
        if ($returnVar === 0 && !empty($output[0])) {
            $parts = preg_split('/\s+/', trim($output[0]));
            if (count($parts) >= 5) {
                $inodesUsed = str_replace('%', '', $parts[4]);
                if (is_numeric($inodesUsed) && $inodesUsed > 90) {
                    $issues[] = "⚠️ INODOS CASI AGOTADOS: " . $inodesUsed . "% usado";
                }
                if ($logFile) {
                    logMsg("   • Inodos usados: " . $inodesUsed . "%", $logFile);
                }
            }
        }
    }
    
    // 3. Verificar permisos de escritura
    if (!is_writable($directory)) {
        $issues[] = "❌ DIRECTORIO NO ESCRIBIBLE: " . $directory;
    }
    
    // 4. Verificar límite de archivos abiertos
    if (function_exists('shell_exec')) {
        $ulimit = @shell_exec('ulimit -n 2>/dev/null');
        if ($ulimit && is_numeric(trim($ulimit))) {
            if ($logFile) {
                logMsg("   • Límite archivos abiertos: " . trim($ulimit), $logFile);
            }
        }
    }
    
    return $issues;
}

/**
 * Función para verificar si un archivo ZIP existe y es accesible
 */
function verifyZipFile($zipPath, $logFile = null) {
    if (!file_exists($zipPath)) {
        if ($logFile) {
            logMsg("❌ ERROR CRÍTICO: El archivo ZIP no existe: " . basename($zipPath), $logFile);
        }
        return false;
    }
    
    if (!is_readable($zipPath)) {
        if ($logFile) {
            logMsg("❌ ERROR CRÍTICO: El archivo ZIP no es legible: " . basename($zipPath), $logFile);
        }
        return false;
    }
    
    if (!is_writable($zipPath)) {
        if ($logFile) {
            logMsg("⚠️ ADVERTENCIA: El archivo ZIP no es escribible: " . basename($zipPath), $logFile);
        }
        return false;
    }
    
    return true;
}

// Definir funciones de logging ANTES de usarlas
function logMsg($msg, $logFile) {
    if (empty($logFile)) {
        error_log("BACKUP FILES: logMsg llamado con logFile vacío: $msg");
        return;
    }
    $timestamp = date('H:i:s');
    @file_put_contents($logFile, "[$timestamp] $msg\n", FILE_APPEND | LOCK_EX);
}

function updateProg($prog, $progressFile, $logFile, $heartbeatFile = null) {
    if (empty($progressFile)) {
        error_log("BACKUP FILES: updateProg llamado con progressFile vacío");
        return;
    }
    // Asegurar que el archivo se escriba correctamente
    $result = @file_put_contents($progressFile, (string)$prog, LOCK_EX);
    if ($result === false) {
        error_log("ERROR: No se pudo escribir progreso en $progressFile");
    }
    if (!empty($logFile)) {
        logMsg("PROGRESO: $prog%", $logFile);
    }
    // Actualizar heartbeat también si está disponible
    if ($heartbeatFile && file_exists($heartbeatFile)) {
        @file_put_contents($heartbeatFile, date('Y-m-d H:i:s') . " - Progreso: $prog%\n", FILE_APPEND | LOCK_EX);
    }
    // Forzar sincronización del sistema de archivos
    clearstatcache();
}

// Crear archivos de progreso INMEDIATAMENTE
$progressFile = $backupDir . DIRECTORY_SEPARATOR . 'progress_' . $fecha . '.txt';
$logFile = $backupDir . DIRECTORY_SEPARATOR . 'log_' . $fecha . '.txt';
$heartbeatFile = $backupDir . DIRECTORY_SEPARATOR . 'heartbeat_' . $fecha . '.txt';
$checkpointFile = $backupDir . DIRECTORY_SEPARATOR . 'checkpoint_' . $fecha . '.json'; // Para resume

// Si es backup automático, usar nombre diferente
// El ZIP se crea con prefijo "incomplete_" hasta que termine exitosamente
if ($isAutomatic == 1) {
    $zipFile = $backupDir . DIRECTORY_SEPARATOR . 'incomplete_automatic_backup_' . $fecha . '.zip';
    $zipFileFinal = $backupDir . DIRECTORY_SEPARATOR . 'automatic_backup_' . $fecha . '.zip';
} else {
    $zipFile = $backupDir . DIRECTORY_SEPARATOR . 'incomplete_files_dolibarr_' . $fecha . '.zip';
    $zipFileFinal = $backupDir . DIRECTORY_SEPARATOR . 'files_dolibarr_' . $fecha . '.zip';
}

// Crear lock manual si NO es backup automático
$manualLockFile = $backupDir . DIRECTORY_SEPARATOR . 'manual_backup.lock';
if ($isAutomatic == 0) {
    @file_put_contents($manualLockFile, date('Y-m-d H:i:s') . " - PID: " . getmypid() . " - Tipo: $tipo - Backup ID: $backupId\n", LOCK_EX);
    error_log("BACKUP FILES: Lock manual creado: $manualLockFile");
}

// Crear archivos iniciales INMEDIATAMENTE - ANTES de cerrar conexión
$backupTypeLabel = getBackupTranslation('backup_type_files', $backupLang);
$initialLog = "═══════════════════════════════════════════════════════════\n";
$initialLog .= getBackupTranslation('BackupFiles', $backupLang) . " - DOLIBARR\n";
$initialLog .= "═══════════════════════════════════════════════════════════\n";
$initialLog .= getBackupTranslation('backup_started', $backupLang) . "...\n";
$initialLog .= getBackupTranslation('BackupType', $backupLang) . ": $backupTypeLabel\n";
$initialLog .= getBackupTranslation('FileManagerDate', $backupLang) . ": " . date('Y-m-d H:i:s') . "\n";
$initialLog .= "Backup ID: $backupId\n";
$initialLog .= "Script: create_backup_files.php\n";
$initialLog .= "Progress file: $progressFile\n";
$initialLog .= "Log file: $logFile\n";
$initialLog .= "Heartbeat file: $heartbeatFile\n";
$initialLog .= "═══════════════════════════════════════════════════════════\n";

// Escribir todos los archivos de una vez - SIN @ para ver errores
// IMPORTANTE: Hacer esto ANTES de cualquier output o header
try {
    $result1 = file_put_contents($progressFile, '0', LOCK_EX);
    if ($result1 === false) {
        throw new Exception("No se pudo escribir progressFile: $progressFile");
    }
    
    $result2 = file_put_contents($logFile, $initialLog, LOCK_EX);
    if ($result2 === false) {
        throw new Exception("No se pudo escribir logFile: $logFile");
    }
    
    $result3 = file_put_contents($heartbeatFile, date('Y-m-d H:i:s') . " - Archivos iniciales creados\n", LOCK_EX);
    if ($result3 === false) {
        throw new Exception("No se pudo escribir heartbeatFile: $heartbeatFile");
    }
} catch (Exception $e) {
    // Si falla la escritura, loguear el error pero continuar
    error_log("BACKUP FILES ERROR al crear archivos: " . $e->getMessage());
    error_log("BACKUP FILES DEBUG: backupDir = $backupDir");
    error_log("BACKUP FILES DEBUG: backupDir existe = " . (is_dir($backupDir) ? "SI" : "NO"));
    error_log("BACKUP FILES DEBUG: backupDir escribible = " . (is_writable($backupDir) ? "SI" : "NO"));
    // Intentar crear el directorio si no existe
    if (!is_dir($backupDir)) {
        @mkdir($backupDir, 0755, true);
        error_log("BACKUP FILES DEBUG: Intentando crear directorio: $backupDir");
    }
}

// Loggear resultados inmediatamente
error_log("BACKUP FILES DEBUG: progressFile escrito: " . ($result1 !== false ? "SI ($result1 bytes)" : "NO"));
error_log("BACKUP FILES DEBUG: logFile escrito: " . ($result2 !== false ? "SI ($result2 bytes)" : "NO"));
error_log("BACKUP FILES DEBUG: heartbeatFile escrito: " . ($result3 !== false ? "SI ($result3 bytes)" : "NO"));
error_log("BACKUP FILES DEBUG: backupDir: $backupDir");
error_log("BACKUP FILES DEBUG: backupDir existe: " . (is_dir($backupDir) ? "SI" : "NO"));
error_log("BACKUP FILES DEBUG: backupDir escribible: " . (is_writable($backupDir) ? "SI" : "NO"));

// Asegurar que los archivos se escriban al disco
clearstatcache();
usleep(500000); // Esperar 0.5 segundos para asegurar escritura

// Verificar que los archivos se crearon correctamente
if (!file_exists($progressFile)) {
    error_log("ERROR CRÍTICO: No se pudo crear archivo de progreso: $progressFile");
    error_log("ERROR CRÍTICO: Directorio existe: " . (is_dir($backupDir) ? "SI" : "NO"));
    error_log("ERROR CRÍTICO: Directorio escribible: " . (is_writable($backupDir) ? "SI" : "NO"));
} else {
    error_log("BACKUP FILES DEBUG: progressFile existe y tiene " . filesize($progressFile) . " bytes");
}
if (!file_exists($logFile)) {
    error_log("ERROR CRÍTICO: No se pudo crear archivo de log: $logFile");
} else {
    error_log("BACKUP FILES DEBUG: logFile existe y tiene " . filesize($logFile) . " bytes");
}
if (!file_exists($heartbeatFile)) {
    error_log("ERROR CRÍTICO: No se pudo crear archivo heartbeat: $heartbeatFile");
} else {
    error_log("BACKUP FILES DEBUG: heartbeatFile existe y tiene " . filesize($heartbeatFile) . " bytes");
}

// LIMPIAR CUALQUIER OUTPUT ANTERIOR
while (ob_get_level()) {
    ob_end_clean();
}

// Enviar respuesta inmediata al cliente y continuar ejecutando en background
ignore_user_abort(true);
safeExtendExecutionTime(0);

// Limpiar cualquier salida de error previa
@ob_end_clean();

// Headers para ejecución en background (IMPORTANTE: antes de cualquier output)
if (!headers_sent()) {
    header('Connection: close');
    header('Content-Length: 1024');
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    
    // Enviar respuesta JSON simple para que el fetch() no se cuelgue
    echo json_encode(array('status' => 'started', 'backup_id' => $backupId, 'message' => 'Backup de archivos iniciado'));
    flush();
    
    // Si FastCGI, usar fastcgi_finish_request inmediatamente
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
} else {
    // Si los headers ya se enviaron, solo enviar padding
    echo str_repeat(' ', 1024);
    flush();
}

// Si usamos FastCGI, necesitamos enviar más bytes
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    // Enviar padding para asegurar que el cliente reciba la respuesta
    echo str_repeat(' ', 1024);
    flush();
}

// CRÍTICO: Actualizar progreso INMEDIATAMENTE después de cerrar conexión
// Esto asegura que el cliente vea que el proceso comenzó
// Verificar que las rutas no estén vacías antes de escribir
if (!empty($progressFile) && !empty($logFile) && !empty($heartbeatFile)) {
    $result = file_put_contents($progressFile, '1', LOCK_EX);
    error_log("BACKUP FILES DEBUG: Progreso actualizado a 1%: " . ($result !== false ? "SI" : "NO"));
    file_put_contents($logFile, "[INICIO] Proceso iniciado después de cerrar conexión\n", FILE_APPEND | LOCK_EX);
    file_put_contents($heartbeatFile, date('Y-m-d H:i:s') . " - Conexión cerrada, continuando ejecución\n", FILE_APPEND | LOCK_EX);
    clearstatcache(); // Forzar actualización de caché
} else {
    error_log("BACKUP FILES ERROR: Rutas vacías - progressFile: " . (empty($progressFile) ? "VACIO" : $progressFile));
    error_log("BACKUP FILES ERROR: logFile: " . (empty($logFile) ? "VACIO" : $logFile));
    error_log("BACKUP FILES ERROR: heartbeatFile: " . (empty($heartbeatFile) ? "VACIO" : $heartbeatFile));
}

// CRÍTICO: Cargar configuración SIN ejecutar main.inc.php completo (para evitar exit/die)
// main.inc.php tiene verificaciones de seguridad que ejecutan exit() y detienen el script
// En su lugar, cargar directamente conf.php y definir variables necesarias
$confPath = __DIR__ . '/../../../conf/conf.php';
if (!file_exists($confPath)) {
    @file_put_contents($logFile, "❌ ERROR: No se encontró conf.php en: $confPath\n", FILE_APPEND | LOCK_EX);
    @file_put_contents($progressFile, '-1');
    exit;
}

// Heartbeat ya está definido arriba (línea 41), solo actualizar
@file_put_contents($heartbeatFile, date('Y-m-d H:i:s') . " - Cargando conf.php\n", LOCK_EX);

// Cargar conf.php (esto define las variables de BD sin ejecutar main.inc.php)
require_once $confPath;

@file_put_contents($heartbeatFile, date('Y-m-d H:i:s') . " - conf.php cargado\n", FILE_APPEND | LOCK_EX);
@file_put_contents($logFile, "✅ conf.php cargado directamente (sin main.inc.php completo)\n", FILE_APPEND | LOCK_EX);

try {
    // Actualizar heartbeat al entrar al try
    if (file_exists($heartbeatFile)) {
        @file_put_contents($heartbeatFile, date('Y-m-d H:i:s') . " - ✅✅✅ DENTRO DEL TRY PRINCIPAL ✅✅✅\n", FILE_APPEND | LOCK_EX);
    }
    
    // Guardar información del usuario que crea el backup
    // Intentar obtener el usuario desde múltiples fuentes (tanto para manuales como automáticos)
    $user_login = 'unknown';
    $user_id = 0;
        
        // Método 1: Desde parámetros GET/POST (pasados desde setup.php) - PRIORIDAD ALTA
    if (isset($_GET['user_login']) && !empty($_GET['user_login']) && trim($_GET['user_login']) !== '' && trim($_GET['user_login']) !== 'unknown') {
        $user_login = trim($_GET['user_login']);
        $user_id = isset($_GET['user_id']) && !empty($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        if (isset($logFile)) {
            logMsg("Usuario obtenido desde GET: " . $user_login . " (ID: " . $user_id . ")", $logFile);
        }
    } elseif (isset($_POST['user_login']) && !empty($_POST['user_login']) && trim($_POST['user_login']) !== '' && trim($_POST['user_login']) !== 'unknown') {
        $user_login = trim($_POST['user_login']);
        $user_id = isset($_POST['user_id']) && !empty($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        if (isset($logFile)) {
            logMsg("Usuario obtenido desde POST: " . $user_login . " (ID: " . $user_id . ")", $logFile);
        }
    }
    // Método 2: Desde global $user (si está disponible)
    else {
        global $user;
        if (isset($user) && is_object($user)) {
            $user_login = !empty($user->login) ? $user->login : 'unknown';
            $user_id = !empty($user->id) ? intval($user->id) : 0;
        }
        // Método 3: Desde sesión (último recurso)
        elseif (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user'])) {
            if (is_object($_SESSION['user'])) {
                $user_login = !empty($_SESSION['user']->login) ? $_SESSION['user']->login : 'unknown';
                $user_id = !empty($_SESSION['user']->id) ? intval($_SESSION['user']->id) : 0;
            } elseif (is_array($_SESSION['user'])) {
                $user_login = !empty($_SESSION['user']['login']) ? $_SESSION['user']['login'] : 'unknown';
                $user_id = !empty($_SESSION['user']['id']) ? intval($_SESSION['user']['id']) : 0;
            }
        }
    }
    
    $backup_info = array(
        'user_id' => $user_id,
        'user_login' => $user_login,
        'created_at' => date('Y-m-d H:i:s')
    );
    @file_put_contents($backupDir . DIRECTORY_SEPARATOR . 'backup_info_' . $fecha . '.json', json_encode($backup_info));
    
    @file_put_contents($logFile, "Usuario del backup: " . $user_login . " (ID: " . $user_id . ")\n", FILE_APPEND | LOCK_EX);
    
    // Registrar en log de actividad
    try {
        $libPath = __DIR__ . '/../lib/filemanager.lib.php';
        if (file_exists($libPath)) {
            if (!function_exists('logFileManagerActivity')) {
                require_once $libPath;
            }
            if (function_exists('logFileManagerActivity')) {
                // NOTA: No sobrescribir $zipFile - usar el nombre final para el log
                logFileManagerActivity('create_backup', $zipFileFinal, $user_id, "Backup de archivos iniciado - ID: $fecha");
            }
        }
    } catch (Exception $e) {
        error_log("No se pudo registrar backup en log de actividad: " . $e->getMessage());
    }

    // Las funciones logMsg() y updateProg() ya están definidas arriba (antes del try)

    // INICIO
    $tipoTexto = ($tipo == 'files_only') ? 'ARCHIVOS' : (($tipo == 'database_only') ? 'BASE DE DATOS' : 'COMPLETO');
    logMsg("═══════════════════════════════════════════════════════════", $logFile);
    logMsg("TIPO DE COPIA DE SEGURIDAD: $tipoTexto", $logFile);
    logMsg("═══════════════════════════════════════════════════════════", $logFile);
    logMsg("Modo: " . ($isAutomatic == 1 ? 'AUTOMÁTICO' : 'MANUAL'), $logFile);
    logMsg("Tipo (código): $tipo", $logFile);
    logMsg("Backup ID: $fecha", $logFile);
    logMsg("Fecha: " . date('Y-m-d H:i:s'), $logFile);
    logMsg("═══════════════════════════════════════════════════════════", $logFile);
    logMsg("", $logFile);
    logMsg("=== BACKUP DE ARCHIVOS INICIADO ===", $logFile);
    logMsg("IMPORTANTE: Se realizará una COPIA SOLO LECTURA. Dolibarr no será modificado.", $logFile);

    // Actualizar heartbeat para confirmar que llegamos aquí
    @file_put_contents($heartbeatFile, date('Y-m-d H:i:s') . " - Dentro del try, iniciando proceso\n", FILE_APPEND | LOCK_EX);

    // PROGRESO 5% - ACTUALIZAR INMEDIATAMENTE
    updateProg(5, $progressFile, $logFile, $heartbeatFile);
    // Forzar escritura al disco
    clearstatcache();
    usleep(50000); // Esperar 0.05 segundos para asegurar escritura
    logMsg("Obteniendo ruta raíz de Dolibarr...", $logFile);
    
    // Obtener ruta raíz de Dolibarr
    $dolibarrRoot = null;
    
    // Método 1: Desde variable global (conf.php ya fue cargado)
    global $dolibarr_main_document_root;
    if (!empty($dolibarr_main_document_root) && is_dir($dolibarr_main_document_root)) {
        $dolibarrRoot = realpath($dolibarr_main_document_root);
        logMsg("Ruta desde variable global: $dolibarrRoot", $logFile);
    }
    
    // Método 2: Calcular desde ubicación del script (filemanager está en custom/filemanager)
    if (!$dolibarrRoot) {
        $calculatedRoot = realpath(__DIR__ . '/../../..');
        if ($calculatedRoot && is_dir($calculatedRoot)) {
            $dolibarrRoot = $calculatedRoot;
            logMsg("Ruta calculada: $dolibarrRoot", $logFile);
        }
    }
    
    // Verificación final
    if (!$dolibarrRoot || !is_dir($dolibarrRoot)) {
        logMsg("❌ ERROR: No se pudo determinar la ruta raíz", $logFile);
        logMsg("Métodos intentados: variable global, conf.php, cálculo relativo, DOCUMENT_ROOT", $logFile);
        updateProg(-1, $progressFile, $logFile, $heartbeatFile);
            exit;
    }
    
    logMsg("✅ Ruta raíz de Dolibarr: $dolibarrRoot", $logFile);
    logMsg("   Verificación: " . (is_dir($dolibarrRoot) ? 'OK' : 'FALLO'), $logFile);

    // PROGRESO 10%
    updateProg(10, $progressFile, $logFile, $heartbeatFile);
    logMsg("Listando archivos y carpetas...", $logFile);

    // Función recursiva para listar archivos (sin mostrar progreso de conteo)
    // Usar closure para tener acceso a $dolibarrRoot
    $listFiles = function($dir, $excludeDirs = [], $progressFile, $logFile, &$totalFiles, $rootDir) use (&$listFiles) {
        // Verificar límite de memoria
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        $memoryLimitBytes = is_numeric($memoryLimit) ? $memoryLimit : 1024 * 1024 * 1024; // Default 1GB si no está en bytes
        
        // Si usamos más del 80% de memoria, loguear advertencia
        if ($memoryUsage > ($memoryLimitBytes * 0.8)) {
            error_log("ADVERTENCIA: Uso de memoria alto: " . round($memoryUsage/1024/1024, 2) . " MB");
        }
        
        $files = [];
        
        // Verificar que el directorio existe y es legible
        if (!is_dir($dir) || !is_readable($dir)) {
            return $files;
        }
        
        // Intentar leer el directorio
        $items = @scandir($dir);
        if ($items === false) {
            error_log("ERROR: No se pudo leer directorio: $dir");
            return $files;
        }
        
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;
            
            $fullPath = $dir . DIRECTORY_SEPARATOR . $item;
            
            // Excluir ciertos directorios
            $shouldExclude = false;
            foreach ($excludeDirs as $exclude) {
                if (strpos($fullPath, $exclude) !== false) {
                    $shouldExclude = true;
                    break;
                }
            }
            
            if ($shouldExclude) {
                continue;
            }
            
            // Verificar si es enlace simbólico problemático
            if (is_link($fullPath)) {
                continue; // Saltar enlaces simbólicos para evitar loops
            }
            
            if (is_dir($fullPath)) {
                // Verificar profundidad de recursión (evitar loops infinitos)
                $depth = substr_count($fullPath, DIRECTORY_SEPARATOR) - substr_count($rootDir, DIRECTORY_SEPARATOR);
                if ($depth > 50) {
                    error_log("ADVERTENCIA: Profundidad de directorio muy alta, omitiendo: $fullPath");
                    continue;
                }
                
                try {
                    // Recursivamente listar subdirectorios
                    $subfiles = $listFiles($fullPath, $excludeDirs, $progressFile, $logFile, $totalFiles, $rootDir);
                    $files = array_merge($files, $subfiles);
                } catch (Exception $e) {
                    error_log("ERROR listando subdirectorio $fullPath: " . $e->getMessage());
                    continue;
                } catch (Error $e) {
                    error_log("ERROR FATAL listando subdirectorio $fullPath: " . $e->getMessage());
                    continue;
                }
            } else {
                // Verificar que el archivo existe antes de agregarlo
                if (file_exists($fullPath) && is_readable($fullPath)) {
                    // Verificar si está en la lista de archivos excluidos
                    $fileName = basename($fullPath);
                    global $excludeFiles;
                    if (isset($excludeFiles) && in_array($fileName, $excludeFiles)) {
                        continue; // Saltar archivos excluidos
                    }
                    $files[] = $fullPath;
                    $totalFiles++;
                }
            }
        }
        
        return $files;
    };

    // Directorios a excluir
    $excludeDirs = [
        '/custom/filemanager/backups',
        '/custom/filemanager/cache',
        '/custom/filemanager/deletedfiles',
        '/documents/ckeditor',
        '/includes',
        '/install',
        '/_dev',
        '/tmp',
        '/.git'
    ];
    
    // Archivos específicos a excluir (muy grandes o innecesarios)
    $excludeFiles = [
        'filemanager.zip',      // Archivo de instalación del módulo (muy grande)
        '.DS_Store',            // Archivos de macOS
        'Thumbs.db',            // Archivos de Windows
        'desktop.ini'           // Archivos de Windows
    ];

    $totalFiles = 0;
    $allFiles = array();
    $filesListFile = $backupDir . DIRECTORY_SEPARATOR . 'filelist_' . $fecha . '.json';
    $listingCheckpointFile = $backupDir . DIRECTORY_SEPARATOR . 'listing_checkpoint_' . $fecha . '.json';
    $preAnalyzedFile = $backupDir . DIRECTORY_SEPARATOR . 'pre_analyzed_files.json';
    
    // ========== OPCIÓN 1: USAR LISTA PRE-ANALIZADA (MÁS RÁPIDO) ==========
    // El análisis ya guardó la lista de archivos, ¡usarla directamente!
    if (empty($allFiles) && file_exists($preAnalyzedFile)) {
        logMsg("🚀 MODO TURBO: Usando lista del análisis previo...", $logFile);
        $preAnalyzedData = @json_decode(@file_get_contents($preAnalyzedFile), true);
        
        if ($preAnalyzedData && isset($preAnalyzedData['files']) && is_array($preAnalyzedData['files'])) {
            $allFiles = $preAnalyzedData['files'];
            $actualFileCount = count($allFiles);
            $analysisTime = $preAnalyzedData['timestamp'] ?? 'desconocido';
            
            logMsg("   ✅ Lista cargada: " . number_format($actualFileCount) . " archivos", $logFile);
            logMsg("   📅 Análisis realizado: $analysisTime", $logFile);
            logMsg("   ⚡ ¡SIN NECESIDAD DE LISTAR DE NUEVO!", $logFile);
            
            // Guardar como lista de este backup específico
            @file_put_contents($filesListFile, json_encode($allFiles));
            
            // Actualizar heartbeat
            @file_put_contents($heartbeatFile, date('Y-m-d H:i:s') . " - Lista pre-analizada cargada: $actualFileCount archivos\n", FILE_APPEND | LOCK_EX);
            
            // Saltar TODA la fase de listado
            $listProgress = 30;
            updateProg($listProgress, $progressFile, $logFile, $heartbeatFile);
            
            // Eliminar archivo pre-analizado (ya no se necesita)
            @unlink($preAnalyzedFile);
        } else {
            logMsg("   ⚠️ Lista pre-analizada corrupta, usando método tradicional...", $logFile);
            $allFiles = array();
        }
    }
    
    // ========== OPCIÓN 2: VERIFICAR SI EXISTE LISTA DE ESTE BACKUP ==========
    if (empty($allFiles) && file_exists($filesListFile) && filesize($filesListFile) > 100) {
        logMsg("📂 Cargando lista de archivos guardada...", $logFile);
        $savedFilesList = @file_get_contents($filesListFile);
        $allFiles = @json_decode($savedFilesList, true);
        
        if (is_array($allFiles) && count($allFiles) > 0) {
            $actualFileCount = count($allFiles);
            logMsg("   ✅ Lista cargada: " . number_format($actualFileCount) . " archivos", $logFile);
            logMsg("   ⏱️ Tiempo: instantáneo (lista pre-cargada)", $logFile);
            
            // Actualizar heartbeat
            @file_put_contents($heartbeatFile, date('Y-m-d H:i:s') . " - Lista cargada: $actualFileCount archivos\n", FILE_APPEND | LOCK_EX);
            
            // Saltar la fase de listado
            $listProgress = 30;
            updateProg($listProgress, $progressFile, $logFile, $heartbeatFile);
        } else {
            logMsg("   ⚠️ Lista guardada vacía o corrupta, re-listando...", $logFile);
            $allFiles = array(); // Resetear para forzar re-listado
        }
    }
    
    // ========== OPCIÓN 3: VERIFICAR CHECKPOINT DE LISTADO PARCIAL ==========
    if (empty($allFiles) && file_exists($listingCheckpointFile)) {
        logMsg("📂 Detectado listado parcial, reanudando...", $logFile);
        $listingCheckpoint = @json_decode(@file_get_contents($listingCheckpointFile), true);
        
        if ($listingCheckpoint && isset($listingCheckpoint['files']) && isset($listingCheckpoint['dirs_processed'])) {
            $allFiles = $listingCheckpoint['files'];
            $dirsAlreadyProcessed = $listingCheckpoint['dirs_processed'];
            logMsg("   ✅ Checkpoint cargado: " . number_format(count($allFiles)) . " archivos de " . count($dirsAlreadyProcessed) . " directorios", $logFile);
            
            // Actualizar heartbeat
            @file_put_contents($heartbeatFile, date('Y-m-d H:i:s') . " - Reanudando listado desde checkpoint\n", FILE_APPEND | LOCK_EX);
        }
    }
    
    // Si no hay lista cargada, hacer el listado INCREMENTAL (resistente a timeouts)
    if (empty($allFiles) || !isset($dirsAlreadyProcessed)) {
        $dirsAlreadyProcessed = isset($dirsAlreadyProcessed) ? $dirsAlreadyProcessed : [];
        
        // Actualizar heartbeat antes del listado
        @file_put_contents($heartbeatFile, date('Y-m-d H:i:s') . " - Iniciando listado incremental\n", FILE_APPEND | LOCK_EX);
        
        $startListTime = microtime(true);
        $dirsProcessedThisRun = 0;
        $filesFoundThisRun = 0;
        
        // Configuración dinámica basada en el entorno
        // En localhost: procesar todos los directorios de una vez
        // En producción: limitar para no exceder el timeout
        $isLocalhost = (isset($_SERVER['HTTP_HOST']) && (
            strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
            strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false
        )) || php_sapi_name() === 'cli';
        
        // Detectar límite de tiempo real
        $maxExecTime = ini_get('max_execution_time');
        $maxExecTime = empty($maxExecTime) ? 300 : (int)$maxExecTime;
        
        // Calcular cuántos directorios procesar
        // PRODUCCIÓN: El hosting mata procesos MUY RÁPIDO, debemos ser ULTRA conservadores
        if ($isLocalhost || $maxExecTime == 0 || $maxExecTime >= 300) {
            $maxDirsPerRun = 9999; // Sin límite en localhost
            $maxTimePerRun = 0; // Sin límite de tiempo
        } else {
            // PRODUCCIÓN: ULTRA conservador - el hosting puede matar en 30s
            $maxDirsPerRun = 10; // SOLO 10 directorios por ejecución
            $maxTimePerRun = 15; // Máximo 15 segundos por ejecución
        }
        
        $listingComplete = false;
        
        // Mostrar configuración detectada (DESPUÉS de definir las variables)
        logMsg("📂 LISTADO INCREMENTAL DE ARCHIVOS", $logFile);
        logMsg("═══════════════════════════════════════════════════════════", $logFile);
        $envInfo = $isLocalhost ? "🏠 LOCAL (sin límites)" : "🌐 PRODUCCIÓN (límites activos)";
        logMsg("Entorno: $envInfo", $logFile);
        logMsg("Max directorios/ejecución: " . ($maxDirsPerRun >= 9999 ? "ILIMITADO" : $maxDirsPerRun), $logFile);
        logMsg("Max tiempo/ejecución: " . ($maxTimePerRun <= 0 ? "ILIMITADO" : $maxTimePerRun . "s"), $logFile);
        logMsg("", $logFile);
        
        try {
            // Obtener lista de directorios de nivel 0 y 1
            $topDirs = [];
            $topItems = @scandir($dolibarrRoot);
            if ($topItems !== false) {
                foreach ($topItems as $item) {
                    if ($item == '.' || $item == '..') continue;
                    $fullPath = $dolibarrRoot . DIRECTORY_SEPARATOR . $item;
                    
                    // Verificar exclusiones
                    $shouldExclude = false;
                    foreach ($excludeDirs as $exclude) {
                        if (strpos($fullPath, $exclude) !== false) {
                            $shouldExclude = true;
                            break;
                        }
                    }
                    if ($shouldExclude) continue;
                    
                    if (is_dir($fullPath) && !is_link($fullPath)) {
                        $topDirs[] = $fullPath;
                    } elseif (is_file($fullPath) && is_readable($fullPath)) {
                        // Archivos en raíz
                        $fileName = basename($fullPath);
                        if (!in_array($fileName, $excludeFiles)) {
                            $allFiles[] = $fullPath;
                            $filesFoundThisRun++;
                        }
                    }
                }
            }
            
            logMsg("   Directorios principales encontrados: " . count($topDirs), $logFile);
            logMsg("   Directorios ya procesados: " . count($dirsAlreadyProcessed), $logFile);
            
            // Procesar cada directorio de nivel 1
            foreach ($topDirs as $topDir) {
                // VERIFICAR TIEMPO ANTES de procesar cada directorio
                $elapsedTime = microtime(true) - $startListTime;
                if ($maxTimePerRun > 0 && $elapsedTime >= $maxTimePerRun) {
                    logMsg("   ⏸️ Pausa preventiva después de " . round($elapsedTime, 1) . "s", $logFile);
                    break;
                }
                
                // Saltar si ya fue procesado
                if (in_array($topDir, $dirsAlreadyProcessed)) {
                    continue;
                }
                
                // GUARDAR CHECKPOINT ANTES de procesar (por si el hosting mata durante el listado)
                $listingCheckpointData = [
                    'backup_id' => $fecha,
                    'last_update' => date('Y-m-d H:i:s'),
                    'files' => $allFiles,
                    'dirs_processed' => $dirsAlreadyProcessed,
                    'total_dirs' => count($topDirs),
                    'phase' => 'listing',
                    'current_dir' => basename($topDir)
                ];
                @file_put_contents($listingCheckpointFile, json_encode($listingCheckpointData));
                
                // Extender tiempo de ejecución
                safeExtendExecutionTime(0);
                
                // Actualizar heartbeat
                @file_put_contents($heartbeatFile, date('Y-m-d H:i:s') . " - Listando: " . basename($topDir) . "\n", FILE_APPEND | LOCK_EX);
                
                logMsg("   🔄 Procesando: " . basename($topDir) . "...", $logFile);
                
                // Listar este directorio recursivamente
                $dirFiles = $listFiles($topDir, $excludeDirs, $progressFile, $logFile, $totalFiles, $dolibarrRoot);
                $allFiles = array_merge($allFiles, $dirFiles);
                $filesFoundThisRun += count($dirFiles);
                
                // Marcar como procesado
                $dirsAlreadyProcessed[] = $topDir;
                $dirsProcessedThisRun++;
                
                // GUARDAR CHECKPOINT DESPUÉS de procesar
                $listingCheckpointData = [
                    'backup_id' => $fecha,
                    'last_update' => date('Y-m-d H:i:s'),
                    'files' => $allFiles,
                    'dirs_processed' => $dirsAlreadyProcessed,
                    'total_dirs' => count($topDirs),
                    'phase' => 'listing'
                ];
                @file_put_contents($listingCheckpointFile, json_encode($listingCheckpointData));
                
                logMsg("   ✅ " . basename($topDir) . ": " . number_format(count($dirFiles)) . " archivos (Total: " . number_format(count($allFiles)) . ")", $logFile);
                
                // Verificar si debemos pausar DESPUÉS de procesar
                $elapsedTime = microtime(true) - $startListTime;
                $shouldPause = false;
                $pauseReason = '';
                
                // Pausar si excedemos el número máximo de directorios
                if ($dirsProcessedThisRun >= $maxDirsPerRun) {
                    $shouldPause = true;
                    $pauseReason = "después de $dirsProcessedThisRun directorios";
                }
                
                // Pausar si excedemos el tiempo máximo
                if ($maxTimePerRun > 0 && $elapsedTime >= $maxTimePerRun) {
                    $shouldPause = true;
                    $pauseReason = "después de " . round($elapsedTime, 1) . "s";
                }
                
                if ($shouldPause) {
                    logMsg("   ⏸️ Pausa $pauseReason", $logFile);
                    break;
                }
            }
            
            // Verificar si el listado está completo
            if (count($dirsAlreadyProcessed) >= count($topDirs)) {
                $listingComplete = true;
                logMsg("   🎉 LISTADO COMPLETO", $logFile);
                
                // Eliminar checkpoint de listado y guardar lista final
                @unlink($listingCheckpointFile);
                @file_put_contents($filesListFile, json_encode($allFiles));
                logMsg("   ✓ Lista guardada: " . basename($filesListFile), $logFile);
            } else {
                $remaining = count($topDirs) - count($dirsAlreadyProcessed);
                logMsg("   ⏳ Faltan $remaining directorios por listar", $logFile);
                logMsg("   ⏳ El próximo reinicio continuará automáticamente", $logFile);
            }
            
            $listTime = round(microtime(true) - $startListTime, 2);
            $actualFileCount = count($allFiles);
            
            logMsg("   Tiempo esta ejecución: $listTime segundos", $logFile);
            logMsg("   Total archivos hasta ahora: " . number_format($actualFileCount), $logFile);
            
            // Actualizar heartbeat
            @file_put_contents($heartbeatFile, date('Y-m-d H:i:s') . " - Listado: $actualFileCount archivos\n", FILE_APPEND | LOCK_EX);
            
            // Guardar checkpoint para la fase de ZIP
            $initialCheckpoint = [
                'backup_id' => $fecha,
                'last_update' => date('Y-m-d H:i:s'),
                'files_added' => 0,
                'total_files' => $actualFileCount,
                'zip_size_mb' => 0,
                'bytes_processed' => 0,
                'last_index' => -1,
                'status' => 'in_progress',
                'phase' => $listingComplete ? 'ready_to_zip' : 'listing',
                'files_list_file' => $filesListFile,
                'listing_complete' => $listingComplete
            ];
            @file_put_contents($checkpointFile, json_encode($initialCheckpoint, JSON_PRETTY_PRINT));
            
            // Actualizar progreso
            $listProgress = $listingComplete ? 30 : (10 + (count($dirsAlreadyProcessed) / max(1, count($topDirs)) * 20));
            updateProg($listProgress, $progressFile, $logFile, $heartbeatFile);
            
            // Si el listado no está completo, salir para que el watchdog reinicie
            if (!$listingComplete) {
                logMsg("", $logFile);
                logMsg("⏳ LISTADO EN PROGRESO - Esperando reinicio automático...", $logFile);
                exit(0);
            }
            
        } catch (Exception $e) {
            logMsg("ERROR en listado de archivos: " . $e->getMessage(), $logFile);
            @file_put_contents($heartbeatFile, date('Y-m-d H:i:s') . " - ERROR en listado: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
            // NO salir, guardar lo que tenemos y continuar en el siguiente reinicio
        } catch (Error $e) {
            logMsg("ERROR FATAL en listado de archivos: " . $e->getMessage(), $logFile);
            @file_put_contents($heartbeatFile, date('Y-m-d H:i:s') . " - ERROR FATAL en listado: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
            // NO salir, guardar lo que tenemos y continuar en el siguiente reinicio
        }
    } // Fin del if (empty($allFiles))

    // PROGRESO 30%
    updateProg(30, $progressFile, $logFile, $heartbeatFile);
    logMsg("═══════════════════════════════════════════════════════════", $logFile);
    logMsg("CREANDO ARCHIVO ZIP", $logFile);
    logMsg("═══════════════════════════════════════════════════════════", $logFile);
    logMsg("Destino: $zipFile", $logFile);
    logMsg("", $logFile);
    
    // ========== DIAGNÓSTICO DEL SISTEMA ANTES DE CREAR EL ZIP ==========
    logMsg("🔍 DIAGNÓSTICO PRE-BACKUP:", $logFile);
    $backupDirCheck = dirname($zipFile);
    logMsg("   • Directorio de backup: " . $backupDirCheck, $logFile);
    logMsg("   • Es escribible: " . (is_writable($backupDirCheck) ? '✓ Sí' : '✗ No'), $logFile);
    
    // Verificar límites del sistema
    $fsIssues = checkFilesystemLimits($backupDirCheck, $logFile);
    if (!empty($fsIssues)) {
        logMsg("", $logFile);
        logMsg("⚠️ ADVERTENCIAS DEL SISTEMA:", $logFile);
        foreach ($fsIssues as $issue) {
            logMsg("   " . $issue, $logFile);
        }
        logMsg("", $logFile);
        logMsg("💡 El backup continuará, pero podría fallar si los recursos son insuficientes.", $logFile);
    } else {
        logMsg("   ✓ Límites del sistema correctos", $logFile);
    }
    logMsg("", $logFile);
    
    // ========== FORZAR DIRECTORIO TEMPORAL PARA ZIPARCHIVE ==========
    // El hosting borra archivos en /tmp - forzamos que TODO se escriba en nuestro directorio
    $originalTmpDir = sys_get_temp_dir();
    $safeTmpDir = $backupDir;
    
    // Forzar TODAS las variables de entorno de directorios temporales
    putenv("TMPDIR=" . $safeTmpDir);
    putenv("TMP=" . $safeTmpDir);
    putenv("TEMP=" . $safeTmpDir);
    
    // Forzar para PHP también
    if (function_exists('ini_set')) {
        @ini_set('sys_temp_dir', $safeTmpDir);
    }
    
    // Verificar que el cambio funcionó
    $newTmpDir = sys_get_temp_dir();
    logMsg("🔒 PROTECCIÓN ANTI-BORRADO ACTIVADA:", $logFile);
    logMsg("   • Directorio temporal original: " . $originalTmpDir, $logFile);
    logMsg("   • Directorio temporal forzado: " . $safeTmpDir, $logFile);
    logMsg("   • sys_get_temp_dir() actual: " . $newTmpDir, $logFile);
    
    // Crear archivo .htaccess para proteger el directorio (por si acaso)
    $htaccessPath = $backupDir . '/.htaccess';
    if (!file_exists($htaccessPath)) {
        @file_put_contents($htaccessPath, "Options -Indexes\n<Files \"*.part\">\n  Order Allow,Deny\n  Allow from all\n</Files>\n");
    }
    
    // ========== SISTEMA DE CHECKPOINT/RESUME ==========
    // Verificar si existe un backup previo que podemos continuar
    $resumeFromIndex = 0;
    $isResuming = false;
    
    if (file_exists($checkpointFile) && file_exists($zipFile)) {
        $checkpointData = @json_decode(file_get_contents($checkpointFile), true);
        
        if ($checkpointData && isset($checkpointData['last_index']) && $checkpointData['status'] === 'in_progress') {
            // Hay un backup incompleto - intentar resumir
            $lastIndex = (int)$checkpointData['last_index'];
            $lastFilesAdded = (int)($checkpointData['files_added'] ?? 0);
            $zipSizeMB = $checkpointData['zip_size_mb'] ?? 0;
            
            logMsg("", $logFile);
            logMsg("🔄 ═══════════════════════════════════════════════════════════", $logFile);
            logMsg("🔄 DETECTADO BACKUP INCOMPLETO - REANUDANDO", $logFile);
            logMsg("🔄 ═══════════════════════════════════════════════════════════", $logFile);
            logMsg("   Checkpoint encontrado: " . basename($checkpointFile), $logFile);
            logMsg("   Último índice: " . number_format($lastIndex), $logFile);
            logMsg("   Archivos ya añadidos: " . number_format($lastFilesAdded), $logFile);
            logMsg("   Tamaño ZIP actual: {$zipSizeMB} MB", $logFile);
            logMsg("", $logFile);
            
            // Verificar que el ZIP existe y tiene tamaño
            clearstatcache(true, $zipFile);
            $currentZipSize = @filesize($zipFile);
            
            if ($currentZipSize > 1000) {
                // Intentar reabrir el ZIP existente
                $zip = new ZipArchive();
                $openResult = $zip->open($zipFile);
                
                if ($openResult === TRUE) {
                    $resumeFromIndex = $lastIndex + 1; // Empezar desde el siguiente
                    $isResuming = true;
                    logMsg("   ✅ ZIP reabierto exitosamente", $logFile);
                    logMsg("   📁 Archivos en ZIP: " . $zip->numFiles, $logFile);
                    logMsg("   🔄 Reanudando desde índice: " . number_format($resumeFromIndex), $logFile);
                    logMsg("", $logFile);
                } else {
                    logMsg("   ⚠️ No se pudo reabrir el ZIP, creando nuevo...", $logFile);
                }
            } else {
                logMsg("   ⚠️ ZIP vacío o muy pequeño, creando nuevo...", $logFile);
            }
        }
    }
    
    // Si no estamos reanudando, crear nuevo ZIP
    if (!$isResuming) {
        $zip = new ZipArchive();
        $openResult = $zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        
        logMsg("ZIP inicializado: " . ($openResult === TRUE ? '✓ OK' : '✗ ERROR: ' . $openResult), $logFile);
        
        // Intentar garantizar la existencia del archivo en disco
        if ($openResult === TRUE) {
            clearstatcache(true, $zipFile);
            if (!file_exists($zipFile)) {
                @touch($zipFile);
                clearstatcache(true, $zipFile);
            }
            
            if (file_exists($zipFile)) {
                logMsg("   ✓ Archivo ZIP verificado en disco: " . basename($zipFile), $logFile);
            } else {
                logMsg("   ⚠️ Advertencia: El archivo ZIP aún no está visible en disco.", $logFile);
            }
        }
    }
    
    if ($openResult === TRUE) {
        // Si estamos reanudando, usar los valores del checkpoint
        $addedFiles = $isResuming ? ($checkpointData['files_added'] ?? 0) : 0;
        $totalToAdd = count($allFiles);
        $totalBytes = $isResuming ? ($checkpointData['bytes_processed'] ?? 0) : 0;
        $errorFiles = 0;
        
        logMsg("", $logFile);
        logMsg("═══════════════════════════════════════════════════════════", $logFile);
        logMsg("AGREGANDO ARCHIVOS AL ZIP", $logFile);
        logMsg("═══════════════════════════════════════════════════════════", $logFile);
        logMsg("Total archivos a procesar: " . number_format($totalToAdd), $logFile);
        logMsg("Directorio raíz: $dolibarrRoot", $logFile);
        logMsg("", $logFile);
        // ========== CONFIGURACIÓN DINÁMICA SEGÚN ENTORNO ==========
        $memoryLimit = ini_get('memory_limit');
        $memoryBytes = return_bytes($memoryLimit);
        
        // Detectar si es hosting compartido (tienen timeouts agresivos)
        $serverSoftware = $_SERVER['SERVER_SOFTWARE'] ?? '';
        $hostName = php_uname('n');
        $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
        
        $isSharedHosting = (
            strpos($serverSoftware, 'LiteSpeed') !== false ||
            strpos(strtolower($hostName), 'hostinger') !== false ||
            strpos(strtolower($hostName), 'bluehost') !== false ||
            strpos(strtolower($hostName), 'godaddy') !== false ||
            strpos(strtolower($hostName), 'siteground') !== false ||
            strpos($docRoot, '/home/') === 0 ||
            strpos($docRoot, '/public_html') !== false
        );
        
        // Detectar si es localhost para máxima velocidad
        $isLocalhost = (isset($_SERVER['HTTP_HOST']) && (
            strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
            strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false
        )) || php_sapi_name() === 'cli';
        
        // CONFIGURACIÓN POR ENTORNO
        if ($isLocalhost) {
            // 🚀 LOCALHOST/CLI: RÁPIDO pero respetando memoria
            // Flush frecuente para evitar agotar RAM
            $flushInterval = 5000;  // Flush cada 5,000 archivos (balance velocidad/memoria)
            $maxMemoryFile = 50 * 1024 * 1024;  // 50MB archivos en memoria
            $serverType = "🏠 LOCALHOST (VELOCIDAD OPTIMIZADA)";
        } elseif ($isSharedHosting) {
            // 🐌 HOSTING COMPARTIDO: modo ULTRA-conservador
            // Los hostings matan procesos después de 30 segundos o menos
            $flushInterval = 200;  // Flush cada 200 archivos (MUY frecuente)
            $maxMemoryFile = 5 * 1024 * 1024;  // Solo 5MB para hosting compartido
            $serverType = "🏢 Hosting compartido (modo ULTRA-conservador)";
        } else {
            // 🖥️ SERVIDOR DEDICADO/VPS: balance velocidad/estabilidad
            if ($memoryBytes >= 512 * 1024 * 1024) {
                $flushInterval = 10000;  // 512MB+ = flush cada 10,000
                $maxMemoryFile = 100 * 1024 * 1024;  // 100MB
            } elseif ($memoryBytes >= 256 * 1024 * 1024) {
                $flushInterval = 5000;  // 256MB+ = flush cada 5,000
                $maxMemoryFile = 50 * 1024 * 1024;   // 50MB
            } elseif ($memoryBytes >= 128 * 1024 * 1024) {
                $flushInterval = 2000;  // 128MB+ = flush cada 2,000
                $maxMemoryFile = 30 * 1024 * 1024;   // 30MB
            } else {
                $flushInterval = 1000;  // <128MB = flush cada 1,000
                $maxMemoryFile = 20 * 1024 * 1024;   // 20MB
            }
            $serverType = "🖥️ Servidor dedicado/VPS";
        }
        
        logMsg("⚙️ CONFIGURACIÓN DINÁMICA DE BACKUP:", $logFile);
        logMsg("   • Entorno: $serverType", $logFile);
        logMsg("   • Memoria disponible: " . $memoryLimit . " (" . round($memoryBytes/1024/1024) . " MB)", $logFile);
        logMsg("   • Flush al disco cada: " . number_format($flushInterval) . " archivos", $logFile);
        logMsg("   • Archivos en memoria: hasta " . round($maxMemoryFile/1024/1024) . " MB", $logFile);
        logMsg("   • max_execution_time: " . (ini_get('max_execution_time') ?: "ilimitado") . "s", $logFile);
        if ($isLocalhost) {
            logMsg("   🚀 MODO TURBO ACTIVADO - Sin pausas innecesarias", $logFile);
        }
        logMsg("", $logFile);
        
        $batchSize = 50; // Logs más frecuentes
        $lastLogSize = 0;
        $startTime = microtime(true);
        $lastBatchTime = $startTime;
        $currentFolder = '';
        $lastFlushTime = $startTime;
        $flushCount = 0;
        
        // Registro inicial de tiempo
        logMsg("⏱️ Tiempo de inicio: " . date('H:i:s'), $logFile);
        logMsg("⏱️ Límite max_execution_time: " . ini_get('max_execution_time') . "s (0=ilimitado)", $logFile);
        if ($isResuming) {
            logMsg("🔄 MODO RESUME: Saltando primeros " . number_format($resumeFromIndex) . " archivos", $logFile);
        }
        logMsg("", $logFile);
        
        foreach ($allFiles as $index => $file) {
            // ========== SALTAR ARCHIVOS YA PROCESADOS (RESUME) ==========
            if ($index < $resumeFromIndex) {
                continue; // Saltar archivos ya en el ZIP
            }
            
            try {
                // ========== EXTENDER TIEMPO CADA 500 ARCHIVOS ==========
                // CRÍTICO: Evitar que el hosting mate el proceso por timeout
                if ($addedFiles > 0 && $addedFiles % 500 == 0) {
                    safeExtendExecutionTime(0);
                    // Actualizar heartbeat
                    @file_put_contents($heartbeatFile, date('Y-m-d H:i:s') . " - Procesando archivo $addedFiles\n", FILE_APPEND | LOCK_EX);
                }
                
                // Obtener tamaño del archivo
                $fileSize = @filesize($file);
                if ($fileSize === false) $fileSize = 0;
                $totalBytes += $fileSize;
                
                // Nombre relativo dentro del ZIP
                $localName = str_replace($dolibarrRoot, '', $file);
                $localName = ltrim($localName, DIRECTORY_SEPARATOR);
                $localName = 'dolibarr_' . $fecha . DIRECTORY_SEPARATOR . $localName;
                
                // Detectar carpeta actual para log
                $fileFolder = dirname($localName);
                
                // SOLO LECTURA - no modifica archivos originales
                // Usar addFile() para archivos grandes (lee del disco sin memoria)
                // Usar addFromString() para archivos pequeños (mejor compatibilidad)

                // Verificar si es un archivo SQL muy grande (>10MB) - excluir
                $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                $isLargeSql = in_array($extension, ['sql', 'sql.gz', 'sql.bz2', 'dump']) && $fileSize > 10 * 1024 * 1024;

                if ($isLargeSql) {
                    // Archivo SQL muy grande: excluir del backup
                    $errorFiles++;
                    if ($addedFiles % 1000 == 0) { // Solo loggear cada 1000 para no saturar
                        logMsg("  ⏭️ Archivo SQL grande excluido: " . basename($file) . " (" . round($fileSize/1024/1024, 2) . " MB > 10MB límite)", $logFile);
                    }
                } elseif ($fileSize > $maxMemoryFile) {
                    // Archivo grande no-SQL: usar addFile() - lee del disco directamente
                    // NO salta archivos grandes, los incluye usando addFile()
                    if ($zip->addFile($file, $localName)) {
                        $addedFiles++;
                        if ($addedFiles % 500 == 0) { // Loggear cada 500 archivos grandes
                            logMsg("  📁 Archivo grande incluido: " . basename($file) . " (" . round($fileSize/1024/1024, 2) . " MB)", $logFile);
                        }
                    } else {
                        $errorFiles++;
                        logMsg("  ❌ Error agregando archivo grande: " . basename($file), $logFile);
                    }
                } else {
                    // Archivo normal: cargar en memoria y agregar
                    $fileContent = @file_get_contents($file);
                    if ($fileContent !== false) {
                        if ($zip->addFromString($localName, $fileContent)) {
                            $addedFiles++;
                        } else {
                            $errorFiles++;
                        }
                        unset($fileContent); // Liberar memoria inmediatamente
                    } else {
                        // Archivo vacío o no leíble - añadir entrada vacía
                        $zip->addFromString($localName, '');
                        $addedFiles++;
                    }
                }
                
                // ========== FLUSH PERIÓDICO: Por intervalo O por memoria alta ==========
                // Esto FUERZA la escritura al disco y evita agotar la RAM
                $currentMemory = memory_get_usage(true);
                $memoryThreshold = $memoryBytes * 0.7; // 70% de la memoria disponible
                $shouldFlush = ($addedFiles > 0 && $addedFiles % $flushInterval == 0);
                
                // Forzar flush si la memoria está alta (cada 500 archivos mínimo)
                if (!$shouldFlush && $currentMemory > $memoryThreshold && $addedFiles % 500 == 0) {
                    $shouldFlush = true;
                    logMsg("⚠️ FLUSH FORZADO POR MEMORIA: " . round($currentMemory/1024/1024) . " MB / " . round($memoryBytes/1024/1024) . " MB", $logFile);
                }
                
                if ($shouldFlush) {
                    // ANTI-TIMEOUT: Extender tiempo y actualizar heartbeat antes del flush
                    safeExtendExecutionTime(0);
                    @file_put_contents($heartbeatFile, date('Y-m-d H:i:s') . " - FLUSH en progreso - Archivos: $addedFiles");
                    
                    // Liberar memoria antes del flush
                    if (function_exists('gc_collect_cycles')) {
                        gc_collect_cycles();
                    }
                    // CRÍTICO: Extender tiempo ANTES del flush
                    safeExtendExecutionTime(0);
                    
                    logMsg("", $logFile);
                    logMsg("🔄 FLUSH AL DISCO cada " . number_format($flushInterval) . " archivos (archivo " . number_format($addedFiles) . " de " . number_format($totalToAdd) . ")...", $logFile);
                    
                    // ANTI-TIMEOUT: Solicitar 5 minutos extra para el flush
                    set_time_limit(300);
                    safeExtendExecutionTime(0);
                    
                    // Cerrar para forzar escritura (medir tiempo)
                    $closeStartTime = microtime(true);
                    $closeResult = $zip->close();
                    $closeTime = round(microtime(true) - $closeStartTime, 2);
                    
                    // Heartbeat después del close
                    @file_put_contents($heartbeatFile, date('Y-m-d H:i:s') . " - close() OK en {$closeTime}s - Archivos: $addedFiles");
                    
                    if (!$closeResult) {
                        logMsg("   ⚠️ Advertencia: close() retornó false (tiempo: {$closeTime}s)", $logFile);
                    } else {
                        logMsg("   ✓ close() completado en {$closeTime}s", $logFile);
                    }
                    
                    // ANTI-TIMEOUT: Otra extensión después del close
                    safeExtendExecutionTime(0);
                    
                    // Verificar archivo
                    clearstatcache(true, $zipFile);
                    if (!file_exists($zipFile)) {
                        logMsg("   ❌ ERROR CRÍTICO: El ZIP desapareció después del close!", $logFile);
                        logMsg("   Abortando backup...", $logFile);
                        updateProg(100, $progressFile, $logFile, $heartbeatFile);
                        exit(1);
                    }
                    
                    $currentZipSize = filesize($zipFile);
                    logMsg("   ✓ ZIP guardado: " . round($currentZipSize / 1024 / 1024, 2) . " MB (" . number_format($currentZipSize) . " bytes)", $logFile);
                    
                    // ANTI-TIMEOUT: Extensión antes de reabrir
                    safeExtendExecutionTime(0);
                    set_time_limit(300);
                    
                    // Reabrir el ZIP existente para AÑADIR más archivos
                    // IMPORTANTE: Usar solo open() sin flags para abrir ZIP existente
                    // Esto permite añadir nuevos archivos sin borrar los existentes
                    $openStartTime = microtime(true);
                    $zip = new ZipArchive();
                    $reopenResult = $zip->open($zipFile);
                    $openTime = round(microtime(true) - $openStartTime, 2);
                    
                    // Heartbeat después del open
                    @file_put_contents($heartbeatFile, date('Y-m-d H:i:s') . " - open() OK en {$openTime}s - Archivos: $addedFiles");
                    
                    if ($reopenResult !== TRUE) {
                        $errorMsg = "Código de error: " . $reopenResult;
                        switch ($reopenResult) {
                            case ZipArchive::ER_EXISTS: $errorMsg = "El archivo ya existe"; break;
                            case ZipArchive::ER_INCONS: $errorMsg = "Archivo ZIP inconsistente"; break;
                            case ZipArchive::ER_INVAL: $errorMsg = "Argumento inválido"; break;
                            case ZipArchive::ER_MEMORY: $errorMsg = "Error de memoria"; break;
                            case ZipArchive::ER_NOENT: $errorMsg = "Archivo no encontrado"; break;
                            case ZipArchive::ER_NOZIP: $errorMsg = "No es un archivo ZIP"; break;
                            case ZipArchive::ER_OPEN: $errorMsg = "No se puede abrir el archivo"; break;
                            case ZipArchive::ER_READ: $errorMsg = "Error de lectura"; break;
                            case ZipArchive::ER_SEEK: $errorMsg = "Error de seek"; break;
                        }
                        logMsg("   ❌ ERROR al reabrir ZIP: " . $errorMsg, $logFile);
                        
                        // ========== ESTRATEGIA DE RECUPERACIÓN SEGURA ==========
                        // NUNCA sobrescribir el ZIP existente - contiene archivos valiosos
                        // En su lugar, crear un ZIP de continuación con nombre diferente
                        
                        $zipPartNumber = isset($zipPartNumber) ? $zipPartNumber + 1 : 2;
                        $zipPartFile = str_replace('.tmp.zip', "_part{$zipPartNumber}.tmp.zip", $zipFile);
                        if ($zipPartFile === $zipFile) {
                            $zipPartFile = str_replace('.zip', "_part{$zipPartNumber}.zip", $zipFile);
                        }
                        
                        logMsg("   🔄 RECUPERACIÓN SEGURA: Creando ZIP de continuación...", $logFile);
                        logMsg("   📁 ZIP original preservado: " . basename($zipFile) . " (" . round($currentZipSize/1024/1024, 2) . " MB)", $logFile);
                        logMsg("   📁 Nuevo ZIP parte $zipPartNumber: " . basename($zipPartFile), $logFile);
                        
                        // Guardar referencia al ZIP original
                        if (!isset($zipParts)) {
                            $zipParts = array();
                        }
                        $zipParts[] = array(
                            'file' => $zipFile,
                            'size' => $currentZipSize,
                            'files_count' => $addedFiles
                        );
                        
                        // Crear nuevo ZIP para los archivos restantes
                        $zip = new ZipArchive();
                        $reopenResult = $zip->open($zipPartFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
                        
                        if ($reopenResult !== TRUE) {
                            logMsg("   ❌ No se pudo crear ZIP de continuación", $logFile);
                            logMsg("   ⚠️ El backup parcial se guardó en: " . basename($zipFile), $logFile);
                            logMsg("   📊 Archivos guardados: " . number_format($addedFiles) . " de " . number_format($totalToAdd), $logFile);
                            
                            // Guardar info de backup parcial
                            $partialInfo = array(
                                'status' => 'partial',
                                'original_zip' => $zipFile,
                                'files_saved' => $addedFiles,
                                'total_files' => $totalToAdd,
                                'error' => $errorMsg,
                                'timestamp' => date('Y-m-d H:i:s')
                            );
                            @file_put_contents($checkpointFile, json_encode($partialInfo, JSON_PRETTY_PRINT));
                            
                            updateProg(100, $progressFile, $logFile, $heartbeatFile);
                            exit(1);
                        }
                        
                        // Actualizar zipFile a la nueva parte
                        $previousZipFile = $zipFile;
                        $zipFile = $zipPartFile;
                        logMsg("   ✅ ZIP de continuación creado exitosamente", $logFile);
                        logMsg("   ℹ️ Archivos restantes se agregarán a: " . basename($zipPartFile), $logFile);
                    }
                    
                    // Verificar cuántos archivos tiene el ZIP reabierto
                    $numFilesAfterReopen = $zip->numFiles;
                    logMsg("   ✓ ZIP reabierto - Archivos en ZIP: " . number_format($numFilesAfterReopen), $logFile);
                    
                    // Verificar integridad del ZIP
                    if ($numFilesAfterReopen > 0) {
                        logMsg("   ✅ ZIP contiene " . number_format($numFilesAfterReopen) . " archivos", $logFile);
                    } elseif ($currentZipSize > 1000) {
                        // El ZIP tiene tamaño pero numFiles=0, posible problema de índice
                        logMsg("   ℹ️ ZIP tiene tamaño (" . round($currentZipSize / 1024 / 1024, 2) . " MB) pero numFiles=0", $logFile);
                        logMsg("   ℹ️ Esto puede ser normal dependiendo del método de creación", $logFile);
                    }
                    
                    logMsg("   ✓ Continuando con el backup...", $logFile);
                    
                    // ========== GUARDAR CHECKPOINT PARA RESUME ==========
                    // Si el proceso muere, este archivo permite ver el estado
                    $flushCount++;
                    $timeSinceLastFlush = round(microtime(true) - $lastFlushTime, 2);
                    $totalRuntime = round(microtime(true) - $startTime, 2);
                    $lastFlushTime = microtime(true);
                    
                    $checkpointData = [
                        'backup_id' => $fecha,
                        'last_update' => date('Y-m-d H:i:s'),
                        'files_added' => $addedFiles,
                        'total_files' => $totalToAdd,
                        'zip_size_mb' => round($currentZipSize / 1024 / 1024, 2),
                        'bytes_processed' => $totalBytes,
                        'last_index' => $index,
                        'status' => 'in_progress',
                        'close_time_sec' => $closeTime ?? 0,
                        'open_time_sec' => $openTime ?? 0,
                        'flush_count' => $flushCount,
                        'time_since_last_flush_sec' => $timeSinceLastFlush,
                        'total_runtime_sec' => $totalRuntime
                    ];
                    @file_put_contents($checkpointFile, json_encode($checkpointData, JSON_PRETTY_PRINT));
                    
                    // Log de tiempo
                    logMsg("   ⏱️ Flush #$flushCount | Tiempo: {$timeSinceLastFlush}s | Total: {$totalRuntime}s", $logFile);
                    
                    // Forzar escritura al disco
                    clearstatcache();
                    logMsg("   📊 Progreso: " . round(($addedFiles / $totalToAdd) * 100, 1) . "% completado", $logFile);
                    logMsg("   🎯 Faltan: " . number_format($totalToAdd - $addedFiles) . " archivos", $logFile);
                    logMsg("", $logFile);
                    
                    // Actualizar heartbeat y progreso
                    @file_put_contents($heartbeatFile, date('Y-m-d H:i:s') . " - Flush completado, archivos: $addedFiles\n", FILE_APPEND | LOCK_EX);
                    $progress = 30 + (($addedFiles / $totalToAdd) * 55);
                    updateProg(round($progress), $progressFile, $logFile, $heartbeatFile);
                    
                    // Extender tiempo de ejecución
                    safeExtendExecutionTime(0);
                }
                
                // ========== VERIFICACIÓN: ZIP creciendo correctamente ==========
                // Verificar periódicamente que el ZIP está creciendo (usa el mismo intervalo del flush)
                if ($addedFiles > 0 && $addedFiles % $flushInterval == 0) {
                    clearstatcache(true, $zipFile);
                    $checkZipSize = file_exists($zipFile) ? @filesize($zipFile) : 0;
                    $elapsedSec = microtime(true) - $startTime;
                    logMsg("📍 Checkpoint " . number_format($addedFiles) . ": ZIP=" . round($checkZipSize/1024/1024, 2) . " MB, Tiempo=" . round($elapsedSec) . "s", $logFile);
                    
                    // Si después de varios flushes el ZIP sigue en 0, hay un problema
                    if ($addedFiles >= ($flushInterval * 2) && $checkZipSize < 1000) {
                        logMsg("⚠️ ALERTA: El ZIP no está creciendo. Posible problema con ZipArchive.", $logFile);
                        logMsg("   Intentando forzar escritura...", $logFile);
                        // Forzar un flush anticipado
                        $zip->close();
                        clearstatcache(true, $zipFile);
                        $afterCloseSize = file_exists($zipFile) ? @filesize($zipFile) : 0;
                        logMsg("   Tamaño después de close(): " . round($afterCloseSize/1024/1024, 2) . " MB", $logFile);
                        $zip = new ZipArchive();
                        $reopenCheck = $zip->open($zipFile);
                        if ($reopenCheck !== TRUE) {
                            logMsg("   ⚠️ No se pudo reabrir el ZIP para verificación", $logFile);
                            // No crear nuevo - simplemente continuar con el ZIP cerrado
                            // El próximo flush regular intentará el close/open
                        }
                    }
                }
                
                // Log detallado cada 50 archivos
                if ($addedFiles % $batchSize == 0 || $addedFiles == $totalToAdd) {
                    $progress = 30 + (($addedFiles / $totalToAdd) * 55);
                    updateProg(round($progress), $progressFile, $logFile, $heartbeatFile);
                    
                    $elapsed = microtime(true) - $startTime;
                    $batchElapsed = microtime(true) - $lastBatchTime;
                    $lastBatchTime = microtime(true);
                    
                    $rate = $elapsed > 0 ? ($addedFiles / $elapsed) : 0;
                    $remaining = $rate > 0 ? (($totalToAdd - $addedFiles) / $rate) : 0;
                    
                    // ========== VERIFICACIÓN CRÍTICA: Comprobar que el archivo ZIP existe ==========
                    clearstatcache(true, $zipFile);
                    if (!file_exists($zipFile)) {
                        logMsg("", $logFile);
                        logMsg("╔═══════════════════════════════════════════════════════════╗", $logFile);
                        logMsg("║           ❌ ERROR CRÍTICO DETECTADO                       ║", $logFile);
                        logMsg("╚═══════════════════════════════════════════════════════════╝", $logFile);
                        logMsg("", $logFile);
                        logMsg("⚠️ EL ARCHIVO ZIP FUE BORRADO DURANTE EL PROCESO", $logFile);
                        logMsg("   Archivo esperado: " . basename($zipFile), $logFile);
                        logMsg("   Archivos procesados antes del error: " . number_format($addedFiles), $logFile);
                        logMsg("   Progreso: " . round($progress) . "%", $logFile);
                        logMsg("", $logFile);
                        
                        // Diagnóstico del sistema
                        logMsg("🔍 DIAGNÓSTICO DEL SISTEMA:", $logFile);
                        $fsIssues = checkFilesystemLimits(dirname($zipFile), $logFile);
                        if (!empty($fsIssues)) {
                            logMsg("   ⚠️ PROBLEMAS DETECTADOS:", $logFile);
                            foreach ($fsIssues as $issue) {
                                logMsg("   " . $issue, $logFile);
                            }
                        } else {
                            logMsg("   ✓ No se detectaron problemas de límites del sistema", $logFile);
                        }
                        logMsg("", $logFile);
                        
                        logMsg("💡 POSIBLES CAUSAS:", $logFile);
                        logMsg("   1. Script de limpieza temporal del servidor", $logFile);
                        logMsg("   2. Límite de espacio en disco alcanzado", $logFile);
                        logMsg("   3. Límite de inodos agotado (demasiados archivos)", $logFile);
                        logMsg("   4. Proceso externo eliminando archivos temporales", $logFile);
                        logMsg("   5. Permisos insuficientes en el directorio", $logFile);
                        logMsg("", $logFile);
                        
                        logMsg("✅ SOLUCIONES RECOMENDADAS:", $logFile);
                        logMsg("   • Verificar espacio disponible en disco", $logFile);
                        logMsg("   • Desactivar scripts de limpieza temporal durante backup", $logFile);
                        logMsg("   • Aumentar límite de inodos si es posible", $logFile);
                        logMsg("   • Usar un directorio diferente para backups", $logFile);
                        logMsg("   • Contactar con soporte del servidor", $logFile);
                        
                        updateProg(100, $progressFile, $logFile, $heartbeatFile);
                        exit(1);
                    }
                    
                    // Obtener tamaño actual del ZIP
                    $zipSize = @filesize($zipFile);
                    if ($zipSize === false) {
                        logMsg("⚠️ ADVERTENCIA: No se puede leer el tamaño del archivo ZIP", $logFile);
                        $zipSize = 0;
                    }
                    
                    // Calcular velocidad de crecimiento del ZIP
                    $zipGrowth = $zipSize - $lastLogSize;
                    $lastLogSize = $zipSize;
                    
                    // Formatear tamaños
                    $zipSizeMB = round($zipSize / 1024 / 1024, 2);
                    $totalBytesMB = round($totalBytes / 1024 / 1024, 2);
                    $remainingMin = round($remaining / 60, 1);
                    $elapsedMin = round($elapsed / 60, 1);
                    
                    logMsg("───────────────────────────────────────────────────────────", $logFile);
                    logMsg("📊 PROGRESO: " . round($progress) . "% | Archivos: " . number_format($addedFiles) . "/" . number_format($totalToAdd), $logFile);
                    logMsg("📁 Carpeta actual: " . dirname(str_replace($dolibarrRoot, '', $file)), $logFile);
                    logMsg("📦 Tamaño ZIP: {$zipSizeMB} MB | Datos procesados: {$totalBytesMB} MB", $logFile);
                    logMsg("⚡ Velocidad: " . round($rate) . " archivos/seg | Lote: " . round($batchElapsed, 2) . "s", $logFile);
                    logMsg("⏱️ Tiempo: {$elapsedMin} min transcurrido | ~{$remainingMin} min restante", $logFile);
                    if ($errorFiles > 0) {
                        logMsg("⚠️ Errores: $errorFiles archivos no agregados", $logFile);
                    }
                    
                    // Liberar memoria cada 500 archivos
                    if ($addedFiles % 500 == 0) {
                        gc_collect_cycles();
                        $memUsage = round(memory_get_usage(true) / 1024 / 1024, 1);
                        logMsg("💾 Memoria: {$memUsage} MB", $logFile);
                    }
                }
            } catch (Exception $e) {
                $errorFiles++;
                logMsg("  ✗ ERROR: " . basename($file) . " - " . $e->getMessage(), $logFile);
            }
        }
        
        logMsg("───────────────────────────────────────────────────────────", $logFile);
        logMsg("", $logFile);
        logMsg("═══════════════════════════════════════════════════════════", $logFile);
        logMsg("RESUMEN DE ARCHIVOS AGREGADOS", $logFile);
        logMsg("═══════════════════════════════════════════════════════════", $logFile);
        logMsg("✓ Archivos agregados: " . number_format($addedFiles), $logFile);
        if ($errorFiles > 0) {
            logMsg("✗ Archivos con error: " . number_format($errorFiles), $logFile);
        }
        logMsg("📊 Datos totales procesados: " . round($totalBytes / 1024 / 1024, 2) . " MB", $logFile);
        logMsg("⏱️ Tiempo total: " . round((microtime(true) - $startTime) / 60, 1) . " minutos", $logFile);
        logMsg("", $logFile);
        
        // PROGRESO 87%
        updateProg(87, $progressFile, $logFile, $heartbeatFile);
        logMsg("Iniciando proceso de cierre del ZIP...", $logFile);
        logMsg("Verificando integridad de los archivos agregados...", $logFile);
        
        // Obtener estadísticas antes de cerrar
        $zipSizeBefore = file_exists($zipFile) ? filesize($zipFile) : 0;
        logMsg("Tamaño del ZIP en memoria: " . round($zipSizeBefore/1024/1024, 2) . " MB", $logFile);
        
        // PROGRESO 90%
        updateProg(90, $progressFile, $logFile, $heartbeatFile);
        logMsg("=== INICIANDO PROCESO DE CIERRE DEL ZIP ===", $logFile);
        logMsg("Ruta del archivo ZIP: $zipFile", $logFile);
        logMsg("Estado actual del archivo: " . (file_exists($zipFile) ? 'EXISTE' : 'NO EXISTE'), $logFile);
        if (file_exists($zipFile)) {
            logMsg("Tamaño actual del archivo: " . filesize($zipFile) . " bytes", $logFile);
        }
        
        logMsg("Forzando sincronización con disco...", $logFile);
        
        // Flush de buffers de PHP (no del ZIP, ya que no tiene flush())
        safeExtendExecutionTime(0); // Asegurar que no expire durante el cierre
        flush(); // Forzar salida de buffers de PHP
        
        $zipSizeAfterAdd = file_exists($zipFile) ? filesize($zipFile) : 0;
        logMsg("Tamaño ZIP después de agregar archivos: " . round($zipSizeAfterAdd/1024/1024, 2) . " MB (" . number_format($zipSizeAfterAdd) . " bytes)", $logFile);
        if ($zipSizeBefore > 0) {
            logMsg("Crecimiento del archivo: " . round(($zipSizeAfterAdd - $zipSizeBefore)/1024/1024, 2) . " MB", $logFile);
        }
        
        // PROGRESO 93%
        updateProg(93, $progressFile, $logFile, $heartbeatFile);
        
        // ================================================================
        // INFORMACIÓN DETALLADA DEL ZIP ANTES DE CERRAR
        // ================================================================
        logMsg("", $logFile);
        logMsg("═══════════════════════════════════════════════════════════", $logFile);
        logMsg("📦 INFORMACIÓN DEL ARCHIVO ZIP", $logFile);
        logMsg("═══════════════════════════════════════════════════════════", $logFile);
        logMsg("📁 Nombre: " . basename($zipFile), $logFile);
        logMsg("📂 Ruta completa: $zipFile", $logFile);
        logMsg("📊 Total archivos agregados: " . number_format($addedFiles), $logFile);
        logMsg("📊 Total datos procesados: " . round($totalBytes / 1024 / 1024, 2) . " MB", $logFile);
        if ($errorFiles > 0) {
            logMsg("⚠️ Archivos con errores: " . number_format($errorFiles), $logFile);
        }
        
        // Obtener estadísticas del ZIP antes de cerrar
        $numFiles = $zip->numFiles;
        $zipStatus = $zip->status;
        $zipStatusSys = $zip->statusSys;
        $zipComment = $zip->comment ?: '(sin comentario)';
        
        logMsg("", $logFile);
        logMsg("📈 ESTADÍSTICAS DEL ZIP (antes de cerrar):", $logFile);
        logMsg("   • Archivos en ZIP: " . number_format($numFiles), $logFile);
        logMsg("   • Estado: " . $zipStatus, $logFile);
        logMsg("   • Estado sistema: " . $zipStatusSys, $logFile);
        
        // Tamaño actual en disco
        clearstatcache(true, $zipFile);
        $currentSize = file_exists($zipFile) ? @filesize($zipFile) : 0;
        logMsg("   • Tamaño actual en disco: " . round($currentSize / 1024 / 1024, 2) . " MB", $logFile);
        
        // Ratio de compresión estimado
        if ($totalBytes > 0 && $currentSize > 0) {
            $compressionRatio = round(($currentSize / $totalBytes) * 100, 1);
            logMsg("   • Ratio de compresión: ~" . $compressionRatio . "% del original", $logFile);
        }
        
        // Tiempo total hasta ahora
        $totalElapsed = microtime(true) - $startTime;
        logMsg("   • Tiempo total hasta ahora: " . round($totalElapsed / 60, 1) . " minutos", $logFile);
        
        // Velocidad promedio
        $avgSpeed = $totalElapsed > 0 ? ($addedFiles / $totalElapsed) : 0;
        logMsg("   • Velocidad promedio: " . round($avgSpeed) . " archivos/segundo", $logFile);
        
        logMsg("", $logFile);
        logMsg("═══════════════════════════════════════════════════════════", $logFile);
        logMsg("🔄 CERRANDO ARCHIVO ZIP", $logFile);
        logMsg("═══════════════════════════════════════════════════════════", $logFile);
        
        // Información del entorno
        logMsg("", $logFile);
        logMsg("🖥️ INFORMACIÓN DEL SERVIDOR:", $logFile);
        logMsg("   • Hostname: " . gethostname(), $logFile);
        logMsg("   • PHP: " . phpversion(), $logFile);
        logMsg("   • Sistema: " . php_uname('s') . " " . php_uname('r'), $logFile);
        
        // Memoria
        $memUsed = round(memory_get_usage(true) / 1024 / 1024, 2);
        $memPeak = round(memory_get_peak_usage(true) / 1024 / 1024, 2);
        $memLimit = ini_get('memory_limit');
        logMsg("   • Memoria usada: {$memUsed} MB (pico: {$memPeak} MB, límite: {$memLimit})", $logFile);
        
        // Disco
        $diskFree = @disk_free_space(dirname($zipFile));
        $diskTotal = @disk_total_space(dirname($zipFile));
        if ($diskFree && $diskTotal) {
            $diskFreeGB = round($diskFree / 1024 / 1024 / 1024, 2);
            $diskTotalGB = round($diskTotal / 1024 / 1024 / 1024, 2);
            $diskUsedPercent = round((1 - ($diskFree / $diskTotal)) * 100, 1);
            logMsg("   • Disco: {$diskFreeGB} GB libres de {$diskTotalGB} GB ({$diskUsedPercent}% usado)", $logFile);
        }
        
        // Tiempo límite
        $maxExec = ini_get('max_execution_time');
        $elapsed = round(microtime(true) - $startTime);
        logMsg("   • Tiempo ejecución: {$elapsed}s de {$maxExec}s máximo", $logFile);
        
        logMsg("", $logFile);
        logMsg("╔═══════════════════════════════════════════════════════════╗", $logFile);
        logMsg("║           📦 INFORMACIÓN DEL ARCHIVO ZIP                   ║", $logFile);
        logMsg("╚═══════════════════════════════════════════════════════════╝", $logFile);
        logMsg("", $logFile);
        
        // Obtener estadísticas del ZIP
        clearstatcache(true, $zipFile);
        $currentZipSize = file_exists($zipFile) ? @filesize($zipFile) : 0;
        $numFilesInZip = $zip->numFiles;
        
        // Calcular porcentajes y tamaños
        $totalBytesMB = round($totalBytes / 1024 / 1024, 2);
        $currentZipMB = round($currentZipSize / 1024 / 1024, 2);
        $compressionRatio = $totalBytes > 0 ? round(($currentZipSize / $totalBytes) * 100, 1) : 0;
        $savedMB = round(($totalBytes - $currentZipSize) / 1024 / 1024, 2);
        $savedPercent = $totalBytes > 0 ? round((1 - ($currentZipSize / $totalBytes)) * 100, 1) : 0;
        
        logMsg("📊 ESTADÍSTICAS DEL ZIP:", $logFile);
        logMsg("   ┌─────────────────────────────────────────────────────┐", $logFile);
        logMsg("   │ Archivos agregados:     " . str_pad(number_format($addedFiles), 20, ' ', STR_PAD_LEFT) . " │", $logFile);
        logMsg("   │ Archivos en ZIP:        " . str_pad(number_format($numFilesInZip), 20, ' ', STR_PAD_LEFT) . " │", $logFile);
        logMsg("   │ Errores:                " . str_pad(number_format($errorFiles), 20, ' ', STR_PAD_LEFT) . " │", $logFile);
        logMsg("   └─────────────────────────────────────────────────────┘", $logFile);
        logMsg("", $logFile);
        
        logMsg("💾 TAMAÑOS:", $logFile);
        logMsg("   ┌─────────────────────────────────────────────────────┐", $logFile);
        logMsg("   │ Datos originales:       " . str_pad($totalBytesMB . " MB", 20, ' ', STR_PAD_LEFT) . " │", $logFile);
        logMsg("   │ Tamaño ZIP actual:      " . str_pad($currentZipMB . " MB", 20, ' ', STR_PAD_LEFT) . " │", $logFile);
        logMsg("   │ Espacio ahorrado:       " . str_pad($savedMB . " MB", 20, ' ', STR_PAD_LEFT) . " │", $logFile);
        logMsg("   └─────────────────────────────────────────────────────┘", $logFile);
        logMsg("", $logFile);
        
        logMsg("📈 COMPRESIÓN:", $logFile);
        logMsg("   ┌─────────────────────────────────────────────────────┐", $logFile);
        logMsg("   │ Ratio de compresión:    " . str_pad($compressionRatio . "%", 20, ' ', STR_PAD_LEFT) . " │", $logFile);
        logMsg("   │ Reducción total:        " . str_pad($savedPercent . "%", 20, ' ', STR_PAD_LEFT) . " │", $logFile);
        logMsg("   └─────────────────────────────────────────────────────┘", $logFile);
        logMsg("", $logFile);
        
        // Barra de progreso visual del tamaño
        $barLength = 40;
        $filledLength = $totalBytes > 0 ? round(($currentZipSize / $totalBytes) * $barLength) : 0;
        $bar = str_repeat('█', $filledLength) . str_repeat('░', $barLength - $filledLength);
        logMsg("   ZIP: [{$bar}] {$compressionRatio}%", $logFile);
        logMsg("", $logFile);
        
        // Estimación de tiempo de cierre basada en tamaño y archivos
        $estimatedCloseTime = max(1, round(($currentZipSize / (30 * 1024 * 1024)) + ($addedFiles / 10000)));
        logMsg("⏱️ TIEMPO:", $logFile);
        logMsg("   • Tiempo transcurrido: " . round((microtime(true) - $startTime) / 60, 1) . " minutos", $logFile);
        logMsg("   • Tiempo estimado cierre: ~{$estimatedCloseTime} segundos", $logFile);
        logMsg("", $logFile);
        
        logMsg("╔═══════════════════════════════════════════════════════════╗", $logFile);
        logMsg("║           ⏳ CERRANDO ARCHIVO ZIP                          ║", $logFile);
        logMsg("╚═══════════════════════════════════════════════════════════╝", $logFile);
        logMsg("", $logFile);
        
        // Mostrar resumen de lo que se va a comprimir
        logMsg("📋 RESUMEN ANTES DEL CIERRE:", $logFile);
        logMsg("   ├─ Archivos a comprimir: " . number_format($addedFiles), $logFile);
        logMsg("   ├─ Archivos en el ZIP: " . number_format($numFilesInZip), $logFile);
        logMsg("   ├─ Datos originales: " . $totalBytesMB . " MB", $logFile);
        logMsg("   ├─ Tamaño actual ZIP: " . $currentZipMB . " MB", $logFile);
        logMsg("   ├─ Compresión actual: " . $compressionRatio . "%", $logFile);
        logMsg("   └─ Errores durante proceso: " . $errorFiles, $logFile);
        logMsg("", $logFile);
        
        // Calcular velocidad de procesamiento
        $elapsedSoFar = microtime(true) - $startTime;
        $filesPerSecond = $elapsedSoFar > 0 ? round($addedFiles / $elapsedSoFar) : 0;
        $mbPerSecond = $elapsedSoFar > 0 ? round($totalBytes / 1024 / 1024 / $elapsedSoFar, 2) : 0;
        
        logMsg("⚡ VELOCIDAD DE PROCESAMIENTO:", $logFile);
        logMsg("   ├─ Archivos/segundo: " . $filesPerSecond, $logFile);
        logMsg("   ├─ MB/segundo: " . $mbPerSecond, $logFile);
        logMsg("   └─ Tiempo hasta ahora: " . round($elapsedSoFar) . " segundos", $logFile);
        logMsg("", $logFile);
        
        logMsg("⚠️ NOTA IMPORTANTE:", $logFile);
        logMsg("   La operación zip->close() es BLOQUEANTE.", $logFile);
        logMsg("   NO habrá actualizaciones de progreso durante este proceso.", $logFile);
        logMsg("   El tiempo depende del tamaño y número de archivos.", $logFile);
        logMsg("   Estimación: ~" . max(1, round($currentZipSize / (30 * 1024 * 1024))) . " segundos", $logFile);
        logMsg("", $logFile);
        
        logMsg("🔄 INICIANDO COMPRESIÓN FINAL...", $logFile);
        logMsg("", $logFile);
        
        // Actualizar progreso antes del cierre
        updateProg(94, $progressFile, $logFile, $heartbeatFile);
        
        // ========== VERIFICACIÓN FINAL ANTES DE CERRAR EL ZIP ==========
        logMsg("", $logFile);
        logMsg("🔍 VERIFICACIÓN PRE-CIERRE:", $logFile);
        clearstatcache(true, $zipFile);
        
        if (!file_exists($zipFile)) {
            logMsg("", $logFile);
            logMsg("╔═══════════════════════════════════════════════════════════╗", $logFile);
            logMsg("║           ❌ ERROR CRÍTICO: ARCHIVO ZIP NO EXISTE         ║", $logFile);
            logMsg("╚═══════════════════════════════════════════════════════════╝", $logFile);
            logMsg("", $logFile);
            logMsg("⚠️ El archivo ZIP fue borrado antes de poder cerrarlo", $logFile);
            logMsg("   Archivo esperado: " . basename($zipFile), $logFile);
            logMsg("   Archivos que se procesaron: " . number_format($addedFiles), $logFile);
            logMsg("", $logFile);
            
            // Diagnóstico del sistema
            logMsg("🔍 DIAGNÓSTICO DEL SISTEMA:", $logFile);
            $fsIssues = checkFilesystemLimits(dirname($zipFile), $logFile);
            if (!empty($fsIssues)) {
                logMsg("", $logFile);
                logMsg("⚠️ PROBLEMAS DETECTADOS:", $logFile);
                foreach ($fsIssues as $issue) {
                    logMsg("   " . $issue, $logFile);
                }
            }
            logMsg("", $logFile);
            
            logMsg("💡 ACCIÓN RECOMENDADA:", $logFile);
            logMsg("   Contacte con su proveedor de hosting para:", $logFile);
            logMsg("   • Verificar que no hay scripts de limpieza automática", $logFile);
            logMsg("   • Aumentar límites de espacio en disco e inodos", $logFile);
            logMsg("   • Configurar una excepción para " . dirname($zipFile), $logFile);
            
            updateProg(100, $progressFile, $logFile, $heartbeatFile);
            exit(1);
        }
        
        $preCloseSize = @filesize($zipFile);
        logMsg("   ✓ Archivo ZIP existe: " . basename($zipFile), $logFile);
        logMsg("   ✓ Tamaño actual: " . round($preCloseSize / (1024*1024), 2) . " MB", $logFile);
        logMsg("   ✓ Listo para cerrar", $logFile);
        logMsg("", $logFile);
        
        // Cerrar ZIP (esta operación puede tardar con muchos archivos)
        $closeStartTime = microtime(true);
        logMsg("🔄 [" . date('H:i:s') . "] >>> zip->close() INICIADO <<<", $logFile);
        logMsg("   Archivos: " . number_format($numFilesInZip) . " | Tamaño: " . $currentZipMB . " MB", $logFile);
        logMsg("   Por favor espere...", $logFile);
        
        // Forzar escritura del log antes del close
        clearstatcache();
        
        $closeResult = $zip->close();
        $closeElapsed = microtime(true) - $closeStartTime;
        
        logMsg("", $logFile);
        logMsg("🔄 [" . date('H:i:s') . "] >>> zip->close() FINALIZADO <<<", $logFile);
        logMsg("", $logFile);
        
        if ($closeResult) {
            logMsg("╔═══════════════════════════════════════════════════════════╗", $logFile);
            logMsg("║           ✅ ZIP CERRADO EXITOSAMENTE                      ║", $logFile);
            logMsg("╚═══════════════════════════════════════════════════════════╝", $logFile);
            logMsg("", $logFile);
            logMsg("⏱️ Tiempo de cierre: " . round($closeElapsed, 2) . " segundos (" . round($closeElapsed / 60, 1) . " min)", $logFile);
            
            // Calcular velocidad durante el cierre
            $closeFilesPerSecond = $closeElapsed > 0 ? round($numFilesInZip / $closeElapsed) : 0;
            logMsg("⚡ Velocidad de cierre: " . $closeFilesPerSecond . " archivos/segundo", $logFile);
        } else {
            logMsg("╔═══════════════════════════════════════════════════════════╗", $logFile);
            logMsg("║           ❌ ERROR AL CERRAR ZIP                           ║", $logFile);
            logMsg("╚═══════════════════════════════════════════════════════════╝", $logFile);
            logMsg("", $logFile);
            logMsg("⏱️ Tiempo transcurrido: " . round($closeElapsed, 2) . " segundos", $logFile);
        }
        
        // Esperar un poco para que el sistema termine de escribir
        logMsg("", $logFile);
        logMsg("⏳ Sincronizando con disco...", $logFile);
        usleep(500000); // 0.5 segundos
        
        // Obtener tamaño final después de cerrar
        clearstatcache(true, $zipFile);
        $zipSizeFinal = file_exists($zipFile) ? @filesize($zipFile) : 0;
        $zipSizeFinalMB = round($zipSizeFinal / 1024 / 1024, 2);
        $zipSizeFinalGB = round($zipSizeFinal / 1024 / 1024 / 1024, 2);
        
        logMsg("", $logFile);
        logMsg("╔═══════════════════════════════════════════════════════════╗", $logFile);
        logMsg("║           📊 RESULTADO FINAL DEL BACKUP                    ║", $logFile);
        logMsg("╚═══════════════════════════════════════════════════════════╝", $logFile);
        logMsg("", $logFile);
        
        logMsg("📁 ARCHIVO:", $logFile);
        logMsg("   ├─ Nombre: " . basename($zipFile), $logFile);
        logMsg("   ├─ Ruta: " . $zipFile, $logFile);
        logMsg("   └─ Existe: " . (file_exists($zipFile) ? '✅ SÍ' : '❌ NO'), $logFile);
        logMsg("", $logFile);
        
        logMsg("📦 TAMAÑO FINAL:", $logFile);
        logMsg("   ├─ Bytes: " . number_format($zipSizeFinal), $logFile);
        logMsg("   ├─ MB: " . $zipSizeFinalMB, $logFile);
        if ($zipSizeFinal > 1024 * 1024 * 1024) {
            logMsg("   └─ GB: " . $zipSizeFinalGB, $logFile);
        } else {
            logMsg("   └─ GB: N/A (menor a 1 GB)", $logFile);
        }
        logMsg("", $logFile);
        
        // Ratio de compresión final
        if ($totalBytes > 0 && $zipSizeFinal > 0) {
            $finalRatio = round(($zipSizeFinal / $totalBytes) * 100, 1);
            $saved = round(($totalBytes - $zipSizeFinal) / 1024 / 1024, 2);
            $savedPercent = round(100 - $finalRatio, 1);
            
            logMsg("📉 COMPRESIÓN:", $logFile);
            logMsg("   ├─ Datos originales: " . $totalBytesMB . " MB", $logFile);
            logMsg("   ├─ Tamaño comprimido: " . $zipSizeFinalMB . " MB", $logFile);
            logMsg("   ├─ Ratio: " . $finalRatio . "% del original", $logFile);
            logMsg("   ├─ Ahorro: " . $saved . " MB (" . $savedPercent . "%)", $logFile);
            
            // Barra visual de compresión
            $barLen = 30;
            $filled = round(($finalRatio / 100) * $barLen);
            $bar = str_repeat('█', $filled) . str_repeat('░', $barLen - $filled);
            logMsg("   └─ [{$bar}] " . $finalRatio . "%", $logFile);
        }
        logMsg("", $logFile);
        
        // Tiempo total
        $totalTime = microtime(true) - $startTime;
        $totalMinutes = round($totalTime / 60, 1);
        
        logMsg("⏱️ TIEMPOS:", $logFile);
        logMsg("   ├─ Tiempo total: " . round($totalTime) . " seg (" . $totalMinutes . " min)", $logFile);
        logMsg("   ├─ Tiempo de cierre: " . round($closeElapsed) . " seg", $logFile);
        logMsg("   └─ Tiempo de proceso: " . round($totalTime - $closeElapsed) . " seg", $logFile);
        logMsg("", $logFile);
        
        if ($zipSizeFinal > 0) {
            logMsg("✓ ZIP válido y con contenido", $logFile);
            
            // PROGRESO 95%
            updateProg(95, $progressFile, $logFile, $heartbeatFile);
            logMsg("Verificando integridad del ZIP...", $logFile);
            logMsg("Estructura del ZIP: dolibarr_" . $fecha . "/", $logFile);
            
            // ========== VERIFICACIÓN CRÍTICA: TODOS LOS ARCHIVOS PROCESADOS ==========
            logMsg("", $logFile);
            logMsg("🔍 VERIFICACIÓN DE COMPLETITUD:", $logFile);
            logMsg("   • Archivos esperados: " . number_format($totalToAdd), $logFile);
            logMsg("   • Archivos procesados: " . number_format($addedFiles), $logFile);
            logMsg("   • Archivos con error: " . number_format($errorFiles), $logFile);
            logMsg("   • Tamaño ZIP: " . round($zipSizeFinal / 1024 / 1024, 2) . " MB", $logFile);
            
            // Verificar que se procesaron TODOS los archivos
            $completionRate = $totalToAdd > 0 ? ($addedFiles / $totalToAdd) * 100 : 0;
            logMsg("   • Tasa de completitud: " . round($completionRate, 2) . "%", $logFile);
            
            if ($addedFiles < $totalToAdd) {
                logMsg("", $logFile);
                logMsg("╔═══════════════════════════════════════════════════════════╗", $logFile);
                logMsg("║        ⚠️ ADVERTENCIA: BACKUP INCOMPLETO                   ║", $logFile);
                logMsg("╚═══════════════════════════════════════════════════════════╝", $logFile);
                logMsg("", $logFile);
                logMsg("⚠️ NO SE PROCESARON TODOS LOS ARCHIVOS", $logFile);
                logMsg("   Faltaron: " . number_format($totalToAdd - $addedFiles) . " archivos", $logFile);
                logMsg("   El backup puede estar INCOMPLETO", $logFile);
                
                // Si faltan más del 5% de archivos, marcar como fallido
                if ($completionRate < 95) {
                    logMsg("", $logFile);
                    logMsg("❌ BACKUP MARCADO COMO FALLIDO (< 95% completitud)", $logFile);
                    updateProg(100, $progressFile, $logFile, $heartbeatFile);
                    exit(1);
                } else {
                    logMsg("", $logFile);
                    logMsg("✓ Backup aceptado (>= 95% completitud)", $logFile);
                }
            } else {
                logMsg("   ✅ TODOS los archivos fueron procesados correctamente", $logFile);
            }
            
            // Verificar que el ZIP tiene contenido
            if ($zipSizeFinal < 1000) {
                logMsg("", $logFile);
                logMsg("╔═══════════════════════════════════════════════════════════╗", $logFile);
                logMsg("║        ❌ ERROR: ZIP VACÍO O CORRUPTO                      ║", $logFile);
                logMsg("╚═══════════════════════════════════════════════════════════╝", $logFile);
                logMsg("", $logFile);
                logMsg("❌ El archivo ZIP tiene un tamaño inválido: " . $zipSizeFinal . " bytes", $logFile);
                logMsg("   Esto indica que el archivo está vacío o corrupto", $logFile);
                updateProg(100, $progressFile, $logFile, $heartbeatFile);
                exit(1);
            }
            
            logMsg("", $logFile);
            
            // PROGRESO 97%
            updateProg(97, $progressFile, $logFile, $heartbeatFile);
            logMsg("Proceso de backup de archivos completado", $logFile);
            
            // PROGRESO 98%
            updateProg(98, $progressFile, $logFile, $heartbeatFile);
            logMsg("Preparando información final...", $logFile);
            
            // PROGRESO 99%
            updateProg(99, $progressFile, $logFile, $heartbeatFile);
            logMsg("Finalizando proceso...", $logFile);
            
            // ========== COMBINAR PARTES DEL ZIP SI EXISTEN ==========
            // Si se crearon múltiples partes debido a errores de reopen, combinarlas
            if (isset($zipParts) && !empty($zipParts)) {
                logMsg("", $logFile);
                logMsg("╔═══════════════════════════════════════════════════════════╗", $logFile);
                logMsg("║           🔄 COMBINANDO PARTES DEL ZIP                     ║", $logFile);
                logMsg("╚═══════════════════════════════════════════════════════════╝", $logFile);
                logMsg("", $logFile);
                logMsg("Se detectaron " . (count($zipParts) + 1) . " partes de ZIP:", $logFile);
                
                $totalPartsSize = 0;
                foreach ($zipParts as $i => $part) {
                    logMsg("   Parte " . ($i + 1) . ": " . basename($part['file']) . " (" . round($part['size']/1024/1024, 2) . " MB)", $logFile);
                    $totalPartsSize += $part['size'];
                }
                $currentPartSize = file_exists($zipFile) ? @filesize($zipFile) : 0;
                logMsg("   Parte " . (count($zipParts) + 1) . " (actual): " . basename($zipFile) . " (" . round($currentPartSize/1024/1024, 2) . " MB)", $logFile);
                $totalPartsSize += $currentPartSize;
                logMsg("", $logFile);
                logMsg("📊 Tamaño total combinado: " . round($totalPartsSize/1024/1024, 2) . " MB", $logFile);
                
                // Intentar combinar las partes en un solo ZIP
                // Crear un nuevo ZIP final y agregar contenido de todas las partes
                $combinedZipFile = str_replace('.tmp.zip', '_combined.tmp.zip', $zipParts[0]['file']);
                if ($combinedZipFile === $zipParts[0]['file']) {
                    $combinedZipFile = str_replace('.zip', '_combined.zip', $zipParts[0]['file']);
                }
                
                logMsg("🔄 Combinando partes en: " . basename($combinedZipFile), $logFile);
                
                $combinedZip = new ZipArchive();
                $combineResult = $combinedZip->open($combinedZipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);
                
                if ($combineResult === TRUE) {
                    $combinedFiles = 0;
                    $combineErrors = 0;
                    
                    // Función para extraer y agregar archivos de una parte
                    $allParts = $zipParts;
                    $allParts[] = array('file' => $zipFile, 'size' => $currentPartSize);
                    
                    foreach ($allParts as $partIdx => $part) {
                        if (!file_exists($part['file'])) {
                            logMsg("   ⚠️ Parte " . ($partIdx + 1) . " no encontrada: " . basename($part['file']), $logFile);
                            continue;
                        }
                        
                        $partZip = new ZipArchive();
                        if ($partZip->open($part['file']) === TRUE) {
                            $partFileCount = $partZip->numFiles;
                            logMsg("   📦 Procesando parte " . ($partIdx + 1) . ": " . $partFileCount . " archivos...", $logFile);
                            
                            for ($j = 0; $j < $partFileCount; $j++) {
                                $entryName = $partZip->getNameIndex($j);
                                $entryContent = $partZip->getFromIndex($j);
                                
                                if ($entryContent !== false) {
                                    // Evitar duplicados
                                    if ($combinedZip->locateName($entryName) === false) {
                                        if ($combinedZip->addFromString($entryName, $entryContent)) {
                                            $combinedFiles++;
                                        } else {
                                            $combineErrors++;
                                        }
                                    }
                                }
                                unset($entryContent);
                                
                                // Flush cada 5000 archivos para liberar memoria
                                if ($combinedFiles % 5000 == 0 && $combinedFiles > 0) {
                                    gc_collect_cycles();
                                    logMsg("      Combinados: " . number_format($combinedFiles) . " archivos...", $logFile);
                                }
                            }
                            $partZip->close();
                        } else {
                            logMsg("   ❌ No se pudo abrir parte " . ($partIdx + 1), $logFile);
                            $combineErrors++;
                        }
                    }
                    
                    $combinedZip->close();
                    clearstatcache(true, $combinedZipFile);
                    $combinedSize = file_exists($combinedZipFile) ? @filesize($combinedZipFile) : 0;
                    
                    logMsg("", $logFile);
                    logMsg("✅ Combinación completada:", $logFile);
                    logMsg("   • Archivos combinados: " . number_format($combinedFiles), $logFile);
                    logMsg("   • Errores: " . number_format($combineErrors), $logFile);
                    logMsg("   • Tamaño final: " . round($combinedSize/1024/1024, 2) . " MB", $logFile);
                    
                    // Si la combinación fue exitosa, usar el archivo combinado
                    if ($combinedSize > 0 && $combinedFiles > 0) {
                        // Eliminar las partes originales
                        foreach ($allParts as $part) {
                            if (file_exists($part['file']) && $part['file'] !== $combinedZipFile) {
                                @unlink($part['file']);
                            }
                        }
                        $zipFile = $combinedZipFile;
                        $zipSizeFinal = $combinedSize;
                        logMsg("✅ Usando archivo combinado como backup final", $logFile);
                    } else {
                        logMsg("⚠️ Combinación fallida, manteniendo partes separadas", $logFile);
                        @unlink($combinedZipFile);
                    }
                } else {
                    logMsg("❌ No se pudo crear ZIP combinado", $logFile);
                    logMsg("⚠️ Los archivos quedarán en partes separadas", $logFile);
                }
                logMsg("", $logFile);
            }
            
            // ========== RENOMBRAR ZIP DE .tmp A FINAL ==========
            // Solo ahora el backup aparecerá en la lista de backups disponibles
            if (file_exists($zipFile) && isset($zipFileFinal)) {
                // Ajustar nombre final si el archivo actual es diferente
                $currentBasename = basename($zipFile);
                if (strpos($currentBasename, '_combined') !== false || strpos($currentBasename, '_part') !== false) {
                    // Reconstruir el nombre final correcto
                    $zipFileFinal = dirname($zipFile) . DIRECTORY_SEPARATOR . ($isAutomatic ? 'automatic_backup_' : 'files_dolibarr_') . $fecha . '.zip';
                }
                
                if (rename($zipFile, $zipFileFinal)) {
                    logMsg("✅ Archivo ZIP renombrado a versión final", $logFile);
                    $zipFile = $zipFileFinal; // Actualizar referencia
                } else {
                    logMsg("⚠️ No se pudo renombrar el ZIP (permanece como .tmp)", $logFile);
                }
            }
            
            // Actualizar tamaño final después del renombrado
            clearstatcache(true, $zipFile);
            $zipSizeFinal = file_exists($zipFile) ? @filesize($zipFile) : $zipSizeFinal;
            
            // PROGRESO 100% - BACKUP COMPLETADO
            updateProg(100, $progressFile, $logFile, $heartbeatFile);
            
            // ========== MARCAR CHECKPOINT COMO COMPLETADO ==========
            $finalCheckpoint = [
                'backup_id' => $fecha,
                'last_update' => date('Y-m-d H:i:s'),
                'files_added' => $addedFiles,
                'total_files' => $totalToAdd,
                'zip_size_mb' => round($zipSizeFinal / 1024 / 1024, 2),
                'status' => 'completed',
                'was_resumed' => $isResuming,
                'total_runtime_sec' => round(microtime(true) - $startTime, 2)
            ];
            @file_put_contents($checkpointFile, json_encode($finalCheckpoint, JSON_PRETTY_PRINT));
            
            logMsg("=== BACKUP DE ARCHIVOS COMPLETADO EXITOSAMENTE ===", $logFile);
            logMsg("Archivo final: " . basename($zipFile), $logFile);
            logMsg("Tamaño final: " . number_format($zipSizeFinal) . " bytes (" . round($zipSizeFinal/1024/1024, 2) . " MB)", $logFile);
            logMsg("Total archivos respaldados: $addedFiles", $logFile);
            if ($isResuming) {
                logMsg("📌 Este backup fue REANUDADO desde un checkpoint anterior", $logFile);
            }
            logMsg("", $logFile);
            logMsg("═══════════════════════════════════════════════════════════", $logFile);
            logMsg("✅ COPIA DE SEGURIDAD COMPLETADA EXITOSAMENTE", $logFile);
            logMsg("═══════════════════════════════════════════════════════════", $logFile);
            logMsg("El archivo ZIP se ha creado correctamente y está listo para descargar.", $logFile);
            logMsg("Los archivos temporales se eliminarán automáticamente en unos segundos.", $logFile);
            
            // Eliminar lock manual si NO es backup automático
            if ($isAutomatic == 0 && isset($manualLockFile) && file_exists($manualLockFile)) {
                @unlink($manualLockFile);
                error_log("BACKUP FILES: Lock manual eliminado: $manualLockFile");
                logMsg("Lock manual eliminado (backup manual completado)", $logFile);
            }
            
            // Registrar finalización del backup en log de actividad
            try {
                if (!function_exists('logFileManagerActivity') && file_exists(__DIR__ . '/../lib/filemanager.lib.php')) {
                    require_once __DIR__ . '/../lib/filemanager.lib.php';
                }
                if (function_exists('logFileManagerActivity')) {
                    $zipSizeMB = round($zipSizeFinal / 1024 / 1024, 2);
                    logFileManagerActivity('backup_completed', $zipFile, $user_id, "Backup de archivos completado - Tamaño: $zipSizeMB MB - Archivos: $addedFiles");
                }
            } catch (Exception $e) {
                error_log("No se pudo registrar finalización de backup en log de actividad: " . $e->getMessage());
            }
            
            // Actualizar archivo backup_info con el usuario cuando termina el backup exitosamente
            $backupInfoFile = $backupDir . DIRECTORY_SEPARATOR . 'backup_info_' . $fecha . '.json';
            if (file_exists($backupInfoFile)) {
                // Intentar obtener el usuario una vez más al finalizar (por si cambió o no se capturó bien al inicio)
                $final_user_login = $user_login;
                $final_user_id = $user_id;
                
                // Si el usuario es 'unknown', intentar obtenerlo de nuevo
                if ($final_user_login === 'unknown' || empty($final_user_login)) {
                    global $user;
                    if (isset($user) && is_object($user) && !empty($user->login)) {
                        $final_user_login = $user->login;
                        $final_user_id = !empty($user->id) ? intval($user->id) : 0;
                    } elseif (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['dol_login']) && !empty($_SESSION['dol_login'])) {
                        $final_user_login = $_SESSION['dol_login'];
                        $final_user_id = isset($_SESSION['dol_userid']) ? intval($_SESSION['dol_userid']) : 0;
                    }
                }
                
                // Actualizar el archivo backup_info con el usuario final
                $backup_info_updated = array(
                    'user_id' => $final_user_id,
                    'user_login' => $final_user_login,
                    'created_at' => date('Y-m-d H:i:s'),
                    'completed_at' => date('Y-m-d H:i:s'),
                    'backup_type' => $tipo,
                    'is_automatic' => $isAutomatic
                );
                @file_put_contents($backupInfoFile, json_encode($backup_info_updated));
                if (isset($logFile)) {
                    logMsg("Usuario final del backup actualizado: " . $final_user_login . " (ID: " . $final_user_id . ")", $logFile);
                }
            }
            
            // Eliminar lock manual si NO es backup automático
            if ($isAutomatic == 0 && isset($manualLockFile) && file_exists($manualLockFile)) {
                @unlink($manualLockFile);
                error_log("BACKUP FILES: Lock manual eliminado: $manualLockFile");
                if (isset($logFile)) {
                    logMsg("Lock manual eliminado (backup manual completado)", $logFile);
                }
            }
            
            // Eliminar lock de auto_backup si ES backup automático
            if ($isAutomatic == 1) {
                $autoLockFile = $backupDir . DIRECTORY_SEPARATOR . 'auto_backup.lock';
                if (file_exists($autoLockFile)) {
                    @unlink($autoLockFile);
                    error_log("BACKUP FILES: Lock automático eliminado: $autoLockFile");
                    if (isset($logFile)) {
                        logMsg("Lock automático eliminado (backup automático completado)", $logFile);
                    }
                }
            }
            
            // Verificar que el ZIP existe y tiene contenido válido antes de limpiar
            if (file_exists($zipFile) && filesize($zipFile) > 1000) {
                logMsg("Iniciando limpieza de archivos temporales...", $logFile);
                
                // Lista de archivos temporales a eliminar (TODO excepto ZIPs y logs)
                $tempFilesToDelete = [];
                
                // Archivos de progreso (eliminar)
                if (file_exists($progressFile)) {
                    $tempFilesToDelete[] = $progressFile;
                }
                
                // Heartbeat file (eliminar)
                $heartbeatFile = $backupDir . DIRECTORY_SEPARATOR . 'heartbeat_' . $fecha . '.txt';
                if (file_exists($heartbeatFile)) {
                    $tempFilesToDelete[] = $heartbeatFile;
                }
                
                // backup_info_*.json (eliminar - ya no se necesita)
                $backupInfoFile = $backupDir . DIRECTORY_SEPARATOR . 'backup_info_' . $fecha . '.json';
                if (file_exists($backupInfoFile)) {
                    $tempFilesToDelete[] = $backupInfoFile;
                }
                
                // Buscar archivos temporales adicionales del mismo backup_id
                $allTempFiles = glob($backupDir . DIRECTORY_SEPARATOR . '*' . $fecha . '*');
                foreach ($allTempFiles as $file) {
                    // Solo eliminar si NO es un archivo ZIP y NO es un archivo de log
                    if (file_exists($file) && !preg_match('/\.zip$/i', $file) && !preg_match('/log_/i', basename($file))) {
                        $tempFilesToDelete[] = $file;
                    }
                }
                
                // También buscar en /tmp si se usó como alternativa
                $tempBackupDir = sys_get_temp_dir() . '/dolibarr_backups';
                if (is_dir($tempBackupDir)) {
                    $tempAllFiles = glob($tempBackupDir . DIRECTORY_SEPARATOR . '*' . $fecha . '*');
                    foreach ($tempAllFiles as $file) {
                        if (file_exists($file) && !preg_match('/\.zip$/i', $file) && !preg_match('/log_/i', basename($file))) {
                            $tempFilesToDelete[] = $file;
                        }
                    }
                }
                
                // Eliminar todos los archivos temporales (excepto logs)
                $deletedCount = 0;
                foreach ($tempFilesToDelete as $tempFile) {
                    if (file_exists($tempFile) && !preg_match('/\.zip$/i', $tempFile) && !preg_match('/log_/i', basename($tempFile))) {
                        if (@unlink($tempFile)) {
                            $deletedCount++;
                            logMsg("  ✓ Eliminado: " . basename($tempFile), $logFile);
                        } else {
                            logMsg("  ✗ No se pudo eliminar: " . basename($tempFile), $logFile);
                        }
                    }
                }
                
                // ========== LIMPIEZA GLOBAL DE ARCHIVOS TEMPORALES ==========
                // Eliminar TODOS los archivos que NO sean .zip o log_*.txt
                $allBackupFiles = glob($backupDir . DIRECTORY_SEPARATOR . '*');
                foreach ($allBackupFiles as $file) {
                    if (is_file($file)) {
                        $basename = basename($file);
                        // Mantener solo: *.zip y log_*.txt
                        $isZip = preg_match('/\.zip$/i', $basename);
                        $isLog = preg_match('/^log_.*\.txt$/i', $basename);
                        
                        if (!$isZip && !$isLog) {
                            if (@unlink($file)) {
                                $deletedCount++;
                                logMsg("  ✓ Limpieza global: " . $basename, $logFile);
                            }
                        }
                    }
                }
                
                logMsg("Limpieza completada: $deletedCount archivos temporales eliminados", $logFile);
                logMsg("✓ Archivos finales: ZIP (" . basename($zipFile) . ") y Log (" . basename($logFile) . ")", $logFile);
            } else {
                logMsg("ERROR: ZIP creado pero está vacío", $logFile);
                updateProg(100, $progressFile, $logFile, $heartbeatFile);
            }
        } else {
            logMsg("ERROR creando ZIP. Código de error: $openResult", $logFile);
            updateProg(100, $progressFile, $logFile, $heartbeatFile);
        }
    }

} catch (Exception $e) {
    // Error en backup
    if (isset($logFile)) {
        @file_put_contents($logFile, "ERROR CRÍTICO: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
        @file_put_contents($logFile, "Stack trace: " . $e->getTraceAsString() . "\n", FILE_APPEND | LOCK_EX);
        @file_put_contents($logFile, "Archivo: " . $e->getFile() . " línea " . $e->getLine() . "\n", FILE_APPEND | LOCK_EX);
    }
    if (isset($progressFile)) {
        @file_put_contents($progressFile, '-1');
    }
    
    // Eliminar lock manual si NO es backup automático
    if ($isAutomatic == 0 && isset($manualLockFile) && file_exists($manualLockFile)) {
        @unlink($manualLockFile);
        error_log("BACKUP FILES: Lock manual eliminado después de error: $manualLockFile");
    }
    
    // Log también en el log de PHP
    error_log("BACKUP ERROR: " . $e->getMessage() . " en " . $e->getFile() . " línea " . $e->getLine());
    
    // Asegurar que se escriban los archivos
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    exit;
} catch (Error $e) {
    // Capturar errores fatales también
    if (isset($logFile)) {
        @file_put_contents($logFile, "ERROR FATAL: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
        @file_put_contents($logFile, "Archivo: " . $e->getFile() . " línea " . $e->getLine() . "\n", FILE_APPEND | LOCK_EX);
    }
    if (isset($progressFile)) {
        @file_put_contents($progressFile, '-1');
    }
    
    // Eliminar lock manual si NO es backup automático
    if ($isAutomatic == 0 && isset($manualLockFile) && file_exists($manualLockFile)) {
        @unlink($manualLockFile);
        error_log("BACKUP FILES: Lock manual eliminado después de error fatal: $manualLockFile");
    }
    
    error_log("BACKUP FATAL ERROR: " . $e->getMessage());
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    exit;
}

// La limpieza ya se hizo dentro del bloque try si el ZIP se creó exitosamente
exit;

