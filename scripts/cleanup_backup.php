<?php
/**
 * Limpia todos los archivos generados por un backup específico
 * Incluye: ZIP, estados, logs, filelists, análisis, etc.
 * FUNCIONA EN CUALQUIER HOSTING - Detección robusta de rutas
 */

// Headers JSON PRIMERO - antes de cualquier salida
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

// Registrar función de shutdown para capturar errores fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Limpiar cualquier salida previa
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        // Asegurar headers JSON
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8', true);
            header('Cache-Control: no-cache, must-revalidate', true);
        }
        // Devolver error en JSON
        echo json_encode([
            'success' => false,
            'error' => 'Error fatal: ' . $error['message'],
            'file' => basename($error['file']),
            'line' => $error['line']
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
});

// Función para encontrar main.inc.php de forma robusta
function findMainIncFile() {
    $possiblePaths = [];
    
    // Método 1: Desde la ubicación del script
    $scriptDir = @realpath(__DIR__);
    if ($scriptDir) {
        $possiblePaths = array_merge($possiblePaths, [
            $scriptDir . '/../../../main.inc.php',      // custom/filemanager/scripts -> raíz
            $scriptDir . '/../../../htdocs/main.inc.php', // custom/filemanager/scripts -> htdocs
            $scriptDir . '/../../main.inc.php',         // alternativa
            $scriptDir . '/../../htdocs/main.inc.php',   // alternativa htdocs
            dirname(dirname(dirname($scriptDir))) . '/main.inc.php',
            dirname(dirname(dirname($scriptDir))) . '/htdocs/main.inc.php',
        ]);
    }
    
    // Método 2: Desde DOCUMENT_ROOT
    if (isset($_SERVER['DOCUMENT_ROOT'])) {
        $docRoot = @realpath($_SERVER['DOCUMENT_ROOT']);
        if ($docRoot) {
            $possiblePaths = array_merge($possiblePaths, [
                $docRoot . '/main.inc.php',
                $docRoot . '/htdocs/main.inc.php',
                dirname($docRoot) . '/main.inc.php',
                dirname($docRoot) . '/htdocs/main.inc.php',
            ]);
        }
    }
    
    // Método 3: Desde SCRIPT_FILENAME
    if (isset($_SERVER['SCRIPT_FILENAME'])) {
        $scriptFile = @realpath($_SERVER['SCRIPT_FILENAME']);
        if ($scriptFile) {
            $scriptDir = dirname($scriptFile);
            $maxLevels = 10;
            $level = 0;
            while ($scriptDir && $scriptDir !== '/' && $level < $maxLevels) {
                $possiblePaths[] = $scriptDir . '/main.inc.php';
                $possiblePaths[] = $scriptDir . '/htdocs/main.inc.php';
                $scriptDir = dirname($scriptDir);
                $level++;
            }
        }
    }
    
    // Probar cada ruta
    foreach ($possiblePaths as $path) {
        $realPath = @realpath($path);
        if ($realPath && @file_exists($realPath)) {
            return $realPath;
        }
    }
    
    return null;
}

// Intentar cargar Dolibarr para verificación de acceso (opcional)
$dolibarrLoaded = false;
$userAuthenticated = false;
$mainPath = findMainIncFile();
if ($mainPath) {
    try {
        // Configurar constantes antes de incluir
        if (!defined('NOTOKENRENEWAL')) define('NOTOKENRENEWAL', 1);
        if (!defined('NOREQUIREMENU')) define('NOREQUIREMENU', 1);
        if (!defined('NOREQUIREHTML')) define('NOREQUIREHTML', 1);
        if (!defined('NOREQUIREAJAX')) define('NOREQUIREAJAX', 1);
        if (!defined('NOREQUIRESOC')) define('NOREQUIRESOC', 1);
        
        // Capturar cualquier salida HTML
        ob_start();
        @require_once $mainPath;
        $htmlOutput = ob_get_clean();
        
        // Si hay HTML, es un error pero continuamos sin autenticación
        if (empty($htmlOutput) || (strpos($htmlOutput, '<html') === false && strpos($htmlOutput, '<!DOCTYPE') === false)) {
            $dolibarrLoaded = true;
            // Verificar usuario si está disponible
            if (isset($user) && is_object($user) && !empty($user->admin)) {
                $userAuthenticated = true;
            }
        }
    } catch (Exception $e) {
        // Error al cargar, continuamos sin autenticación estricta
    } catch (Error $e) {
        // Error fatal, continuamos sin autenticación estricta
    }
}

// Intentar verificar acceso usando filemanager.lib.php si está disponible
$accessGranted = false;
$libPath = __DIR__ . '/../lib/filemanager.lib.php';
if (@file_exists($libPath)) {
    try {
        @require_once $libPath;
        if (function_exists('checkFileManagerAccess')) {
            $accessGranted = @checkFileManagerAccess();
        } else {
            // Si la función no existe, asumir acceso si Dolibarr está cargado
            $accessGranted = $dolibarrLoaded;
        }
    } catch (Exception $e) {
        // Error al cargar lib, continuamos sin verificación estricta
        $accessGranted = true; // Permitir acceso si no se puede verificar
    } catch (Error $e) {
        // Error fatal, continuamos sin verificación estricta
        $accessGranted = true; // Permitir acceso si no se puede verificar
    }
} else {
    // Si no existe la lib, permitir acceso (puede ser un entorno sin Dolibarr completo)
    $accessGranted = true;
}

// Si no se pudo verificar acceso y Dolibarr está cargado, denegar
if (!$accessGranted && $dolibarrLoaded && !$userAuthenticated) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Acceso denegado']);
    exit;
}

$backupId = $_GET['backup_id'] ?? $_POST['backup_id'] ?? null;

if (empty($backupId)) {
    echo json_encode(['success' => false, 'error' => 'backup_id requerido']);
    exit;
}

// Detectar directorio de backups - Detección robusta
$backupDir = __DIR__ . '/../backups';
$backupDir = @realpath($backupDir) ?: $backupDir;

// Si no existe, intentar crearlo
if (!@is_dir($backupDir)) {
    @mkdir($backupDir, 0777, true);
    $backupDir = @realpath($backupDir) ?: $backupDir;
}

if (!@is_dir($backupDir)) {
    // Limpiar cualquier salida previa
    while (ob_get_level() > 0) {
        @ob_end_clean();
    }
    echo json_encode(['success' => false, 'error' => 'Directorio de backups no existe: ' . $backupDir]);
    exit;
}

if (!@is_writable($backupDir)) {
    // Intentar cambiar permisos
    @chmod($backupDir, 0777);
    if (!@is_writable($backupDir)) {
        // Limpiar cualquier salida previa
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        echo json_encode(['success' => false, 'error' => 'Directorio de backups no escribible: ' . $backupDir]);
        exit;
    }
}

$cleaned = [];
$errors = [];
$totalSize = 0;

// Patrones de archivos relacionados con este backup
$patterns = [
    'chunk_state_' . $backupId . '.json',
    'backup_progress_' . $backupId . '.json',
    'backup_log_' . $backupId . '.txt',
    'filelist_' . $backupId . '.json',
    'file_order_map_' . $backupId . '.json',
    'file_analysis_' . $backupId . '.json',
    'backup_' . $backupId . '.zip',
    'backup_' . $backupId . '.zip.*', // Archivos temporales del ZIP
];

// Buscar archivos que coincidan con el backup_id
$filesToDelete = [];

// Buscar archivos específicos
foreach ($patterns as $pattern) {
    $matches = glob($backupDir . '/' . $pattern);
    foreach ($matches as $file) {
        if (file_exists($file)) {
            $filesToDelete[] = $file;
        }
    }
}

// También buscar por patrón más amplio (por si hay variaciones)
$allFiles = @glob($backupDir . '/*' . $backupId . '*');
if ($allFiles) {
    foreach ($allFiles as $file) {
        if (@file_exists($file) && !in_array($file, $filesToDelete)) {
            $filesToDelete[] = $file;
        }
    }
}

// Eliminar archivos
foreach ($filesToDelete as $file) {
    $fileSize = @filesize($file);
    if ($fileSize !== false) {
        $totalSize += $fileSize;
    }
    
    if (@unlink($file)) {
        $cleaned[] = basename($file);
    } else {
        $errors[] = "No se pudo eliminar: " . basename($file);
    }
}

$totalSizeMB = round($totalSize / 1024 / 1024, 2);

// Limpiar cualquier salida previa antes de devolver JSON
while (ob_get_level() > 0) {
    @ob_end_clean();
}

// Asegurar headers JSON
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8', true);
    header('Cache-Control: no-cache, must-revalidate', true);
}

// Preparar respuesta
$response = [
    'success' => empty($errors),
    'cleaned' => $cleaned,
    'errors' => $errors,
    'files_deleted' => count($cleaned),
    'total_size_mb' => $totalSizeMB,
    'message' => empty($errors) 
        ? 'Limpieza completada: ' . count($cleaned) . ' archivos eliminados (' . $totalSizeMB . ' MB)'
        : 'Limpieza parcial: ' . count($cleaned) . ' archivos eliminados, ' . count($errors) . ' errores'
];

// Devolver JSON válido
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;

