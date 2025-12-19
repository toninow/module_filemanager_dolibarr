<?php
/**
 * File Manager Library
 * Funciones auxiliares para el administrador de archivos
 */

/**
 * Obtener configuración del módulo (con caché para evitar múltiples consultas)
 */
function getFileManagerConfig()
{
    global $db, $conf;
    
    // Caché estático para evitar múltiples consultas en la misma ejecución
    static $config_cache = null;
    if ($config_cache !== null) {
        return $config_cache;
    }
    
    // Configuración dinámica adaptada a cualquier entorno
    $default_config = array(
        'FILEMANAGER_ROOT_PATH' => dirname(dirname(dirname(__DIR__))), // Auto-detecta raíz de Dolibarr
        'FILEMANAGER_ALLOW_DELETE' => 1,
        'FILEMANAGER_ALLOW_UPLOAD' => 1,
        'FILEMANAGER_ALLOW_CREATE_FOLDER' => 1,
        'FILEMANAGER_MAX_FILE_SIZE' => 10485760,
        'FILEMANAGER_ALLOWED_EXTENSIONS' => array(),
        'FILEMANAGER_SHOW_HIDDEN_FILES' => 0,
        'FILEMANAGER_DEFAULT_VIEW' => 'grid',
        'FILEMANAGER_SHOW_DELETE_BUTTON' => 1,
        'FILEMANAGER_MAX_FILES' => 100,
        'FILEMANAGER_ALLOW_PROTECTED_ACTIONS' => 0,
        'FILEMANAGER_LANGUAGE' => 'auto' // auto = usar idioma de Dolibarr
    );
    
    // Cargar configuración desde la base de datos (llx_const) con timeout
    if (isset($db) && $db && !empty($db->database_name)) {
        try {
            $entity = isset($conf->entity) ? (int)$conf->entity : 0;
            $sql = "SELECT name, value FROM " . MAIN_DB_PREFIX . "const WHERE entity = " . $entity . " AND name LIKE 'FILEMANAGER_%' LIMIT 50";
            $resql = $db->query($sql);
            if ($resql) {
                while ($obj = $db->fetch_object($resql)) {
                    $key = $obj->name;
                    $value = $obj->value;
                    
                    // Convertir valores según el tipo
                    if ($key === 'FILEMANAGER_ALLOW_DELETE' || $key === 'FILEMANAGER_ALLOW_UPLOAD' || 
                        $key === 'FILEMANAGER_SHOW_DELETE_BUTTON' || $key === 'FILEMANAGER_ALLOW_PROTECTED_ACTIONS') {
                        // Forzar conversión a entero y asegurar que '1' o 1 o 'true' sean 1
                        $int_value = (int)$value;
                        $default_config[$key] = ($int_value > 0 || $value === '1' || $value === 'true' || $value === true) ? 1 : 0;
                    } elseif ($key === 'FILEMANAGER_MAX_FILES') {
                        $default_config[$key] = (int)$value;
                    } elseif ($key === 'FILEMANAGER_ALLOWED_EXTENSIONS') {
                        $default_config[$key] = !empty($value) ? explode(',', $value) : array();
                    } else {
                        $default_config[$key] = $value;
                    }
                }
                $db->free($resql);
            }
        } catch (Exception $e) {
            // Si hay error en la consulta, usar configuración por defecto
            error_log("FileManager: Error cargando configuración desde BD: " . $e->getMessage());
        }
    }
    
    // Si existe archivo de configuración personalizada, cargarla (sobrescribe BD)
    $config_file = __DIR__ . '/../config.php';
    if (file_exists($config_file) && is_readable($config_file)) {
        try {
            include $config_file;
            if (isset($filemanager_config)) {
                // Validar y sobrescribir configuraciones personalizadas
                foreach ($filemanager_config as $key => $value) {
                    if ($key === 'FILEMANAGER_ROOT_PATH') {
                        // Validar que la ruta existe
                        if (@is_dir($value)) {
                            $default_config[$key] = $value;
                        }
                    } else {
                        $default_config[$key] = $value;
                    }
                }
            }
        } catch (Exception $e) {
            error_log("FileManager: Error cargando config.php: " . $e->getMessage());
        }
    }
    
    // Guardar en caché y retornar
    $config_cache = $default_config;
    return $default_config;
}

/**
 * Verificar si es archivo delicado (configuración, base de datos, etc.)
 */
function isSensitiveFile($path, $name)
{
    $normalized_path = str_replace('\\', '/', strtolower($path));
    $normalized_name = strtolower($name);
    
    // Archivos de configuración delicados
    $sensitive_files = array(
        'main.inc.php', 'master.inc.php', 'conf.php', 'config.php', 'database.php',
        'db.php', 'settings.php', 'config.inc.php', 'conf.inc.php', 'database.inc.php',
        'db.inc.php', 'settings.inc.php', '.env', 'environment.php', 'env.php',
        'credentials.php', 'secrets.php', 'keys.php', 'tokens.php', 'auth.php',
        'login.php', 'admin.php', 'install.php', 'setup.php', 'installer.php'
    );
    
    if (in_array($normalized_name, $sensitive_files)) return true;
    
    // Archivos con extensiones delicadas
    $sensitive_extensions = array('inc', 'conf', 'env', 'key', 'pem', 'p12', 'pfx', 'crt', 'cer');
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (in_array($extension, $sensitive_extensions)) return true;
    
    // Archivos en carpetas de configuración (solo las realmente críticas)
    $sensitive_paths = array(
        '/conf/', '/config/', '/settings/', '/database/', '/db/', '/auth/',
        '/security/', '/admin/', '/install/', '/setup/', '/installer/',
        '/core/', '/includes/'
    );
    
    foreach ($sensitive_paths as $sensitive_path) {
        if (strpos($normalized_path, $sensitive_path) !== false) return true;
    }
    
    return false;
}


/**
 * Verificar si es archivo del sistema de Dolibarr
 */
function isSystemFile($path, $name)
{
    static $config_cache = null;
    static $allow_protected_cache = null;
    
    if ($config_cache === null) {
        $config_cache = getFileManagerConfig();
        $allow_protected_cache = isset($config_cache['FILEMANAGER_ALLOW_PROTECTED_ACTIONS']) && 
                                 $config_cache['FILEMANAGER_ALLOW_PROTECTED_ACTIONS'] == 1;
    }
    
    $allow_protected = $allow_protected_cache;
    
    // Normalizar la ruta para comparaciones
    $normalized_path = str_replace('\\', '/', $path);
    
    // Archivos críticos de Dolibarr (en cualquier ubicación) - SIEMPRE protegidos
    $critical_files = array(
        'main.inc.php', 'master.inc.php', 'filefunc.inc.php', 'opcachepreload.php'
    );
    if (in_array($name, $critical_files)) return true;
    
    // Si está permitido trabajar con archivos protegidos, no proteger carpetas por defecto
    if ($allow_protected) {
        // Solo proteger el módulo filemanager propio
        if (strpos($normalized_path, '/custom/filemanager/') !== false || 
            strpos($normalized_path, '\\custom\\filemanager\\') !== false ||
            strpos($normalized_path, '/custom/filemanager') !== false ||
            strpos($normalized_path, '\\custom\\filemanager') !== false) {
            return true;
        }
        
        // NO proteger carpetas por defecto de Dolibarr cuando está habilitada la opción
        return false;
    }
    
    // Si NO está permitido, proteger todo como antes
    // Archivos críticos de Dolibarr (en cualquier ubicación)
    $all_critical_files = array(
        'main.inc.php', 'master.inc.php', 'index.php', 'robots.txt', 'security.txt',
        'filefunc.inc.php', 'opcachepreload.php', 'favicon.ico'
    );
    if (in_array($name, $all_critical_files)) return true;
    
    // Carpetas críticas de Dolibarr
    $critical_dirs = array(
        'admin', 'core', 'includes', 'install', 'langs', 'theme', 'api', 'compta',
        'accountancy', 'adherents', 'asset', 'asterisk', 'barcode', 'blockedlog',
        'bom', 'bookmarks', 'categories', 'collab', 'comm', 'commande', 'contact',
        'contrat', 'cron', 'custom', 'datapolicy', 'dav', 'debugbar', 'delivery',
        'don', 'ecm', 'emailcollector', 'eventorganization', 'expedition',
        'expensereport', 'exports', 'externalsite', 'fichinter', 'fourn', 'ftp',
        'holiday', 'hrm', 'imports', 'intracommreport', 'knowledgemanagement',
        'loan', 'mailmanspip', 'margin', 'modulebuilder', 'mrp', 'multicurrency',
        'opensurvey', 'partnership', 'paybox', 'paypal', 'printing', 'product',
        'projet', 'public', 'reception', 'recruitment', 'resource', 'salaries',
        'societe', 'stripe', 'supplier_proposal', 'support', 'takepos', 'ticket',
        'user', 'variants', 'viewimage.php', 'webservices', 'website', 'workstation',
        'zapier'
    );
    if (in_array($name, $critical_dirs)) return true;
    
    // Archivos con extensiones críticas en carpetas del sistema
    $critical_extensions = array('php', 'inc', 'sql', 'xml', 'conf', 'htaccess');
    $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (in_array($extension, $critical_extensions)) {
        // Verificar si está en carpetas críticas de Dolibarr
        $system_paths = array(
            '/core/', '/includes/', '/admin/', '/install/', '/langs/', '/theme/',
            '/api/', '/compta/', '/accountancy/', '/adherents/', '/asset/',
            '/asterisk/', '/barcode/', '/blockedlog/', '/bom/', '/bookmarks/',
            '/categories/', '/collab/', '/comm/', '/commande/', '/contact/',
            '/contrat/', '/cron/', '/datapolicy/', '/dav/', '/debugbar/',
            '/delivery/', '/don/', '/ecm/', '/emailcollector/', '/eventorganization/',
            '/expedition/', '/expensereport/', '/exports/', '/externalsite/',
            '/fichinter/', '/fourn/', '/ftp/', '/holiday/', '/hrm/', '/imports/',
            '/intracommreport/', '/knowledgemanagement/', '/loan/', '/mailmanspip/',
            '/margin/', '/modulebuilder/', '/mrp/', '/multicurrency/', '/opensurvey/',
            '/partnership/', '/paybox/', '/paypal/', '/printing/', '/product/',
            '/projet/', '/public/', '/reception/', '/recruitment/', '/resource/',
            '/salaries/', '/societe/', '/stripe/', '/supplier_proposal/', '/support/',
            '/takepos/', '/ticket/', '/user/', '/variants/', '/webservices/',
            '/website/', '/workstation/', '/zapier/'
        );
        
        foreach ($system_paths as $system_path) {
            if (strpos($normalized_path, $system_path) !== false) {
                return true;
            }
        }
    }
    
    // Archivos de configuración específicos
    $config_files = array('conf.php', 'conf.php.old', 'module.xml', 'mod*.class.php');
    foreach ($config_files as $pattern) {
        if (fnmatch($pattern, $name)) return true;
    }
    
    return false;
}

/**
 * Sistema de logs del FileManager
 */
function logFileManagerAction($action, $file_path, $file_name, $user_id = null, $details = '')
{
    $log_file = __DIR__ . '/../logs/filemanager.log';
    $log_dir = dirname($log_file);
    
    // Crear directorio de logs si no existe
    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }
    
    // Obtener información del usuario
    global $user;
    $user_id = $user_id ?: (isset($user->id) ? $user->id : 'unknown');
    $user_name = isset($user->login) ? $user->login : 'unknown';
    
    // Obtener IP del usuario
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    
    // Crear entrada de log
    $timestamp = date('Y-m-d H:i:s');
    $log_entry = sprintf(
        "[%s] USER:%s(ID:%s) IP:%s ACTION:%s FILE:%s PATH:%s DETAILS:%s\n",
        $timestamp,
        $user_name,
        $user_id,
        $ip,
        $action,
        $file_name,
        $file_path,
        $details
    );
    
    // Escribir al archivo de log
    file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
}

/**
 * Verifica si un directorio es un módulo de Dolibarr
 */
function isModuleDirectory($dir_name)
{
    // Lista de módulos conocidos de Dolibarr
    $known_modules = array(
        'mpmods', 'filemanager', 'doliantifraude', 'ecommerce', 'ficharhoras',
        'importator', 'labelprint', 'pos', 'posclosesession', 'tikehau'
    );
    
    return in_array($dir_name, $known_modules);
}

/**
 * Desactiva un módulo en Dolibarr
 */
function deactivateModule($module_name)
{
    global $db, $user;
    
    try {
        // Buscar el módulo en la base de datos
        $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "rights_def WHERE module = '" . $db->escape($module_name) . "'";
        $resql = $db->query($sql);
        
        if ($resql && $db->num_rows($resql) > 0) {
            // Desactivar el módulo
            $sql = "UPDATE " . MAIN_DB_PREFIX . "rights_def SET perms = 0 WHERE module = '" . $db->escape($module_name) . "'";
            $db->query($sql);
            
            // Log de la desactivación
            logFileManagerAction('MODULE_DEACTIVATED', $module_name, $module_name, null, "Módulo desactivado automáticamente al mover a papelera");
            
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Error desactivando módulo $module_name: " . $e->getMessage());
        return false;
    }
}

/**
 * Reactiva un módulo en Dolibarr
 */
function reactivateModule($module_name)
{
    global $db, $user;
    
    try {
        // Buscar el módulo en la base de datos
        $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . "rights_def WHERE module = '" . $db->escape($module_name) . "'";
        $resql = $db->query($sql);
        
        if ($resql && $db->num_rows($resql) > 0) {
            // Reactivar el módulo
            $sql = "UPDATE " . MAIN_DB_PREFIX . "rights_def SET perms = 1 WHERE module = '" . $db->escape($module_name) . "'";
            $db->query($sql);
            
            // Log de la reactivación
            logFileManagerAction('MODULE_REACTIVATED', $module_name, $module_name, null, "Módulo reactivado automáticamente al restaurar desde papelera");
            
            return true;
        }
        
        return false;
    } catch (Exception $e) {
        error_log("Error reactivando módulo $module_name: " . $e->getMessage());
        return false;
    }
}

/**
 * Calcula el tamaño total de una carpeta recursivamente (ULTRA OPTIMIZADO)
 */
function calculateFolderSize($folder_path)
{
    $total_size = 0;
    $file_count = 0;
    
    if (!is_dir($folder_path)) {
        return array('size' => 0, 'count' => 0);
    }
    
    // CACHÉ: Verificar si ya calculamos esta carpeta recientemente
    $cache_file = __DIR__ . '/cache/folder_sizes.json';
    $cache_dir = dirname($cache_file);
    if (!is_dir($cache_dir)) {
        mkdir($cache_dir, 0755, true);
    }
    
    $cache_data = array();
    if (file_exists($cache_file)) {
        $cache_data = json_decode(file_get_contents($cache_file), true) ?: array();
    }
    
    $cache_key = md5($folder_path);
    $cache_time = 300; // 5 minutos de caché
    
    // Verificar caché
    if (isset($cache_data[$cache_key]) && 
        (time() - $cache_data[$cache_key]['timestamp']) < $cache_time) {
        return $cache_data[$cache_key]['data'];
    }
    
    // Contar TODOS los archivos recursivamente (SIN LÍMITES ARTIFICIALES)
    try {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($folder_path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $total_size += $file->getSize();
                $file_count++;
            }
        }
        
        $result = array('size' => $total_size, 'count' => $file_count);
        
        // Guardar en caché
        $cache_data[$cache_key] = array(
            'data' => $result,
            'timestamp' => time()
        );
        file_put_contents($cache_file, json_encode($cache_data));
        
        return $result;
    } catch (Exception $e) {
        // En caso de error, devolver valores por defecto
        error_log("Error calculando tamaño de carpeta $folder_path: " . $e->getMessage());
        return array('size' => 0, 'count' => 0);
    }
    
    return array('size' => $total_size, 'count' => $file_count);
}

/**
 * Formatea bytes en unidades legibles
 */
function formatBytes($bytes, $precision = 2)
{
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Obtener razón de protección
 */
function getProtectionReason($path, $name)
{
    if (isSystemFile($path, $name)) {
        return 'Archivo protegido del sistema';
    }
    return 'Archivo protegido';
}

/**
 * Obtener icono para archivo según su extensión
 */
function getFileIcon($extension)
{
    $extension = strtolower($extension);
    
    $icons = array(
        // Documentos
        'pdf' => '📄',
        'doc' => '📝',
        'docx' => '📝',
        'txt' => '📄',
        'rtf' => '📝',
        'odt' => '📝',
        'ods' => '📊',
        'xls' => '📊',
        'xlsx' => '📊',
        'ppt' => '📊',
        'pptx' => '📊',
        
        // Imágenes
        'jpg' => '🖼️',
        'jpeg' => '🖼️',
        'png' => '🖼️',
        'gif' => '🖼️',
        'bmp' => '🖼️',
        'svg' => '🖼️',
        'webp' => '🖼️',
        'ico' => '🖼️',
        
        // Videos
        'mp4' => '🎥',
        'avi' => '🎥',
        'mov' => '🎥',
        'wmv' => '🎥',
        'flv' => '🎥',
        'webm' => '🎥',
        'mkv' => '🎥',
        
        // Audio
        'mp3' => '🎵',
        'wav' => '🎵',
        'flac' => '🎵',
        'aac' => '🎵',
        'ogg' => '🎵',
        'wma' => '🎵',
        
        // Archivos comprimidos
        'zip' => '📦',
        'rar' => '📦',
        '7z' => '📦',
        'tar' => '📦',
        'gz' => '📦',
        'bz2' => '📦',
        
        // Código
        'php' => '🐘',
        'js' => '📜',
        'css' => '🎨',
        'html' => '🌐',
        'htm' => '🌐',
        'xml' => '📄',
        'json' => '📄',
        'sql' => '🗄️',
        'py' => '🐍',
        'java' => '☕',
        'cpp' => '⚙️',
        'c' => '⚙️',
        'h' => '⚙️',
        'cs' => '🔷',
        'rb' => '💎',
        'go' => '🐹',
        'rs' => '🦀',
        'swift' => '🍎',
        'kt' => '🟣',
        
        // Configuración
        'ini' => '⚙️',
        'conf' => '⚙️',
        'config' => '⚙️',
        'env' => '⚙️',
        'htaccess' => '⚙️',
        'htpasswd' => '⚙️',
        
        // Base de datos
        'db' => '🗄️',
        'sqlite' => '🗄️',
        'sqlite3' => '🗄️',
        
        // Otros
        'log' => '📋',
        'bak' => '💾',
        'backup' => '💾',
        'old' => '💾',
        'tmp' => '📄',
        'temp' => '📄'
    );
    
    if (isset($icons[$extension])) {
        return $icons[$extension];
    }
    
    // Icono por defecto
    return '📄';
}



/**
 * Detectar módulo desde la ruta
 */
function detectModuleFromPath($path)
{
    $normalized_path = str_replace('\\', '/', $path);
    
    // Buscar en /custom/ para módulos externos
    if (preg_match('/\/custom\/([^\/]+)/', $normalized_path, $matches)) {
        $module_name = $matches[1];
        
        // Verificar que no sea el módulo filemanager
        if ($module_name !== 'filemanager') {
            return $module_name;
        }
    }
    
    return null;
}

/**
 * Crear información de restauración
 */
function createRestoreInfo($trash_path, $original_path, $name, $type)
{
    global $user;
    
    // Crear directorio de papelera si no existe
    $trash_dir = dirname($trash_path);
    if (!is_dir($trash_dir)) {
        mkdir($trash_dir, 0755, true);
    }
    
    $metadata = array(
        'original_name' => $name,
        'original_path' => $original_path,
        'current_path' => dirname($original_path),
        'file_type' => $type,
        'deleted_date' => date('Y-m-d H:i:s'),
        'deleted_by' => $user->login ?? 'unknown'
    );
    
    // Si es carpeta, crear archivo .metadata.json OCULTO DENTRO de la carpeta
    if ($type === 'folder') {
        $metadata_file = $trash_path . '/.metadata.json';
        if (is_dir($trash_path)) {
            file_put_contents($metadata_file, json_encode($metadata, JSON_PRETTY_PRINT));
        }
    } else {
        // Si es archivo, crear archivo .metadata.json OCULTO AL LADO del archivo
        $metadata_file = $trash_path . '.metadata.json';
        file_put_contents($metadata_file, json_encode($metadata, JSON_PRETTY_PRINT));
    }
    
    $restore_info = "ARCHIVO ELIMINADO\n";
    $restore_info .= "================\n\n";
    $restore_info .= "Nombre original: " . $name . "\n";
    $restore_info .= "Ruta original: " . $original_path . "\n";
    $restore_info .= "Fecha de eliminación: " . date('Y-m-d H:i:s') . "\n";
    $restore_info .= "Eliminado por: " . ($user->login ?? 'unknown') . "\n\n";
    $restore_info .= "Para restaurar este archivo, use la función de restauración del administrador de archivos.\n";
    
    // Crear archivo de información de restauración en el directorio de papelera
    $restore_file = $trash_dir . '/RESTAURAR.txt';
    file_put_contents($restore_file, $restore_info);
}

/**
 * Copiar directorio recursivamente
 */
function copyDirectory($src, $dst)
{
    if (is_dir($src)) {
        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }
        $files = scandir($src);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                copyDirectory($src . '/' . $file, $dst . '/' . $file);
            }
        }
        return true;
    } else if (file_exists($src)) {
        return copy($src, $dst);
    }
    return false;
}

/**
 * Eliminar directorio recursivamente
 */
function removeDirectory($dir)
{
    if (is_dir($dir)) {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file != '.' && $file != '..') {
                if (!removeDirectory($dir . '/' . $file)) {
                    return false;
                }
            }
        }
        return rmdir($dir);
    } else if (file_exists($dir)) {
        return unlink($dir);
    }
    return false;
}

/**
 * Buscar archivo en papelera
 */
function findTrashItem($trash_base_folder, $item_name)
{
    if (!is_dir($trash_base_folder)) return null;
    
    $date_folders = scandir($trash_base_folder);
    foreach ($date_folders as $date_folder) {
        if ($date_folder == '.' || $date_folder == '..') continue;
        $date_path = $trash_base_folder . '/' . $date_folder;
        if (is_dir($date_path)) {
            $result = findTrashItemRecursive($date_path, $item_name);
            if ($result) return $result;
        }
    }
    return null;
}

/**
 * Búsqueda recursiva en papelera
 */
function findTrashItemRecursive($path, $item_name)
{
    if (!is_dir($path)) return null;
    
    $items = scandir($path);
    foreach ($items as $item) {
        if ($item == '.' || $item == '..') continue;
        $full_path = $path . '/' . $item;
        
        if (is_dir($full_path)) {
            $metadata_file = $full_path . '/.metadata.json';
            if (file_exists($metadata_file)) {
                $metadata = json_decode(file_get_contents($metadata_file), true);
                if ($metadata && $metadata['original_name'] === $item_name) {
                    $file_path = $full_path . '/' . $metadata['original_name'];
                    if (file_exists($file_path)) {
                        return array('path' => $full_path, 'metadata' => $metadata);
                    }
                }
            }
        }
    }
    return null;
}

/**
 * Escanear papelera recursivamente
 */
function scanTrashRecursively($path, $date_folder)
{
    $items = array();
    if (!is_dir($path)) return $items;
    
    $dir_items = scandir($path);
    foreach ($dir_items as $item) {
        if ($item == '.' || $item == '..') continue;
        $full_path = $path . '/' . $item;
        
        if (is_dir($full_path)) {
            $metadata_file = $full_path . '/.metadata.json';
            if (file_exists($metadata_file)) {
                $metadata = json_decode(file_get_contents($metadata_file), true);
                if ($metadata) {
                    $items[] = array(
                        'name' => $metadata['original_name'],
                        'display_name' => $metadata['original_name'] . ' (Eliminado: ' . date('d/m/Y H:i', strtotime($metadata['deleted_date'])) . ')',
                        'path' => $full_path,
                        'is_dir' => $metadata['file_type'] === 'directory',
                        'size' => $metadata['file_type'] === 'directory' ? '-' : filesize($full_path . '/' . $metadata['original_name']),
                        'date' => strtotime($metadata['deleted_date']),
                        'date_folder' => $date_folder,
                        'metadata' => $metadata,
                        'original_path' => $metadata['original_path'],
                        'current_path' => $metadata['current_path']
                    );
                }
            }
        }
    }
    return $items;
}

/**
 * Verificar si una ruta está protegida
 */
function isProtectedPath($path)
{
    static $config_cache = null;
    static $allow_protected_cache = null;
    
    if ($config_cache === null) {
        $config_cache = getFileManagerConfig();
        $allow_protected_cache = isset($config_cache['FILEMANAGER_ALLOW_PROTECTED_ACTIONS']) && 
                                 $config_cache['FILEMANAGER_ALLOW_PROTECTED_ACTIONS'] == 1;
    }
    
    $allow_protected = $allow_protected_cache;
    
    $normalized_path = str_replace('\\', '/', $path);
    
    // Solo archivos CRÍTICOS de Dolibarr (muy específicos) - SIEMPRE protegidos
    $critical_files = array(
        'main.inc.php', 'master.inc.php', 'filefunc.inc.php', 'opcachepreload.php'
    );
    
    $filename = basename($path);
    if (in_array($filename, $critical_files)) return true;
    
    // Si está permitido trabajar con archivos protegidos, no proteger carpetas por defecto
    if ($allow_protected) {
        // Solo proteger el módulo filemanager propio (más seguro)
        if (strpos($normalized_path, '/custom/filemanager/') !== false || 
            strpos($normalized_path, '\\custom\\filemanager\\') !== false ||
            strpos($normalized_path, '/custom/filemanager') !== false ||
            strpos($normalized_path, '\\custom\\filemanager') !== false) {
            return true;
        }
        
        // NO proteger carpetas por defecto de Dolibarr cuando está habilitada la opción
        return false;
    }
    
    // Si NO está permitido trabajar con archivos protegidos, proteger todo como antes
    // Solo carpetas CRÍTICAS del sistema (muy específicas)
    $critical_system_dirs = array(
        'admin', 'core', 'includes', 'install'
    );
    
    // Proteger específicamente el módulo filemanager (solo este módulo)
    if (strpos($normalized_path, '/custom/filemanager/') !== false || 
        strpos($normalized_path, '\\custom\\filemanager\\') !== false ||
        strpos($normalized_path, '/custom/filemanager') !== false ||
        strpos($normalized_path, '\\custom\\filemanager') !== false) {
        return true;
    }
    
    foreach ($critical_system_dirs as $dir) {
        if (strpos($normalized_path, '/' . $dir . '/') !== false || 
            strpos($normalized_path, '\\' . $dir . '\\') !== false ||
            strpos($normalized_path, '/' . $dir) === strlen($normalized_path) - strlen('/' . $dir) ||
            strpos($normalized_path, '\\' . $dir) === strlen($normalized_path) - strlen('\\' . $dir)) {
            return true;
        }
    }
    
    // Proteger archivos de configuración
    if (strpos($normalized_path, '/conf/') !== false || 
        strpos($normalized_path, '\\conf\\') !== false) {
        return true;
    }
    
    // Proteger archivos de documentos del sistema
    if (strpos($normalized_path, '/documents/') !== false || 
        strpos($normalized_path, '\\documents\\') !== false) {
        return true;
    }
    
    return false;
}

/**
 * Obtener lista de archivos y carpetas protegidos de Dolibarr
 */
function getProtectedItems()
{
    return array(
        'archivos_criticos' => array(
            'main.inc.php', 'master.inc.php', 'index.php', 'robots.txt', 'security.txt',
            'filefunc.inc.php', 'opcachepreload.php', 'favicon.ico', 'logo.png',
            'document.php', 'viewimage.php', 'index.html_'
        ),
        'carpetas_modulos' => array(
            'admin', 'core', 'includes', 'install', 'langs', 'theme', 'api', 'compta',
            'comm', 'contact', 'fourn', 'product', 'projet', 'user', 'societe',
            'accountancy', 'adherents', 'asset', 'asterisk', 'barcode', 'blockedlog',
            'bom', 'bookmarks', 'categories', 'cgi-bin', 'collab', 'cron', 'custom',
            'datapolicy', 'dav', 'debugbar', 'delivery', 'don', 'ecm', 'emailcollector',
            'eventorganization', 'expedition', 'expensereport', 'exports', 'externalsite',
            'fichinter', 'ftp', 'holiday', 'hrm', 'imports', 'intracommreport',
            'knowledgemanagement', 'loan', 'mailmanspip', 'margin', 'modulebuilder',
            'multicurrency', 'opensurvey', 'partnership', 'paybox', 'paypal', 'pos',
            'printing', 'public', 'reception', 'recruitment', 'resource', 'salaries',
            'stripe', 'supplier_proposal', 'support', 'takepos', 'ticket', 'variants',
            'webservices', 'website', 'workstation', 'zapier'
        ),
        'carpetas_sistema' => array(
            'conf', 'documents'
        ),
        'modulos_custom' => array(
            'custom/filemanager'
        ),
        'archivos_error' => array(
            '400.shtml', '401.shtml', '403.shtml', '404.shtml', '500.shtml'
        )
    );
}

/**
 * Crear archivo ZIP de una carpeta o archivo
 */
function createZip($source, $destination) {
    if (!extension_loaded('zip')) {
        return false;
    }
    
    $zip = new ZipArchive();
    if ($zip->open($destination, ZipArchive::CREATE) !== TRUE) {
        return false;
    }
    
    if (is_file($source)) {
        $zip->addFile($source, basename($source));
    } else {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($source) + 1);
                $zip->addFile($filePath, $relativePath);
            }
        }
    }
    
    $zip->close();
    return true;
}

/**
 * Obtener el tipo MIME de un archivo
 */
function getMimeType($file) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file);
    finfo_close($finfo);
    return $mimeType;
}

/**
 * Verificar si un archivo es visualizable
 */
function isViewableFile($file) {
    $viewableExtensions = array(
        'txt', 'md', 'json', 'xml', 'html', 'htm', 'css', 'js', 'php', 'sql',
        'jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp',
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'
    );
    
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    return in_array($extension, $viewableExtensions);
}

/**
 * Obtener el contenido de un archivo para visualización
 */
function getFileContent($file) {
    if (!is_file($file) || !is_readable($file)) {
        return false;
    }
    
    $mimeType = getMimeType($file);
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    
    // Archivos de texto
    if (strpos($mimeType, 'text/') === 0 || in_array($extension, array('txt', 'md', 'json', 'xml', 'html', 'htm', 'css', 'js', 'php', 'sql'))) {
        return array(
            'type' => 'text',
            'content' => file_get_contents($file),
            'mime' => $mimeType
        );
    }
    
    // Imágenes
    if (strpos($mimeType, 'image/') === 0) {
        return array(
            'type' => 'image',
            'content' => base64_encode(file_get_contents($file)),
            'mime' => $mimeType
        );
    }
    
    // PDF
    if ($mimeType === 'application/pdf') {
        return array(
            'type' => 'pdf',
            'content' => base64_encode(file_get_contents($file)),
            'mime' => $mimeType
        );
    }
    
    return false;
}

/**
 * Registrar actividad en el log del filemanager (archivo y base de datos)
 */
function logFileManagerActivity($action, $path, $user_id = null, $details = '') {
    global $db, $user;
    
    // Validar que la acción no esté vacía
    if (empty($action)) {
        error_log("FileManager: Intento de registrar log sin acción");
        return false;
    }
    
    // Obtener información del usuario
    $user_id = $user_id ?: (isset($user) && is_object($user) && !empty($user->id) ? $user->id : 0);
    $user_name = 'unknown';
    if (isset($user) && is_object($user)) {
        if (!empty($user->login)) {
            $user_name = $user->login;
        } elseif (!empty($user->firstname) || !empty($user->lastname)) {
            $user_name = trim(($user->firstname ?? '') . ' ' . ($user->lastname ?? ''));
            if (empty($user_name)) {
                $user_name = 'unknown';
            }
        }
    }
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'unknown');
    $timestamp = date('Y-m-d H:i:s');
    $file_name = !empty($path) ? basename($path) : '';
    
    // Guardar en archivo de texto
    $log_file = __DIR__ . '/../logs/filemanager.log';
    $log_dir = dirname($log_file);
    
    if (!is_dir($log_dir)) {
        @mkdir($log_dir, 0755, true);
    }
    
    $log_entry = sprintf(
        "[%s] User:%s IP:%s Action:%s Path:%s Details:%s\n",
        $timestamp,
        $user_name,
        $ip,
        $action,
        $path ?: '(empty)',
        $details ?: ''
    );
    
    @file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);
    
    // Guardar en base de datos
    // Intentar obtener $db desde global si no está disponible
    if (!isset($db)) {
        global $db;
    }
    
    // Si aún no hay $db, intentar crear conexión PDO directa
    $usePDO = false;
    $pdo = null;
    if (!isset($db) || !is_object($db) || !method_exists($db, 'query')) {
        // Intentar crear conexión PDO usando variables de conf.php
        try {
            $confPath = dirname(__DIR__, 2) . '/conf/conf.php';
            if (file_exists($confPath)) {
                // Cargar variables sin ejecutar main.inc.php
                include_once $confPath;
                
                if (isset($dolibarr_main_db_host) && isset($dolibarr_main_db_name)) {
                    $host = $dolibarr_main_db_host;
                    $name = $dolibarr_main_db_name;
                    $dbuser = $dolibarr_main_db_user ?? 'root';
                    $dbpass = $dolibarr_main_db_pass ?? '';
                    $port = $dolibarr_main_db_port ?? 3306;
                    
                    $dsn = "mysql:host=$host;port=$port;dbname=$name;charset=utf8mb4";
                    $pdo = new PDO($dsn, $dbuser, $dbpass, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_TIMEOUT => 5
                    ]);
                    $usePDO = true;
                    file_put_contents($log_file, "[DEBUG] Usando PDO para conexión directa a BD\n", FILE_APPEND | LOCK_EX);
                }
            }
        } catch (Exception $e) {
            file_put_contents($log_file, "[ERROR] No se pudo crear conexión PDO: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);
        }
    }
    
    if (isset($db) && is_object($db) && method_exists($db, 'query')) {
        try {
            // Verificar si la tabla existe, si no existe, crearla
            $check_table = $db->query("SHOW TABLES LIKE 'llx_filemanager_logs'");
            $table_exists = $check_table && $db->num_rows($check_table) > 0;
            
            if (!$table_exists) {
                // Crear la tabla
                $create_sql = "
                CREATE TABLE IF NOT EXISTS llx_filemanager_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT,
                    user_name VARCHAR(255),
                    ip_address VARCHAR(45),
                    action VARCHAR(100) NOT NULL,
                    file_path TEXT,
                    file_name VARCHAR(255),
                    details TEXT,
                    timestamp DATETIME NOT NULL,
                    INDEX idx_user_id (user_id),
                    INDEX idx_action (action),
                    INDEX idx_timestamp (timestamp),
                    INDEX idx_file_name (file_name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ";
                $create_result = $db->query($create_sql);
                if ($create_result) {
                file_put_contents($log_file, "[DEBUG] Tabla llx_filemanager_logs creada\n", FILE_APPEND | LOCK_EX);
                    // Limpiar cache de tablas
                    $db->query("FLUSH TABLES");
                } else {
                    $create_error = $db->lasterror();
                    file_put_contents($log_file, "[ERROR] No se pudo crear tabla: $create_error\n", FILE_APPEND | LOCK_EX);
                }
                // Verificar nuevamente después de crear
                $check_table = $db->query("SHOW TABLES LIKE 'llx_filemanager_logs'");
                $table_exists = $check_table && $db->num_rows($check_table) > 0;
            }
            
            if ($table_exists) {
                // Preparar valores de forma segura
            $user_id = intval($user_id);
                $user_name = trim($user_name) ?: 'unknown';
                $action = trim($action) ?: 'unknown';
                $path = $path ?: '';
                $file_name = $file_name ?: '';
                $ip = $ip ?: 'unknown';
                $details = $details ?: '';
                
                // Escapar valores usando el método escape de Dolibarr
            $user_name_escaped = $db->escape($user_name);
            $action_escaped = $db->escape($action);
            $path_escaped = $db->escape($path);
            $file_name_escaped = $db->escape($file_name);
            $ip_escaped = $db->escape($ip);
            $timestamp_escaped = $db->escape($timestamp);
            $details_escaped = $db->escape($details);
            
                // Construir SQL con valores escapados
                $sql = "INSERT INTO llx_filemanager_logs (user_id, user_name, action, file_path, file_name, ip_address, timestamp, details) VALUES (
                    " . intval($user_id) . ",
                    '" . $user_name_escaped . "',
                    '" . $action_escaped . "',
                    '" . $path_escaped . "',
                    '" . $file_name_escaped . "',
                    '" . $ip_escaped . "',
                    '" . $timestamp_escaped . "',
                    '" . $details_escaped . "'
                )";
            
            $result = $db->query($sql);
            
            if ($result) {
                    file_put_contents($log_file, "[DEBUG] Log guardado correctamente en BD (ID: " . ($db->last_insert_id('llx_filemanager_logs') ?: 'N/A') . ")\n", FILE_APPEND | LOCK_EX);
            } else {
                $error = $db->lasterror();
                file_put_contents($log_file, "[ERROR] No se pudo guardar en BD: $error\n", FILE_APPEND | LOCK_EX);
                    file_put_contents($log_file, "[DEBUG] SQL ejecutado: " . substr($sql, 0, 200) . "...\n", FILE_APPEND | LOCK_EX);
                }
            } else {
                file_put_contents($log_file, "[ERROR] Tabla llx_filemanager_logs no existe y no se pudo crear\n", FILE_APPEND | LOCK_EX);
            }
        } catch (Exception $e) {
            file_put_contents($log_file, "[EXCEPTION] " . $e->getMessage() . " en " . $e->getFile() . " línea " . $e->getLine() . "\n", FILE_APPEND | LOCK_EX);
            file_put_contents($log_file, "[DEBUG] Stack trace: " . substr($e->getTraceAsString(), 0, 500) . "\n", FILE_APPEND | LOCK_EX);
        } catch (Error $e) {
            file_put_contents($log_file, "[FATAL] " . $e->getMessage() . " en " . $e->getFile() . " línea " . $e->getLine() . "\n", FILE_APPEND | LOCK_EX);
        }
    } elseif ($usePDO && $pdo instanceof PDO) {
        // Usar PDO directamente si $db no está disponible
        try {
            // Verificar si la tabla existe
            $stmt = $pdo->query("SHOW TABLES LIKE 'llx_filemanager_logs'");
            $table_exists = $stmt && $stmt->rowCount() > 0;
            
            if (!$table_exists) {
                // Crear la tabla
                $create_sql = "
                CREATE TABLE IF NOT EXISTS llx_filemanager_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT,
                    user_name VARCHAR(255),
                    ip_address VARCHAR(45),
                    action VARCHAR(100) NOT NULL,
                    file_path TEXT,
                    file_name VARCHAR(255),
                    details TEXT,
                    timestamp DATETIME NOT NULL,
                    INDEX idx_user_id (user_id),
                    INDEX idx_action (action),
                    INDEX idx_timestamp (timestamp),
                    INDEX idx_file_name (file_name)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
                ";
                $pdo->exec($create_sql);
                file_put_contents($log_file, "[DEBUG] Tabla llx_filemanager_logs creada usando PDO\n", FILE_APPEND | LOCK_EX);
                $table_exists = true;
            }
            
            if ($table_exists) {
                // Preparar valores
                $user_id = intval($user_id);
                $user_name = trim($user_name) ?: 'unknown';
                $action = trim($action) ?: 'unknown';
                $path = $path ?: '';
                $file_name = $file_name ?: '';
                $ip = $ip ?: 'unknown';
                $details = $details ?: '';
                
                // Usar prepared statement para seguridad
                try {
                    $sql = "INSERT INTO llx_filemanager_logs (user_id, user_name, action, file_path, file_name, ip_address, timestamp, details) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $pdo->prepare($sql);

                    if ($stmt === false) {
                        throw new Exception("Error preparing statement");
                    }

                    $result = $stmt->execute([
                        $user_id,
                        $user_name,
                        $action,
                        $path,
                        $file_name,
                        $ip,
                        $timestamp,
                        $details
                    ]);

                    if ($result) {
                        $insertId = $pdo->lastInsertId();
                        file_put_contents($log_file, "[DEBUG] Log guardado correctamente en BD usando PDO (ID: $insertId)\n", FILE_APPEND | LOCK_EX);
                    } else {
                        $error = $stmt->errorInfo();
                        throw new Exception("Execute failed: " . ($error[2] ?? 'Error desconocido'));
                    }
                } catch (Exception $e) {
                    // Fallback: usar query directa con datos escapados si prepared statement falla
                    file_put_contents($log_file, "[WARNING] Prepared statement falló, intentando con query directa: " . $e->getMessage() . "\n", FILE_APPEND | LOCK_EX);

                    try {
                        // Escapar manualmente y usar query directa
                        $user_name_esc = $pdo->quote($user_name);
                        $action_esc = $pdo->quote($action);
                        $path_esc = $pdo->quote($path);
                        $file_name_esc = $pdo->quote($file_name);
                        $ip_esc = $pdo->quote($ip);
                        $timestamp_esc = $pdo->quote($timestamp);
                        $details_esc = $pdo->quote($details);

                        $fallback_sql = "INSERT INTO llx_filemanager_logs (user_id, user_name, action, file_path, file_name, ip_address, timestamp, details) VALUES (
                            " . intval($user_id) . ",
                            $user_name_esc,
                            $action_esc,
                            $path_esc,
                            $file_name_esc,
                            $ip_esc,
                            $timestamp_esc,
                            $details_esc
                        )";

                        $pdo->exec($fallback_sql);
                        $insertId = $pdo->lastInsertId();
                        file_put_contents($log_file, "[DEBUG] Log guardado con fallback directo (ID: $insertId)\n", FILE_APPEND | LOCK_EX);
                    } catch (Exception $fallbackError) {
                        file_put_contents($log_file, "[ERROR] Tanto prepared statement como fallback fallaron: " . $fallbackError->getMessage() . "\n", FILE_APPEND | LOCK_EX);
                        file_put_contents($log_file, "[ERROR] Datos del log perdido: user_id=$user_id, action=$action, path=$path\n", FILE_APPEND | LOCK_EX);
                    }
                }
            }
        } catch (PDOException $e) {
            file_put_contents($log_file, "[EXCEPTION PDO] " . $e->getMessage() . " en " . $e->getFile() . " línea " . $e->getLine() . "\n", FILE_APPEND | LOCK_EX);
        } catch (Exception $e) {
            file_put_contents($log_file, "[EXCEPTION] " . $e->getMessage() . " en " . $e->getFile() . " línea " . $e->getLine() . "\n", FILE_APPEND | LOCK_EX);
        }
    } else {
        $db_status = 'NULL';
        if (isset($db)) {
            if (is_object($db)) {
                $db_status = 'Object (tipo: ' . get_class($db) . ')';
            } else {
                $db_status = 'No es objeto';
            }
        }
        if ($usePDO && !($pdo instanceof PDO)) {
            $db_status .= ' - PDO no disponible';
        }
        file_put_contents($log_file, "[DEBUG] No se guarda en BD: db=$db_status\n", FILE_APPEND | LOCK_EX);
    }
}

/**
 * Obtener logs del filemanager
 */
function getFileManagerLogs($limit = 100) {
    global $db;
    
    $log_file = __DIR__ . '/../logs/filemanager.log';
    
    if (!file_exists($log_file)) {
        return array();
    }
    
    $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) {
        return array();
    }
    
    // Obtener las últimas líneas
    $lines = array_slice($lines, -$limit);
    $logs = array();
    
    // Obtener nombres de usuarios de la base de datos
    $user_names = array();
    if (is_object($db)) {
        $sql = "SELECT rowid, login, firstname, lastname FROM " . MAIN_DB_PREFIX . "user";
        $resql = $db->query($sql);
        if ($resql) {
            while ($obj = $db->fetch_object($resql)) {
                $user_names[$obj->rowid] = trim($obj->firstname . ' ' . $obj->lastname);
                if (empty($user_names[$obj->rowid])) {
                    $user_names[$obj->rowid] = $obj->login;
                }
            }
        }
    }
    
    foreach ($lines as $line) {
        if (preg_match('/^\[([^\]]+)\] User:([^\s]+) IP:([^\s]+) Action:([^\s]+) Path:([^\s]+) Details:(.*)$/', $line, $matches)) {
            $user_id = $matches[2];
            $user_name = isset($user_names[$user_id]) ? $user_names[$user_id] : 'Usuario #' . $user_id;
            
            $logs[] = array(
                'timestamp' => $matches[1],
                'user_id' => $user_id,
                'user_name' => $user_name,
                'ip' => $matches[3],
                'action' => $matches[4],
                'path' => $matches[5],
                'details' => $matches[6]
            );
        }
    }
    
    return array_reverse($logs); // Más recientes primero
}

/**
 * Obtener logs del filemanager desde base de datos
 */
function getFileManagerLogsFromDB($limit = 100) {
    global $db;
    
    if (!$db || empty($db->database_name)) {
        return array();
    }
    
    $sql = "SELECT user_id, action, file_path, file_name, ip_address, date_action 
            FROM llx_filemanager_logs 
            ORDER BY date_action DESC 
            LIMIT " . (int)$limit;
    
    $result = $db->query($sql);
    if (!$result || $db->num_rows($result) == 0) {
        return array();
    }
    
    $logs = array();
    while ($obj = $db->fetch_object($result)) {
        $logs[] = array(
            'timestamp' => $obj->date_action,
            'user_name' => 'Usuario #' . $obj->user_id,
            'ip' => $obj->ip_address,
            'action' => $obj->action,
            'path' => $obj->file_path,
            'details' => 'Archivo: ' . $obj->file_name,
            'file_name' => $obj->file_name
        );
    }
    
    return $logs;
}

/**
 * Limpiar logs antiguos
 */
function cleanOldLogs($days = 30) {
    $log_file = __DIR__ . '/../logs/filemanager.log';
    
    if (!file_exists($log_file)) {
        return;
    }
    
    $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) {
        return;
    }
    
    $cutoff_date = date('Y-m-d', strtotime("-{$days} days"));
    $filtered_lines = array();
    
    foreach ($lines as $line) {
        if (preg_match('/^\[([^\]]+)\]/', $line, $matches)) {
            $log_date = date('Y-m-d', strtotime($matches[1]));
            if ($log_date >= $cutoff_date) {
                $filtered_lines[] = $line;
            }
        }
    }
    
    file_put_contents($log_file, implode("\n", $filtered_lines) . "\n");
}
