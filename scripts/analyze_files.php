<?php
/**
 * ANÁLISIS DE ARCHIVOS PARA BACKUP - VERSIÓN ROBUSTA PARA HOSTING RESTRINGIDO
 * Soluciona errores 500 causados por límites de memoria/tiempo
 */

// Configuración crítica para hosting restringido
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Configurar límites seguros para hosting
$maxTime = 300; // 300 segundos máximo (5 minutos para procesar TODOS los archivos)
$maxMemory = '4096M'; // 4096MB máximo (4GB para procesar 140k+ archivos sin límites)

@set_time_limit($maxTime);
@ini_set('max_execution_time', $maxTime);
@ini_set('memory_limit', $maxMemory);

// Variables globales para estado de continuación
$continuationState = null;
$continuationFile = sys_get_temp_dir() . '/analyze_continuation_' . session_id() . '.json';

// Detectar si estamos en modo CLI
$isCliMode = php_sapi_name() === 'cli';

// Headers de respuesta (solo en modo web)
if (!$isCliMode) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
    header('Access-Control-Allow-Origin: *');
}

// Parse CLI parameters if in CLI mode
$cliParams = [];
if ($isCliMode) {
    parse_str(implode('&', array_slice($argv, 1)), $cliParams);
}


// Función para verificar recursos del sistema
function checkAnalysisResources() {
    $memoryUsage = memory_get_usage(true);
    $memoryLimit = return_bytes(ini_get('memory_limit'));
    $timeElapsed = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
    $timeLimit = ini_get('max_execution_time');

    return [
        'memory_used_mb' => round($memoryUsage / 1024 / 1024, 1),
        'memory_limit_mb' => round($memoryLimit / 1024 / 1024, 1),
        'memory_percent' => round(($memoryUsage / $memoryLimit) * 100, 1),
        'time_elapsed' => round($timeElapsed, 1),
        'time_limit' => $timeLimit,
        'time_remaining' => round($timeLimit - $timeElapsed, 1),
        'is_critical' => ($memoryUsage / $memoryLimit) > 0.9 || ($timeElapsed / $timeLimit) > 0.85
    ];
}

// Función helper para return_bytes (en caso de que no exista)
if (!function_exists('return_bytes')) {
    function return_bytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = (float) $val;
        switch($last) {
            case 'g': $val *= 1024 * 1024 * 1024; break;
            case 'm': $val *= 1024 * 1024; break;
            case 'k': $val *= 1024; break;
        }
        return $val;
    }
}

function complete_file_scan($rootDir, $continuationState = null, $continuationFile = null) {
    $stats = [
        'total_files' => $continuationState['total_files'] ?? 0,
        'total_folders' => $continuationState['total_folders'] ?? 0,
        'total_size_bytes' => $continuationState['total_size_bytes'] ?? 0,
        'partial' => false,
        'scanned_dirs' => $continuationState['scanned_dirs'] ?? 0,
        'errors' => $continuationState['errors'] ?? 0,
        'memory_peak' => $continuationState['memory_peak'] ?? 0,
        'time_elapsed' => 0,
        'processed_dirs' => $continuationState['processed_dirs'] ?? [],
        'all_files' => $continuationState['all_files'] ?? []  // Lista completa de archivos
    ];

    // Si hay estado de continuación, empezar desde donde se detuvo
    $startDir = $continuationState['last_dir'] ?? '';
    $startFromDir = !empty($startDir);

    $startTime = microtime(true);

    // EXCLUSIÓN PARA EVITAR AGOTAMIENTO DE RECURSOS
    $excludeDirs = [
        $rootDir . '/custom/filemanager/backups',  // Backups existentes
        $rootDir . '/custom/filemanager/cache',    // Cache temporal
        $rootDir . '/custom/filemanager/logs',     // Logs del sistema de backup
        $rootDir . '/tmp',                         // Archivos temporales del sistema
        $rootDir . '/.git',                        // Control de versiones
        $rootDir . '/node_modules',                // Dependencias de desarrollo
        $rootDir . '/vendor',                      // Dependencias de desarrollo
        // Excluir módulos grandes automáticamente para evitar agotamiento de recursos
        $rootDir . '/custom/ecommerce',            // Módulo ecommerce muy grande
        $rootDir . '/custom/ecommerce/theme',      // Tema del ecommerce
        $rootDir . '/custom/ficharhoras/lib/fpdf181', // Librería grande
    ];

    // NOTA: Se incluyen includes, install, _dev, documents/ckeditor
    // El usuario quiere análisis COMPLETO de TODOS los archivos de Dolibarr

    $fileCount = 0;
    // LÍMITES ESTRICTOS PARA EVITAR AGOTAMIENTO DE RECURSOS
    $maxFiles = 500000;   // Máximo 500,000 archivos (suficiente para 140k+ archivos)
    $maxDirs = 200000;    // Máximo 200,000 directorios (para procesar TODOS los directorios)
    $processedDirs = [];

    // Función recursiva para escanear directorios - CON LÍMITES ESTRICTOS
    $scanDir = function($dir) use (&$stats, &$excludeDirs, &$fileCount, &$processedDirs, &$scanDir, &$startTime, &$maxFiles, &$maxDirs, &$rootDir) {
        // Verificar límites de archivos y directorios
        if ($stats['total_files'] >= $maxFiles) {
            $stats['partial'] = true;
            $stats['error_reason'] = 'max_files_reached';
            return;
        }
        if ($stats['total_folders'] >= $maxDirs) {
            $stats['partial'] = true;
            $stats['error_reason'] = 'max_dirs_reached';
            return;
        }

        // Verificar recursos cada 500 directorios (menos frecuente para mejor rendimiento con muchos archivos)
        if ($stats['scanned_dirs'] % 500 === 0) {
            $resources = checkAnalysisResources();
            $stats['memory_peak'] = max($stats['memory_peak'], $resources['memory_used_mb']);

            // Si recursos críticos, marcar como parcial y detener
            if ($resources['is_critical']) {
                $stats['partial'] = true;
                $stats['error_reason'] = 'recursos_criticos';
                return;
            }

            // Si tiempo bajo, marcar como parcial (90 segundos para análisis completo)
            if ($resources['time_remaining'] < 90) {
                $stats['partial'] = true;
                $stats['error_reason'] = 'tiempo_bajo';
                return;
            }
        }

        // Verificar si el directorio debe excluirse
        foreach ($excludeDirs as $exclude) {
            if (strpos($dir, $exclude) === 0) {
                return;
            }
        }

        if (!is_dir($dir) || !is_readable($dir)) {
            $stats['errors']++;
            return;
        }

        // LÍMITES ESTRICTOS: máximo {$maxDirs} directorios, {$maxFiles} archivos

        // Evitar procesar el mismo directorio múltiples veces
        $realPath = realpath($dir);
        if ($realPath && isset($processedDirs[$realPath])) {
            return;
        }
        $processedDirs[$realPath] = true;
        $stats['total_folders']++;
        $stats['scanned_dirs']++;

        $items = @scandir($dir);
        if (!$items) {
            $stats['errors']++;
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;

            $fullPath = $dir . '/' . $item;

            // Si es un directorio, procesarlo recursivamente
            if (is_dir($fullPath) && !is_link($fullPath)) {
                $scanDir($fullPath);

                // Verificar si se marcó como parcial durante el escaneo recursivo
                if ($stats['partial']) {
                    return;
                }
            }
            // Si es un archivo, contarlo
            elseif (is_file($fullPath)) {
                // LÍMITE DE ARCHIVOS: máximo {$maxFiles} archivos permitidos

                // Obtener tamaño de archivo de forma segura
                $size = @filesize($fullPath);
                if ($size !== false) {
                    $stats['total_files']++;
                    $stats['total_size_bytes'] += $size;
                    $fileCount++;
                    // Agregar archivo a la lista completa
                    $stats['all_files'][] = [
                        'path' => $fullPath,
                        'size' => $size,
                        'relative_path' => str_replace($rootDir . '/', '', $fullPath)
                    ];
                } else {
                    $stats['errors']++;
                }
            }
        }
    };

    // Directorios principales a analizar - ESCANEO CON LÍMITES ESTRICTOS
    $mainDirs = ['htdocs', 'custom', 'documents', 'langs', 'includes', 'theme'];

    // Determinar desde qué directorio continuar
    $startMainDirIndex = 0;
    if ($startFromDir && !empty($continuationState['completed_main_dirs'] ?? [])) {
        // Encontrar el último directorio completado y continuar desde el siguiente
        $completedDirs = $continuationState['completed_main_dirs'];
        foreach ($mainDirs as $index => $dir) {
            $fullDir = $rootDir . '/' . $dir;
            if (!in_array($fullDir, $completedDirs)) {
                $startMainDirIndex = $index;
                break;
            }
        }
        error_log("ANALYZE_FILES: Continuing from main dir index $startMainDirIndex");
    }

    // Iniciar escaneo de directorios principales
    try {
        for ($i = $startMainDirIndex; $i < count($mainDirs); $i++) {
            $dir = $mainDirs[$i];
            $fullDir = $rootDir . '/' . $dir;

            error_log("ANALYZING DIR: $dir -> $fullDir - exists: " . (is_dir($fullDir) ? 'YES' : 'NO'));
            if (is_dir($fullDir) && is_readable($fullDir)) {
                $scanDir($fullDir);

                // Verificar si se marcó como parcial durante el escaneo
                if ($stats['partial']) {
                    // Guardar estado para continuación incluyendo directorios completados
                    $completedMainDirs = [];
                    for ($j = 0; $j < $i; $j++) {
                        $completedMainDirs[] = $rootDir . '/' . $mainDirs[$j];
                    }

                    $currentState = [
                        'total_files' => $stats['total_files'],
                        'total_folders' => $stats['total_folders'],
                        'total_size_bytes' => $stats['total_size_bytes'],
                        'scanned_dirs' => $stats['scanned_dirs'],
                        'errors' => $stats['errors'],
                        'memory_peak' => $stats['memory_peak'],
                        'processed_dirs' => $stats['processed_dirs'],
                        'completed_main_dirs' => $completedMainDirs,
                        'last_dir' => $fullDir,
                        'timestamp' => time()
                    ];
                    file_put_contents($continuationFile, json_encode($currentState));
                    error_log("PARTIAL: Saved state at dir $dir - files: {$stats['total_files']}");
                    break;
                }

                error_log("DIR $dir COMPLETED");
            } else {
                error_log("DIR $dir SKIPPED: not accessible");
            }
        }
    } catch (Exception $e) {
        $stats['partial'] = true;
        $stats['error_reason'] = 'exception: ' . $e->getMessage();
        $stats['errors']++;
    }

    // Calcular estadísticas finales
    $stats['time_elapsed'] = round(microtime(true) - $startTime, 2);
    $stats['total_size_mb'] = round($stats['total_size_bytes'] / 1024 / 1024, 2);
    $stats['estimated_zip_mb'] = round($stats['total_size_mb'] * 0.7, 2);

    // Información de recursos finales
    $finalResources = checkAnalysisResources();
    $stats['memory_peak'] = max($stats['memory_peak'], $finalResources['memory_used_mb']);
    $stats['final_memory_mb'] = $finalResources['memory_used_mb'];
    $stats['final_time_elapsed'] = $finalResources['time_elapsed'];

    return $stats;
}

// EJECUCIÓN PRINCIPAL CON MANEJO DE ERRORES ROBUSTO
try {
    // Log inicial para debugging
    error_log("ANALYZE_FILES: Starting execution - Host: " . ($httpHost ?? 'unknown'));
    // Manejar acciones (por compatibilidad con backup_chunk.php)
    $action = ($isCliMode ? ($cliParams['action'] ?? 'analyze') : ($_GET['action'] ?? 'analyze'));
    if ($action === 'continue') {
        // Cargar estado de continuación si existe
        if (file_exists($continuationFile)) {
            $continuationState = json_decode(file_get_contents($continuationFile), true);
            error_log("ANALYZE_FILES: Continuando desde estado guardado - archivos procesados: " . ($continuationState['total_files'] ?? 0));
        } else {
            error_log("ANALYZE_FILES: No se encontró estado de continuación, empezando desde cero");
        }
    } else {
        // Nuevo análisis - limpiar estado anterior
        if (file_exists($continuationFile)) {
            unlink($continuationFile);
        }
    }
    // DETECCIÓN DINÁMICA DE RUTAS - MISMA LÓGICA QUE BACKUP_CHUNK.PHP
    $scriptDir = dirname(__FILE__); // /custom/filemanager/scripts
    $moduleDir = dirname($scriptDir); // /custom/filemanager
    $customDir = dirname($moduleDir); // /custom
    $dolibarrRoot = dirname($customDir); // Raíz calculada

    // DETECCIÓN ESPECIAL PARA 2BYTE.ES - INTENTAR RUTAS CONOCIDAS PERO CON FALLBACK
    $httpHost = $_SERVER['HTTP_HOST'] ?? 'unknown';
    $pathDetectionLog = [];

    if (strpos($httpHost, 'musicalprincesa.2byte.es') !== false) {
        error_log("ANALYZE_FILES: Detected 2byte.es host, starting path detection");
        // Estamos en 2Byte.es - intentar rutas conocidas pero con verificación
        $possible2bytePaths = [
            '/home/muprincesa/domains/musicalprincesa.2byte.es/private_html',
            '/home/muprincesa/domains/musicalprincesa.2byte.es/public_html',
            '/home/muprincesa/public_html',
            '/home/muprincesa/private_html'
        ];

        $dolibarrRoot = null;
        foreach ($possible2bytePaths as $path) {
            $pathDetectionLog[] = "Checking 2byte.es path: $path";
            error_log("ANALYZE_FILES: Checking path: $path");
            if (is_dir($path) && is_readable($path)) {
                $pathDetectionLog[] = "Found readable path: $path";
                error_log("ANALYZE_FILES: Found readable path: $path");
                // Verificar si tiene archivos/carpetas típicos de Dolibarr
                $dolibarrIndicators = ['htdocs', 'custom', 'documents', 'langs', 'includes'];
                $confidence = 0;

                foreach ($dolibarrIndicators as $indicator) {
                    if (is_dir($path . '/' . $indicator)) {
                        $confidence += 2;
                        error_log("ANALYZE_FILES: Found Dolibarr indicator: $indicator");
                    }
                }

                if (file_exists($path . '/conf.php') || file_exists($path . '/main.inc.php')) {
                    $confidence += 1;
                    error_log("ANALYZE_FILES: Found Dolibarr config file");
                }

                if ($confidence >= 2) {
                    $dolibarrRoot = $path;
                    $pathDetectionLog[] = "Selected as Dolibarr root (confidence: $confidence): $path";
                    error_log("ANALYZE_FILES: Selected Dolibarr root: $path (confidence: $confidence)");
                    break;
                } else {
                    $pathDetectionLog[] = "Path exists but low confidence ($confidence): $path";
                    error_log("ANALYZE_FILES: Path exists but low confidence ($confidence): $path");
                }
            } else {
                $pathDetectionLog[] = "Path not accessible: $path";
                error_log("ANALYZE_FILES: Path not accessible: $path");
            }
        }

        // Si no se encontró ninguna ruta específica de 2byte, continuar con detección automática
        if (!$dolibarrRoot) {
            $pathDetectionLog[] = "No valid 2byte.es paths found, falling back to auto-detection";
            error_log("ANALYZE_FILES: No valid 2byte.es paths found, falling back to auto-detection");
            $dolibarrRoot = dirname(dirname(dirname(dirname($scriptDir))));
            error_log("ANALYZE_FILES: Using fallback root: $dolibarrRoot");
        }
    }

    if (!$dolibarrRoot || !is_dir($dolibarrRoot) || !is_readable($dolibarrRoot)) {
        // Verificar si la ruta calculada existe, si no, intentar rutas alternativas comunes
        $user = get_current_user();

        // Lista exhaustiva de posibles rutas - PRIORIDAD PARA 2BYTE.ES
        $possibleRoots = [
            // Rutas específicas para 2Byte.es (usuario reportado) - PRIORIDAD MÁXIMA
            '/home/muprincesa/domains/musicalprincesa.2byte.es/private_html',
            '/home/muprincesa/domains/musicalprincesa.2byte.es/public_html',

            // Rutas basadas en usuario actual del sistema
            '/home/' . $user . '/public_html',
            '/home/' . $user . '/private_html',
            '/home/' . $user . '/domains/' . $httpHost . '/public_html',
            '/home/' . $user . '/domains/' . $httpHost . '/private_html',
            '/home/' . $user . '/domains/' . $httpHost . '/httpdocs',

            // Rutas comunes de hosting
            '/var/www/html',
            '/var/www/vhosts/' . $httpHost,
            '/var/www/' . $httpHost,

            // Rutas de Plesk/cPanel alternativas
            '/home/httpd/vhosts/' . $httpHost,
            '/usr/local/apache/htdocs',

            // Subir niveles desde el directorio del script
            dirname(dirname(dirname(dirname($scriptDir)))), // 4 niveles arriba
            dirname(dirname(dirname(dirname(dirname($scriptDir))))), // 5 niveles arriba
            dirname(dirname(dirname(dirname(dirname(dirname($scriptDir)))))), // 6 niveles arriba

            // Directorios relativos
            getcwd(),
            dirname(getcwd()),
            dirname(dirname(getcwd())),
        ];

        $foundDolibarrRoot = false;
        foreach ($possibleRoots as $possibleRoot) {
            if (is_dir($possibleRoot) && is_readable($possibleRoot)) {
                // Verificar si tiene archivos/carpetas típicos de Dolibarr
                $dolibarrIndicators = ['htdocs', 'custom', 'documents', 'langs', 'includes', 'install'];
                $dolibarrFiles = ['conf.php', 'filefunc.inc.php', 'master.inc.php'];

                $isDolibarrRoot = false;
                $confidence = 0;

                // Contar indicadores de directorios
                foreach ($dolibarrIndicators as $indicator) {
                    if (is_dir($possibleRoot . '/' . $indicator)) {
                        $confidence += 2; // Directorios pesan más
                    }
                }

                // Contar archivos indicadores
                foreach ($dolibarrFiles as $file) {
                    if (file_exists($possibleRoot . '/' . $file)) {
                        $confidence += 1;
                    }
                }

                // Si encontramos al menos 3 indicadores, es probablemente Dolibarr
                if ($confidence >= 3) {
                    $dolibarrRoot = $possibleRoot;
                    $foundDolibarrRoot = true;
                    break;
                }
            }
        }

        // Si no se encontró ninguna ruta válida, usar la calculada inicialmente
        if (!$foundDolibarrRoot) {
            // Intentar usar la ruta del script como último recurso
            $scriptBasedRoot = dirname(dirname(dirname(dirname($scriptDir))));
            error_log("ANALYZE_FILES: Trying script-based root: $scriptBasedRoot");
            if (is_dir($scriptBasedRoot) && is_readable($scriptBasedRoot)) {
                $dolibarrRoot = $scriptBasedRoot;
                $pathDetectionLog[] = "Using script-based root: $scriptBasedRoot";
                error_log("ANALYZE_FILES: Using script-based root successfully");
            } else {
                // Último recurso: intentar el directorio actual o superior
                $fallbackRoots = [
                    getcwd(),
                    dirname(getcwd()),
                    dirname(dirname(getcwd())),
                    '/tmp' // Siempre debería existir
                ];

                foreach ($fallbackRoots as $fallback) {
                    error_log("ANALYZE_FILES: Trying fallback root: $fallback");
                    if (is_dir($fallback) && is_readable($fallback)) {
                        $dolibarrRoot = $fallback;
                        $pathDetectionLog[] = "Using fallback root: $fallback";
                        error_log("ANALYZE_FILES: Using fallback root: $fallback");
                        break;
                    }
                }

                if (!$dolibarrRoot) {
                    $errorMsg = "CRÍTICO: No se pudo detectar ninguna ruta válida de Dolibarr. Sistema de archivos inaccesible.";
                    $errorMsg .= " | Path detection log: " . implode('; ', $pathDetectionLog);
                    $errorMsg .= " | Script dir: $scriptDir | User: " . get_current_user();
                    error_log("ANALYZE_FILES: $errorMsg");
                    throw new Exception($errorMsg);
                }
            }
        }
    }

    $rootDir = $dolibarrRoot;

    // Verificar que el directorio raíz existe
    error_log("ANALYZE_FILES: Final root dir: $rootDir");
    if (!is_dir($rootDir)) {
        $errorMsg = "Directorio raíz no existe: $rootDir";
        error_log("ANALYZE_FILES: ERROR - $errorMsg");
        throw new Exception($errorMsg);
    }

    if (!is_readable($rootDir)) {
        $errorMsg = "Directorio raíz no es legible: $rootDir";
        error_log("ANALYZE_FILES: ERROR - $errorMsg");
        throw new Exception($errorMsg);
    }
    error_log("ANALYZE_FILES: Root dir verified successfully");

    // Verificar recursos antes de iniciar
    $initialResources = checkAnalysisResources();
    error_log("ANALYZE_FILES: Initial resources - Memory: {$initialResources['memory_percent']}%, Time remaining: {$initialResources['time_remaining']}s");
    if ($initialResources['memory_percent'] > 70) {
        $errorMsg = "Memoria inicial demasiado alta: {$initialResources['memory_percent']}%";
        error_log("ANALYZE_FILES: $errorMsg");
        throw new Exception($errorMsg);
    }

    // Ejecutar análisis con manejo de errores mejorado
    error_log("ANALYZE_FILES: Starting file scan on root: $rootDir");
    try {
        $stats = complete_file_scan($rootDir, $continuationState, $continuationFile);
        error_log("ANALYZE_FILES: File scan completed successfully - files: {$stats['total_files']}");
    } catch (Exception $e) {
        error_log("ANALYZE_FILES: File scan failed: " . $e->getMessage());
        // Si el análisis falla, devolver estadísticas mínimas
        $stats = [
            'total_files' => 0,
            'total_folders' => 0,
            'total_size_bytes' => 0,
            'partial' => true,
            'scanned_dirs' => 0,
            'errors' => 1,
            'memory_peak' => 0,
            'time_elapsed' => 0,
            'processed_dirs' => [],
            'error_message' => $e->getMessage()
        ];
    }

    // Si no es parcial, limpiar archivo de continuación
    if (!$stats['partial']) {
        if (file_exists($continuationFile)) {
            unlink($continuationFile);
        }
        error_log("ANALYZE_FILES: Análisis completado - total files: {$stats['total_files']}");
    }

    // Inicializar respuesta
    $response = [];

    // Si el análisis se completó exitosamente (no parcial) O es parcial solo por tiempo, guardar la lista de archivos para el backup
    $isPartialByTimeOnly = ($stats['partial'] && isset($stats['error_reason']) && $stats['error_reason'] === 'tiempo_bajo');
    if ((!$stats['partial'] || $isPartialByTimeOnly) && !empty($stats['all_files'])) {
        $backupDir = dirname(__DIR__) . '/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0777, true);
        }

        // Generar ID de backup basado en timestamp (similar al que usa el backup)
        $backupId = date('Ymd_His');
        $filesListFile = $backupDir . '/filelist_' . $backupId . '.json';

        // Guardar lista de archivos para que el backup la use
        $sourceType = $isPartialByTimeOnly ? 'analysis_partial_time' : 'analysis_complete';
        $filesData = [
            'backup_id' => $backupId,
            'total_files' => count($stats['all_files']),
            'total_size_bytes' => $stats['total_size_bytes'],
            'files' => $stats['all_files'],
            'created_at' => date('Y-m-d H:i:s'),
            'source' => $sourceType,
            'partial' => $stats['partial'] ?? false,
            'error_reason' => $stats['error_reason'] ?? null
        ];

        // Guardar la lista de archivos (comprimir si es muy grande)
        $jsonContent = json_encode($filesData, JSON_PRETTY_PRINT);
        $shouldCompress = strlen($jsonContent) > 1024 * 1024; // Comprimir si > 1MB

        if ($shouldCompress) {
            // Intentar comprimir el archivo
            $compressedFile = $filesListFile . '.gz';
            $gzHandle = @gzopen($compressedFile, 'wb9'); // Máxima compresión

            if ($gzHandle !== false) {
                // Compresión exitosa
                gzwrite($gzHandle, $jsonContent);
                gzclose($gzHandle);

                // Crear un archivo indicador de que está comprimido
                file_put_contents($filesListFile . '.compressed', '1');

                error_log("ANALYZE_FILES: Lista de archivos COMPRIMIDA: $compressedFile (" . count($stats['all_files']) . " archivos, " . round(strlen($jsonContent) / 1024 / 1024, 1) . "MB -> " . round(filesize($compressedFile) / 1024 / 1024, 1) . "MB)");
            } else {
                // Fallback: guardar sin comprimir si la compresión falla
                error_log("ANALYZE_FILES: ERROR en compresión, guardando sin comprimir: $filesListFile");
                file_put_contents($filesListFile, $jsonContent);
                error_log("ANALYZE_FILES: Lista de archivos guardada (sin comprimir): $filesListFile (" . count($stats['all_files']) . " archivos)");
            }
        } else {
            file_put_contents($filesListFile, $jsonContent);
            error_log("ANALYZE_FILES: Lista de archivos guardada: $filesListFile (" . count($stats['all_files']) . " archivos)");
        }

        // Agregar información del backup a la respuesta
        $response['backup_ready'] = true;
        $response['backup_id'] = $backupId;
        $response['files_list_saved'] = true;
    }

    // Preparar respuesta de éxito
    $response = array_merge($response, [
        'success' => true,
        'partial' => $stats['partial'],
        'stats' => $stats,
        'timestamp' => date('Y-m-d H:i:s'),
        'server_info' => [
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'host_restricted' => true
        ]
    ]);

} catch (Exception $e) {
    // Error crítico - devolver información de diagnóstico
    $errorResources = checkAnalysisResources();

    $response = [
        'success' => false,
        'error' => 'Error en análisis de archivos: ' . $e->getMessage(),
        'error_type' => 'analysis_exception',
        'timestamp' => date('Y-m-d H:i:s'),
        'diagnostics' => [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'memory_used_mb' => $errorResources['memory_used_mb'],
            'memory_percent' => $errorResources['memory_percent'],
            'time_elapsed' => $errorResources['time_elapsed'],
            'time_remaining' => $errorResources['time_remaining'],
            'php_version' => PHP_VERSION,
            'server_host' => $_SERVER['HTTP_HOST'] ?? 'unknown',
            'script_dir' => dirname(__FILE__),
            'current_user' => get_current_user(),
            'path_detection_log' => $pathDetectionLog ?? [],
            'root_dir_exists' => is_dir('/var/www/html/dolibarr-website'),
            'root_dir_readable' => is_readable('/var/www/html/dolibarr-website'),
            'script_based_root' => dirname(dirname(dirname(dirname(__FILE__)))),
            'script_based_root_exists' => is_dir(dirname(dirname(dirname(dirname(__FILE__)))))
        ]
    ];
}

// Agregar información de debug si se solicita
if (($isCliMode && isset($cliParams['debug'])) || (!$isCliMode && isset($_GET['debug']))) {
    $response['debug'] = [
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'query_string' => $_SERVER['QUERY_STRING'] ?? '',
        'script_filename' => $_SERVER['SCRIPT_FILENAME'] ?? '',
        'current_working_dir' => getcwd(),
        'included_files' => count(get_included_files()),
        'peak_memory' => memory_get_peak_usage(true) / 1024 / 1024,
        'path_detection_log' => $pathDetectionLog ?? [],
        'final_dolibarr_root' => $dolibarrRoot ?? 'not_set',
        'server_host' => $_SERVER['HTTP_HOST'] ?? 'unknown'
    ];
}

// Output basado en el modo de ejecución
if ($isCliMode) {
    // Modo CLI: mostrar información legible y JSON
    echo "=== ANÁLISIS DE ARCHIVOS COMPLETADO ===\n";
    echo "Archivos totales: " . ($response['total_files'] ?? 0) . "\n";
    echo "Carpetas totales: " . ($response['total_folders'] ?? 0) . "\n";
    echo "Tamaño total: " . ($response['total_size_mb'] ?? 0) . " MB\n";
    echo "Estado: " . ($response['status'] ?? 'unknown') . "\n";
    if (isset($response['error'])) {
        echo "Error: " . $response['error'] . "\n";
    }
    echo "\nJSON Response:\n";
    echo json_encode($response, JSON_PRETTY_PRINT);
} else {
    // Modo web: devolver JSON
    echo json_encode($response, JSON_PRETTY_PRINT);
}