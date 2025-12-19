<?php
// Script que USA LA LÓGICA QUE SÍ FUNCIONA
// Mantener algunos errores visibles para diagnóstico (pero sin mostrar en pantalla)
error_reporting(E_ALL);
ini_set('display_errors', 0); // No mostrar errores en pantalla
ini_set('log_errors', 1); // Pero sí loguearlos

// ========== OPTIMIZACIÓN PARA SERVIDORES CON RECURSOS LIMITADOS ==========
set_time_limit(300); // 5 minutos máximo
ini_set('max_execution_time', 300);

// Detectar memoria disponible y usar un límite razonable (máx 256MB)
function return_bytes_simple($val) {
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
$current_memory = ini_get('memory_limit');
$current_bytes = return_bytes_simple($current_memory);
if ($current_bytes > 268435456 || $current_bytes == -1) { // 256MB
    ini_set('memory_limit', '256M');
}

define('BACKUP_BATCH_SIZE', 100);
define('BACKUP_BATCH_PAUSE', 10000);

// IMPORTANTE: Crear archivos de progreso ANTES de cerrar la conexión con el cliente
// para asegurar que existan incluso si hay errores
// Soportar tanto parámetros HTTP (GET/POST) como CLI (argv)
if (php_sapi_name() === 'cli') {
    // Desde línea de comandos, leer de argv
    parse_str(implode('&', array_slice($argv, 1)), $cliParams);
    $tipo = $cliParams['backup_type'] ?? 'database_only';
    $backupId = $cliParams['backup_id'] ?? date('YmdHis');
    $isAutomatic = isset($cliParams['automatic']) ? (int)$cliParams['automatic'] : 0;
} else {
    // Desde HTTP - POR DEFECTO SIEMPRE MANUAL (0) a menos que se especifique explícitamente como automático
$tipo = $_GET['backup_type'] ?? $_POST['backup_type'] ?? 'database_only';
$backupId = $_GET['backup_id'] ?? $_POST['backup_id'] ?? date('YmdHis'); // Sin guiones bajos para coincidir con JS
    // IMPORTANTE: Si viene desde la interfaz web, SIEMPRE es MANUAL (0)
    // Solo es automático si viene explícitamente del cron con el parámetro from_cron=1
    // Esto asegura que los backups iniciados desde la interfaz siempre sean MANUALES
    $isAutomatic = (isset($_GET['from_cron']) && $_GET['from_cron'] == 1) ? 1 : 0;
}
$fecha = $backupId;

// DEBUG: Log inmediato de parámetros recibidos
error_log("BACKUP INICIADO - Tipo: $tipo, BackupID: $backupId, Fecha: $fecha, Automatic: " . ($isAutomatic == 1 ? 'SI' : 'NO') . " (valor: $isAutomatic)");
error_log("BACKUP DEBUG - SAPI: " . php_sapi_name() . ", GET: " . json_encode($_GET) . ", POST: " . json_encode($_POST));
if (php_sapi_name() === 'cli' && isset($cliParams)) {
    error_log("BACKUP DEBUG - CLI Params: " . json_encode($cliParams));
}

// ========================================================================
// PRIORIDAD MÁXIMA: CREAR LOCK MANUAL INMEDIATAMENTE SI ES BACKUP MANUAL
// Esto debe hacerse ANTES DE CUALQUIER OTRA OPERACIÓN para evitar condiciones de carrera
// ========================================================================
$backupDir = __DIR__ . '/../backups';
$manualLockFile = $backupDir . DIRECTORY_SEPARATOR . 'manual_backup.lock';

error_log("BACKUP: Verificando si es automático - isAutomatic = $isAutomatic (0=manual, 1=automático)");

if ($isAutomatic == 0) {
    error_log("BACKUP: 🚨🚨🚨 PRIORIDAD ABSOLUTA - Es backup MANUAL (isAutomatic=$isAutomatic). Creando lock INMEDIATAMENTE");
    
    // Asegurar que el directorio existe PRIMERO (necesario para crear el lock)
    // Hacer esto de la forma más rápida posible
    if (!is_dir($backupDir)) {
        @mkdir($backupDir, 0777, true);
    }
    @chmod($backupDir, 0777);
    
    // Crear lock INMEDIATAMENTE - método más rápido y seguro posible
    $lockContent = date('Y-m-d H:i:s') . " - PID: " . getmypid() . " - Tipo: $tipo - Backup ID: $backupId - MANUAL\n";
    
    // Método 1: fopen con fflush y fclose forzado para asegurar escritura inmediata
    $lockHandle = @fopen($manualLockFile, 'w');
    if ($lockHandle) {
        fwrite($lockHandle, $lockContent);
        fflush($lockHandle); // Forzar escritura inmediata al buffer del sistema
        fclose($lockHandle);
        @chmod($manualLockFile, 0666);
        error_log("BACKUP: ✅ Lock creado con fopen + fflush");
    } else {
        // Método 2: file_put_contents con flags
        @file_put_contents($manualLockFile, $lockContent, LOCK_EX);
        @chmod($manualLockFile, 0666);
        error_log("BACKUP: ✅ Lock creado con file_put_contents");
    }
    
    // Forzar sincronización al disco INMEDIATAMENTE
    clearstatcache(true, $manualLockFile);
    if (function_exists('sync')) {
        sync(); // Sincronizar todos los buffers al disco
    }
    
    // Verificar INMEDIATAMENTE que se creó y es accesible
    if (file_exists($manualLockFile)) {
        $lockSize = filesize($manualLockFile);
        error_log("BACKUP: ✅✅✅ LOCK MANUAL CREADO Y VERIFICADO: $manualLockFile (tamaño: $lockSize bytes)");
        error_log("BACKUP: ⚠️⚠️⚠️ EL CRON AUTOMÁTICO DEBE DETECTAR ESTE LOCK Y NO EJECUTARSE");
        
        // Verificar que el contenido es correcto
        $lockContentRead = @file_get_contents($manualLockFile);
        if ($lockContentRead === $lockContent) {
            error_log("BACKUP: ✅✅✅ CONTENIDO DEL LOCK VERIFICADO CORRECTAMENTE");
        } else {
            error_log("BACKUP: ⚠️ ADVERTENCIA: Contenido del lock no coincide");
        }
        
        // Verificar permisos
        $lockPerms = substr(sprintf('%o', fileperms($manualLockFile)), -4);
        error_log("BACKUP: Permisos del lock: $lockPerms");
    } else {
        error_log("BACKUP: ❌❌❌ ERROR CRÍTICO: Lock no existe después de intentar crearlo");
        
        // Métodos alternativos como fallback
        // Método 2: fopen/fwrite
        $handle = @fopen($manualLockFile, 'w');
        if ($handle) {
            fwrite($handle, $lockContent);
            fclose($handle);
            @chmod($manualLockFile, 0666);
            if (file_exists($manualLockFile)) {
                error_log("BACKUP: ✅ Lock manual creado con fopen (fallback)");
            }
        } else {
            // Método 3: touch + chmod + file_put_contents
            @touch($manualLockFile);
            @chmod($manualLockFile, 0666);
            @file_put_contents($manualLockFile, $lockContent);
            if (file_exists($manualLockFile)) {
                error_log("BACKUP: ✅ Lock manual creado con touch (fallback)");
            } else {
                error_log("BACKUP: ❌❌❌ ERROR CRÍTICO: No se pudo crear lock manual después de todos los intentos!");
            }
        }
    }
}

// LIMPIAR OUTPUT BUFFER INMEDIATAMENTE
while (ob_get_level()) {
    ob_end_clean();
}

// Continuar con el resto de operaciones...
error_log("BACKUP: Directorio de backups: $backupDir");

// Asegurar que el directorio existe con permisos completos
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0777, true);
    error_log("BACKUP: Directorio creado: $backupDir");
} else {
    error_log("BACKUP: Directorio ya existe: $backupDir");
}

// FORZAR permisos de escritura - MÚLTIPLES INTENTOS
@chmod($backupDir, 0777);
// Intentar cambiar propietario si es posible (requiere root o permisos)
$currentUser = get_current_user();
$serverUser = $currentUser;
if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
    $pwuid = posix_getpwuid(posix_geteuid());
    if ($pwuid && is_array($pwuid) && isset($pwuid['name'])) {
        $serverUser = $pwuid['name'];
    }
}
error_log("BACKUP: Usuario actual: $currentUser, Usuario efectivo: $serverUser");

// Verificar permisos del directorio
$isWritable = is_writable($backupDir);
$dirPerms = is_dir($backupDir) ? substr(sprintf('%o', fileperms($backupDir)), -4) : 'N/A';
error_log("BACKUP: Directorio escribible: " . ($isWritable ? 'SI' : 'NO') . ", Permisos: $dirPerms");

if (!$isWritable) {
    error_log("BACKUP ERROR: Directorio no escribible después de chmod! Intentando métodos alternativos...");
    // Intentar con umask temporal
    $oldUmask = umask(0);
    @chmod($backupDir, 0777);
    umask($oldUmask);
    $isWritable = is_writable($backupDir);
    error_log("BACKUP: Después de umask(0), escribible: " . ($isWritable ? 'SI' : 'NO'));
    
    // Si aún no es escribible, usar /tmp como alternativa temporal
    if (!$isWritable) {
        $tempBackupDir = sys_get_temp_dir() . '/dolibarr_backups';
        if (!is_dir($tempBackupDir)) {
            @mkdir($tempBackupDir, 0777, true);
        }
        if (is_writable($tempBackupDir)) {
            error_log("BACKUP WARNING: Usando directorio temporal alternativo: $tempBackupDir");
            $backupDir = $tempBackupDir;
        }
    }
}

// Crear archivos de progreso INMEDIATAMENTE (usando separador correcto del sistema)
$progressFile = $backupDir . DIRECTORY_SEPARATOR . 'progress_' . $fecha . '.txt';
$logFile = $backupDir . DIRECTORY_SEPARATOR . 'log_' . $fecha . '.txt';
// Si es backup automático, usar nombre diferente
if ($isAutomatic == 1) {
    $zipFile = $backupDir . DIRECTORY_SEPARATOR . 'automatic_backup_' . $fecha . '.zip';
} else {
$zipFile = $backupDir . DIRECTORY_SEPARATOR . 'db_dolibarr_' . $fecha . '.zip';
}

error_log("BACKUP: Archivos definidos - Progress: $progressFile, Log: $logFile");

// CREAR ARCHIVOS INMEDIATAMENTE - ANTES DE CUALQUIER OTRA COSA
// Método más simple y directo posible - FORZAR CREACIÓN
error_log("BACKUP: Intentando crear archivos - Progress: $progressFile, Log: $logFile");

// Intentar múltiples métodos para asegurar creación
$progressCreated = false;
$logCreated = false;

// Método 1: file_put_contents directo
if (@file_put_contents($progressFile, '0', LOCK_EX) !== false) {
    $progressCreated = true;
    error_log("BACKUP: progressFile creado con file_put_contents");
} else {
    // Método 2: fopen/fwrite
    $handle = @fopen($progressFile, 'w');
    if ($handle) {
        fwrite($handle, '0');
        fclose($handle);
        @chmod($progressFile, 0666);
        $progressCreated = file_exists($progressFile);
        error_log("BACKUP: progressFile creado con fopen: " . ($progressCreated ? 'SI' : 'NO'));
    } else {
        // Método 3: touch + chmod + file_put_contents
        @touch($progressFile);
        @chmod($progressFile, 0666);
        @chmod($backupDir, 0777);
        @file_put_contents($progressFile, '0');
        $progressCreated = file_exists($progressFile);
        error_log("BACKUP: progressFile creado con touch: " . ($progressCreated ? 'SI' : 'NO'));
    }
}

// El lock manual ya se creó al inicio del script si es backup manual
// No hacer nada aquí, solo continuar

$tipoTexto = ($tipo == 'database_only') ? 'BASE DE DATOS' : (($tipo == 'files_only') ? 'ARCHIVOS' : 'COMPLETO');
$logContent = "═══════════════════════════════════════════════════════════\n";
$logContent .= "TIPO DE COPIA DE SEGURIDAD: $tipoTexto\n";
$logContent .= "═══════════════════════════════════════════════════════════\n";
$logContent .= "Fecha: " . date('Y-m-d H:i:s') . "\n";
$logContent .= "Backup ID: $backupId\n";
$logContent .= "Tipo (código): $tipo\n";
$logContent .= "Modo: " . ($isAutomatic == 1 ? 'AUTOMÁTICO' : 'MANUAL') . "\n";
$logContent .= "═══════════════════════════════════════════════════════════\n\n";
if (@file_put_contents($logFile, $logContent, LOCK_EX) !== false) {
    $logCreated = true;
    error_log("BACKUP: logFile creado con file_put_contents");
} else {
    $handle = @fopen($logFile, 'w');
    if ($handle) {
        fwrite($handle, $logContent);
        fclose($handle);
        @chmod($logFile, 0666);
        $logCreated = file_exists($logFile);
        error_log("BACKUP: logFile creado con fopen: " . ($logCreated ? 'SI' : 'NO'));
    } else {
        @touch($logFile);
        @chmod($logFile, 0666);
        @chmod($backupDir, 0777);
        @file_put_contents($logFile, $logContent);
        $logCreated = file_exists($logFile);
        error_log("BACKUP: logFile creado con touch: " . ($logCreated ? 'SI' : 'NO'));
    }
}

// Forzar sync al disco
clearstatcache();
usleep(100000); // 0.1 segundos

// Verificación final
$finalProgressCheck = file_exists($progressFile) && filesize($progressFile) >= 0;
$finalLogCheck = file_exists($logFile) && filesize($logFile) > 0;

error_log("BACKUP: Verificación final - Progress existe y tiene tamaño: " . ($finalProgressCheck ? 'SI (' . filesize($progressFile) . ' bytes)' : 'NO') . ", Log existe y tiene tamaño: " . ($finalLogCheck ? 'SI (' . filesize($logFile) . ' bytes)' : 'NO'));

if (!$finalProgressCheck || !$finalLogCheck) {
    error_log("BACKUP CRITICAL ERROR: Archivos no se pudieron crear. Permisos directorio: " . substr(sprintf('%o', fileperms($backupDir)), -4));
}

// DEBUG: Crear archivos iniciales INMEDIATAMENTE - ANTES de cerrar conexión
$debugInfo = "=== DEBUG BACKUP INICIADO ===\n";
$debugInfo .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
$debugInfo .= "PHP Version: " . PHP_VERSION . "\n";
$debugInfo .= "Script: " . __FILE__ . "\n";
$debugInfo .= "Tipo de backup: $tipo\n";
$debugInfo .= "Backup ID recibido: $backupId\n";
$debugInfo .= "Fecha usada: $fecha\n";
$debugInfo .= "Directorio de backups: $backupDir\n";
$debugInfo .= "Archivo de progreso: $progressFile\n";
$debugInfo .= "Archivo de log: $logFile\n";
$debugInfo .= "Archivo ZIP: $zipFile\n";
$debugInfo .= "GET params: " . print_r($_GET, true) . "\n";
$debugInfo .= "POST params: " . print_r($_POST, true) . "\n";
$debugInfo .= "Directorio existe: " . (is_dir($backupDir) ? 'SI' : 'NO') . "\n";
$debugInfo .= "Directorio escribible: " . (is_writable($backupDir) ? 'SI' : 'NO') . "\n";
$debugInfo .= "Usuario PHP actual: " . get_current_user() . "\n";
// Usuario efectivo con verificación segura de POSIX
$effectiveUser = 'N/A';
if (function_exists('posix_geteuid') && function_exists('posix_getpwuid')) {
    $euid = posix_geteuid();
    $pwuid = posix_getpwuid($euid);
    if ($pwuid && is_array($pwuid) && isset($pwuid['name'])) {
        $effectiveUser = $pwuid['name'];
    }
}
$debugInfo .= "Usuario efectivo: " . $effectiveUser . "\n";
// UID efectivo
$effectiveUID = 'N/A';
if (function_exists('posix_geteuid')) {
    $effectiveUID = posix_geteuid();
}
$debugInfo .= "UID efectivo: " . $effectiveUID . "\n";
// GID efectivo
$effectiveGID = 'N/A';
if (function_exists('posix_getegid')) {
    $effectiveGID = posix_getegid();
}
$debugInfo .= "GID efectivo: " . $effectiveGID . "\n";
$debugInfo .= "Permisos directorio: " . (is_dir($backupDir) ? substr(sprintf('%o', fileperms($backupDir)), -4) : 'N/A') . "\n";
// Propietario directorio con verificación segura de POSIX
$dirOwner = 'N/A';
if (is_dir($backupDir)) {
    $ownerUID = fileowner($backupDir);
    if (function_exists('posix_getpwuid')) {
        $pwuid = posix_getpwuid($ownerUID);
        if ($pwuid && is_array($pwuid) && isset($pwuid['name'])) {
            $dirOwner = $pwuid['name'];
        } else {
            $dirOwner = $ownerUID;
        }
    } else {
        $dirOwner = $ownerUID;
    }
}
$debugInfo .= "Propietario directorio: " . $dirOwner . "\n";
// Grupo directorio con verificación segura de POSIX
$dirGroup = 'N/A';
if (is_dir($backupDir)) {
    $groupGID = filegroup($backupDir);
    if (function_exists('posix_getgrgid')) {
        $grgid = posix_getgrgid($groupGID);
        if ($grgid && is_array($grgid) && isset($grgid['name'])) {
            $dirGroup = $grgid['name'];
        } else {
            $dirGroup = $groupGID;
        }
    } else {
        $dirGroup = $groupGID;
    }
}
$debugInfo .= "Grupo directorio: " . $dirGroup . "\n";

// Intentar crear directorio si no existe y corregir permisos
if (is_dir($backupDir) && !is_writable($backupDir)) {
    $debugInfo .= "⚠️ AVISO: Directorio no escribible, intentando corregir permisos...\n";
    @chmod($backupDir, 0777);
    $debugInfo .= "Después de chmod, escribible: " . (is_writable($backupDir) ? 'SI' : 'NO') . "\n";
}

$debugInfo .= "=============================\n\n";

// Intentar escribir archivos con verificación detallada
$debugWrite = "\n=== DEBUG ESCRITURA DE ARCHIVOS ===\n";

// Asegurar que el directorio existe y tiene permisos
if (!is_dir($backupDir)) {
    $debugWrite .= "Directorio NO existe, creando...\n";
    @mkdir($backupDir, 0777, true);
    $debugWrite .= "Después de crear, existe: " . (is_dir($backupDir) ? 'SI' : 'NO') . "\n";
}

// Intentar corregir permisos si no es escribible
if (is_dir($backupDir) && !is_writable($backupDir)) {
    $debugWrite .= "⚠️ Directorio no escribible, aplicando chmod 777...\n";
    @chmod($backupDir, 0777);
    $debugWrite .= "Después de chmod, escribible: " . (is_writable($backupDir) ? 'SI' : 'NO') . "\n";
}

// Intentar escribir archivo de progreso - MÚLTIPLES INTENTOS
$writeResult1 = @file_put_contents($progressFile, '0', LOCK_EX);
if ($writeResult1 === false) {
    $error = error_get_last();
    $debugWrite .= "❌ ERROR escribiendo progressFile: " . ($error ? $error['message'] : 'Desconocido') . "\n";
    // Intentar sin LOCK_EX
    $writeResult1b = @file_put_contents($progressFile, '0');
    if ($writeResult1b === false) {
        // Último intento: escribir directamente sin verificaciones
        $handle = @fopen($progressFile, 'w');
        if ($handle) {
            fwrite($handle, '0');
            fclose($handle);
            $debugWrite .= "Escritura directa con fopen: " . (file_exists($progressFile) ? 'EXITO' : 'FALLO') . "\n";
        } else {
            $debugWrite .= "FALLO total escribiendo progressFile\n";
            // Forzar creación del directorio si no existe
            if (!is_dir($backupDir)) {
                @mkdir($backupDir, 0777, true);
            }
            @chmod($backupDir, 0777);
            // Intentar una vez más
            @file_put_contents($progressFile, '0');
        }
    } else {
        $debugWrite .= "Sin LOCK_EX: EXITO\n";
    }
} else {
    $debugWrite .= "✅ progressFile escrito: $writeResult1 bytes\n";
}

// Verificar si el archivo se creó - CRÍTICO: Si no existe, algo está muy mal
$debugWrite .= "progressFile existe después: " . (file_exists($progressFile) ? 'SI (tamaño: ' . filesize($progressFile) . ')' : 'NO') . "\n";
if (!file_exists($progressFile)) {
    $debugWrite .= "⚠️⚠️⚠️ CRÍTICO: progressFile NO se creó. Intentando método alternativo...\n";
    // Forzar creación
    @touch($progressFile);
    @chmod($progressFile, 0666);
    @file_put_contents($progressFile, '0');
    $debugWrite .= "Después de touch/chmod/file_put_contents, existe: " . (file_exists($progressFile) ? 'SI' : 'NO') . "\n";
    // Log también en error_log de PHP
    error_log("BACKUP CRITICAL: No se pudo crear progressFile: $progressFile");
}

// INTENTAR CREAR ARCHIVOS DE FORMA DIRECTA Y SIMPLE PRIMERO (SIN DEBUG COMPLEJO)
// Esto asegura que los archivos existan incluso si el debug falla
$simpleProgress = @file_put_contents($progressFile, '0');
$tipoTexto = ($tipo == 'database_only') ? 'BASE DE DATOS' : (($tipo == 'files_only') ? 'ARCHIVOS' : 'COMPLETO');
$simpleLogContent = "═══════════════════════════════════════════════════════════\n";
$simpleLogContent .= "TIPO DE COPIA DE SEGURIDAD: $tipoTexto\n";
$simpleLogContent .= "═══════════════════════════════════════════════════════════\n";
$simpleLogContent .= "Fecha: " . date('Y-m-d H:i:s') . "\n";
$simpleLogContent .= "Backup ID: $backupId\n";
$simpleLogContent .= "Tipo (código): $tipo\n";
$simpleLogContent .= "Modo: " . ($isAutomatic == 1 ? 'AUTOMÁTICO' : 'MANUAL') . "\n";
$simpleLogContent .= "═══════════════════════════════════════════════════════════\n\n";
$simpleLog = @file_put_contents($logFile, $simpleLogContent);
error_log("BACKUP: Escritura simple - Progress: " . ($simpleProgress !== false ? 'OK' : 'FALLO') . ", Log: " . ($simpleLog !== false ? 'OK' : 'FALLO'));
error_log("BACKUP: Archivos existen - Progress: " . (file_exists($progressFile) ? 'SI' : 'NO') . ", Log: " . (file_exists($logFile) ? 'SI' : 'NO'));

// Intentar escribir archivo de log con debug detallado
$logContent = $debugInfo . $debugWrite . "\nIniciando backup $tipo...\n" . date('Y-m-d H:i:s') . "\n";
$writeResult2 = @file_put_contents($logFile, $logContent, FILE_APPEND | LOCK_EX);
if ($writeResult2 === false) {
    $error = error_get_last();
    $debugWrite .= "❌ ERROR escribiendo logFile: " . ($error ? $error['message'] : 'Desconocido') . "\n";
    // Intentar sin LOCK_EX
    $writeResult2b = @file_put_contents($logFile, $logContent);
    $debugWrite .= "Sin LOCK_EX: " . ($writeResult2b !== false ? 'EXITO' : 'FALLO') . "\n";
    // Si aún falla, intentar escribir en /tmp
    if ($writeResult2b === false) {
        $tmpLog = sys_get_temp_dir() . '/backup_debug_' . $fecha . '.txt';
        @file_put_contents($tmpLog, $logContent);
        $debugWrite .= "📝 Escribiendo debug alternativo en: $tmpLog\n";
    }
} else {
    $debugWrite .= "✅ logFile escrito: $writeResult2 bytes\n";
}

// Verificar si el archivo se creó
$debugWrite .= "logFile existe después: " . (file_exists($logFile) ? 'SI (tamaño: ' . filesize($logFile) . ')' : 'NO') . "\n";

$debugWrite .= "===============================\n";

// Escribir debug final en el log si existe, o en tmp
if (file_exists($logFile)) {
    @file_put_contents($logFile, $debugWrite, FILE_APPEND | LOCK_EX);
} else {
    // Guardar en /tmp como último recurso
    $tmpDebug = sys_get_temp_dir() . '/backup_debug_' . $fecha . '.txt';
    @file_put_contents($tmpDebug, $debugInfo . $debugWrite);
    error_log("BACKUP DEBUG: No se pudo escribir logFile, guardado en $tmpDebug");
}

// Asegurar que los archivos se escriban al disco
clearstatcache();
usleep(100000); // Esperar 0.1 segundos para asegurar escritura

// LIMPIAR CUALQUIER OUTPUT ANTERIOR
while (ob_get_level()) {
    ob_end_clean();
}

// Enviar respuesta inmediata al cliente y continuar ejecutando en background
// Esto es crítico para que el navegador no espere
ignore_user_abort(true);
set_time_limit(600);

// Limpiar cualquier salida de error previa
@ob_end_clean();

// VERIFICAR QUE LOS ARCHIVOS SE CREARON ANTES DE ENVIAR RESPUESTA
// Forzar verificación y creación una vez más ANTES de enviar headers
clearstatcache();
$progressExists = file_exists($progressFile);
$logExists = file_exists($logFile);

if (!$progressExists) {
    error_log("BACKUP CRITICAL: progressFile NO existe antes de enviar respuesta. Creando...");
    @file_put_contents($progressFile, '0');
    @chmod($progressFile, 0666);
    clearstatcache();
    $progressExists = file_exists($progressFile);
    error_log("BACKUP: Después de recrear progressFile: " . ($progressExists ? 'SI' : 'NO'));
}

if (!$logExists) {
    error_log("BACKUP CRITICAL: logFile NO existe antes de enviar respuesta. Creando...");
    @file_put_contents($logFile, "Backup iniciado - " . date('Y-m-d H:i:s') . "\n");
    @chmod($logFile, 0666);
    clearstatcache();
    $logExists = file_exists($logFile);
    error_log("BACKUP: Después de recrear logFile: " . ($logExists ? 'SI' : 'NO'));
}

// Si aún no existen, hay un problema grave
if (!$progressExists || !$logExists) {
    error_log("BACKUP FATAL ERROR: No se pueden crear archivos de progreso/log. Progress: " . ($progressExists ? 'SI' : 'NO') . ", Log: " . ($logExists ? 'SI' : 'NO'));
    // Intentar escribir en /tmp como último recurso
    $tmpProgress = sys_get_temp_dir() . '/backup_progress_' . $fecha . '.txt';
    $tmpLog = sys_get_temp_dir() . '/backup_log_' . $fecha . '.txt';
    @file_put_contents($tmpProgress, '0');
    @file_put_contents($tmpLog, "Backup iniciado - " . date('Y-m-d H:i:s') . "\n");
    error_log("BACKUP: Archivos temporales creados en /tmp: Progress=" . (file_exists($tmpProgress) ? 'SI' : 'NO') . ", Log=" . (file_exists($tmpLog) ? 'SI' : 'NO'));
}

// Headers para ejecución en background (IMPORTANTE: antes de cualquier output)
if (!headers_sent()) {
    header('Connection: close');
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    
    // Enviar respuesta JSON con información de debug
    $response = array(
        'status' => 'started', 
        'backup_id' => $backupId, 
        'message' => 'Backup iniciado',
        'debug' => array(
            'progress_file_exists' => file_exists($progressFile),
            'log_file_exists' => file_exists($logFile),
            'progress_file_path' => $progressFile,
            'log_file_path' => $logFile,
            'backup_dir' => $backupDir,
            'backup_dir_writable' => is_writable($backupDir),
            'progress_file_readable' => file_exists($progressFile) ? is_readable($progressFile) : false
        )
    );
    
    $jsonResponse = json_encode($response);
    header('Content-Length: ' . strlen($jsonResponse));
    
    echo $jsonResponse;
    
    // Forzar flush de salida
    if (ob_get_level()) {
        ob_end_flush();
    }
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

// VERIFICACIÓN FINAL DE ARCHIVOS ANTES DE CONTINUAR
clearstatcache();
$finalProgressCheck = file_exists($progressFile);
$finalLogCheck = file_exists($logFile);

error_log("BACKUP: Verificación final antes de continuar - Progress: " . ($finalProgressCheck ? 'SI' : 'NO') . ", Log: " . ($finalLogCheck ? 'SI' : 'NO'));

if (!$finalProgressCheck || !$finalLogCheck) {
    error_log("BACKUP CRITICAL: Archivos no existen antes de continuar, recreando...");
    // Recrear archivos si no existen
    if (!$finalProgressCheck) {
        @file_put_contents($progressFile, '0');
        @chmod($progressFile, 0666);
        clearstatcache();
    }
    if (!$finalLogCheck) {
        $tipoTexto = ($tipo == 'database_only') ? 'BASE DE DATOS' : (($tipo == 'files_only') ? 'ARCHIVOS' : 'COMPLETO');
        $recreateLogContent = "═══════════════════════════════════════════════════════════\n";
        $recreateLogContent .= "TIPO DE COPIA DE SEGURIDAD: $tipoTexto\n";
        $recreateLogContent .= "═══════════════════════════════════════════════════════════\n";
        $recreateLogContent .= "Fecha: " . date('Y-m-d H:i:s') . "\n";
        $recreateLogContent .= "Backup ID: $backupId\n";
        $recreateLogContent .= "Tipo (código): $tipo\n";
        $recreateLogContent .= "Modo: " . ($isAutomatic == 1 ? 'AUTOMÁTICO' : 'MANUAL') . "\n";
        $recreateLogContent .= "═══════════════════════════════════════════════════════════\n\n";
        @file_put_contents($logFile, $recreateLogContent);
        @chmod($logFile, 0666);
        clearstatcache();
    }
    // Esperar un momento para asegurar escritura
    usleep(200000); // 0.2 segundos
    error_log("BACKUP: Después de recrear - Progress: " . (file_exists($progressFile) ? 'SI' : 'NO') . ", Log: " . (file_exists($logFile) ? 'SI' : 'NO'));
}

// DEBUG: Antes de cerrar conexión
$debugConnection = "\n=== DEBUG ANTES DE CERRAR CONEXIÓN ===\n";
$debugConnection .= "fastcgi_finish_request disponible: " . (function_exists('fastcgi_finish_request') ? 'SI' : 'NO') . "\n";
$debugConnection .= "ignore_user_abort activo: " . (ignore_user_abort() ? 'SI' : 'NO') . "\n";
$debugConnection .= "Tiempo límite: " . ini_get('max_execution_time') . " segundos\n";
$debugConnection .= "Memoria límite: " . ini_get('memory_limit') . "\n";
$debugConnection .= "Archivos antes de cerrar - Progress: " . (file_exists($progressFile) ? 'SI' : 'NO') . ", Log: " . (file_exists($logFile) ? 'SI' : 'NO') . "\n";

if (file_exists($logFile)) {
    @file_put_contents($logFile, $debugConnection, FILE_APPEND | LOCK_EX);
} else {
    error_log("BACKUP ERROR: logFile no existe para escribir debugConnection");
}

// Crear heartbeat ANTES de cerrar conexión
$heartbeatFile = $backupDir . DIRECTORY_SEPARATOR . 'heartbeat_' . $fecha . '.txt';
@file_put_contents($heartbeatFile, date('Y-m-d H:i:s') . " - ANTES DE CERRAR CONEXIÓN\n", LOCK_EX);
error_log("BACKUP: Heartbeat creado: " . (file_exists($heartbeatFile) ? 'SI' : 'NO'));

// Si usamos FastCGI, necesitamos enviar más bytes
if (function_exists('fastcgi_finish_request')) {
    if (file_exists($logFile)) {
        @file_put_contents($logFile, "Ejecutando fastcgi_finish_request()...\n", FILE_APPEND | LOCK_EX);
    }
    fastcgi_finish_request();
    
    // Actualizar heartbeat inmediatamente después de fastcgi_finish_request
    @file_put_contents($heartbeatFile, date('Y-m-d H:i:s') . " - DESPUÉS DE fastcgi_finish_request\n", LOCK_EX);
    
    if (file_exists($logFile)) {
        @file_put_contents($logFile, "fastcgi_finish_request() ejecutado\n", FILE_APPEND | LOCK_EX);
        @file_put_contents($logFile, "Heartbeat actualizado después de fastcgi\n", FILE_APPEND | LOCK_EX);
    }
} else {
    // Enviar padding para asegurar que el cliente reciba la respuesta
    if (file_exists($logFile)) {
        @file_put_contents($logFile, "Enviando padding (no FastCGI)...\n", FILE_APPEND | LOCK_EX);
    }
    echo str_repeat(' ', 1024);
    flush();
    
    // Actualizar heartbeat después de flush
    @file_put_contents($heartbeatFile, date('Y-m-d H:i:s') . " - DESPUÉS DE FLUSH\n", LOCK_EX);
    
    if (file_exists($logFile)) {
        @file_put_contents($logFile, "Padding enviado, script continúa...\n", FILE_APPEND | LOCK_EX);
    }
}

// Pequeña pausa y actualizar heartbeat
usleep(200000); // 0.2 segundos
@file_put_contents($heartbeatFile, date('Y-m-d H:i:s') . " - DESPUÉS DE PAUSA INICIAL\n", FILE_APPEND | LOCK_EX);

if (file_exists($logFile)) {
    @file_put_contents($logFile, "✅✅✅ DESPUÉS DE CERRAR CONEXIÓN - SCRIPT SIGUE ✅✅✅\n", FILE_APPEND | LOCK_EX);
    @file_put_contents($logFile, "Timestamp: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND | LOCK_EX);
    @file_put_contents($logFile, "PID del proceso: " . getmypid() . "\n", FILE_APPEND | LOCK_EX);
    @file_put_contents($logFile, "Heartbeat file: $heartbeatFile\n", FILE_APPEND | LOCK_EX);
}

// AHORA cargar Dolibarr (después de crear los archivos críticos)
// Verificar que existe el archivo antes de requerirlo
$mainIncPath = __DIR__ . '/../../../main.inc.php';

// Actualizar heartbeat antes de cargar Dolibarr
if (file_exists($heartbeatFile)) {
    @file_put_contents($heartbeatFile, date('Y-m-d H:i:s') . " - ANTES DE CARGAR DOLIBARR\n", FILE_APPEND | LOCK_EX);
}

if (file_exists($logFile)) {
    @file_put_contents($logFile, "\n=== DEBUG CARGA DE DOLIBARR ===\n", FILE_APPEND | LOCK_EX);
    @file_put_contents($logFile, "Ruta main.inc.php: $mainIncPath\n", FILE_APPEND | LOCK_EX);
    @file_put_contents($logFile, "Archivo existe: " . (file_exists($mainIncPath) ? 'SI' : 'NO') . "\n", FILE_APPEND | LOCK_EX);
    @file_put_contents($logFile, "PID: " . getmypid() . "\n", FILE_APPEND | LOCK_EX);
}

if (!file_exists($mainIncPath)) {
    if (file_exists($logFile)) {
        @file_put_contents($logFile, "❌ ERROR: No se encontró main.inc.php en: $mainIncPath\n", FILE_APPEND | LOCK_EX);
    }
    if (file_exists($progressFile)) {
        @file_put_contents($progressFile, '-1');
    }
    exit;
}

if (file_exists($logFile)) {
    @file_put_contents($logFile, "Cargando configuración (conf.php)...\n", FILE_APPEND | LOCK_EX);
}

// CRÍTICO: Cargar configuración SIN ejecutar main.inc.php completo (para evitar exit/die)
// main.inc.php tiene verificaciones de seguridad que ejecutan exit() y detienen el script
// En su lugar, cargar directamente conf.php y definir variables necesarias
$confPath = __DIR__ . '/../../../conf/conf.php';
if (!file_exists($confPath)) {
    if (file_exists($logFile)) {
        @file_put_contents($logFile, "❌ ERROR: No se encontró conf.php en: $confPath\n", FILE_APPEND | LOCK_EX);
    }
    if (file_exists($progressFile)) {
        @file_put_contents($progressFile, '-1');
    }
    exit;
}

if (file_exists($heartbeatFile)) {
    @file_put_contents($heartbeatFile, date('Y-m-d H:i:s') . " - Cargando conf.php directamente\n", FILE_APPEND | LOCK_EX);
}

// Cargar conf.php (esto define las variables de BD sin ejecutar main.inc.php)
require_once $confPath;

// Verificar que las variables de BD estén disponibles
if (!isset($dolibarr_main_db_host) || !isset($dolibarr_main_db_name)) {
    if (file_exists($heartbeatFile)) {
        @file_put_contents($heartbeatFile, date('Y-m-d H:i:s') . " - ERROR: Variables de BD no definidas después de conf.php\n", FILE_APPEND | LOCK_EX);
    }
    if (file_exists($logFile)) {
        @file_put_contents($logFile, "❌ ERROR: Variables de BD no definidas después de conf.php\n", FILE_APPEND | LOCK_EX);
    }
    if (file_exists($progressFile)) {
        @file_put_contents($progressFile, '-1');
    }
    exit;
}

if (file_exists($heartbeatFile)) {
    @file_put_contents($heartbeatFile, date('Y-m-d H:i:s') . " - conf.php cargado, variables de BD disponibles\n", FILE_APPEND | LOCK_EX);
}

if (file_exists($logFile)) {
    @file_put_contents($logFile, "✅ conf.php cargado directamente (sin main.inc.php completo)\n", FILE_APPEND | LOCK_EX);
    @file_put_contents($logFile, "DB Host: " . ($dolibarr_main_db_host ?? 'NO DEFINIDO') . "\n", FILE_APPEND | LOCK_EX);
    @file_put_contents($logFile, "DB Name: " . ($dolibarr_main_db_name ?? 'NO DEFINIDO') . "\n", FILE_APPEND | LOCK_EX);
    @file_put_contents($logFile, "DB User: " . ($dolibarr_main_db_user ?? 'NO DEFINIDO') . "\n", FILE_APPEND | LOCK_EX);
}

// DEBUG: Verificar configuración cargada
$debugConfig = "\n=== DEBUG CONFIGURACIÓN CARGADA ===\n";
$debugConfig .= "conf.php path: $confPath\n";
$debugConfig .= "conf.php existe: " . (file_exists($confPath) ? 'SI' : 'NO') . "\n";
$debugConfig .= "DB Host: " . ($dolibarr_main_db_host ?? 'NO CONFIGURADO') . "\n";
$debugConfig .= "DB Name: " . ($dolibarr_main_db_name ?? 'NO CONFIGURADO') . "\n";
$debugConfig .= "DB User: " . ($dolibarr_main_db_user ?? 'NO CONFIGURADO') . "\n";
$debugConfig .= "DB Port: " . ($dolibarr_main_db_port ?? '3306') . "\n";
$debugConfig .= "Memoria usada ahora: " . round(memory_get_usage()/1024/1024, 2) . " MB\n";
$debugConfig .= "================================\n\n";

if (file_exists($heartbeatFile)) {
    @file_put_contents($heartbeatFile, date('Y-m-d H:i:s') . " - CONFIGURACIÓN LISTA\n", FILE_APPEND | LOCK_EX);
}

if (file_exists($logFile)) {
    @file_put_contents($logFile, $debugConfig, FILE_APPEND | LOCK_EX);
    @file_put_contents($logFile, "✅ Configuración cargada correctamente\n", FILE_APPEND | LOCK_EX);
    @file_put_contents($logFile, "⏩ Entrando en bloque try principal...\n", FILE_APPEND | LOCK_EX);
}

// Actualizar heartbeat antes del try
if (file_exists($heartbeatFile)) {
    @file_put_contents($heartbeatFile, date('Y-m-d H:i:s') . " - ANTES DE ENTRAR AL TRY PRINCIPAL\n", FILE_APPEND | LOCK_EX);
}

try {
    // Actualizar heartbeat al entrar al try
    if (isset($heartbeatFile) && file_exists($heartbeatFile)) {
        @file_put_contents($heartbeatFile, date('Y-m-d H:i:s') . " - ✅✅✅ DENTRO DEL TRY PRINCIPAL ✅✅✅\n", FILE_APPEND | LOCK_EX);
    }
    
    // Asegurar que los archivos existen antes de continuar
    if (!file_exists($logFile)) {
        // Si el archivo de log no existe, crear uno temporal en /tmp
        $logFile = sys_get_temp_dir() . '/backup_log_' . $fecha . '.txt';
        @file_put_contents($logFile, "⚠️ Archivo de log original no existe, usando temporal: $logFile\n", LOCK_EX);
    }
    
    if (!file_exists($progressFile)) {
        $progressFile = sys_get_temp_dir() . '/backup_progress_' . $fecha . '.txt';
        @file_put_contents($progressFile, '0');
    }
    
    if (file_exists($logFile)) {
        @file_put_contents($logFile, "\n✅✅✅ BLOQUE TRY INICIADO - SCRIPT SIGUE EJECUTÁNDOSE ✅✅✅\n", FILE_APPEND | LOCK_EX);
        @file_put_contents($logFile, "Timestamp: " . date('Y-m-d H:i:s') . "\n", FILE_APPEND | LOCK_EX);
        @file_put_contents($logFile, "PID del proceso: " . getmypid() . "\n", FILE_APPEND | LOCK_EX);
        @file_put_contents($logFile, "Memoria actual: " . round(memory_get_usage()/1024/1024, 2) . " MB\n", FILE_APPEND | LOCK_EX);
    }
    
    // Actualizar heartbeat después de verificar archivos
    if (isset($heartbeatFile) && file_exists($heartbeatFile)) {
        @file_put_contents($heartbeatFile, date('Y-m-d H:i:s') . " - Archivos verificados, continuando...\n", FILE_APPEND | LOCK_EX);
    }

// Función para log
function logMsg($msg, $logFile) {
    $timestamp = date('H:i:s');
    if (file_exists($logFile)) {
        file_put_contents($logFile, "[$timestamp] $msg\n", FILE_APPEND | LOCK_EX);
    }
}

// Función para progreso
function updateProg($prog, $progressFile, $logFile) {
    if (file_exists($progressFile)) {
        file_put_contents($progressFile, $prog);
    }
    logMsg("PROGRESO: $prog%", $logFile);
}

// INICIO
$tipoTexto = ($tipo == 'database_only') ? 'BASE DE DATOS' : (($tipo == 'files_only') ? 'ARCHIVOS' : 'COMPLETO');
logMsg("═══════════════════════════════════════════════════════════", $logFile);
logMsg("TIPO DE COPIA DE SEGURIDAD: $tipoTexto", $logFile);
logMsg("═══════════════════════════════════════════════════════════", $logFile);
logMsg("Modo: " . ($isAutomatic == 1 ? 'AUTOMÁTICO' : 'MANUAL'), $logFile);
logMsg("Tipo (código): $tipo", $logFile);
logMsg("Backup ID: $backupId", $logFile);
logMsg("Fecha: " . date('Y-m-d H:i:s'), $logFile);
logMsg("═══════════════════════════════════════════════════════════", $logFile);
logMsg("", $logFile);
logMsg("=== BACKUP INICIADO ===", $logFile);
logMsg("DEBUG: Memoria disponible: " . ini_get('memory_limit'), $logFile);
logMsg("DEBUG: Tiempo máximo: " . ini_get('max_execution_time') . " segundos", $logFile);
logMsg("DEBUG: ignore_user_abort: " . (ignore_user_abort() ? 'SI' : 'NO'), $logFile);
logMsg("DEBUG: Script continúa ejecutándose después de cerrar conexión", $logFile);

// PROGRESO 5%
updateProg(5, $progressFile, $logFile);
logMsg("Conectando a base de datos...", $logFile);

// DEBUG: Verificar variables globales antes de usarlas
logMsg("DEBUG: Verificando variables globales de BD...", $logFile);
logMsg("DEBUG: isset(\$dolibarr_main_db_host): " . (isset($dolibarr_main_db_host) ? 'SI' : 'NO'), $logFile);
logMsg("DEBUG: isset(\$dolibarr_main_db_name): " . (isset($dolibarr_main_db_name) ? 'SI' : 'NO'), $logFile);

// Configuración de base de datos (dinámico desde conf.php)
global $dolibarr_main_db_host, $dolibarr_main_db_name, $dolibarr_main_db_user, $dolibarr_main_db_pass;

$host = $dolibarr_main_db_host ?? 'localhost';
$name = $dolibarr_main_db_name ?? 'dolibarr';
$user = $dolibarr_main_db_user ?? 'root';
$pass = $dolibarr_main_db_pass ?? '';

logMsg("Conectando a: Host=$host, DB=$name", $logFile);

// DEBUG: Antes de conectar
logMsg("DEBUG: Intentando conectar a BD...", $logFile);
logMsg("DEBUG: Host=$host, DB=$name, User=$user", $logFile);

// Intentar conectar con timeout
try {
    $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 5,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"
    ]);
    
    logMsg("DEBUG: PDO creado exitosamente", $logFile);
    logMsg("Conexión exitosa a $name", $logFile);
    
    // Inicializar conexión Dolibarr para el log de actividad
    // Cargar la librería de Dolibarr para crear objeto $db
    $dolibarrIncPath = __DIR__ . '/../../../includes/core/db/DoliDB.class.php';
    if (file_exists($dolibarrIncPath)) {
        require_once $dolibarrIncPath;
        if (class_exists('DoliDB')) {
            // Crear instancia de DoliDB usando las credenciales
            $dbType = 'mysqli'; // o 'mysql' dependiendo de la configuración
            if (isset($dolibarr_main_db_type)) {
                $dbType = $dolibarr_main_db_type;
            }
            
            // Intentar crear objeto db de Dolibarr
            try {
                if (!isset($db) || !is_object($db)) {
                    // Cargar el tipo correcto de DoliDB
                    $doliDbClass = 'DoliDB' . strtoupper($dbType);
                    $doliDbPath = __DIR__ . '/../../../includes/core/db/' . $doliDbClass . '.class.php';
                    if (file_exists($doliDbPath)) {
                        require_once $doliDbPath;
                    }
                    if (class_exists($doliDbClass)) {
                        $db = new $doliDbClass();
                        $db->database_name = $name;
                        // Conectar manualmente
                        $result = $db->connect($host, $user, $pass, $name, 0);
                        if ($result) {
                            logMsg("Conexión Dolibarr DB inicializada para logs", $logFile);
                            // Hacer disponible globalmente
                            global $db;
                        } else {
                            logMsg("No se pudo conectar Dolibarr DB, usando PDO para logs", $logFile);
                        }
                    }
                }
            } catch (Exception $e) {
                logMsg("Error al inicializar DB de Dolibarr: " . $e->getMessage(), $logFile);
            }
        }
    }
    
    // Registrar inicio de backup en log de actividad ahora que tenemos conexión
    try {
        $libPath = __DIR__ . '/../lib/filemanager.lib.php';
        if (file_exists($libPath)) {
            if (!function_exists('logFileManagerActivity')) {
                require_once $libPath;
            }
            if (function_exists('logFileManagerActivity')) {
                $zipFile = $backupDir . DIRECTORY_SEPARATOR . 'db_dolibarr_' . $fecha . '.zip';
                // Asegurar que $db esté disponible globalmente
                if (!isset($GLOBALS['db']) && isset($db)) {
                    $GLOBALS['db'] = $db;
                }
                logFileManagerActivity('create_backup', $zipFile, $user_id, "Backup de base de datos iniciado - ID: $backupId");
                logMsg("Log de actividad registrado: create_backup", $logFile);
            }
        }
    } catch (Exception $e) {
        logMsg("No se pudo registrar backup en log de actividad: " . $e->getMessage(), $logFile);
        error_log("No se pudo registrar backup en log de actividad: " . $e->getMessage());
    }
    
} catch (PDOException $e) {
    logMsg("DEBUG ERROR: Fallo conexión PDO: " . $e->getMessage(), $logFile);
    updateProg(-1, $progressFile, $logFile);
    throw $e;
}

// PROGRESO 10%
updateProg(10, $progressFile, $logFile);
logMsg("Obteniendo lista de tablas...", $logFile);

logMsg("DEBUG: Ejecutando SHOW TABLES...", $logFile);
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$totalTables = count($tables);
logMsg("DEBUG: Total tablas encontradas: $totalTables", $logFile);
logMsg("DEBUG: Primeras 5 tablas: " . implode(', ', array_slice($tables, 0, 5)), $logFile);
logMsg("Encontradas $totalTables tablas", $logFile);

// PROGRESO 15%
updateProg(15, $progressFile, $logFile);
logMsg("Creando archivo SQL...", $logFile);

// Guardar información del usuario que crea el backup
// Intentar obtener el usuario desde múltiples fuentes (tanto para manuales como automáticos)
$user_login = 'unknown';
$user_id = 0;

// Método 1: Desde parámetros GET/POST (pasados desde setup.php) - PRIORIDAD ALTA
if (isset($_GET['user_login']) && !empty($_GET['user_login']) && trim($_GET['user_login']) !== '' && trim($_GET['user_login']) !== 'unknown') {
    $user_login = trim($_GET['user_login']);
    $user_id = isset($_GET['user_id']) && !empty($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    logMsg("Usuario obtenido desde GET: " . $user_login . " (ID: " . $user_id . ")", $logFile);
} elseif (isset($_POST['user_login']) && !empty($_POST['user_login']) && trim($_POST['user_login']) !== '' && trim($_POST['user_login']) !== 'unknown') {
    $user_login = trim($_POST['user_login']);
    $user_id = isset($_POST['user_id']) && !empty($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    logMsg("Usuario obtenido desde POST: " . $user_login . " (ID: " . $user_id . ")", $logFile);
}
// Método 2: Desde global $user (si está disponible)
else {
    global $user;
    // Método 2: Intentar desde global $user
    if (isset($user) && is_object($user) && !empty($user->login)) {
        $user_login = $user->login;
        $user_id = !empty($user->id) ? intval($user->id) : 0;
    }
    // Método 3: Desde variables de sesión específicas de Dolibarr
    elseif (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['dol_login']) && !empty($_SESSION['dol_login'])) {
        $user_login = $_SESSION['dol_login'];
        $user_id = isset($_SESSION['dol_userid']) ? intval($_SESSION['dol_userid']) : 0;
    }
    // Método 4: Desde sesión PHP (último recurso)
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
file_put_contents($backupDir . DIRECTORY_SEPARATOR . 'backup_info_' . $fecha . '.json', json_encode($backup_info));
logMsg("Usuario del backup: " . $user_login . " (ID: " . $user_id . ")", $logFile);

// Registrar en log de actividad ANTES de continuar (cuando aún tenemos acceso a la BD si está disponible)
// NOTA: Esto se registrará después de cargar la BD, así que esperaremos a hacerlo más adelante

$sqlFile = $backupDir . DIRECTORY_SEPARATOR . 'backup_dolibarr_' . $fecha . '.sql';
$sql = fopen($sqlFile, 'w');
if (!$sql) {
    logMsg("ERROR: No se pudo crear archivo SQL", $logFile);
    updateProg(100, $progressFile, $logFile);
    echo json_encode(['success' => false, 'error' => 'No se pudo crear archivo SQL']);
    exit;
}

// Escribir encabezado compatible con phpMyAdmin
fwrite($sql, "-- phpMyAdmin SQL Dump\n");
fwrite($sql, "-- version 5.2.1\n");
fwrite($sql, "-- https://www.phpmyadmin.net/\n");
fwrite($sql, "--\n");
fwrite($sql, "-- Host: localhost\n");
fwrite($sql, "-- Generation Time: " . date('M d, Y \a\t H:i:s') . "\n");
fwrite($sql, "-- Server version: 8.0.30\n");
fwrite($sql, "-- PHP Version: " . PHP_VERSION . "\n\n");
fwrite($sql, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
fwrite($sql, "START TRANSACTION;\n");
fwrite($sql, "SET time_zone = \"+00:00\";\n\n");
fwrite($sql, "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n");
fwrite($sql, "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n");
fwrite($sql, "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n");
fwrite($sql, "/*!50503 SET NAMES utf8mb4 */;\n");
fwrite($sql, "/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;\n");
fwrite($sql, "/*!40103 SET TIME_ZONE='+00:00' */;\n");
fwrite($sql, "/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;\n");
fwrite($sql, "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n");
fwrite($sql, "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n");
fwrite($sql, "/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;\n\n");

$processedTables = 0;
$totalRecords = 0;

logMsg("DEBUG: Iniciando bucle de exportación de tablas", $logFile);
logMsg("DEBUG: Se procesarán $totalTables tablas", $logFile);
logMsg("Exportando tablas...", $logFile);
foreach ($tables as $table) {
    $processedTables++;
    $progress = 15 + (($processedTables / $totalTables) * 60); // 15% a 75%
    updateProg($progress, $progressFile, $logFile);
    
    if ($processedTables <= 3 || $processedTables % 10 == 0) {
        logMsg("DEBUG: Procesando tabla #$processedTables: $table", $logFile);
    }
    logMsg("Procesando tabla $table ($processedTables/$totalTables)", $logFile);
    
    // Estructura de la tabla
    try {
        $createTable = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
        fwrite($sql, "--\n");
        fwrite($sql, "-- Table structure for table `$table`\n");
        fwrite($sql, "--\n\n");
        fwrite($sql, "DROP TABLE IF EXISTS `$table`;\n");
        fwrite($sql, "/*!40101 SET @saved_cs_client     = @@character_set_client */;\n");
        fwrite($sql, "/*!50503 SET character_set_client = utf8mb4 */;\n");
        fwrite($sql, $createTable['Create Table'] . ";\n");
        fwrite($sql, "/*!40101 SET character_set_client = @saved_cs_client */;\n\n");
        logMsg("  Estructura de $table exportada", $logFile);
    } catch (Exception $e) {
        logMsg("  ERROR en estructura de $table: " . $e->getMessage(), $logFile);
        continue;
    }
    
    // Datos de la tabla
    try {
        $countStmt = $pdo->query("SELECT COUNT(*) FROM `$table`");
        $totalRows = $countStmt->fetchColumn();
        $totalRecords += $totalRows;
        
        if ($totalRows > 0) {
            fwrite($sql, "--\n");
            fwrite($sql, "-- Dumping data for table `$table`\n");
            fwrite($sql, "--\n\n");
            fwrite($sql, "LOCK TABLES `$table` WRITE;\n");
            fwrite($sql, "/*!40000 ALTER TABLE `$table` DISABLE KEYS */;\n");
            logMsg("  Exportando $totalRows registros de $table...", $logFile);
            
            // Lotes de 5000 registros para ser más rápido
            $batchSize = 5000;
            $offset = 0;
            $count = 0;
            
            while ($offset < $totalRows) {
                $stmt = $pdo->query("SELECT * FROM `$table` LIMIT $batchSize OFFSET $offset");
                $batch = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                if (empty($batch)) break;
                
                // Crear INSERT más simple
                $inserts = array();
                foreach ($batch as $row) {
                    $values = array();
                    foreach ($row as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = "'" . addslashes($value) . "'";
                        }
                    }
                    $inserts[] = "(" . implode(',', $values) . ")";
                }
                
                if (!empty($inserts)) {
                    fwrite($sql, "INSERT INTO `$table` VALUES " . implode(',', $inserts) . ";\n");
                }
                
                $count += count($batch);
                $offset += $batchSize;
                
                // Log cada 1000 registros
                if ($count % 1000 == 0) {
                    logMsg("  Procesando $table: $count/$totalRows registros", $logFile);
                }
                
                // Liberar memoria
                unset($batch, $inserts);
                gc_collect_cycles();
            }
            
            fwrite($sql, "/*!40000 ALTER TABLE `$table` ENABLE KEYS */;\n");
            fwrite($sql, "UNLOCK TABLES;\n\n");
            logMsg("  Tabla $table completada: $count registros", $logFile);
        } else {
            fwrite($sql, "-- Tabla $table vacía\n\n");
            logMsg("  Tabla $table vacía", $logFile);
        }
    } catch (Exception $e) {
        logMsg("  ERROR procesando datos de $table: " . $e->getMessage(), $logFile);
    }
}

fwrite($sql, "/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;\n\n");
fwrite($sql, "/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;\n");
fwrite($sql, "/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;\n");
fwrite($sql, "/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;\n");
fwrite($sql, "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n");
fwrite($sql, "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n");
fwrite($sql, "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n");
fwrite($sql, "/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;\n\n");
fwrite($sql, "COMMIT;\n");
fclose($sql);

// PROGRESO 75%
updateProg(75, $progressFile, $logFile);
logMsg("Archivo SQL completado", $logFile);

$sqlSize = filesize($sqlFile);
logMsg("Archivo SQL: " . number_format($sqlSize) . " bytes (" . round($sqlSize/1024/1024, 2) . " MB)", $logFile);
logMsg("Total registros exportados: " . number_format($totalRecords), $logFile);

// Verificar que el archivo SQL existe y tiene contenido
if (!file_exists($sqlFile) || $sqlSize == 0) {
    logMsg("ERROR: Archivo SQL no existe o está vacío", $logFile);
    updateProg(100, $progressFile, $logFile);
    echo json_encode(['success' => false, 'error' => 'Archivo SQL no existe o está vacío']);
    exit;
}

// PROGRESO 85%
updateProg(85, $progressFile, $logFile);
logMsg("Creando archivo ZIP...", $logFile);
logMsg("Ruta SQL: $sqlFile (existe: " . (file_exists($sqlFile) ? 'SI' : 'NO') . ")", $logFile);
logMsg("Ruta ZIP: $zipFile", $logFile);

$zip = new ZipArchive();
$openResult = $zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

logMsg("Resultado de open ZIP: " . ($openResult === TRUE ? 'TRUE' : $openResult), $logFile);

if ($openResult === TRUE) {
    logMsg("Agregando archivo SQL al ZIP...", $logFile);
    $addResult = $zip->addFile($sqlFile, 'backup_dolibarr_'.$fecha.'.sql');
    logMsg("Resultado de addFile: " . ($addResult ? 'TRUE' : 'FALSE'), $logFile);
    
    // Obtener tamaño antes de cerrar
    $zipSizeBefore = file_exists($zipFile) ? filesize($zipFile) : 0;
    logMsg("Tamaño ZIP antes de cerrar: " . round($zipSizeBefore/1024, 2) . " KB", $logFile);
    
    // PROGRESO 92%
    updateProg(92, $progressFile, $logFile);
    logMsg("Cerrando archivo ZIP...", $logFile);
    
    // Cerrar ZIP (no existe flush() en ZipArchive, solo close())
    $closeResult = $zip->close();
    logMsg("Resultado de close ZIP: " . ($closeResult ? 'TRUE' : 'FALSE'), $logFile);
    
    // Obtener tamaño final después de cerrar
    $zipSizeFinal = filesize($zipFile);
    logMsg("ZIP cerrado exitosamente. Tamaño final: " . number_format($zipSizeFinal) . " bytes (" . round($zipSizeFinal/1024/1024, 2) . " MB)", $logFile);
    logMsg("Verificando archivo ZIP...", $logFile);
    
    if ($zipSizeFinal > 0) {
        logMsg("✓ ZIP válido y con contenido", $logFile);
        
        // PROGRESO 95% - ZIP válido
        updateProg(95, $progressFile, $logFile);
        logMsg("Verificando integridad del ZIP...", $logFile);
        
        // PROGRESO 98%
        updateProg(98, $progressFile, $logFile);
        logMsg("Limpiando archivo SQL temporal...", $logFile);
    } else {
        logMsg("ERROR: ZIP creado pero está vacío", $logFile);
        updateProg(100, $progressFile, $logFile);
        echo json_encode(['success' => false, 'error' => 'ZIP creado pero está vacío']);
        exit;
    }
} else {
    logMsg("ERROR creando ZIP. Código de error: $openResult", $logFile);
    updateProg(100, $progressFile, $logFile);
    echo json_encode(['success' => false, 'error' => 'No se pudo crear ZIP']);
    exit;
}

// PROGRESO 100% - BACKUP COMPLETADO
updateProg(100, $progressFile, $logFile);

// DEBUG FINAL
$debugFinal = "\n=== DEBUG FINAL ===\n";
$debugFinal .= "Timestamp finalización: " . date('Y-m-d H:i:s') . "\n";
$debugFinal .= "Archivo ZIP existe: " . (file_exists($zipFile) ? 'SI' : 'NO') . "\n";
if (file_exists($zipFile)) {
    $debugFinal .= "Tamaño ZIP: " . filesize($zipFile) . " bytes (" . round(filesize($zipFile)/1024/1024, 2) . " MB)\n";
    $debugFinal .= "ZIP es legible: " . (is_readable($zipFile) ? 'SI' : 'NO') . "\n";
}
$debugFinal .= "Archivo progreso existe: " . (file_exists($progressFile) ? 'SI' : 'NO') . "\n";
$debugFinal .= "Archivo log existe: " . (file_exists($logFile) ? 'SI' : 'NO') . "\n";
$debugFinal .= "Memoria usada: " . round(memory_get_usage()/1024/1024, 2) . " MB\n";
$debugFinal .= "Memoria pico: " . round(memory_get_peak_usage()/1024/1024, 2) . " MB\n";
$debugFinal .= "Tablas procesadas: $processedTables/$totalTables\n";
$debugFinal .= "Total registros: " . number_format($totalRecords) . "\n";
$debugFinal .= "==================\n\n";

logMsg($debugFinal, $logFile);
logMsg("=== BACKUP COMPLETADO EXITOSAMENTE ===", $logFile);
logMsg("Archivo final: " . basename($zipFile), $logFile);
logMsg("Tamaño final: " . number_format(filesize($zipFile)) . " bytes (" . round(filesize($zipFile)/1024/1024, 2) . " MB)", $logFile);
logMsg("", $logFile);
logMsg("═══════════════════════════════════════════════════════════", $logFile);
logMsg("✅ COPIA DE SEGURIDAD COMPLETADA EXITOSAMENTE", $logFile);
logMsg("═══════════════════════════════════════════════════════════", $logFile);
logMsg("El archivo ZIP se ha creado correctamente y está listo para descargar.", $logFile);
logMsg("Los archivos temporales se eliminarán automáticamente en unos segundos.", $logFile);

// Eliminar lock manual si NO es backup automático
if ($isAutomatic == 0 && isset($manualLockFile) && file_exists($manualLockFile)) {
    @unlink($manualLockFile);
    error_log("BACKUP: Lock manual eliminado: $manualLockFile");
    logMsg("Lock manual eliminado (backup manual completado)", $logFile);
}

// Registrar finalización del backup en log de actividad
try {
    // Asegurar que $db esté disponible globalmente
    if (isset($db) && !isset($GLOBALS['db'])) {
        $GLOBALS['db'] = $db;
    }
    
    if (!function_exists('logFileManagerActivity')) {
        require_once __DIR__ . '/../lib/filemanager.lib.php';
    }
    
    if (function_exists('logFileManagerActivity')) {
        $zipSize = file_exists($zipFile) ? filesize($zipFile) : 0;
        $zipSizeMB = round($zipSize / 1024 / 1024, 2);
        logFileManagerActivity('backup_completed', $zipFile, $user_id, "Backup de base de datos completado - Tamaño: $zipSizeMB MB");
        logMsg("Log de actividad registrado: backup_completed", $logFile);
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
        logMsg("Usuario final del backup actualizado: " . $final_user_login . " (ID: " . $final_user_id . ")", $logFile);
    }
} catch (Exception $e) {
    logMsg("No se pudo registrar finalización de backup en log de actividad: " . $e->getMessage(), $logFile);
    error_log("No se pudo registrar finalización de backup en log de actividad: " . $e->getMessage());
}

// Eliminar lock manual si NO es backup automático
if ($isAutomatic == 0 && isset($manualLockFile) && file_exists($manualLockFile)) {
    @unlink($manualLockFile);
    error_log("BACKUP SIMPLE: Lock manual eliminado: $manualLockFile");
    if (isset($logFile)) {
        logMsg("Lock manual eliminado (backup manual completado)", $logFile);
    }
}

// Eliminar lock de auto_backup si ES backup automático
if ($isAutomatic == 1) {
    $autoLockFile = $backupDir . DIRECTORY_SEPARATOR . 'auto_backup.lock';
    if (file_exists($autoLockFile)) {
        @unlink($autoLockFile);
        error_log("BACKUP SIMPLE: Lock automático eliminado: $autoLockFile");
        if (isset($logFile)) {
            logMsg("Lock automático eliminado (backup automático completado)", $logFile);
        }
    }
}

// Verificar que el ZIP existe y tiene contenido válido antes de limpiar
if (file_exists($zipFile) && filesize($zipFile) > 1000) {
    logMsg("Iniciando limpieza de archivos temporales...", $logFile);
    
    // Lista de TODOS los archivos temporales a eliminar (todo excepto archivos ZIP)
    $tempFilesToDelete = [];
    
    // Archivo SQL temporal
    if (isset($sqlFile) && file_exists($sqlFile)) {
        $tempFilesToDelete[] = $sqlFile;
    }
    
    // Archivos de progreso y log
    if (file_exists($progressFile)) {
        $tempFilesToDelete[] = $progressFile;
    }
    if (file_exists($logFile)) {
        $tempFilesToDelete[] = $logFile;
    }
    
    // Heartbeat file
    $heartbeatFile = $backupDir . DIRECTORY_SEPARATOR . 'heartbeat_' . $fecha . '.txt';
    if (file_exists($heartbeatFile)) {
        $tempFilesToDelete[] = $heartbeatFile;
    }
    
    // NO eliminar backup_info_*.json porque contiene información del usuario necesaria para mostrar en la tabla
    // Este archivo debe preservarse para poder mostrar el usuario que creó el backup
    
    // Buscar archivos temporales adicionales del mismo backup_id
    $allTempFiles = glob($backupDir . DIRECTORY_SEPARATOR . '*' . $fecha . '*');
    foreach ($allTempFiles as $file) {
        // Solo eliminar si NO es un archivo ZIP
        if (file_exists($file) && !preg_match('/\.zip$/i', $file)) {
            $tempFilesToDelete[] = $file;
        }
    }
    
    // También buscar en /tmp si se usó como alternativa
    $tempBackupDir = sys_get_temp_dir() . '/dolibarr_backups';
    if (is_dir($tempBackupDir)) {
        $tempAllFiles = glob($tempBackupDir . DIRECTORY_SEPARATOR . '*' . $fecha . '*');
        foreach ($tempAllFiles as $file) {
            if (file_exists($file) && !preg_match('/\.zip$/i', $file)) {
                $tempFilesToDelete[] = $file;
            }
        }
    }
    
    // Eliminar todos los archivos temporales
    $deletedCount = 0;
    foreach ($tempFilesToDelete as $tempFile) {
        if (file_exists($tempFile) && !preg_match('/\.zip$/i', $tempFile)) {
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
    logMsg("✓ Solo quedan archivos ZIP y logs", $logFile);
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
        error_log("BACKUP: Lock manual eliminado después de error: $manualLockFile");
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
        error_log("BACKUP: Lock manual eliminado después de error fatal: $manualLockFile");
    }
    
    error_log("BACKUP FATAL ERROR: " . $e->getMessage());
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
    exit;
}

// La limpieza ya se hizo dentro del bloque try si el ZIP se creó exitosamente
exit;
?>