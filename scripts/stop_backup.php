<?php
/**
 * Script para crear archivo de stop para cancelar un backup en progreso
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

// Intentar cargar Dolibarr (opcional - solo para validación de usuario si es necesario)
$dolibarrLoaded = false;
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
        }
    } catch (Exception $e) {
        // Error al cargar, continuamos sin autenticación
    } catch (Error $e) {
        // Error fatal, continuamos sin autenticación
    }
}

// Limpiar cualquier salida previa
while (ob_get_level() > 0) {
    @ob_end_clean();
}

// Obtener backup_id
$backupId = $_GET['backup_id'] ?? $_POST['backup_id'] ?? '';

if (empty($backupId)) {
    // Asegurar headers JSON
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8', true);
        header('Cache-Control: no-cache, must-revalidate', true);
    }
    echo json_encode(['success' => false, 'error' => 'backup_id no proporcionado'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// Directorio de backups - Detección robusta
$backupDir = __DIR__ . '/../backups';
$backupDir = @realpath($backupDir) ?: $backupDir;

// Si no existe, intentar crearlo
if (!@is_dir($backupDir)) {
    @mkdir($backupDir, 0777, true);
    $backupDir = @realpath($backupDir) ?: $backupDir;
}

// Verificar que el directorio existe y es escribible
if (!@is_dir($backupDir)) {
    echo json_encode(['success' => false, 'error' => 'Directorio de backups no existe: ' . $backupDir]);
    exit;
}

if (!@is_writable($backupDir)) {
    // Intentar cambiar permisos
    @chmod($backupDir, 0777);
    if (!@is_writable($backupDir)) {
        echo json_encode(['success' => false, 'error' => 'Directorio de backups no escribible: ' . $backupDir]);
        exit;
    }
}

// Limpiar cualquier salida previa antes de devolver JSON
while (ob_get_level() > 0) {
    @ob_end_clean();
}

// Asegurar headers JSON
if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8', true);
    header('Cache-Control: no-cache, must-revalidate', true);
}

// Crear archivo de stop
$stopFile = $backupDir . '/stop_backup_' . $backupId . '.txt';
$result = @file_put_contents($stopFile, date('Y-m-d H:i:s') . "\nSTOP\n");

if ($result !== false) {
    // Asegurar permisos del archivo
    @chmod($stopFile, 0666);
    $response = ['success' => true, 'message' => 'Archivo de stop creado', 'file' => basename($stopFile)];
} else {
    $response = ['success' => false, 'error' => 'No se pudo crear el archivo de stop en: ' . $stopFile];
}

// Devolver JSON válido
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;

