<?php
/**
 * File Manager - Administrador de Archivos
 * Versión completamente funcional
 */

// Incluir el entorno de Dolibarr
require_once '../../main.inc.php';

// ========== VERIFICACIÓN DE SEGURIDAD 1: MÓDULO ACTIVADO ==========
if (empty($conf->global->MAIN_MODULE_FILEMANAGER)) {
    // Módulo desactivado - mostrar página de error
    llxHeader('', 'FileManager - Módulo Desactivado');
    print '<div style="max-width: 800px; margin: 50px auto; padding: 30px; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center;">';
    print '<div style="font-size: 72px; color: #dc3545; margin-bottom: 20px;">⚠️</div>';
    print '<h1 style="color: #dc3545; margin-bottom: 20px;">Módulo FileManager Desactivado</h1>';
    print '<p style="font-size: 18px; color: #6c757d; margin-bottom: 30px;">';
    print 'El módulo FileManager está actualmente desactivado en Dolibarr.<br>';
    print 'Para utilizar esta funcionalidad, debe activar el módulo desde el menú de administración.';
    print '</p>';
    print '<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 30px;">';
    print '<p style="margin: 0; color: #495057;"><strong>¿Necesita ayuda?</strong></p>';
    print '<p style="margin: 10px 0 0 0; color: #6c757d;">Contacte al administrador de Dolibarr para activar el módulo.</p>';
    print '</div>';
    print '</div>';
    llxFooter();
    exit;
}

// ========== VERIFICACIÓN DE SEGURIDAD 2: PERMISOS DE ADMINISTRADOR ==========
if (!$user->admin) {
    // Usuario no es administrador - mostrar página de error
    llxHeader('', 'FileManager - Acceso Denegado');
    print '<div style="max-width: 800px; margin: 50px auto; padding: 30px; background: white; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center;">';
    print '<div style="font-size: 72px; color: #dc3545; margin-bottom: 20px;">🔒</div>';
    print '<h1 style="color: #dc3545; margin-bottom: 20px;">Acceso Denegado</h1>';
    print '<p style="font-size: 18px; color: #6c757d; margin-bottom: 30px;">';
    print 'No tiene permisos de administrador para acceder al FileManager.<br>';
    print 'Esta funcionalidad está restringida únicamente a administradores de Dolibarr.';
    print '</p>';
    print '<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-top: 30px;">';
    print '<p style="margin: 0; color: #495057;"><strong>¿Necesita acceso al FileManager?</strong></p>';
    print '<p style="margin: 10px 0 0 0; color: #6c757d;">Contacte al administrador de Dolibarr para solicitar permisos de administrador.</p>';
    print '</div>';
    print '</div>';
    llxFooter();
    exit;
}

// Incluir la librería del filemanager
require_once 'lib/filemanager.lib.php';
require_once 'lib/filemanager_i18n.php';

// Obtener configuración
$config = getFileManagerConfig();

// FORZAR el idioma seleccionado en FileManager si no es "auto"
if (isset($config['FILEMANAGER_LANGUAGE']) && $config['FILEMANAGER_LANGUAGE'] !== 'auto') {
    $fm_lang_short = $config['FILEMANAGER_LANGUAGE'];
    
    // Convertir código corto a código largo de Dolibarr
    $lang_map = array(
        'es' => 'es_ES',
        'en' => 'en_US',
        'fr' => 'fr_FR',
        'de' => 'de_DE'
    );
    
    $fm_lang = isset($lang_map[$fm_lang_short]) ? $lang_map[$fm_lang_short] : $fm_lang_short;
    
    // Forzar el idioma en el objeto $langs
    global $langs;
    $langs->setDefaultLang($fm_lang);
    $langs->load('main');
}

// DEBUG TEMPORAL - Mostrar valor de configuración visible en pantalla
if (isset($_GET['debug']) && $_GET['debug'] == '1') {
    $debug_val = isset($config['FILEMANAGER_ALLOW_PROTECTED_ACTIONS']) ? $config['FILEMANAGER_ALLOW_PROTECTED_ACTIONS'] : 'NO DEFINIDO';
    $debug_type = gettype($debug_val);
    print '<div style="position: fixed; top: 10px; right: 10px; background: red; color: white; padding: 20px; z-index: 99999; border: 3px solid black; font-weight: bold; font-size: 16px;">';
    print 'DEBUG CONFIG:<br>';
    print 'FILEMANAGER_ALLOW_PROTECTED_ACTIONS = ' . var_export($debug_val, true) . '<br>';
    print 'Tipo: ' . $debug_type . '<br>';
    print 'Es 1?: ' . (($debug_val == 1 || $debug_val === '1' || $debug_val === true) ? 'SÍ' : 'NO');
    print '</div>';
}

// Obtener la ruta actual
$current_path = isset($_GET['path']) ? $_GET['path'] : $config['FILEMANAGER_ROOT_PATH'];
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'files';

// Función de seguridad: verificar que la ruta esté dentro de Dolibarr
function isPathSafeFM($path) {
    global $config;
    $DOLIBARR_ROOT = $config['FILEMANAGER_ROOT_PATH'];
    
    // Normalizar rutas
    $realPath = realpath($path);
    $realDolibarrRoot = realpath($DOLIBARR_ROOT);
    
    if (!$realPath || !$realDolibarrRoot) {
        return false;
    }
    
    // Verificar que la ruta esté dentro de Dolibarr
    return strpos($realPath, $realDolibarrRoot) === 0;
}

// Validar la ruta
if (!is_dir($current_path) || !isPathSafeFM($current_path)) {
    $current_path = $config['FILEMANAGER_ROOT_PATH'];
}

// Obtener archivos y carpetas
$folders = array();
$files = array();
$trash_files = array();

// SIEMPRE contar archivos y carpetas del directorio actual (incluso desde papelera)
$items_in_current_path = array();
if (is_dir($current_path)) {
    $scanned_items = scandir($current_path);
    foreach ($scanned_items as $item) {
        if ($item === '.' || $item === '..') continue;
        $item_path = $current_path . DIRECTORY_SEPARATOR . $item;
        $items_in_current_path[] = array('name' => $item, 'path' => $item_path, 'is_dir' => is_dir($item_path));
    }
}

if ($active_tab === 'files') {
    $items = scandir($current_path);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $item_path = $current_path . DIRECTORY_SEPARATOR . $item;
        
        if (is_dir($item_path)) {
            // Contar archivos en la carpeta
            $file_count = 0;
            if (is_dir($item_path)) {
                $items_in_folder = scandir($item_path);
                foreach ($items_in_folder as $item_in_folder) {
                    if ($item_in_folder !== '.' && $item_in_folder !== '..') {
                        $file_count++;
                    }
                }
            }
            
            // Verificar si es carpeta protegida
            $is_protected = isProtectedPath($item_path);
            $is_system = isSystemFile($item_path, $item);
            $is_sensitive = isSensitiveFile($item_path, $item);
            
            // Verificar si ES ORIGINAL DE DOLIBARR (sin considerar la opción)
            // Lista de carpetas originales de Dolibarr
            $dolibarr_original_dirs = array(
                'admin', 'core', 'includes', 'install', 'langs', 'theme', 'api', 'compta',
                'accountancy', 'adherents', 'asset', 'asterisk', 'barcode', 'blockedlog',
                'bom', 'bookmarks', 'categories', 'collab', 'comm', 'commande', 'contact',
                'contrat', 'cron', 'datapolicy', 'dav', 'debugbar', 'delivery',
                'don', 'ecm', 'emailcollector', 'eventorganization', 'expedition',
                'expensereport', 'exports', 'externalsite', 'fichinter', 'fourn', 'ftp',
                'holiday', 'hrm', 'imports', 'intracommreport', 'knowledgemanagement',
                'loan', 'mailmanspip', 'margin', 'modulebuilder', 'mrp', 'multicurrency',
                'opensurvey', 'partnership', 'paybox', 'paypal', 'printing', 'product',
                'projet', 'public', 'reception', 'recruitment', 'resource', 'salaries',
                'societe', 'stripe', 'supplier_proposal', 'support', 'takepos', 'ticket',
                'user', 'variants', 'viewimage.php', 'webservices', 'website', 'workstation',
                'zapier', 'conf', 'documents', 'cgi-bin'
            );
            
            // Normalizar ruta para verificar
            $normalized_path_for_check = str_replace('\\', '/', strtolower($item_path));
            $is_original_dolibarr_folder = in_array(strtolower($item), $dolibarr_original_dirs) || 
                                          strpos($normalized_path_for_check, '/conf/') !== false ||
                                          strpos($normalized_path_for_check, '/documents/') !== false;
            
            // Guardar si originalmente era protegido (para el estilo morado)
            $was_originally_protected = $is_original_dolibarr_folder || $is_protected || $is_system;
            
            // Si está permitido trabajar con archivos protegidos, no marcar como protegido
            $config_val = isset($config['FILEMANAGER_ALLOW_PROTECTED_ACTIONS']) ? $config['FILEMANAGER_ALLOW_PROTECTED_ACTIONS'] : 0;
            $allow_protected = ($config_val == 1 || $config_val === '1' || $config_val === true || $config_val === 'true' || (int)$config_val > 0);
            
            $should_protect = false;
            if ($allow_protected) {
                // Solo proteger el módulo filemanager propio
                $normalized_path = str_replace('\\', '/', $item_path);
                if (strpos($normalized_path, '/custom/filemanager/') !== false || 
                    strpos($normalized_path, '\\custom\\filemanager\\') !== false ||
                    strpos($normalized_path, '/custom/filemanager') !== false ||
                    strpos($normalized_path, '\\custom\\filemanager') !== false) {
                    $should_protect = true;
                }
            } else {
                // Comportamiento normal: proteger si es protegido o sistema
                $should_protect = $is_protected || $is_system;
            }
            
            // Mostrar todas las carpetas, marcar las protegidas
            $folders[] = array(
                'name' => $item,
                'path' => $item_path,
                'file_count' => $file_count,
                'modified' => date('Y-m-d H:i:s', filemtime($item_path)),
                'protected' => $should_protect,
                'originally_protected' => $was_originally_protected, // Estado original para el estilo
                'sensitive' => $is_sensitive
            );
        } else {
            // Verificar si es archivo sensible o protegido
            $is_sensitive = isSensitiveFile($item_path, $item);
            $is_system = isSystemFile($item_path, $item);
            $is_protected = isProtectedPath($item_path);
            
            // Verificar si ES ORIGINAL DE DOLIBARR (sin considerar la opción)
            // Archivos críticos de Dolibarr
            $critical_dolibarr_files = array(
                'main.inc.php', 'master.inc.php', 'index.php', 'robots.txt', 'security.txt',
                'filefunc.inc.php', 'opcachepreload.php', 'favicon.ico'
            );
            $is_original_dolibarr_file = in_array($item, $critical_dolibarr_files);
            
            // Verificar si está en carpetas originales de Dolibarr
            $normalized_path_for_file_check = str_replace('\\', '/', strtolower($item_path));
            $is_in_original_dir = strpos($normalized_path_for_file_check, '/admin/') !== false ||
                                 strpos($normalized_path_for_file_check, '/core/') !== false ||
                                 strpos($normalized_path_for_file_check, '/includes/') !== false ||
                                 strpos($normalized_path_for_file_check, '/install/') !== false ||
                                 strpos($normalized_path_for_file_check, '/conf/') !== false;
            
            // Guardar si originalmente era protegido (para el estilo morado)
            $was_originally_protected_file = $is_original_dolibarr_file || $is_in_original_dir || $is_protected || $is_system;
            
            // Si está permitido trabajar con archivos protegidos, no marcar como protegido
            $config_val_file = isset($config['FILEMANAGER_ALLOW_PROTECTED_ACTIONS']) ? $config['FILEMANAGER_ALLOW_PROTECTED_ACTIONS'] : 0;
            $allow_protected = ($config_val_file == 1 || $config_val_file === '1' || $config_val_file === true || $config_val_file === 'true' || (int)$config_val_file > 0);
            
            $should_protect = false;
            if ($allow_protected) {
                // Solo proteger archivos críticos específicos
                $critical_files = array('main.inc.php', 'master.inc.php', 'filefunc.inc.php', 'opcachepreload.php');
                if (in_array($item, $critical_files)) {
                    $should_protect = true;
                }
            } else {
                // Comportamiento normal: proteger si es protegido o sistema
                $should_protect = $is_protected || $is_system;
            }
            
            // Mostrar todos los archivos, marcar los protegidos
            $files[] = array(
                'name' => $item,
                'path' => $item_path,
                'size' => filesize($item_path),
                'modified' => date('Y-m-d H:i:s', filemtime($item_path)),
                'extension' => strtolower(pathinfo($item, PATHINFO_EXTENSION)),
                'protected' => $should_protect,
                'originally_protected' => $was_originally_protected_file, // Estado original para el estilo
                'sensitive' => $is_sensitive
            );
        }
    }
}

// SIEMPRE contar elementos en la papelera (excluyendo archivos ocultos y metadata)
    $trash_path = __DIR__ . '/Papelera/';
    if (is_dir($trash_path)) {
        $items = scandir($trash_path);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            // Omitir archivos ocultos (como .metadata.json)
            if (substr($item, 0, 1) === '.') continue;
            
            // Omitir archivos de metadata (archivos que terminan en .metadata.json)
            if (substr($item, -14) === '.metadata.json') continue;
            
            $item_path = $trash_path . $item;
            // Contar archivos si es directorio (excluyendo archivos ocultos)
            $file_count = 0;
            if (is_dir($item_path)) {
                $items_in_trash = scandir($item_path);
                foreach ($items_in_trash as $item_in_trash) {
                    if ($item_in_trash !== '.' && $item_in_trash !== '..' && substr($item_in_trash, 0, 1) !== '.') {
                        $file_count++;
                    }
                }
            }
            
            $trash_files[] = array(
                'name' => $item,
                'path' => $item_path,
                'file_count' => is_dir($item_path) ? $file_count : 1,
                'modified' => date('Y-m-d H:i:s', filemtime($item_path)),
                'deleted_date' => date('Y-m-d H:i:s', filemtime($item_path))
            );
        }
    }

if ($active_tab === 'trash') {
    // Si estamos en la pestaña de papelera, limpiar y re-contar
    $trash_files = array();
    if (is_dir($trash_path)) {
        $items = scandir($trash_path);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            // Omitir archivos ocultos (como .metadata.json)
            if (substr($item, 0, 1) === '.') continue;
            
            // Omitir archivos de metadata (archivos que terminan en .metadata.json)
            if (substr($item, -14) === '.metadata.json') continue;
            
            $item_path = $trash_path . $item;
            // Contar archivos si es directorio (excluyendo archivos ocultos)
            $file_count = 0;
            if (is_dir($item_path)) {
                $items_in_trash = scandir($item_path);
                foreach ($items_in_trash as $item_in_trash) {
                    if ($item_in_trash !== '.' && $item_in_trash !== '..' && substr($item_in_trash, 0, 1) !== '.') {
                        $file_count++;
                    }
                }
            }
            
            $trash_files[] = array(
                'name' => $item,
                'path' => $item_path,
                'file_count' => is_dir($item_path) ? $file_count : 1,
                'modified' => date('Y-m-d H:i:s', filemtime($item_path)),
                'deleted_date' => date('Y-m-d H:i:s', filemtime($item_path))
            );
        }
    }
}

// Calcular totales para mostrar en las pestañas
// Si NO estamos en pestaña files, calcular desde items_in_current_path
if ($active_tab !== 'files') {
    $folders_count_temp = 0;
    $files_count_temp = 0;
    foreach ($items_in_current_path as $item_temp) {
        if ($item_temp['is_dir']) {
            $folders_count_temp++;
        } else {
            $files_count_temp++;
        }
    }
    $total_folders = $folders_count_temp;
    $total_files = $files_count_temp;
} else {
    $total_folders = count($folders);
    $total_files = count($files);
}
$total_items = $total_folders + $total_files;

// Iniciar página
llxHeader('', 'FileManager - ' . t('file_manager'));

// Incluir CSS de FileManager
print '<link rel="stylesheet" href="' . dol_buildpath('/custom/filemanager/css/filemanager.css', 1) . '">';

// Incluir SweetAlert
print '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';

// ========== MODAL DE ADVERTENCIA PARA PRIMERA VEZ ==========
print '<script type="text/javascript">' . "\n";
print '(function() {' . "\n";
print '    // Verificar si ya vio la advertencia (usando localStorage)' . "\n";
print '    var warningKey = "filemanager_warning_accepted";' . "\n";
print '    var hasAcceptedWarning = localStorage.getItem(warningKey);' . "\n";
print '    ' . "\n";
print '    if (!hasAcceptedWarning) {' . "\n";
print '        // Esperar a que SweetAlert esté cargado' . "\n";
print '        var checkSwal = setInterval(function() {' . "\n";
print '            if (typeof Swal !== "undefined") {' . "\n";
print '                clearInterval(checkSwal);' . "\n";
print '                ' . "\n";
print '                // Mostrar modal de advertencia' . "\n";
print '                Swal.fire({' . "\n";
print '                    title: "⚠️ ADVERTENCIA IMPORTANTE",' . "\n";
print '                    html: `' . "\n";
print '                        <div style="text-align: left; font-size: 15px; line-height: 1.8;">' . "\n";
print '                            <p style="color: #dc3545; font-weight: bold; margin-bottom: 15px; font-size: 16px;">' . "\n";
print '                                ⚠️ RIESGOS AL ELIMINAR ARCHIVOS DE DOLIBARR' . "\n";
print '                            </p>' . "\n";
print '                            <p style="margin-bottom: 12px;">' . "\n";
print '                                <strong>El FileManager le permite eliminar archivos y carpetas de Dolibarr.</strong>' . "\n";
print '                            </p>' . "\n";
print '                            <p style="margin-bottom: 12px; color: #dc3545;">' . "\n";
print '                                <strong>⚠️ ADVERTENCIA:</strong> Eliminar archivos incorrectos puede causar:' . "\n";
print '                            </p>' . "\n";
print '                            <ul style="margin-left: 20px; margin-bottom: 15px; color: #6c757d;">' . "\n";
print '                                <li>Pérdida permanente de datos</li>' . "\n";
print '                                <li>Corrupción de la base de datos</li>' . "\n";
print '                                <li>Inestabilidad del sistema</li>' . "\n";
print '                                <li>Pérdida de funcionalidades de Dolibarr</li>' . "\n";
print '                                <li>Necesidad de restaurar desde backup</li>' . "\n";
print '                            </ul>' . "\n";
print '                            <p style="margin-bottom: 12px; color: #495057;">' . "\n";
print '                                <strong>✅ RECOMENDACIONES:</strong>' . "\n";
print '                            </p>' . "\n";
print '                            <ul style="margin-left: 20px; margin-bottom: 15px; color: #6c757d;">' . "\n";
print '                                <li>Realice backups regulares antes de eliminar archivos</li>' . "\n";
print '                                <li>Verifique cuidadosamente qué archivos está eliminando</li>' . "\n";
print '                                <li>Evite eliminar archivos del sistema (carpetas: core, includes, conf, install)</li>' . "\n";
print '                                <li>Use la papelera de reciclaje para poder restaurar archivos</li>' . "\n";
print '                            </ul>' . "\n";
print '                            <div style="background: #fff3cd; border-left: 4px solid #ffc107; padding: 12px; margin-top: 15px; border-radius: 4px;">' . "\n";
print '                                <p style="margin: 0; color: #856404; font-weight: bold;">' . "\n";
print '                                    ⚠️ Usted es responsable de las acciones realizadas con este módulo.' . "\n";
print '                                </p>' . "\n";
print '                            </div>' . "\n";
print '                            <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #dee2e6;">' . "\n";
print '                                <label style="display: flex; align-items: center; cursor: pointer; font-size: 14px; color: #495057;">' . "\n";
print '                                    <input type="checkbox" id="dontShowAgain" style="width: 18px; height: 18px; margin-right: 10px; cursor: pointer;">' . "\n";
print '                                    <span>No volver a mostrar este mensaje</span>' . "\n";
print '                                </label>' . "\n";
print '                            </div>' . "\n";
print '                        </div>' . "\n";
print '                    `,' . "\n";
print '                    icon: "warning",' . "\n";
print '                    iconColor: "#dc3545",' . "\n";
print '                    showCancelButton: true,' . "\n";
print '                    confirmButtonText: "Entiendo los riesgos y deseo continuar",' . "\n";
print '                    cancelButtonText: "Cancelar y salir",' . "\n";
print '                    confirmButtonColor: "#dc3545",' . "\n";
print '                    cancelButtonColor: "#6c757d",' . "\n";
print '                    allowOutsideClick: false,' . "\n";
print '                    allowEscapeKey: false,' . "\n";
print '                    reverseButtons: true,' . "\n";
print '                    width: "700px",' . "\n";
print '                    customClass: {' . "\n";
print '                        popup: "swal2-popup-large"' . "\n";
print '                    },' . "\n";
print '                    didOpen: function() {' . "\n";
print '                        // Asegurar que el checkbox esté disponible' . "\n";
print '                        var checkbox = document.getElementById("dontShowAgain");' . "\n";
print '                        if (checkbox) {' . "\n";
print '                            checkbox.checked = false;' . "\n";
print '                        }' . "\n";
print '                    }' . "\n";
print '                }).then((result) => {' . "\n";
print '                    if (result.isConfirmed) {' . "\n";
print '                        // Verificar si marcó el checkbox' . "\n";
print '                        var checkbox = document.getElementById("dontShowAgain");' . "\n";
print '                        var dontShowAgain = checkbox ? checkbox.checked : false;' . "\n";
print '                        ' . "\n";
print '                        if (dontShowAgain) {' . "\n";
print '                            // Usuario aceptó y marcó "no volver a mostrar" - guardar en localStorage' . "\n";
print '                            localStorage.setItem(warningKey, "true");' . "\n";
print '                        }' . "\n";
print '                        // Si no marcó el checkbox, no guardar (mostrará el mensaje cada vez)' . "\n";
print '                    } else {' . "\n";
print '                        // Usuario canceló - redirigir a inicio de Dolibarr' . "\n";
print '                        window.location.href = "../../index.php";' . "\n";
print '                    }' . "\n";
print '                });' . "\n";
print '            }' . "\n";
print '        }, 100);' . "\n";
print '    }' . "\n";
print '})();' . "\n";
print '</script>' . "\n";

// Crear objeto JavaScript con traducciones
$current_lang = getFileManagerLanguage();
$translations = getFileManagerTranslations();
$js_translations = isset($translations[$current_lang]) ? $translations[$current_lang] : $translations['en'];
print '<script>' . "\n";
print 'var translations = ' . json_encode($js_translations) . ';' . "\n";
print 'function t(key, defaultVal) {' . "\n";
print '    return translations[key] || defaultVal || key;' . "\n";
print '}' . "\n";
print '</script>' . "\n";
// Estilos para el banner de pegar
print '<style>' . "\n";
print '@keyframes slideDown {' . "\n";
print '    from { transform: translateY(-100%); opacity: 0; }' . "\n";
print '    to { transform: translateY(0); opacity: 1; }' . "\n";
print '}' . "\n";
print '@keyframes popupSlideIn {' . "\n";
print '    from { transform: translate(-50%, -50%) scale(0.9); opacity: 0; }' . "\n";
print '    to { transform: translate(-50%, -50%) scale(1); opacity: 1; }' . "\n";
print '}' . "\n";
print '@keyframes fadeIn {' . "\n";
print '    from { opacity: 0; }' . "\n";
print '    to { opacity: 1; }' . "\n";
print '}' . "\n";
print '.paste-dialog-popup {' . "\n";
print '    border-radius: 12px !important;' . "\n";
print '    padding: 0 !important;' . "\n";
print '    overflow: hidden !important;' . "\n";
print '}' . "\n";
print '.paste-dialog-title {' . "\n";
print '    padding: 20px 24px !important;' . "\n";
print '    margin: 0 !important;' . "\n";
print '    border-bottom: 1px solid #e0e0e0 !important;' . "\n";
print '}' . "\n";
print '.paste-dialog-html {' . "\n";
print '    padding: 20px 24px !important;' . "\n";
print '    margin: 0 !important;' . "\n";
print '}' . "\n";
print '</style>' . "\n";

// JavaScript crítico ANTES del HTML
$token = newToken();
$dolibarr_root = $config['FILEMANAGER_ROOT_PATH'];
print '<script type="text/javascript">' . "\n";
print '// ========== PROTECCIÓN CONTRA ERRORES DE SCRIPTS EXTERNOS ==========' . "\n";
print '(function() {' . "\n";
print '    // Interceptar errores de scripts externos para evitar que afecten el FileManager' . "\n";
print '    var originalError = window.onerror;' . "\n";
print '    window.onerror = function(msg, url, line, col, error) {' . "\n";
print '        // Ignorar errores de scripts externos (content.js, adblockers, etc.)' . "\n";
print '        if (url && (url.indexOf("content.js") !== -1 || url.indexOf("adblock") !== -1 || url.indexOf("extension") !== -1)) {' . "\n";
print '            console.warn("Error de script externo ignorado:", msg);' . "\n";
print '            return true; // Prevenir que el error se propague' . "\n";
print '        }' . "\n";
print '        // Si hay un handler original, llamarlo' . "\n";
print '        if (originalError) {' . "\n";
print '            return originalError(msg, url, line, col, error);' . "\n";
print '        }' . "\n";
print '        return false;' . "\n";
print '    };' . "\n";
print '    ' . "\n";
print '    // Proteger appendChild contra elementos null' . "\n";
print '    var originalAppendChild = Node.prototype.appendChild;' . "\n";
print '    Node.prototype.appendChild = function(child) {' . "\n";
print '        if (!this || !child) {' . "\n";
print '            console.warn("appendChild llamado con elemento null/undefined - ignorado");' . "\n";
print '            return null;' . "\n";
print '        }' . "\n";
print '        try {' . "\n";
print '            return originalAppendChild.call(this, child);' . "\n";
print '        } catch (e) {' . "\n";
print '            // Ignorar errores de scripts externos' . "\n";
print '            if (e.message && (e.message.indexOf("content.js") !== -1 || e.message.indexOf("adblock") !== -1)) {' . "\n";
print '                console.warn("Error de appendChild ignorado:", e.message);' . "\n";
print '                return null;' . "\n";
print '            }' . "\n";
print '            throw e;' . "\n";
print '        }' . "\n";
print '    };' . "\n";
print '})();' . "\n";
print '' . "\n";
print 'var FILEMANAGER_TOKEN = "' . $token . '";' . "\n";
print 'var AVAILABLE_PATHS_URL = "' . dol_buildpath('/custom/filemanager/scripts/get_available_paths.php', 1) . '";' . "\n";
print '' . "\n";
print '// Portapapeles para cortar/copiar' . "\n";
print 'var clipboard = { items: [], action: null }; // action: "cut" o "copy"' . "\n";
print '' . "\n";
print '// Definir downloadFolder al inicio para que esté disponible globalmente' . "\n";
print 'function downloadFolder(path) {' . "\n";
print '    if (!path) {' . "\n";
print '        console.error("downloadFolder: path no definido");' . "\n";
print '        return;' . "\n";
print '    }' . "\n";
print '    var token = FILEMANAGER_TOKEN || "' . newToken() . '";' . "\n";
print '    var url = "action.php?action=download_folder&path=" + encodeURIComponent(path) + "&token=" + token;' . "\n";
print '    console.log("downloadFolder: Abriendo URL:", url);' . "\n";
print '    window.open(url, "_blank");' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function toggleContextMenu(button) {' . "\n";
print '    // Cerrar todos los demás menús' . "\n";
print '    var allMenus = document.querySelectorAll(".context-menu");' . "\n";
print '    allMenus.forEach(function(menu) {' . "\n";
print '        if (menu !== button.nextElementSibling) {' . "\n";
print '            menu.style.display = "none";' . "\n";
print '        }' . "\n";
print '    });' . "\n";
print '    // Toggle del menú actual' . "\n";
print '    var menu = button.nextElementSibling;' . "\n";
print '    if (menu) {' . "\n";
print '        menu.style.display = menu.style.display === "none" ? "block" : "none";' . "\n";
print '    }' . "\n";
print '    // Cerrar al hacer clic fuera' . "\n";
print '    setTimeout(function() {' . "\n";
print '        document.addEventListener("click", function closeMenu() {' . "\n";
print '            allMenus.forEach(function(m) { m.style.display = "none"; });' . "\n";
print '            document.removeEventListener("click", closeMenu);' . "\n";
print '        });' . "\n";
print '    }, 100);' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function closeAllMenus() {' . "\n";
print '    document.querySelectorAll(".context-menu").forEach(function(menu) {' . "\n";
print '        menu.style.display = "none";' . "\n";
print '    });' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function cutItem(path, type) {' . "\n";
print '    // Verificar si el elemento está protegido' . "\n";
print '    var item = document.querySelector("[data-path=\'" + path.replace(/\\\\/g, "/") + "\']");' . "\n";
print '    if (item) {' . "\n";
print '        var card = item.closest(".folder-item, .file-item");' . "\n";
print '        if (card && card.style.borderColor === "rgb(255, 193, 7)") { // Amarillo = protegido' . "\n";
print '            Swal.fire({ icon: "error", title: t("not_allowed"), text: t("cannot_cut_protected") });' . "\n";
print '            closeAllMenus();' . "\n";
print '            return;' . "\n";
print '        }' . "\n";
print '    }' . "\n";
print '    clipboard.items = [{ path: path, type: type }];' . "\n";
print '    clipboard.action = "cut";' . "\n";
print '    updatePasteButton();' . "\n";
print '    // Abrir directamente el diálogo de pegado completo' . "\n";
print '    closeAllMenus();' . "\n";
print '    setTimeout(function() { showPasteDialog(); }, 100);' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function copyItem(path, type) {' . "\n";
print '    // Verificar si el elemento está protegido' . "\n";
print '    var item = document.querySelector("[data-path=\'" + path.replace(/\\\\/g, "/") + "\']");' . "\n";
print '    if (item) {' . "\n";
print '        var card = item.closest(".folder-item, .file-item");' . "\n";
print '        if (card && card.style.borderColor === "rgb(255, 193, 7)") { // Amarillo = protegido' . "\n";
print '            Swal.fire({ icon: "error", title: t("not_allowed"), text: t("cannot_copy_protected") });' . "\n";
print '            closeAllMenus();' . "\n";
print '            return;' . "\n";
print '        }' . "\n";
print '    }' . "\n";
print '    clipboard.items = [{ path: path, type: type }];' . "\n";
print '    clipboard.action = "copy";' . "\n";
print '    updatePasteButton();' . "\n";
print '    // Abrir directamente el diálogo de pegado completo' . "\n";
print '    closeAllMenus();' . "\n";
print '    setTimeout(function() { showPasteDialog(); }, 100);' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function updatePasteButton() {' . "\n";
print '    var pasteBtn = document.getElementById("pasteButton");' . "\n";
print '    if (!pasteBtn) return;' . "\n";
print '    if (clipboard.items.length > 0) {' . "\n";
print '        var actionText = clipboard.action === "cut" ? "Cortar" : "Copiar";' . "\n";
print '        var itemsCount = clipboard.items.length;' . "\n";
print '        pasteBtn.innerHTML = clipboard.action === "cut" ? "✂️ Pegar (" + itemsCount + " elemento" + (itemsCount > 1 ? "s" : "") + ")" : "📋 Pegar (" + itemsCount + " elemento" + (itemsCount > 1 ? "s" : "") + ")";' . "\n";
print '        pasteBtn.style.display = "flex";' . "\n";
print '        // NO llamar updatePasteIndicator aquí - ya se maneja en copyItem/cutItem' . "\n";
print '        // Para múltiples elementos, actualizar barra masiva' . "\n";
print '        if (itemsCount > 1) {' . "\n";
print '            updateBulkPasteBar();' . "\n";
print '        }' . "\n";
print '    } else {' . "\n";
print '        pasteBtn.style.display = "none";' . "\n";
print '        hidePasteIndicator();' . "\n";
print '    }' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function updatePasteIndicator() {' . "\n";
print '    // Esta función ya no se usa para elementos individuales' . "\n";
print '    // Solo manejar múltiples elementos' . "\n";
print '    if (clipboard.items.length === 0) {' . "\n";
print '        hidePasteIndicator();' . "\n";
print '        return;' . "\n";
print '    }' . "\n";
print '    var itemsCount = clipboard.items.length;' . "\n";
print '    ' . "\n";
print '    // Solo manejar múltiples elementos' . "\n";
print '    if (itemsCount > 1) {' . "\n";
print '        updateBulkPasteBar();' . "\n";
print '    } else {' . "\n";
print '        // Para un solo elemento, no hacer nada aquí (ya se maneja en copyItem/cutItem)' . "\n";
print '        hideBulkPasteBar();' . "\n";
print '        hidePasteIndicator();' . "\n";
print '    }' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function updateSinglePasteIndicator() {' . "\n";
print '    var indicator = document.getElementById("singlePasteIndicator");' . "\n";
print '    if (!indicator) {' . "\n";
print '        // Crear popup flotante estándar' . "\n";
print '        indicator = document.createElement("div");' . "\n";
print '        indicator.id = "singlePasteIndicator";' . "\n";
print '        indicator.style.cssText = "position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); max-width: 520px; width: 90%; background: #ffffff; color: #333; padding: 0; border-radius: 12px; box-shadow: 0 10px 40px rgba(0,0,0,0.2); z-index: 10000; display: flex; flex-direction: column; animation: popupSlideIn 0.3s ease-out; overflow: hidden;";' . "\n";
print '        if (document.body) {
            document.body.appendChild(indicator);
        }' . "\n";
print '        ' . "\n";
print '        // Crear overlay oscuro detrás del popup' . "\n";
print '        var overlay = document.getElementById("pasteOverlay");' . "\n";
print '        if (!overlay) {' . "\n";
print '            overlay = document.createElement("div");' . "\n";
print '            overlay.id = "pasteOverlay";' . "\n";
print '            overlay.style.cssText = "position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 9999; backdrop-filter: blur(3px); animation: fadeIn 0.2s ease-out;";' . "\n";
print '            overlay.onclick = clearClipboard;' . "\n";
print '            document.body.insertBefore(overlay, indicator);' . "\n";
print '        }' . "\n";
print '        overlay.style.display = "block";' . "\n";
print '    }' . "\n";
print '    var actionText = clipboard.action === "cut" ? "Cortar" : "Copiar";' . "\n";
print '    var actionIcon = clipboard.action === "cut" ? "✂️" : "📋";' . "\n";
print '    var actionColor = clipboard.action === "cut" ? "#ff9800" : "#2196f3";' . "\n";
print '    var itemName = clipboard.items[0].path.split(/[/\\\\]/).pop();' . "\n";
print '    var currentPath = window.location.href.match(/[?&]path=([^&]*)/);' . "\n";
print '    currentPath = currentPath ? decodeURIComponent(currentPath[1]) : ' . json_encode($current_path) . ';' . "\n";
print '    if (!currentPath) currentPath = ' . json_encode($current_path) . ';' . "\n";
print '    indicator.innerHTML = \'<div style="background: linear-gradient(135deg, \' + actionColor + \' 0%, \' + (clipboard.action === "cut" ? "#f57c00" : "#1976d2") + \' 100%); color: white; padding: 20px 24px; display: flex; align-items: center; justify-content: space-between;"><div style="display: flex; align-items: center; gap: 12px;"><div style="font-size: 28px;">\' + actionIcon + \'</div><div><div style="font-weight: 600; font-size: 18px; margin-bottom: 2px;">Pegar elemento</div><div style="font-size: 13px; opacity: 0.9;">\' + itemName + \'</div></div></div><button onclick="clearClipboard()" style="background: rgba(255,255,255,0.2); color: white; border: none; padding: 6px; border-radius: 50%; cursor: pointer; font-size: 18px; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; transition: all 0.2s;" onmouseover="this.style.background=\\\'rgba(255,255,255,0.3)\\\'" onmouseout="this.style.background=\\\'rgba(255,255,255,0.2)\\\'">✖</button></div>\' +' . "\n";
print '               \'<div style="padding: 20px 24px;"><div style="margin-bottom: 16px;"><div style="font-size: 11px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; font-weight: 600;">RUTA DE DESTINO (desde raíz de Dolibarr)</div><div style="background: #f5f5f5; padding: 12px 14px; border-radius: 8px; font-size: 13px; font-family: monospace; word-break: break-all; color: #333; border: 1px solid #e0e0e0;">📁 \' + (function() { var dolibarrRoot = ' . json_encode($dolibarr_root) . '; if (currentPath && currentPath.indexOf(dolibarrRoot) === 0) { var relative = currentPath.substring(dolibarrRoot.length); relative = relative.replace(/^[\\\\/]+/, "") || "."; return relative === "." ? "Raíz de Dolibarr" : relative; } return currentPath; })() + \'</div><div style="font-size: 11px; color: #999; margin-top: 8px; padding: 8px; background: #f9f9f9; border-radius: 6px;">💡 Haz clic en "Pegar" para ver recomendaciones de dónde puedes pegar</div></div></div>\' +' . "\n";
print '               \'<div style="padding: 0 24px 20px 24px; display: flex; align-items: center; gap: 10px; border-top: 1px solid #e0e0e0; padding-top: 20px; margin-top: 0;"><button onclick="showPasteDialog()" style="flex: 1; background: \' + actionColor + \'; color: white; border: none; padding: 12px 20px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px; box-shadow: 0 2px 8px rgba(0,0,0,0.15); transition: all 0.2s;" onmouseover="this.style.transform=\\\'translateY(-1px)\\\'; this.style.boxShadow=\\\'0 4px 12px rgba(0,0,0,0.2)\\\'" onmouseout="this.style.transform=\\\'translateY(0)\\\'; this.style.boxShadow=\\\'0 2px 8px rgba(0,0,0,0.15)\\\'">\' + actionIcon + \' Pegar</button><button onclick="clearClipboard()" style="background: #f5f5f5; color: #666; border: 1px solid #e0e0e0; padding: 12px 20px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.2s;" onmouseover="this.style.background=\\\'#eeeeee\\\'; this.style.borderColor=\\\'#ccc\\\'" onmouseout="this.style.background=\\\'#f5f5f5\\\'; this.style.borderColor=\\\'#e0e0e0\\\'">Cancelar</button></div>\';' . "\n";
print '    indicator.style.display = "flex";' . "\n";
print '    ' . "\n";
print '    // Asegurar que el overlay esté visible' . "\n";
print '    var overlay = document.getElementById("pasteOverlay");' . "\n";
print '    if (overlay) overlay.style.display = "block";' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function updateBulkPasteBar() {' . "\n";
print '    var bulkBar = document.getElementById("bulkActionsBar");' . "\n";
print '    if (!bulkBar) return;' . "\n";
print '    ' . "\n";
print '    var actionText = clipboard.action === "cut" ? "Cortar" : "Copiar";' . "\n";
print '    var itemsCount = clipboard.items.length;' . "\n";
print '    var itemsList = clipboard.items.map(function(item) { return item.path.split(/[/\\\\]/).pop(); }).slice(0, 3).join(", ");' . "\n";
print '    if (clipboard.items.length > 3) itemsList += " y " + (clipboard.items.length - 3) + " más";' . "\n";
print '    var currentPath = window.location.href.match(/[?&]path=([^&]*)/);' . "\n";
print '    currentPath = currentPath ? decodeURIComponent(currentPath[1]) : ' . json_encode($current_path) . ';' . "\n";
print '    if (!currentPath) currentPath = ' . json_encode($current_path) . ';' . "\n";
print '    ' . "\n";
print '    // Agregar información de pegar a la barra existente' . "\n";
print '    var pasteInfo = document.getElementById("pasteInfoInBulkBar");' . "\n";
print '    if (!pasteInfo) {' . "\n";
print '        pasteInfo = document.createElement("div");' . "\n";
print '        pasteInfo.id = "pasteInfoInBulkBar";' . "\n";
print '        pasteInfo.style.cssText = "display: flex; align-items: center; gap: 10px; margin-right: 15px; padding: 8px 15px; background: rgba(255,255,255,0.15); border-radius: 20px;";' . "\n";
print '        var pasteBtn = document.createElement("button");' . "\n";
print '        pasteBtn.id = "bulkPasteBtn";' . "\n";
print '        pasteBtn.onclick = showPasteDialog;' . "\n";
print '        pasteBtn.style.cssText = "background: #28a745; color: white; border: none; padding: 8px 16px; border-radius: 15px; cursor: pointer; font-size: 12px; font-weight: bold; display: flex; align-items: center; gap: 5px;";' . "\n";
print '        if (bulkBar && pasteInfo) {
            bulkBar.appendChild(pasteInfo);
        }' . "\n";
print '        if (pasteInfo && pasteBtn) {
            pasteInfo.appendChild(pasteBtn);
        }' . "\n";
print '    }' . "\n";
print '    ' . "\n";
print '    var pasteBtn = document.getElementById("bulkPasteBtn");' . "\n";
print '    if (pasteBtn) {' . "\n";
print '        pasteBtn.innerHTML = (clipboard.action === "cut" ? "✂️" : "📋") + " Pegar (" + itemsCount + " elemento" + (itemsCount > 1 ? "s" : "") + ")";' . "\n";
print '    }' . "\n";
print '    ' . "\n";
print '    pasteInfo.innerHTML = \'<span style="font-size: 12px; margin-right: 10px;">\' + (clipboard.action === "cut" ? "✂️" : "📋") + \' \' + itemsCount + \' elemento\' + (itemsCount > 1 ? "s" : "") + \' para \' + actionText.toLowerCase() + \': \' + itemsList + \'</span><div style="background: rgba(255,255,255,0.2); padding: 6px 12px; border-radius: 15px; font-size: 11px; font-family: monospace; max-width: 300px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="\' + currentPath + \'">📁 \' + currentPath + \'</div>\';' . "\n";
print '    if (pasteBtn) pasteInfo.appendChild(pasteBtn);' . "\n";
print '    bulkBar.style.display = "flex";' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function hidePasteIndicator() {' . "\n";
print '    var indicator = document.getElementById("singlePasteIndicator");' . "\n";
print '    if (indicator) {' . "\n";
print '        indicator.style.display = "none";' . "\n";
print '    }' . "\n";
print '    var overlay = document.getElementById("pasteOverlay");' . "\n";
print '    if (overlay) {' . "\n";
print '        overlay.style.display = "none";' . "\n";
print '    }' . "\n";
print '    hideBulkPasteBar();' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function hideBulkPasteBar() {' . "\n";
print '    var pasteInfo = document.getElementById("pasteInfoInBulkBar");' . "\n";
print '    if (pasteInfo) pasteInfo.style.display = "none";' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function clearClipboard() {' . "\n";
print '    clipboard.items = [];' . "\n";
print '    clipboard.action = null;' . "\n";
print '    updatePasteButton();' . "\n";
print '    hidePasteIndicator();' . "\n";
print '}' . "\n";
print '' . "\n";
print '// Actualizar indicador cuando se carga la página o cambia la ruta' . "\n";
print 'document.addEventListener("DOMContentLoaded", function() {' . "\n";
print '    // Solo actualizar para múltiples elementos, no para individuales' . "\n";
print '    if (clipboard.items.length > 1) {' . "\n";
print '        setTimeout(updatePasteIndicator, 300);' . "\n";
print '    }' . "\n";
print '    // Monitorear cambios en la URL' . "\n";
print '    var lastUrl = window.location.href;' . "\n";
print '    setInterval(function() {' . "\n";
print '        if (window.location.href !== lastUrl && clipboard.items.length > 1) {' . "\n";
print '            lastUrl = window.location.href;' . "\n";
print '            setTimeout(updatePasteIndicator, 300);' . "\n";
print '        }' . "\n";
print '    }, 500);' . "\n";
print '});' . "\n";
print '' . "\n";
print 'function showClipboardStatus(message) {' . "\n";
print '    // Actualizar indicador' . "\n";
print '    updatePasteIndicator();' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function showPasteDialog() {' . "\n";
print '    if (clipboard.items.length === 0) return;' . "\n";
print '    var currentPath = ' . json_encode($current_path) . ';' . "\n";
print '    var dolibarrRoot = ' . json_encode($dolibarr_root) . ';' . "\n";
print '    ' . "\n";
print '    // Convertir ruta absoluta a relativa desde Dolibarr' . "\n";
print '    function getRelativePath(absolutePath) {' . "\n";
print '        if (!absolutePath || !dolibarrRoot) return absolutePath;' . "\n";
print '        if (absolutePath.indexOf(dolibarrRoot) === 0) {' . "\n";
print '            var relative = absolutePath.substring(dolibarrRoot.length);' . "\n";
print '            return relative.replace(/^[\\\\/]+/, "") || ".";' . "\n";
print '        }' . "\n";
print '        return absolutePath;' . "\n";
print '    }' . "\n";
print '    ' . "\n";
print '    var relativeCurrentPath = getRelativePath(currentPath);' . "\n";
print '    ' . "\n";
print '    // Rutas sugeridas comunes dentro de Dolibarr' . "\n";
print '    var suggestedPaths = [' . "\n";
print '        { label: "📁 Carpeta actual", path: relativeCurrentPath },' . "\n";
print '        { label: "📁 Raíz de Dolibarr", path: "." },' . "\n";
print '        { label: "📁 custom", path: "custom" },' . "\n";
print '        { label: "📁 custom/filemanager", path: "custom/filemanager" },' . "\n";
print '        { label: "📁 documents", path: "documents" },' . "\n";
print '        { label: "📁 htdocs", path: "htdocs" }' . "\n";
print '    ];' . "\n";
print '    ' . "\n";
print '    var actionText = clipboard.action === "cut" ? "Cortar" : "Copiar";' . "\n";
print '    var actionIcon = clipboard.action === "cut" ? "✂️" : "📋";' . "\n";
print '    var actionColor = clipboard.action === "cut" ? "#ff9800" : "#2196f3";' . "\n";
print '    var itemsCount = clipboard.items.length;' . "\n";
print '    var itemsList = clipboard.items.map(function(item) { return item.path.split(/[/\\\\]/).pop(); }).slice(0, 5).join(", ");' . "\n";
print '    if (clipboard.items.length > 5) itemsList += " y " + (clipboard.items.length - 5) + " más";' . "\n";
print '    ' . "\n";
print '    var suggestionsHtml = suggestedPaths.map(function(sug) {' . "\n";
print '        var pathEscaped = JSON.stringify(sug.path).slice(1, -1);' . "\n";
print '        var sq = String.fromCharCode(39);' . "\n";
print '        var btnHtml = "<button onclick=\\"document.getElementById(" + sq + "pastePathInput" + sq + ").value=" + sq + "" + pathEscaped + "" + sq + "; document.getElementById(" + sq + "pastePathInput" + sq + ").focus();\\" style=\\"width: 100%; text-align: left; background: #f5f5f5; border: 1px solid #e0e0e0; padding: 10px 12px; border-radius: 6px; margin-bottom: 8px; cursor: pointer; font-size: 13px; transition: all 0.2s;\\" onmouseover=\\"this.style.background=" + sq + "#e8e8e8" + sq + "; this.style.borderColor=" + sq + "#bbb" + sq + "\\" onmouseout=\\"this.style.background=" + sq + "#f5f5f5" + sq + "; this.style.borderColor=" + sq + "#e0e0e0" + sq + "\\">" + sug.label + " <span style=\\"color: #999; font-size: 11px;\\">(" + sug.path + ")</span></button>";' . "\n";
print '        return btnHtml;' . "\n";
print '    }).join("");' . "\n";
print '    ' . "\n";
print '    Swal.fire({' . "\n";
print '        title: \'<div style="display: flex; align-items: center; gap: 10px; color: \' + actionColor + \';"><span style="font-size: 28px;">\' + actionIcon + \'</span><span>Pegar \' + itemsCount + \' elemento\' + (itemsCount > 1 ? "s" : "") + \'</span></div>\',' . "\n";
print '        html: \'<div style="text-align: left; padding: 10px 0;"><div style="margin-bottom: 16px;"><div style="font-size: 11px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; font-weight: 600;">RUTA DE DESTINO (desde raíz de Dolibarr)</div><input type="text" id="pastePathInput" value="\' + JSON.stringify((relativeCurrentPath === "." || relativeCurrentPath === "" ? "." : relativeCurrentPath)).slice(1, -1) + \'" style="width: 100%; padding: 12px 14px; border: 1px solid #e0e0e0; border-radius: 8px; font-size: 13px; font-family: monospace; background: #fff; margin-bottom: 12px;" placeholder="Ejemplo: custom o . para raíz"><div style="font-size: 11px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; font-weight: 600; margin-top: 12px;">RUTAS DISPONIBLES (clic para seleccionar)</div><div class="paste-suggestions" style="max-height: 180px; overflow-y: auto;">\' + suggestionsHtml + \'</div></div><div><div style="font-size: 11px; color: #666; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; font-weight: 600;">ELEMENTOS A \' + actionText.toUpperCase() + \'</div><div style="background: #f5f5f5; padding: 12px 14px; border-radius: 8px; font-size: 13px; color: #333; border: 1px solid #e0e0e0; max-height: 100px; overflow-y: auto;">\' + itemsList + \'</div></div></div>\',
        didOpen: function() {
            // Cargar rutas disponibles dinámicamente después de abrir el modal
            fetch(AVAILABLE_PATHS_URL + "?t=" + Date.now())
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.success && data.paths) {
                        var availablePaths = data.paths;
                        // Agregar carpeta actual si no está en la lista
                        var hasCurrent = availablePaths.some(function(p) { return p.path === relativeCurrentPath; });
                        if (!hasCurrent && relativeCurrentPath !== "." && relativeCurrentPath !== "") {
                            availablePaths.unshift({ label: "📁 Carpeta actual", path: relativeCurrentPath });
                        }
                        
                        var newSuggestionsHtml = availablePaths.map(function(sug) {
                            var pathEscaped = JSON.stringify(sug.path).slice(1, -1);
                            var sq = String.fromCharCode(39);
                            var btnHtml = "<button onclick=\\"document.getElementById(" + sq + "pastePathInput" + sq + ").value=" + sq + "" + pathEscaped + "" + sq + "; document.getElementById(" + sq + "pastePathInput" + sq + ").focus();\\" style=\\"width: 100%; text-align: left; background: #f5f5f5; border: 1px solid #e0e0e0; padding: 10px 12px; border-radius: 6px; margin-bottom: 8px; cursor: pointer; font-size: 13px; transition: all 0.2s;\\" onmouseover=\\"this.style.background=" + sq + "#e8e8e8" + sq + "; this.style.borderColor=" + sq + "#bbb" + sq + "\\" onmouseout=\\"this.style.background=" + sq + "#f5f5f5" + sq + "; this.style.borderColor=" + sq + "#e0e0e0" + sq + "\\">" + sug.label + " <span style=\\"color: #999; font-size: 11px;\\">(" + sug.path + ")</span></button>";
                            return btnHtml;
                        }).join("");
                        
                        var suggestionsDiv = document.querySelector(".paste-suggestions");
                        if (suggestionsDiv) {
                            suggestionsDiv.innerHTML = newSuggestionsHtml;
                        }
                    }
                })
                .catch(function(err) {
                    console.error("Error cargando rutas disponibles:", err);
                });
        },' . "\n";
print '        icon: null,' . "\n";
print '        showCancelButton: true,' . "\n";
print '        confirmButtonColor: actionColor,' . "\n";
print '        cancelButtonColor: "#6c757d",' . "\n";
print '        confirmButtonText: actionIcon + " Pegar aquí",' . "\n";
print '        cancelButtonText: t("cancel"),' . "\n";
print '        customClass: {' . "\n";
print '            popup: "paste-dialog-popup",' . "\n";
print '            title: "paste-dialog-title",' . "\n";
print '            htmlContainer: "paste-dialog-html"' . "\n";
print '        },' . "\n";
print '        buttonsStyling: true,' . "\n";
print '        reverseButtons: false,' . "\n";
print '        width: "520px",' . "\n";
print '        preConfirm: function() {' . "\n";
print '            var relativePath = document.getElementById("pastePathInput").value.trim();' . "\n";
print '            if (!relativePath) {' . "\n";
print '                Swal.showValidationMessage("Debes especificar una ruta destino");' . "\n";
print '                return false;' . "\n";
print '            }' . "\n";
print '            // Convertir ruta relativa a absoluta' . "\n";
print '            var absolutePath = relativePath;' . "\n";
print '            if (dolibarrRoot && !relativePath.startsWith("/") && relativePath !== ".") {' . "\n";
print '                absolutePath = dolibarrRoot.replace(/[\\\\/]+$/, "") + "/" + relativePath.replace(/^[\\\\/]+/, "");' . "\n";
print '            } else if (relativePath === ".") {' . "\n";
print '                absolutePath = dolibarrRoot;' . "\n";
print '            }' . "\n";
print '            return absolutePath;' . "\n";
print '        }' . "\n";
print '    }).then(function(result) {' . "\n";
print '        if (result.isConfirmed) {' . "\n";
print '            pasteItems(result.value);' . "\n";
print '        }' . "\n";
print '    });' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function pasteItems(destPath) {' . "\n";
print '    if (clipboard.items.length === 0) return;' . "\n";
print '    Swal.fire({' . "\n";
print '        title: "Pegando...",' . "\n";
print '        text: "Por favor espera",' . "\n";
print '        allowOutsideClick: false,' . "\n";
print '        showConfirmButton: false,' . "\n";
print '        didOpen: function() { Swal.showLoading(); }' . "\n";
print '    });' . "\n";
print '    var formData = new FormData();' . "\n";
print '    formData.append("action", clipboard.action === "cut" ? "cut_paste" : "copy_paste");' . "\n";
print '    formData.append("dest_path", destPath);' . "\n";
print '    formData.append("token", FILEMANAGER_TOKEN);' . "\n";
print '    clipboard.items.forEach(function(item, index) {' . "\n";
print '        formData.append("items[" + index + "][path]", item.path);' . "\n";
print '        formData.append("items[" + index + "][type]", item.type);' . "\n";
print '    });' . "\n";
print '    fetch("action.php", {' . "\n";
print '        method: "POST",' . "\n";
print '        body: formData' . "\n";
print '    })' . "\n";
print '    .then(function(response) { return response.json(); })' . "\n";
print '    .then(function(data) {' . "\n";
print '        if (data.success) {' . "\n";
print '            Swal.fire({ icon: "success", title: "¡Pegado correctamente!", text: data.message, timer: 2000, showConfirmButton: false });' . "\n";
print '            clipboard.items = [];' . "\n";
print '            clipboard.action = null;' . "\n";
print '            updatePasteButton();' . "\n";
print '            setTimeout(function() { window.location.reload(); }, 2000);' . "\n";
print '        } else {' . "\n";
print '            Swal.fire({ icon: "error", title: "Error", text: data.message });' . "\n";
print '        }' . "\n";
print '    })' . "\n";
print '    .catch(function(error) {' . "\n";
print '        Swal.fire({ icon: "error", title: t("error"), text: t("error_paste") + error.message });' . "\n";
print '    });' . "\n";
print '}' . "\n";
print '' . "\n";
print '// Manejar selección múltiple' . "\n";
print 'function showBulkActionsMenu() {' . "\n";
print '    var checkboxes = document.querySelectorAll(".file-checkbox:checked");' . "\n";
print '    if (checkboxes.length === 0) {' . "\n";
print '        Swal.fire({ icon: "info", title: "Sin selección", text: "Selecciona al menos un archivo o carpeta" });' . "\n";
print '        return;' . "\n";
print '    }' . "\n";
print '    var items = [];' . "\n";
print '    checkboxes.forEach(function(cb) {' . "\n";
print '        items.push({ path: cb.dataset.path, type: cb.dataset.type });' . "\n";
print '    });' . "\n";
print '    Swal.fire({' . "\n";
print '        title: "Acciones múltiples",' . "\n";
print '        html: "<div style=\'text-align: left; padding: 10px;\'><p><strong>" + items.length + " elemento(s) seleccionado(s)</strong></p>" +' . "\n";
print '               "<button onclick=\'bulkCut()\' style=\'width: 100%; background: #ffc107; color: #000; border: none; padding: 10px; margin: 5px 0; border-radius: 5px; cursor: pointer;\'>✂️ Cortar</button>" +' . "\n";
print '               "<button onclick=\'bulkCopy()\' style=\'width: 100%; background: #17a2b8; color: white; border: none; padding: 10px; margin: 5px 0; border-radius: 5px; cursor: pointer;\'>📋 Copiar</button>" +' . "\n";
print '               "<button onclick=\'Swal.close()\' style=\'width: 100%; background: #6c757d; color: white; border: none; padding: 10px; margin: 5px 0; border-radius: 5px; cursor: pointer;\'>Cancelar</button></div>",' . "\n";
print '        showConfirmButton: false,' . "\n";
print '        showCancelButton: false' . "\n";
print '    });' . "\n";
print '    window.bulkItems = items;' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function bulkCut() {' . "\n";
print '    if (!window.bulkItems || window.bulkItems.length === 0) return;' . "\n";
print '    // Verificar que ningún elemento esté protegido' . "\n";
print '    var protectedItems = [];' . "\n";
print '    window.bulkItems.forEach(function(item) {' . "\n";
print '        var element = document.querySelector("[data-path=\'" + item.path.replace(/\\\\/g, "/") + "\']");' . "\n";
print '        if (element) {' . "\n";
print '            var card = element.closest(".folder-item, .file-item");' . "\n";
print '            if (card && card.style.borderColor === "rgb(255, 193, 7)") { // Amarillo = protegido' . "\n";
print '                protectedItems.push(item.path.split(/[/\\\\]/).pop());' . "\n";
print '            }' . "\n";
print '        }' . "\n";
print '    });' . "\n";
print '    if (protectedItems.length > 0) {' . "\n";
print '        Swal.fire({ icon: "error", title: t("not_allowed"), text: t("cannot_cut_protected_items") + protectedItems.join(", ") });' . "\n";
print '        return;' . "\n";
print '    }' . "\n";
print '    clipboard.items = window.bulkItems;' . "\n";
print '    clipboard.action = "cut";' . "\n";
print '    updatePasteButton();' . "\n";
print '    // Actualizar indicador (aparecerá en barra inferior para múltiples)' . "\n";
print '    setTimeout(function() { updatePasteIndicator(); }, 100);' . "\n";
print '    Swal.close();' . "\n";
print '    deselectAllFiles();' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function bulkCopy() {' . "\n";
print '    if (!window.bulkItems || window.bulkItems.length === 0) return;' . "\n";
print '    // Verificar que ningún elemento esté protegido' . "\n";
print '    var protectedItems = [];' . "\n";
print '    window.bulkItems.forEach(function(item) {' . "\n";
print '        var element = document.querySelector("[data-path=\'" + item.path.replace(/\\\\/g, "/") + "\']");' . "\n";
print '        if (element) {' . "\n";
print '            var card = element.closest(".folder-item, .file-item");' . "\n";
print '            if (card && card.style.borderColor === "rgb(255, 193, 7)") { // Amarillo = protegido' . "\n";
print '                protectedItems.push(item.path.split(/[/\\\\]/).pop());' . "\n";
print '            }' . "\n";
print '        }' . "\n";
print '    });' . "\n";
print '    if (protectedItems.length > 0) {' . "\n";
print '        Swal.fire({ icon: "error", title: t("not_allowed"), text: t("cannot_copy_protected_items") + protectedItems.join(", ") });' . "\n";
print '        return;' . "\n";
print '    }' . "\n";
print '    clipboard.items = window.bulkItems;' . "\n";
print '    clipboard.action = "copy";' . "\n";
print '    updatePasteButton();' . "\n";
print '    // Actualizar indicador (aparecerá en barra inferior para múltiples)' . "\n";
print '    setTimeout(function() { updatePasteIndicator(); }, 100);' . "\n";
print '    Swal.close();' . "\n";
print '    deselectAllFiles();' . "\n";
print '}' . "\n";
print '' . "\n";
print '// Detectar cambios en la configuración y recargar automáticamente' . "\n";
print '(function() {' . "\n";
print '    function checkForReload() {' . "\n";
print '        var reloadRequired = localStorage.getItem("filemanager_reload_required");' . "\n";
print '        var reloadTimestamp = localStorage.getItem("filemanager_reload_timestamp");' . "\n";
print '        ' . "\n";
print '        if (reloadRequired === "true" && reloadTimestamp) {' . "\n";
print '            var timestamp = parseInt(reloadTimestamp);' . "\n";
print '            var now = Date.now();' . "\n";
print '            // Solo recargar si el cambio fue en los últimos 30 segundos' . "\n";
print '            if (now - timestamp < 30000) {' . "\n";
print '                // Limpiar el flag' . "\n";
print '                localStorage.removeItem("filemanager_reload_required");' . "\n";
print '                localStorage.removeItem("filemanager_reload_timestamp");' . "\n";
print '                ' . "\n";
print '                // Recargar la página' . "\n";
print '                location.reload();' . "\n";
print '                return;' . "\n";
print '            }' . "\n";
print '        }' . "\n";
print '    }' . "\n";
print '    ' . "\n";
print '    // Verificar inmediatamente cuando se carga la página' . "\n";
print '    if (document.readyState === "loading") {' . "\n";
print '        document.addEventListener("DOMContentLoaded", checkForReload);' . "\n";
print '    } else {' . "\n";
print '        checkForReload();' . "\n";
print '    }' . "\n";
print '    ' . "\n";
print '    // También escuchar cambios en localStorage (entre pestañas)' . "\n";
print '    window.addEventListener("storage", function(e) {' . "\n";
print '        if (e.key === "filemanager_reload_required" && e.newValue === "true") {' . "\n";
print '            checkForReload();' . "\n";
print '        }' . "\n";
print '    });' . "\n";
print '})();' . "\n";
print '' . "\n";

// Funciones para acciones masivas - DEFINIR PRIMERO para que estén disponibles
print 'function updateBulkActions() {' . "\n";
print '    var checkboxes = document.querySelectorAll(".file-checkbox:checked");' . "\n";
print '    var count = checkboxes.length;' . "\n";
print '    var bulkBar = document.getElementById("bulkActionsBar");' . "\n";
print '    var countSpan = document.getElementById("bulkCount");' . "\n";
print '    ' . "\n";
print '    if (count > 0 && bulkBar && countSpan) {' . "\n";
print '        bulkBar.style.display = "flex";' . "\n";
print '        countSpan.textContent = count + " elemento" + (count > 1 ? "s" : "") + " seleccionado" + (count > 1 ? "s" : "");' . "\n";
print '        // Si hay elementos en el portapapeles, también mostrar info de pegar' . "\n";
print '        if (clipboard.items.length > 1) {' . "\n";
print '            updateBulkPasteBar();' . "\n";
print '        } else {' . "\n";
print '            hideBulkPasteBar();' . "\n";
print '        }' . "\n";
print '    } else if (bulkBar) {' . "\n";
print '        // Si no hay selección pero hay elementos copiados, mostrar solo info de pegar' . "\n";
print '        if (clipboard.items.length > 1) {' . "\n";
print '            bulkBar.style.display = "flex";' . "\n";
print '            if (countSpan) countSpan.style.display = "none";' . "\n";
print '            updateBulkPasteBar();' . "\n";
print '        } else {' . "\n";
print '            bulkBar.style.display = "none";' . "\n";
print '            hideBulkPasteBar();' . "\n";
print '        }' . "\n";
print '    } else if (clipboard.items.length > 1) {' . "\n";
print '        // Si no existe la barra pero hay elementos múltiples copiados, crearla' . "\n";
print '        updatePasteIndicator();' . "\n";
print '    }' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function updateTrashBulkActions() {' . "\n";
print '    var checkboxes = document.querySelectorAll(".trash-checkbox:checked");' . "\n";
print '    var count = checkboxes.length;' . "\n";
print '    var bulkBar = document.getElementById("trashBulkActionsBar");' . "\n";
print '    var countSpan = document.getElementById("trashBulkCount");' . "\n";
print '    ' . "\n";
print '    if (count > 0 && bulkBar && countSpan) {' . "\n";
print '        bulkBar.style.display = "flex";' . "\n";
print '        countSpan.textContent = count + " elemento" + (count > 1 ? "s" : "") + " seleccionado" + (count > 1 ? "s" : "");' . "\n";
print '    } else if (bulkBar) {' . "\n";
print '        bulkBar.style.display = "none";' . "\n";
print '    }' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function deselectAllFiles() {' . "\n";
print '    var checkboxes = document.querySelectorAll(".file-checkbox");' . "\n";
print '    for (var i = 0; i < checkboxes.length; i++) {' . "\n";
print '        checkboxes[i].checked = false;' . "\n";
print '    }' . "\n";
print '    updateBulkActions();' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function deselectAllTrash() {' . "\n";
print '    var checkboxes = document.querySelectorAll(".trash-checkbox");' . "\n";
print '    for (var i = 0; i < checkboxes.length; i++) {' . "\n";
print '        checkboxes[i].checked = false;' . "\n";
print '    }' . "\n";
print '    updateTrashBulkActions();' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function bulkMoveToTrash() {' . "\n";
print '    var checkboxes = document.querySelectorAll(".file-checkbox:checked");' . "\n";
print '    if (checkboxes.length === 0) {' . "\n";
print '        Swal.fire({icon: "info", title: "Info", text: "No hay elementos seleccionados"});' . "\n";
print '        return;' . "\n";
print '    }' . "\n";
print '    ' . "\n";
print '    Swal.fire({' . "\n";
print '        title: t("delete_items_question"),' . "\n";
print '        text: checkboxes.length + t("delete_items_text"),' . "\n";
print '        icon: "warning",' . "\n";
print '        showCancelButton: true,' . "\n";
print '        confirmButtonColor: "#dc3545",' . "\n";
print '        cancelButtonColor: "#6c757d",' . "\n";
print '        confirmButtonText: t("yes_move_to_trash"),' . "\n";
print '        cancelButtonText: t("cancel")' . "\n";
print '    }).then((result) => {' . "\n";
print '        if (result.isConfirmed) {' . "\n";
print '            var paths = [];' . "\n";
print '            for (var i = 0; i < checkboxes.length; i++) {' . "\n";
print '                var path = checkboxes[i].getAttribute("data-path");' . "\n";
print '                if (path) {' . "\n";
print '                    paths.push(path);' . "\n";
print '                }' . "\n";
print '            }' . "\n";
print '            ' . "\n";
print '            if (paths.length === 0) {' . "\n";
print '                Swal.fire({icon: "error", title: t("error"), text: t("no_valid_paths")});' . "\n";
print '                return;' . "\n";
print '            }' . "\n";
print '            ' . "\n";
print '            var formData = new FormData();' . "\n";
print '            formData.append("paths", JSON.stringify(paths));' . "\n";
print '            formData.append("token", FILEMANAGER_TOKEN);' . "\n";
print '            formData.append("action", "bulk_delete_with_module_check");' . "\n";
print '            ' . "\n";
print '            fetch("action.php", {' . "\n";
print '                method: "POST",' . "\n";
print '                body: formData' . "\n";
print '            })' . "\n";
print '            .then(response => response.json())' . "\n";
print '            .then(data => {' . "\n";
print '                if (data.success) {' . "\n";
print '                    Swal.fire("Éxito", data.message, "success").then(() => {' . "\n";
print '                        location.reload();' . "\n";
print '                    });' . "\n";
print '                } else {' . "\n";
print '                    Swal.fire("Error", data.message, "error");' . "\n";
print '                }' . "\n";
print '            })' . "\n";
print '            .catch(error => {' . "\n";
print '                Swal.fire(t("error"), t("error_processing_request") + error.message, "error");' . "\n";
print '            });' . "\n";
print '        }' . "\n";
print '    });' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function bulkRestoreSelected() {' . "\n";
print '    var checkboxes = document.querySelectorAll(".trash-checkbox:checked");' . "\n";
print '    if (checkboxes.length === 0) {' . "\n";
print '        Swal.fire({icon: "info", title: "Info", text: "No hay elementos seleccionados"});' . "\n";
print '        return;' . "\n";
print '    }' . "\n";
print '    ' . "\n";
print '    Swal.fire({' . "\n";
print '        title: "¿Restaurar archivos seleccionados?",' . "\n";
print '        text: "Se restaurarán " + checkboxes.length + " elemento(s) a su ubicación original",' . "\n";
print '        icon: "question",' . "\n";
print '        showCancelButton: true,' . "\n";
print '        confirmButtonColor: "#28a745",' . "\n";
print '        cancelButtonColor: "#6c757d",' . "\n";
print '        confirmButtonText: "Sí, restaurar",' . "\n";
print '        cancelButtonText: t("cancel")' . "\n";
print '    }).then((result) => {' . "\n";
print '        if (result.isConfirmed) {' . "\n";
print '            var paths = [];' . "\n";
print '            for (var i = 0; i < checkboxes.length; i++) {' . "\n";
print '                paths.push(checkboxes[i].getAttribute("data-path"));' . "\n";
print '            }' . "\n";
print '            ' . "\n";
print '            fetch("action.php?action=bulk_restore&token=" + FILEMANAGER_TOKEN, {' . "\n";
print '                method: "POST",' . "\n";
print '                headers: {"Content-Type": "application/x-www-form-urlencoded"},' . "\n";
print '                body: "paths=" + encodeURIComponent(JSON.stringify(paths))' . "\n";
print '            })' . "\n";
print '            .then(response => response.json())' . "\n";
print '            .then(data => {' . "\n";
print '                if (data.success) {' . "\n";
print '                    Swal.fire("Éxito", data.message, "success").then(() => {' . "\n";
print '                        location.reload();' . "\n";
print '                    });' . "\n";
print '                } else {' . "\n";
print '                    Swal.fire("Error", data.message, "error");' . "\n";
print '                }' . "\n";
print '            })' . "\n";
print '            .catch(error => {' . "\n";
print '                Swal.fire(t("error"), t("error_processing_request_simple"), "error");' . "\n";
print '            });' . "\n";
print '        }' . "\n";
print '    });' . "\n";
print '}' . "\n";
print '' . "\n";
// bulkPermanentDeleteSelected está definido más abajo
print '' . "\n";
print 'function permanentDelete(path, name) {' . "\n";
print '    Swal.fire({' . "\n";
print '        title: t("delete_definitely_question"),' . "\n";
print '        text: t("delete_definitely_text") + " \'" + name + "\'",' . "\n";
print '        icon: "warning",' . "\n";
print '        showCancelButton: true,' . "\n";
print '        confirmButtonColor: "#dc3545",' . "\n";
print '        cancelButtonColor: "#6c757d",' . "\n";
print '        confirmButtonText: t("yes_delete_definitely"),' . "\n";
print '        cancelButtonText: t("cancel")' . "\n";
print '    }).then((result) => {' . "\n";
print '        if (result.isConfirmed) {' . "\n";
print '            Swal.fire({' . "\n";
print '                title: "Eliminando...",' . "\n";
print '                text: "Eliminando definitivamente...",' . "\n";
print '                allowOutsideClick: false,' . "\n";
print '                showConfirmButton: false,' . "\n";
print '                willOpen: function() {' . "\n";
print '                    Swal.showLoading();' . "\n";
print '                }' . "\n";
print '            });' . "\n";
print '            ' . "\n";
print '            var formData = new FormData();' . "\n";
print '            formData.append("path", path);' . "\n";
print '            formData.append("token", FILEMANAGER_TOKEN);' . "\n";
print '            ' . "\n";
print '            fetch("action.php?action=permanent_delete", {' . "\n";
print '                method: "POST",' . "\n";
print '                body: formData' . "\n";
print '            })' . "\n";
print '            .then((response) => {' . "\n";
print '                return response.json();' . "\n";
print '            })' . "\n";
print '            .then((data) => {' . "\n";
print '                if (data.success) {' . "\n";
print '                    Swal.fire({' . "\n";
print '                        title: "¡Eliminado!",' . "\n";
print '                        text: data.message,' . "\n";
print '                        icon: "success",' . "\n";
print '                        confirmButtonText: "Aceptar"' . "\n";
print '                    }).then(() => {' . "\n";
print '                        location.reload();' . "\n";
print '                    });' . "\n";
print '                } else {' . "\n";
print '                    Swal.fire({' . "\n";
print '                        title: "Error",' . "\n";
print '                        text: data.message,' . "\n";
print '                        icon: "error",' . "\n";
print '                        confirmButtonText: "Aceptar"' . "\n";
print '                    });' . "\n";
print '                }' . "\n";
print '            })' . "\n";
print '            .catch((error) => {' . "\n";
print '                Swal.fire({' . "\n";
print '                    title: "Error",' . "\n";
print '                    text: t("error_deleting") + error.message,' . "\n";
print '                    icon: "error",' . "\n";
print '                    confirmButtonText: "Aceptar"' . "\n";
print '                });' . "\n";
print '            });' . "\n";
print '        }' . "\n";
print '    });' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function moveToTrash(path, type) {' . "\n";
print '    var name = path.split(/[\\\\/]/).pop();' . "\n";
print '    Swal.fire({' . "\n";
print '        title: t("move_to_trash_question"),' . "\n";
print '        text: t("move_to_trash_text") + " \'" + name + "\'",' . "\n";
print '        icon: "warning",' . "\n";
print '        showCancelButton: true,' . "\n";
print '        confirmButtonColor: "#dc3545",' . "\n";
print '        cancelButtonColor: "#6c757d",' . "\n";
print '        confirmButtonText: t("yes_move_to_trash"),' . "\n";
print '        cancelButtonText: t("cancel")' . "\n";
print '    }).then((result) => {' . "\n";
print '        if (result.isConfirmed) {' . "\n";
print '            Swal.fire({' . "\n";
print '                title: "Enviando...",' . "\n";
print '                text: "Enviando a papelera...",' . "\n";
print '                allowOutsideClick: false,' . "\n";
print '                showConfirmButton: false,' . "\n";
print '                willOpen: function() {' . "\n";
print '                    Swal.showLoading();' . "\n";
print '                }' . "\n";
print '            });' . "\n";
print '            ' . "\n";
print '            var formData = new FormData();' . "\n";
print '            formData.append("path", path);' . "\n";
print '            formData.append("type", type);' . "\n";
print '            formData.append("token", FILEMANAGER_TOKEN);' . "\n";
print '            ' . "\n";
print '            fetch("action.php?action=move_to_trash", {' . "\n";
print '                method: "POST",' . "\n";
print '                body: formData' . "\n";
print '            })' . "\n";
print '            .then((response) => {' . "\n";
print '                return response.json();' . "\n";
print '            })' . "\n";
print '            .then((data) => {' . "\n";
print '                if (data.success) {' . "\n";
print '                    Swal.fire({' . "\n";
print '                        title: "¡Enviado!",' . "\n";
print '                        text: data.message,' . "\n";
print '                        icon: "success",' . "\n";
print '                        confirmButtonText: "Aceptar"' . "\n";
print '                    }).then(() => {' . "\n";
print '                        location.reload();' . "\n";
print '                    });' . "\n";
print '                } else {' . "\n";
print '                    Swal.fire({' . "\n";
print '                        title: "Error",' . "\n";
print '                        text: data.message,' . "\n";
print '                        icon: "error",' . "\n";
print '                        confirmButtonText: "Aceptar"' . "\n";
print '                    });' . "\n";
print '                }' . "\n";
print '            })' . "\n";
print '            .catch((error) => {' . "\n";
print '                Swal.fire({' . "\n";
print '                    title: "Error",' . "\n";
print '                    text: "Error al enviar a papelera: " + error.message,' . "\n";
print '                    icon: "error",' . "\n";
print '                    confirmButtonText: "Aceptar"' . "\n";
print '                });' . "\n";
print '            });' . "\n";
print '        }' . "\n";
print '    });' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function restoreFromTrash(path, name) {' . "\n";
print '    Swal.fire({' . "\n";
print '        title: "¿Restaurar?",' . "\n";
print '        text: "¿Estás seguro de que quieres restaurar \'" + name + "\'?",' . "\n";
print '        icon: "question",' . "\n";
print '        showCancelButton: true,' . "\n";
print '        confirmButtonColor: "#28a745",' . "\n";
print '        cancelButtonColor: "#6c757d",' . "\n";
print '        confirmButtonText: "Sí, restaurar",' . "\n";
print '        cancelButtonText: t("cancel")' . "\n";
print '    }).then((result) => {' . "\n";
print '        if (result.isConfirmed) {' . "\n";
print '            Swal.fire({' . "\n";
print '                title: "Restaurando...",' . "\n";
print '                text: "Restaurando archivo...",' . "\n";
print '                allowOutsideClick: false,' . "\n";
print '                showConfirmButton: false,' . "\n";
print '                willOpen: function() {' . "\n";
print '                    Swal.showLoading();' . "\n";
print '                }' . "\n";
print '            });' . "\n";
print '            ' . "\n";
print '            var formData = new FormData();' . "\n";
print '            formData.append("path", path);' . "\n";
print '            formData.append("token", FILEMANAGER_TOKEN);' . "\n";
print '            ' . "\n";
print '            fetch("action.php?action=restore_from_trash", {' . "\n";
print '                method: "POST",' . "\n";
print '                body: formData' . "\n";
print '            })' . "\n";
print '            .then((response) => {' . "\n";
print '                return response.json();' . "\n";
print '            })' . "\n";
print '            .then((data) => {' . "\n";
print '                if (data.success) {' . "\n";
print '                    Swal.fire({' . "\n";
print '                        title: "¡Restaurado!",' . "\n";
print '                        text: data.message,' . "\n";
print '                        icon: "success",' . "\n";
print '                        confirmButtonText: "Aceptar"' . "\n";
print '                    }).then(() => {' . "\n";
print '                        location.reload();' . "\n";
print '                    });' . "\n";
print '                } else {' . "\n";
print '                    Swal.fire({' . "\n";
print '                        title: "Error",' . "\n";
print '                        text: data.message,' . "\n";
print '                        icon: "error",' . "\n";
print '                        confirmButtonText: "Aceptar"' . "\n";
print '                    });' . "\n";
print '                }' . "\n";
print '            })' . "\n";
print '            .catch((error) => {' . "\n";
print '                Swal.fire({' . "\n";
print '                    title: "Error",' . "\n";
print '                    text: "Error al restaurar: " + error.message,' . "\n";
print '                    icon: "error",' . "\n";
print '                    confirmButtonText: "Aceptar"' . "\n";
print '                });' . "\n";
print '            });' . "\n";
print '        }' . "\n";
print '    });' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function navigateToPath(path) {' . "\n";
print '    // Actualizar indicador después de navegar' . "\n";
print '    if (clipboard.items.length > 0) {' . "\n";
print '        setTimeout(function() {' . "\n";
print '            updatePasteIndicator();' . "\n";
print '        }, 300);' . "\n";
print '    }' . "\n";
print '    window.location.href = "?tab=files&path=" + encodeURIComponent(path);' . "\n";
print '}' . "\n";
print 'function openFile(path) {' . "\n";
print '    var fileName = path.split(/[\\\\/]/).pop();' . "\n";
print '    var fileExtension = fileName.split(".").pop().toLowerCase();' . "\n";
print '    ' . "\n";
print '    // Llamar directamente a showFilePreview después de abrir el modal' . "\n";
print '    Swal.fire({' . "\n";
print '        title: fileName,' . "\n";
print '        html: \'<div id="filePreview" style="max-height: 80vh; overflow-y: auto; min-height: 400px;"></div>\',' . "\n";
print '        width: "1200px", customClass: { popup: "swal2-custom-popup" },' . "\n";
print '        showConfirmButton: true, confirmButtonText: "Cerrar",' . "\n";
print '        showCancelButton: true, cancelButtonText: "Descargar",' . "\n";
print '        cancelButtonColor: "#007bff", confirmButtonColor: "#6c757d",' . "\n";
print '        didOpen: function() {' . "\n";
print '            loadFilePreview(path, fileExtension);' . "\n";
print '        }' . "\n";
print '    }).then(function(result) {' . "\n";
print '        if (result.dismiss === Swal.DismissReason.cancel) {' . "\n";
print '            window.open("action.php?action=download&path=" + encodeURIComponent(path) + "&token=" + FILEMANAGER_TOKEN, "_blank");' . "\n";
print '        }' . "\n";
print '    });' . "\n";
print '}' . "\n";
print 'function loadFilePreview(path, extension) {' . "\n";
print '    var previewDiv = document.getElementById("filePreview");' . "\n";
print '    previewDiv.innerHTML = \'<div style="text-align: center; padding: 40px;"><div class="spinner-border"></div><p>Cargando...</p></div>\';' . "\n";
print '    ' . "\n";
print '    // Imágenes' . "\n";
print '    if (["jpg", "jpeg", "png", "gif", "bmp", "webp", "svg"].includes(extension)) {' . "\n";
print '        previewDiv.innerHTML = \'<div style="text-align: center;"><img src="action.php?action=preview&token=\' + FILEMANAGER_TOKEN + \'&path=\' + encodeURIComponent(path) + \'" style="max-width: 100%; max-height: 70vh; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);" onerror="this.parentElement.innerHTML=\\\'<div style=\\\\\\\'color: #dc3545; padding: 20px;\\\\\\\'>❌ Error al cargar la imagen</div>\\\'"></div>\';' . "\n";
print '    }' . "\n";
print '    // PDF' . "\n";
print '    else if (["pdf"].includes(extension)) {' . "\n";
print '        previewDiv.innerHTML = \'<div style="text-align: center;"><iframe src="action.php?action=preview&token=\' + FILEMANAGER_TOKEN + \'&path=\' + encodeURIComponent(path) + \'" style="width: 100%; height: 70vh; border: 2px solid #dee2e6; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);" onerror="this.parentElement.innerHTML=\\\'<div style=\\\\\\\'color: #dc3545; padding: 20px;\\\\\\\'>❌ Error al cargar el PDF</div>\\\'"></iframe></div>\';' . "\n";
print '    }' . "\n";
print '    // Archivos de código y texto' . "\n";
print '    else if (["php", "js", "css", "txt", "log", "json", "xml", "html", "htm", "sql", "md", "yml", "yaml", "sh", "bash"].includes(extension)) {' . "\n";
print '        fetch("action.php?action=preview&token=" + FILEMANAGER_TOKEN + "&path=" + encodeURIComponent(path))' . "\n";
print '        .then(function(response) { return response.text(); })' . "\n";
print '        .then(function(content) {' . "\n";
print '            previewDiv.innerHTML = \'<pre style="background: #1e1e1e; color: #d4d4d4; padding: 20px; overflow-x: auto; font-family: monospace; font-size: 13px; border-radius: 4px;">\' + escapeHtml(content) + \'</pre>\';' . "\n";
print '        })' . "\n";
print '        .catch(function(error) {' . "\n";
print '            previewDiv.innerHTML = \'<div style="color: #dc3545; padding: 20px;">Error: \' + error.message + \'</div>\';' . "\n";
print '        });' . "\n";
print '    }' . "\n";
print '    // Otros tipos' . "\n";
print '    else {' . "\n";
print '        previewDiv.innerHTML = \'<div style="text-align: center; padding: 60px;"><div style="font-size: 80px; margin-bottom: 20px;">📄</div><h4>Preview no disponible</h4><p style="color: #6c757d;">No se puede mostrar preview para archivos .\' + extension.toUpperCase() + \'</p></div>\';' . "\n";
print '    }' . "\n";
print '    function escapeHtml(text) { var map = { "&": "&amp;", "<": "&lt;", ">": "&gt;", \'"\': "&quot;", "\'": "&#039;" }; return text.replace(/[&<>"\']/g, function(m) { return map[m]; }); }' . "\n";
print '}' . "\n";
print '</script>' . "\n";

// Header principal - Estilo Dolibarr
print '<div style="background: #f8f9fa; border: 1px solid #dee2e6; padding: 8px 12px; margin-bottom: 15px;">';
print '<div style="display: flex; align-items: center; justify-content: space-between;">';
print '<div style="display: flex; align-items: center; gap: 8px;">';
print '<span style="font-size: 18px;">📁</span>';
print '<h1 style="margin: 0; font-size: 16px; font-weight: bold; color: #495057;">' . t('file_manager') . '</h1>';
print '</div>';
print '<div style="display: flex; align-items: center; gap: 10px;">';
print '<input type="text" id="searchInput" placeholder="' . t('search') . '" style="padding: 4px 8px; border: 1px solid #ced4da; border-radius: 3px; width: 200px; font-size: 12px;" onkeyup="filterItems()">';
print '<button onclick="clearSearch()" style="background: #6c757d; color: white; border: none; padding: 4px 8px; border-radius: 3px; cursor: pointer; font-size: 12px;">✕</button>';
print '<a href="admin/setup.php" style="background: #007bff; color: white; text-decoration: none; padding: 4px 12px; border-radius: 3px; font-size: 12px; font-weight: bold;">⚙️ ' . t('config') . '</a>';
print '</div>';
print '</div>';
print '</div>';

// === SECCIÓN 1: RUTA ACTUAL (Breadcrumb Navegable) ===
print '<div style="background: white; border: 3px solid #ffc107; padding: 20px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);">';
print '<div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">';
print '<span style="color: #495057; font-weight: bold; font-size: 14px; padding-right: 15px; border-right: 2px solid #ffc107;">📍 ' . t('location', 'Location') . ':</span>';

// Botón Inicio (siempre apunta a la raíz de Dolibarr)
$dolibarr_root = $config['FILEMANAGER_ROOT_PATH'];
print '<a href="?tab=files&path=' . urlencode($dolibarr_root) . '" style="background: #ffc107; color: #000; text-decoration: none; padding: 10px 18px; border-radius: 20px; font-size: 13px; font-weight: bold; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 8px rgba(255, 193, 7, 0.3); transition: all 0.3s;" onmouseover="this.style.transform=\'translateY(-2px)\'; this.style.boxShadow=\'0 4px 12px rgba(255,193,7,0.4)\'; this.style.background=\'#ffb300\'" onmouseout="this.style.transform=\'translateY(0)\'; this.style.boxShadow=\'0 2px 8px rgba(255,193,7,0.3)\'; this.style.background=\'#ffc107\'">🏠 ' . t('home') . '</a>';

// Generar breadcrumb dinámico solo para rutas dentro de Dolibarr
$relative_path = str_replace($dolibarr_root, '', $current_path);
$relative_path = ltrim($relative_path, DIRECTORY_SEPARATOR);

// Navegación dinámica (breadcrumb)
$breadcrumb_parts = explode(DIRECTORY_SEPARATOR, $relative_path);
$breadcrumb_html = '';
$current_breadcrumb_path = '';

// Filtrar elementos vacíos
$breadcrumb_parts = array_filter($breadcrumb_parts, function($part) {
    return !empty($part);
});

// Reindexar el array
$breadcrumb_parts = array_values($breadcrumb_parts);

foreach ($breadcrumb_parts as $index => $part) {
    $current_breadcrumb_path .= ($current_breadcrumb_path ? DIRECTORY_SEPARATOR : '') . $part;
    $full_breadcrumb_path = $dolibarr_root . DIRECTORY_SEPARATOR . $current_breadcrumb_path;
    
    // Validar que la ruta del breadcrumb esté dentro de Dolibarr
    if (!isPathSafeFM($full_breadcrumb_path)) {
        continue;
    }
    
    // Separador
    if ($index > 0) {
        $breadcrumb_html .= '<span style="color: #6c757d; margin: 0 8px; font-size: 16px;">▶</span>';
    }
    
    if ($index === count($breadcrumb_parts) - 1) {
        // Último elemento (directorio actual) - destacado
        $breadcrumb_html .= '<span style="color: #495057; font-weight: bold; padding: 8px 16px; background: #fff3cd; border-radius: 15px; font-size: 13px; border: 2px solid #ffc107;">📁 ' . htmlspecialchars($part) . '</span>';
    } else {
        // Elementos anteriores - son clickeables (NAVEGABLES DINÁMICAMENTE)
        $breadcrumb_html .= '<a href="?tab=files&path=' . urlencode($full_breadcrumb_path) . '" style="color: #007bff; text-decoration: none; padding: 8px 16px; background: #f8f9fa; border-radius: 15px; font-size: 13px; transition: all 0.3s ease; border: 1px solid #dee2e6; display: inline-block;" onmouseover="this.style.background=\'#007bff\'; this.style.color=\'white\'; this.style.borderColor=\'#007bff\'; this.style.transform=\'translateY(-2px)\'" onmouseout="this.style.background=\'#f8f9fa\'; this.style.color=\'#007bff\'; this.style.borderColor=\'#dee2e6\'; this.style.transform=\'translateY(0)\'">📁 ' . htmlspecialchars($part) . '</a>';
    }
}

// Mostrar breadcrumb
print $breadcrumb_html;
print '</div>';
print '</div>';

// === SECCIÓN ÚNICA: PESTAÑAS, FILTROS Y BOTONES EN LA MISMA FILA ===
print '<div style="display: flex; align-items: center; gap: 20px; margin-bottom: 20px; padding: 15px; background: #fff; border: 2px solid #dee2e6; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); flex-wrap: wrap;">';
// Pestañas (izquierda)
print '<div style="flex: 0 0 auto;">';
print '<div style="display: flex; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; overflow: hidden; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
print '<a href="?tab=files&path=' . urlencode($current_path) . '" style="padding: 15px 25px; background: ' . ($active_tab === 'files' ? '#007bff' : '#f8f9fa') . '; color: ' . ($active_tab === 'files' ? 'white' : '#495057') . '; text-decoration: none; font-size: 14px; font-weight: bold; border-right: 1px solid #dee2e6; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease;">📁 ' . t('files') . ' (' . $total_items . ')</a>';
print '<a href="?tab=trash" style="padding: 15px 25px; background: ' . ($active_tab === 'trash' ? '#007bff' : '#f8f9fa') . '; color: ' . ($active_tab === 'trash' ? 'white' : '#495057') . '; text-decoration: none; font-size: 14px; font-weight: bold; display: flex; align-items: center; gap: 8px; transition: all 0.3s ease;">🗑️ ' . t('trash') . ' (' . count($trash_files) . ')</a>';
print '</div>';
print '</div>';

// Filtros (centro)
print '<div style="flex: 1; display: flex; gap: 15px; align-items: center; justify-content: center; flex-wrap: wrap;">';
print '<div style="display: flex; align-items: center; gap: 8px;">';
print '<label style="font-weight: bold; color: #495057; font-size: 13px;">' . t('sort_by') . '</label>';
print '<select id="sortFilter" style="padding: 6px 12px; border: 1px solid #ced4da; border-radius: 4px; background: white; font-size: 13px;" onchange="sortItems()">';
print '<option value="name">' . t('name') . ' (A-Z)</option>';
print '<option value="name_desc">' . t('name') . ' (Z-A)</option>';
print '<option value="date">' . t('date') . ' (' . t('recent', 'Recent') . ')</option>';
print '<option value="date_old">' . t('date') . ' (' . t('old', 'Old') . ')</option>';
print '<option value="size">' . t('size') . ' (' . t('large', 'Large') . ')</option>';
print '<option value="size_small">' . t('size_small') . '</option>';
print '</select>';
print '</div>';
print '<div style="display: flex; align-items: center; gap: 8px;">';
print '<label style="font-weight: bold; color: #495057; font-size: 13px;">' . t('show') . '</label>';
print '<select id="showFilter" style="padding: 6px 12px; border: 1px solid #ced4da; border-radius: 4px; background: white; font-size: 13px;" onchange="filterItems()">';
print '<option value="all">' . t('show_all') . '</option>';
print '<option value="folders">' . t('show_folders_only') . '</option>';
print '<option value="files">' . t('show_files_only') . '</option>';
print '<option value="protected">' . t('show_protected') . '</option>';
print '</select>';
print '</div>';
print '</div>';

// Botones de acción (derecha)
print '<div id="actionButtonsContainer" style="flex: 0 0 auto; display: flex; gap: 8px; flex-wrap: wrap;">';
print '<button id="selectAllBtn" onclick="toggleSelectAll()" class="butAction" style="background: #6c757d;">☑️ ' . t('select_all') . '</button>';
print '<button onclick="refreshPage()" class="butAction">🔄 ' . t('refresh') . '</button>';
print '<button onclick="createFolder()" class="butAction" style="background: #28a745;">➕ ' . t('new_folder') . '</button>';
print '<button onclick="uploadFile()" class="butAction" style="background: #007bff;">📤 ' . t('upload') . '</button>';
print '<button id="pasteButton" onclick="showPasteDialog()" style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 15px; cursor: pointer; font-size: 13px; font-weight: bold; display: none; align-items: center; gap: 8px;">📋 ' . t('paste') . '</button>';
print '</div>';
print '</div>';

// Contenido principal
if ($active_tab === 'files') {
    // Mostrar carpetas
    if (!empty($folders)) {
        print '<div class="file-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(min(250px, 100%), 1fr)); gap: 20px; margin-bottom: 30px;">';
        
        foreach ($folders as $folder) {
            // Determinar color del borde y fondo (estilo especial para archivos originales de Dolibarr cuando está permitido)
            $config_value_folder = isset($config['FILEMANAGER_ALLOW_PROTECTED_ACTIONS']) ? $config['FILEMANAGER_ALLOW_PROTECTED_ACTIONS'] : 0;
            $allow_protected_check = ($config_value_folder == 1 || $config_value_folder === '1' || $config_value_folder === true || $config_value_folder === 'true' || (int)$config_value_folder > 0);
            
            // Verificar si originalmente era protegido (estado antes de aplicar la opción)
            $was_originally_protected = isset($folder['originally_protected']) ? $folder['originally_protected'] : false;
            
            $is_really_protected_folder = $folder['protected'] && !$allow_protected_check;
            
            // Si está permitido trabajar con protegidos y esta carpeta originalmente era protegida (es original de Dolibarr)
            $is_original_dolibarr = $allow_protected_check && $was_originally_protected;
            
            if ($is_really_protected_folder) {
                // Protegido realmente (amarillo)
                $border_color = '#ffc107';
                $bg_color = 'white';
            } elseif ($is_original_dolibarr) {
                // Original de Dolibarr (morado pastel con borde morado oscuro)
                $border_color = '#6f42c1'; // Morado oscuro
                $bg_color = '#e9d5ff'; // Morado pastel claro
            } else {
                // Normal (verde)
                $border_color = '#28a745';
                $bg_color = 'white';
            }
            
            $icon = '📁';
            
            print '<div class="folder-item" data-path="' . htmlspecialchars($folder['path']) . '" style="border: 2px solid ' . $border_color . '; border-radius: 12px; padding: 20px; background: ' . $bg_color . '; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 6px rgba(0,0,0,0.1); position: relative;" onclick="navigateToPath(\'' . addslashes($folder['path']) . '\')" onmouseover="this.style.transform=\'translateY(-4px)\'; this.style.boxShadow=\'0 8px 15px rgba(0,0,0,0.2)\'; this.style.borderColor=\'#007bff\'" onmouseout="this.style.transform=\'translateY(0)\'; this.style.boxShadow=\'0 4px 6px rgba(0,0,0,0.1)\'; this.style.borderColor=\'' . $border_color . '\'">';
            
            // Checkbox
            print '<input type="checkbox" class="file-checkbox" data-path="' . htmlspecialchars($folder['path']) . '" data-type="folder" style="position: absolute; top: 15px; right: 15px; transform: scale(1.2);" onclick="event.stopPropagation(); updateBulkActions()">';
            
            // Etiqueta protegido (solo si no está permitido trabajar con protegidos)
            // Verificar si la opción está activa - SIMPLIFICADO
            $config_value = isset($config['FILEMANAGER_ALLOW_PROTECTED_ACTIONS']) ? $config['FILEMANAGER_ALLOW_PROTECTED_ACTIONS'] : 0;
            $allow_protected_active = ($config_value == 1 || $config_value === '1' || $config_value === true || $config_value === 'true' || (int)$config_value > 0);
            
            $is_really_protected_for_display = $folder['protected'] && !$allow_protected_active;
            
            if ($is_really_protected_for_display) {
                print '<div style="position: absolute; top: 15px; left: 15px; background: #ffc107; color: #000; padding: 4px 8px; border-radius: 12px; font-size: 10px; font-weight: bold; display: flex; align-items: center; gap: 4px;">';
                print '🔒 ' . t('protected');
                print '</div>';
            }
            
            print '<div style="text-align: center; margin-top: 20px;">';
            print '<div style="font-size: 48px; margin-bottom: 15px;">' . $icon . '</div>';
            print '<div style="font-weight: bold; margin-bottom: 8px; word-break: break-word; font-size: 16px; color: #333;" class="folder-name" data-path="' . htmlspecialchars($folder['path']) . '">' . htmlspecialchars($folder['name']) . '</div>';
            print '<div style="font-size: 12px; color: #6c757d; margin-bottom: 5px;">' . t('folder') . ' (' . $folder['file_count'] . ' ' . t('files') . ')</div>';
            print '<div style="font-size: 11px; color: #999;">' . $folder['modified'] . '</div>';
            
            // Botones para carpetas (mostrar siempre si está permitido trabajar con protegidos)
            $config_value_buttons = isset($config['FILEMANAGER_ALLOW_PROTECTED_ACTIONS']) ? $config['FILEMANAGER_ALLOW_PROTECTED_ACTIONS'] : 0;
            $allow_protected_for_buttons = ($config_value_buttons == 1 || $config_value_buttons === '1' || $config_value_buttons === true || $config_value_buttons === 'true' || (int)$config_value_buttons > 0);
            
            $show_buttons = !$folder['protected'] || $allow_protected_for_buttons;
            if ($show_buttons) {
                // Menú contextual (3 puntos) - abajo a la derecha
                // Solo mostrar cortar/copiar si NO está protegido
                $can_cut_copy = !$folder['protected'];
                print '<div style="position: absolute; bottom: 10px; right: 10px;">';
                print '<div class="context-menu-wrapper" style="position: relative;">';
                print '<button class="context-menu-trigger" onclick="event.stopPropagation(); toggleContextMenu(this)" style="background: #6c757d; color: white; border: none; padding: 6px 10px; border-radius: 50%; cursor: pointer; font-size: 14px; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">⋯</button>';
                print '<div class="context-menu" style="display: none; position: absolute; bottom: 40px; right: 0; background: white; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1000; min-width: 180px; padding: 8px 0;">';
                if ($can_cut_copy) {
                    print '<button onclick="event.stopPropagation(); cutItem(\'' . addslashes($folder['path']) . '\', \'folder\')" class="context-menu-item" style="width: 100%; background: none; border: none; padding: 10px 16px; text-align: left; cursor: pointer; font-size: 13px; display: flex; align-items: center; gap: 8px;" onmouseover="this.style.backgroundColor=\'#f0f0f0\'" onmouseout="this.style.backgroundColor=\'transparent\'">✂️ ' . t('cut') . '</button>';
                    print '<button onclick="event.stopPropagation(); copyItem(\'' . addslashes($folder['path']) . '\', \'folder\')" class="context-menu-item" style="width: 100%; background: none; border: none; padding: 10px 16px; text-align: left; cursor: pointer; font-size: 13px; display: flex; align-items: center; gap: 8px;" onmouseover="this.style.backgroundColor=\'#f0f0f0\'" onmouseout="this.style.backgroundColor=\'transparent\'">📋 ' . t('copy') . '</button>';
                }
                print '<button onclick="event.stopPropagation(); downloadFolder(\'' . addslashes($folder['path']) . '\')" class="context-menu-item" style="width: 100%; background: none; border: none; padding: 10px 16px; text-align: left; cursor: pointer; font-size: 13px; display: flex; align-items: center; gap: 8px;" onmouseover="this.style.backgroundColor=\'#f0f0f0\'" onmouseout="this.style.backgroundColor=\'transparent\'">📦 ' . t('download_zip') . '</button>';
                print '</div>';
                print '</div>';
                print '</div>';
                
                // Botón de papelera - abajo a la izquierda
                print '<div style="position: absolute; bottom: 10px; left: 10px;">';
                print '<button onclick="event.stopPropagation(); moveToTrash(\'' . addslashes($folder['path']) . '\', \'folder\')" style="background: #dc3545; color: white; border: none; padding: 8px 12px; border-radius: 15px; cursor: pointer; display: flex; align-items: center; justify-content: center; width: 40px; height: 40px;" title="' . t('move_to_trash') . '"><i class="fas fa-trash" style="color: white; font-size: 16px;"></i></button>';
                print '</div>';
            }
            print '</div>';
            print '</div>';
        }
        
        print '</div>';
    }
    
    // Mostrar archivos
    if (!empty($files)) {
        print '<div class="file-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(min(250px, 100%), 1fr)); gap: 20px;">';
        
        foreach ($files as $file) {
            // Determinar color del borde y fondo (estilo especial para archivos nativos de Dolibarr cuando está permitido)
            $config_value_file_border = isset($config['FILEMANAGER_ALLOW_PROTECTED_ACTIONS']) ? $config['FILEMANAGER_ALLOW_PROTECTED_ACTIONS'] : 0;
            $allow_protected_for_file = ($config_value_file_border == 1 || $config_value_file_border === '1' || $config_value_file_border === true || $config_value_file_border === 'true' || (int)$config_value_file_border > 0);
            
            // Verificar si originalmente era protegido (estado antes de aplicar la opción)
            $was_originally_protected_file = isset($file['originally_protected']) ? $file['originally_protected'] : false;
            
            $is_really_protected = $file['protected'] && !$allow_protected_for_file;
            
            // Si está permitido trabajar con protegidos y este archivo originalmente era protegido (es nativo de Dolibarr)
            $is_original_dolibarr_file = $allow_protected_for_file && $was_originally_protected_file;
            
            if ($is_really_protected) {
                // Protegido realmente (amarillo)
                $border_color = '#ffc107';
                $bg_color = 'white';
            } elseif ($is_original_dolibarr_file) {
                // Nativo de Dolibarr (morado pastel con borde morado oscuro)
                $border_color = '#6f42c1'; // Morado oscuro
                $bg_color = '#e9d5ff'; // Morado pastel claro
            } else {
                // Normal (verde)
                $border_color = '#28a745';
                $bg_color = 'white';
            }
            
            $icon = getFileIcon($file['extension']);
            // Solo mostrar icono de candado si NO está permitido trabajar con protegidos
            if ($is_really_protected) $icon = '🔒' . $icon;
            
            print '<div class="file-item" data-path="' . htmlspecialchars($file['path']) . '" style="border: 2px solid ' . $border_color . '; border-radius: 12px; padding: 20px; background: ' . $bg_color . '; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 6px rgba(0,0,0,0.1); position: relative;" onclick="openFile(\'' . addslashes($file['path']) . '\')" onmouseover="this.style.transform=\'translateY(-4px)\'; this.style.boxShadow=\'0 8px 15px rgba(0,0,0,0.2)\'" onmouseout="this.style.transform=\'translateY(0)\'; this.style.boxShadow=\'0 4px 6px rgba(0,0,0,0.1)\'">';
            
            // Checkbox para archivos
            print '<input type="checkbox" class="file-checkbox" data-path="' . htmlspecialchars($file['path']) . '" data-type="file" style="position: absolute; top: 15px; right: 15px; transform: scale(1.2);" onclick="event.stopPropagation(); updateBulkActions()">';
            
            // Etiqueta protegido (solo si no está permitido trabajar con protegidos)
            // Re-determinar para archivos
            $config_value_file_label = isset($config['FILEMANAGER_ALLOW_PROTECTED_ACTIONS']) ? $config['FILEMANAGER_ALLOW_PROTECTED_ACTIONS'] : 0;
            $allow_protected_for_file_label = ($config_value_file_label == 1 || $config_value_file_label === '1' || $config_value_file_label === true || $config_value_file_label === 'true' || (int)$config_value_file_label > 0);
            
            $is_really_protected_file = $file['protected'] && !$allow_protected_for_file_label;
            
            if ($is_really_protected_file) {
                print '<div style="position: absolute; top: 15px; left: 15px; background: #ffc107; color: #000; padding: 4px 8px; border-radius: 12px; font-size: 10px; font-weight: bold; display: flex; align-items: center; gap: 4px;">';
                print '🔒 ' . t('protected');
                print '</div>';
            }
            
            print '<div style="text-align: center; margin-top: 20px;">';
            print '<div style="font-size: 48px; margin-bottom: 15px;">' . $icon . '</div>';
            print '<div style="font-weight: bold; margin-bottom: 8px; word-break: break-word; font-size: 16px; color: #333;" class="file-name" data-path="' . htmlspecialchars($file['path']) . '">' . htmlspecialchars($file['name']) . '</div>';
            print '<div style="font-size: 12px; color: #6c757d; margin-bottom: 5px;">' . formatBytes($file['size']) . '</div>';
            print '<div style="font-size: 11px; color: #999;">' . $file['modified'] . '</div>';
            
            // Botones para archivos (mostrar siempre si está permitido trabajar con protegidos)
            $config_value_file_btn = isset($config['FILEMANAGER_ALLOW_PROTECTED_ACTIONS']) ? $config['FILEMANAGER_ALLOW_PROTECTED_ACTIONS'] : 0;
            $allow_protected_for_file_buttons = ($config_value_file_btn == 1 || $config_value_file_btn === '1' || $config_value_file_btn === true || $config_value_file_btn === 'true' || (int)$config_value_file_btn > 0);
            
            $show_file_buttons = !$file['protected'] || $allow_protected_for_file_buttons;
            if ($show_file_buttons) {
                // Menú contextual (3 puntos) - abajo a la derecha
                // Solo mostrar cortar/copiar si NO está protegido
                $can_cut_copy_file = !$file['protected'];
                print '<div style="position: absolute; bottom: 10px; right: 10px;">';
                print '<div class="context-menu-wrapper" style="position: relative;">';
                print '<button class="context-menu-trigger" onclick="event.stopPropagation(); toggleContextMenu(this)" style="background: #6c757d; color: white; border: none; padding: 6px 10px; border-radius: 50%; cursor: pointer; font-size: 14px; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center;">⋯</button>';
                print '<div class="context-menu" style="display: none; position: absolute; bottom: 40px; right: 0; background: white; border: 1px solid #ddd; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 1000; min-width: 180px; padding: 8px 0;">';
                if ($can_cut_copy_file) {
                    print '<button onclick="event.stopPropagation(); cutItem(\'' . addslashes($file['path']) . '\', \'file\')" class="context-menu-item" style="width: 100%; background: none; border: none; padding: 10px 16px; text-align: left; cursor: pointer; font-size: 13px; display: flex; align-items: center; gap: 8px;" onmouseover="this.style.backgroundColor=\'#f0f0f0\'" onmouseout="this.style.backgroundColor=\'transparent\'">✂️ ' . t('cut') . '</button>';
                    print '<button onclick="event.stopPropagation(); copyItem(\'' . addslashes($file['path']) . '\', \'file\')" class="context-menu-item" style="width: 100%; background: none; border: none; padding: 10px 16px; text-align: left; cursor: pointer; font-size: 13px; display: flex; align-items: center; gap: 8px;" onmouseover="this.style.backgroundColor=\'#f0f0f0\'" onmouseout="this.style.backgroundColor=\'transparent\'">📋 ' . t('copy') . '</button>';
                }
                print '</div>';
                print '</div>';
                print '</div>';
                
                // Botón de papelera - abajo a la izquierda
                print '<div style="position: absolute; bottom: 10px; left: 10px;">';
                print '<button onclick="event.stopPropagation(); moveToTrash(\'' . addslashes($file['path']) . '\', \'file\')" style="background: #dc3545; color: white; border: none; padding: 8px 12px; border-radius: 15px; cursor: pointer; display: flex; align-items: center; justify-content: center; width: 40px; height: 40px;" title="' . t('move_to_trash') . '"><i class="fas fa-trash" style="color: white; font-size: 16px;"></i></button>';
                print '</div>';
            }
            print '</div>';
            print '</div>';
        }
        
        print '</div>';
    }
    
    if (empty($folders) && empty($files)) {
        print '<div style="text-align: center; padding: 60px; color: #6c757d;">';
        print '<div style="font-size: 64px; margin-bottom: 20px;">📁</div>';
        print '<h3 style="margin-bottom: 10px;">Esta carpeta está vacía</h3>';
        print '<p>Usa los botones de arriba para crear carpetas o subir archivos</p>';
        print '</div>';
    }
    
    // Botón flotante para acciones masivas en sección Archivos
    print '<div id="bulkActionsBar" style="position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: #007bff; color: white; padding: 12px 24px; border-radius: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); display: none; z-index: 1000; transition: all 0.3s ease;">';
    print '<div style="display: flex; align-items: center; gap: 15px;">';
    print '<span id="bulkCount" style="font-weight: bold; font-size: 14px;">0 ' . t('items_selected') . '</span>';
    print '<button onclick="showBulkActionsMenu()" style="background: #6c757d; color: white; border: none; padding: 8px 16px; border-radius: 15px; cursor: pointer; font-size: 12px; font-weight: bold; display: flex; align-items: center; gap: 5px;">⋯ ' . t('more_options') . '</button>';
    print '<button onclick="bulkMoveToTrash()" style="background: #dc3545; color: white; border: none; padding: 8px 12px; border-radius: 15px; cursor: pointer; display: flex; align-items: center; justify-content: center; width: 40px; height: 40px;" title="' . t('move_to_trash') . '"><i class="fas fa-trash" style="color: white; font-size: 16px;"></i></button>';
    print '<button onclick="deselectAllFiles()" style="background: #6c757d; color: white; border: none; padding: 8px 16px; border-radius: 15px; cursor: pointer; font-size: 12px; font-weight: bold;">✖️ ' . strtoupper(t('cancel')) . '</button>';
    print '</div>';
    print '</div>';
    
} elseif ($active_tab === 'trash') {
    if (!empty($trash_files)) {
        // Botón para eliminar todo
        print '<div style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin-bottom: 20px; border: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center;">';
        print '<div>';
        print '<h4 style="margin: 0; color: #495057; font-size: 16px;">🗑️ ' . t('trash') . ' (' . count($trash_files) . ' ' . t('elements') . ')</h4>';
        print '<p style="margin: 5px 0 0 0; color: #6c757d; font-size: 12px;">' . t('deleted_items_can_restore') . '</p>';
        print '</div>';
        print '<div style="display: flex; gap: 10px;">';
        print '<button onclick="selectAllTrash()" style="background: #6c757d; color: white; border: none; padding: 8px 16px; border-radius: 15px; cursor: pointer; font-size: 12px; font-weight: bold; display: flex; align-items: center; gap: 5px;">☑️ ' . strtoupper(t('select_all')) . '</button>';
        print '<button onclick="bulkPermanentDelete()" style="background: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 15px; cursor: pointer; font-size: 12px; font-weight: bold; display: flex; align-items: center; gap: 5px;">🗑️ ' . t('delete_selected') . '</button>';
        print '<button onclick="emptyTrash()" style="background: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 15px; cursor: pointer; font-size: 12px; font-weight: bold; display: flex; align-items: center; gap: 5px;">🗑️ ' . strtoupper(t('empty_trash')) . '</button>';
        print '</div>';
        print '</div>';
        
        print '<div class="file-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(min(250px, 100%), 1fr)); gap: 20px;">';
        
        foreach ($trash_files as $file) {
            $icon = is_dir($file['path']) ? '📁' : '📄';
            
            print '<div class="trash-item" style="border: 2px solid #dc3545; border-radius: 12px; padding: 20px; background: white; cursor: pointer; transition: all 0.3s ease; box-shadow: 0 4px 6px rgba(0,0,0,0.1); position: relative;" onmouseover="this.style.transform=\'translateY(-4px)\'; this.style.boxShadow=\'0 8px 15px rgba(0,0,0,0.2)\'" onmouseout="this.style.transform=\'translateY(0)\'; this.style.boxShadow=\'0 4px 6px rgba(0,0,0,0.1)\'">';
            
            // Checkbox
            print '<input type="checkbox" class="trash-checkbox" data-path="' . htmlspecialchars($file['path']) . '" data-type="' . (is_dir($file['path']) ? 'folder' : 'file') . '" style="position: absolute; top: 15px; right: 15px; transform: scale(1.2);" onclick="event.stopPropagation(); updateTrashBulkActions()">';
            
            print '<div style="text-align: center;">';
            print '<div style="font-size: 48px; margin-bottom: 15px;">' . $icon . '</div>';
            print '<div style="font-weight: bold; margin-bottom: 8px; word-break: break-word; font-size: 16px; color: #333;" class="trash-name" data-path="' . htmlspecialchars($file['path']) . '">' . htmlspecialchars($file['name']) . '</div>';
            print '<div style="font-size: 12px; color: #6c757d; margin-bottom: 5px;">' . $file['file_count'] . ' ' . t('files') . '</div>';
            print '<div style="font-size: 11px; color: #999;">' . t('deleted') . ' ' . $file['deleted_date'] . '</div>';
            
            // Botones de acción
            print '<div style="display: flex; gap: 8px; justify-content: center; margin-top: 15px;">';
            print '<button onclick="event.stopPropagation(); restoreFromTrash(\'' . addslashes($file['path']) . '\', \'' . addslashes($file['name']) . '\')" style="background: #28a745; color: white; border: none; padding: 6px 12px; border-radius: 15px; cursor: pointer; font-size: 10px; font-weight: bold; display: flex; align-items: center; gap: 4px;">🔄 ' . strtoupper(t('restore')) . '</button>';
            print '<button onclick="event.stopPropagation(); permanentDelete(\'' . addslashes($file['path']) . '\', \'' . addslashes($file['name']) . '\')" style="background: #dc3545; color: white; border: none; padding: 6px 12px; border-radius: 15px; cursor: pointer; font-size: 10px; font-weight: bold; display: flex; align-items: center; gap: 4px;">🗑️ ' . t('permanently_delete') . '</button>';
            print '</div>';
            
            print '</div>';
            print '</div>';
        }
        
        print '</div>';
    } else {
        print '<div style="text-align: center; padding: 60px; color: #6c757d;">';
        print '<div style="font-size: 64px; margin-bottom: 20px;">🗑️</div>';
        print '<h3 style="margin-bottom: 10px;">' . t('trash_empty') . '</h3>';
        print '<p>' . t('deleted_files_will_appear') . '</p>';
        print '</div>';
    }
    
    // Botón flotante para acciones masivas en sección Papelera
    print '<div id="trashBulkActionsBar" style="position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: #dc3545; color: white; padding: 12px 24px; border-radius: 25px; box-shadow: 0 4px 12px rgba(0,0,0,0.3); display: none; z-index: 1000; transition: all 0.3s ease;">';
    print '<div style="display: flex; align-items: center; gap: 15px;">';
    print '<span id="trashBulkCount" style="font-weight: bold; font-size: 14px;">0 ' . t('items_selected') . '</span>';
    print '<button onclick="bulkRestoreSelected()" style="background: #28a745; color: white; border: none; padding: 8px 16px; border-radius: 15px; cursor: pointer; font-size: 12px; font-weight: bold; display: flex; align-items: center; gap: 5px;">↩️ ' . strtoupper(t('restore_selected_files')) . '</button>';
    print '<button onclick="bulkPermanentDeleteSelected()" style="background: #8b0000; color: white; border: none; padding: 8px 16px; border-radius: 15px; cursor: pointer; font-size: 12px; font-weight: bold; display: flex; align-items: center; gap: 5px;">🗑️ ' . t('permanently_delete') . '</button>';
    print '<button onclick="deselectAllTrash()" style="background: #6c757d; color: white; border: none; padding: 8px 16px; border-radius: 15px; cursor: pointer; font-size: 12px; font-weight: bold;">✖️ ' . strtoupper(t('cancel')) . '</button>';
    print '</div>';
    print '</div>';
}

// JavaScript adicional (después del contenido)
print '<script type="text/javascript">' . "\n";

print 'function showFilePreview(path, extension) {' . "\n";
print '    var previewDiv = document.getElementById("filePreview");' . "\n";
print '    ' . "\n";
print '    // Mostrar loading' . "\n";
print '    previewDiv.innerHTML = \'<div style="text-align: center; padding: 40px;"><div class="spinner-border" role="status"><span class="sr-only">Cargando...</span></div><p style="margin-top: 20px;">Cargando preview...</p></div>\';' . "\n";
print '    ' . "\n";
print '    // Imágenes' . "\n";
print '    if (["jpg", "jpeg", "png", "gif", "bmp", "webp", "svg"].includes(extension)) {' . "\n";
print '        previewDiv.innerHTML = \'<div style="text-align: center;"><img src="action.php?action=preview&token=\' + FILEMANAGER_TOKEN + \'&path=\' + encodeURIComponent(path) + \'" style="max-width: 100%; max-height: 70vh; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);" onerror="this.parentElement.innerHTML=\\\'<div style=\\\\\\\'color: #dc3545; padding: 20px;\\\\\\\'>❌ Error al cargar la imagen</div>\\\'"></div>\';' . "\n";
print '    } ' . "\n";
print '    // PDF' . "\n";
print '    else if (["pdf"].includes(extension)) {' . "\n";
print '        previewDiv.innerHTML = \'<div style="text-align: center;"><iframe src="action.php?action=preview&token=\' + FILEMANAGER_TOKEN + \'&path=\' + encodeURIComponent(path) + \'" style="width: 100%; height: 70vh; border: 2px solid #dee2e6; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15);" onerror="this.parentElement.innerHTML=\\\'<div style=\\\\\\\'color: #dc3545; padding: 20px;\\\\\\\'>❌ Error al cargar el PDF</div>\\\'"></iframe></div>\';' . "\n";
print '    } ' . "\n";
print '    // Archivos de código (sintaxis coloreada)' . "\n";
print '    else if (["php", "js", "jsx", "ts", "tsx", "css", "scss", "sass", "less", "html", "htm", "xml", "json", "yaml", "yml", "sql", "sh", "bash", "py", "rb", "java", "c", "cpp", "h", "hpp"].includes(extension)) {' . "\n";
print '        fetch("action.php?action=preview&token=" + FILEMANAGER_TOKEN + "&path=" + encodeURIComponent(path))' . "\n";
print '        .then(function(response) {' . "\n";
print '            return response.text();' . "\n";
print '        })' . "\n";
print '                .then(function(content) {' . "\n";
print '            var codeLang = extension;' . "\n";
print '            if (extension === "htm") codeLang = "html";' . "\n";
print '            if (extension === "yml") codeLang = "yaml";' . "\n";
print '            var codeLangUpper = codeLang.toUpperCase();' . "\n";
print '            previewDiv.innerHTML = \'<div style="position: relative;"><div style="background: #f1f3f5; padding: 8px 12px; border-radius: 8px 8px 0 0; font-size: 12px; color: #495057; font-weight: 600; border-bottom: 2px solid #dee2e6;">📝 Código (\' + codeLangUpper + \')</div><pre style="background: #1e1e1e; color: #d4d4d4; padding: 20px; margin: 0; border-radius: 0 0 8px 8px; overflow-x: auto; font-family: \\\'Consolas\\\', \\\'Monaco\\\', \\\'Courier New\\\', monospace; font-size: 14px; line-height: 1.6; white-space: pre; max-height: 70vh; overflow-y: auto;"><code id="codeContent">\' + escapeHtml(content) + \'</code></pre></div>\';' . "\n";
print '            // Intentar aplicar syntax highlighting si está disponible' . "\n";
print '            if (typeof Prism !== "undefined") {' . "\n";
print '                Prism.highlightElement(document.getElementById("codeContent"));' . "\n";
print '            }' . "\n";
print '        })' . "\n";
print '        .catch(function(error) {' . "\n";
print '            previewDiv.innerHTML = \'<div style="color: #dc3545; padding: 20px; text-align: center;">❌ Error al cargar el archivo: \' + error.message + \'</div>\';' . "\n";
print '        });' . "\n";
print '    } ' . "\n";
print '    // Archivos de texto plano' . "\n";
print '    else if (["txt", "log", "md", "markdown", "readme", "cfg", "ini", "conf", "config"].includes(extension)) {' . "\n";
print '        fetch("action.php?action=preview&token=" + FILEMANAGER_TOKEN + "&path=" + encodeURIComponent(path))' . "\n";
print '        .then(function(response) {' . "\n";
print '            return response.text();' . "\n";
print '        })' . "\n";
print '        .then(function(content) {' . "\n";
print '            var isMarkdown = extension === "md" || extension === "markdown" || extension === "readme";' . "\n";
print '            if (isMarkdown && typeof marked !== "undefined") {' . "\n";
print '                previewDiv.innerHTML = \'<div style="padding: 20px; background: #fff; border-radius: 8px; max-height: 70vh; overflow-y: auto;">\' + marked.parse(content) + \'</div>\';' . "\n";
print '            } else {' . "\n";
print '                previewDiv.innerHTML = \'<div style="background: #f8f9fa; padding: 20px; border-radius: 8px; overflow-x: auto; font-family: \\\'Courier New\\\', monospace; font-size: 14px; line-height: 1.6; white-space: pre-wrap; word-wrap: break-word; max-height: 70vh; overflow-y: auto; border: 1px solid #dee2e6;">\' + escapeHtml(content) + \'</div>\';' . "\n";
print '            }' . "\n";
print '        })' . "\n";
print '        .catch(function(error) {' . "\n";
print '            previewDiv.innerHTML = \'<div style="color: #dc3545; padding: 20px; text-align: center;">❌ Error al cargar el archivo: \' + error.message + \'</div>\';' . "\n";
print '        });' . "\n";
print '    } ' . "\n";
print '    // CSV' . "\n";
print '    else if (["csv"].includes(extension)) {' . "\n";
print '        fetch("action.php?action=preview&token=" + FILEMANAGER_TOKEN + "&path=" + encodeURIComponent(path))' . "\n";
print '        .then(function(response) {' . "\n";
print '            return response.text();' . "\n";
print '        })' . "\n";
print '        .then(function(content) {' . "\n";
print '            var lines = content.split("\\n").filter(line => line.trim());' . "\n";
print '            var table = "<table style=\'width: 100%; border-collapse: collapse; font-size: 13px;\'><tbody>";' . "\n";
print '            lines.forEach(function(line, index) {' . "\n";
print '                var cols = line.split(",");' . "\n";
print '                table += "<tr style=\'background: " + (index % 2 === 0 ? "#fff" : "#f8f9fa") + ";\'>";' . "\n";
print '                cols.forEach(function(col) {' . "\n";
print '                    table += "<td style=\'padding: 8px; border: 1px solid #dee2e6;\'>" + escapeHtml(col) + "</td>";' . "\n";
print '                });' . "\n";
print '                table += "</tr>";' . "\n";
print '            });' . "\n";
print '            table += "</tbody></table>";' . "\n";
print '            previewDiv.innerHTML = \'<div style="background: #fff; border-radius: 8px; max-height: 70vh; overflow: auto; border: 1px solid #dee2e6;">\' + table + \'</div>\';' . "\n";
print '        })' . "\n";
print '        .catch(function(error) {' . "\n";
print '            previewDiv.innerHTML = \'<div style="color: #dc3545; padding: 20px; text-align: center;">❌ Error al cargar el archivo: \' + error.message + \'</div>\';' . "\n";
print '        });' . "\n";
print '    } ' . "\n";
print '    // Video' . "\n";
print '    else if (["mp4", "webm", "ogg", "mov", "avi"].includes(extension)) {' . "\n";
print '        previewDiv.innerHTML = \'<div style="text-align: center;"><video controls style="max-width: 100%; max-height: 70vh; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); background: #000;"><source src="action.php?action=preview&token=\' + FILEMANAGER_TOKEN + \'&path=\' + encodeURIComponent(path) + \'" type="video/\' + extension + \'">Tu navegador no soporta el elemento video.</video></div>\';' . "\n";
print '    } ' . "\n";
print '    // Audio' . "\n";
print '    else if (["mp3", "wav", "ogg", "m4a", "flac", "aac"].includes(extension)) {' . "\n";
print '        previewDiv.innerHTML = \'<div style="text-align: center; padding: 40px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px;"><div style="font-size: 80px; margin-bottom: 20px;">🎵</div><audio controls style="width: 100%; max-width: 600px; background: #fff; border-radius: 30px;"><source src="action.php?action=preview&token=\' + FILEMANAGER_TOKEN + \'&path=\' + encodeURIComponent(path) + \'" type="audio/\' + extension + \'">Tu navegador no soporta el elemento audio.</audio></div>\';' . "\n";
print '    } ' . "\n";
print '    // Tipo no soportado' . "\n";
print '    else {' . "\n";
print '        previewDiv.innerHTML = \'<div style="text-align: center; padding: 60px;"><div style="font-size: 80px; margin-bottom: 20px;">📄</div><h4 style="margin-bottom: 15px;">\' + t("preview_not_available") + \'</h4><p style="color: #6c757d; margin-bottom: 25px;">\' + t("cannot_show_preview") + extension.toUpperCase() + \'</p><button onclick="downloadFile(path)" style="padding: 12px 32px; background: #007bff; color: white; border: none; border-radius: 6px; font-size: 16px; font-weight: 600; cursor: pointer; transition: all 0.3s; box-shadow: 0 4px 8px rgba(0,123,255,0.3);">📥 \' + t("download_file") + \'</button></div>\';' . "\n";
print '    }' . "\n";
print '}' . "\n";
print 'function downloadFile(path) {' . "\n";
print '    fetch("' . dol_buildpath('/custom/filemanager/', 1) . 'action.php?action=download&path=" + encodeURIComponent(path) + "&token=" + FILEMANAGER_TOKEN)' . "\n";
print '    .then(function(response) { return response.blob(); })' . "\n";
print '    .then(function(blob) { var url = window.URL.createObjectURL(blob); var a = document.createElement("a"); a.href = url; a.download = path.split(/[\\\\/]/).pop(); if (document.body) { document.body.appendChild(a); a.click(); window.URL.revokeObjectURL(url); document.body.removeChild(a); } })' . "\n";
print '    .catch(function(error) { alert(t("error_downloading") + error.message); });' . "\n";
print '}' . "\n";
print 'function escapeHtml(text) {' . "\n";
print '    var map = {' . "\n";
print '        "&": "&amp;",' . "\n";
print '        "<": "&lt;",' . "\n";
print '        ">": "&gt;",' . "\n";
print '        \'"\': "&quot;",' . "\n";
print '        "\'": "&#039;"' . "\n";
print '    };' . "\n";
print '    return text.replace(/[&<>"\']/g, function(m) { return map[m]; });' . "\n";
print '}' . "\n";
print 'function createFolder() {' . "\n";
print '    Swal.fire({' . "\n";
print '        title: "Nueva carpeta",' . "\n";
print '        input: "text",' . "\n";
print '        inputLabel: "Nombre de la carpeta",' . "\n";
print '        inputPlaceholder: "Ingrese el nombre de la carpeta",' . "\n";
print '        showCancelButton: true,' . "\n";
print '        confirmButtonText: "Crear",' . "\n";
print '        cancelButtonText: t("cancel"),' . "\n";
print '        confirmButtonColor: "#28a745",' . "\n";
print '        cancelButtonColor: "#6c757d"' . "\n";
print '    }).then(function(result) {' . "\n";
print '        if (result.isConfirmed && result.value) {' . "\n";
print '            // Mostrar loading' . "\n";
print '            Swal.fire({' . "\n";
print '                title: "Creando carpeta...",' . "\n";
print '                allowOutsideClick: false,' . "\n";
print '                showConfirmButton: false,' . "\n";
print '                didOpen: function() {' . "\n";
print '                    Swal.showLoading();' . "\n";
print '                }' . "\n";
print '            });' . "\n";
print '            ' . "\n";
print '            // Crear FormData' . "\n";
print '            var formData = new FormData();' . "\n";
print '            formData.append("action", "create_folder");' . "\n";
print '            formData.append("folder_name", result.value);' . "\n";
print '            formData.append("current_path", ' . json_encode($current_path) . ');' . "\n";
print '            formData.append("token", FILEMANAGER_TOKEN);' . "\n";
print '            ' . "\n";
print '            // Enviar petición' . "\n";
print '            fetch("action.php", {' . "\n";
print '                method: "POST",' . "\n";
print '                body: formData' . "\n";
print '            })' . "\n";
print '            .then(function(response) {' . "\n";
print '                return response.json();' . "\n";
print '            })' . "\n";
print '            .then(function(data) {' . "\n";
print '                if (data.success) {' . "\n";
print '                    Swal.fire({' . "\n";
print '                        icon: "success",' . "\n";
print '                        title: "Carpeta creada",' . "\n";
print '                        text: data.message' . "\n";
print '                    }).then(function() {' . "\n";
print '                        window.location.reload();' . "\n";
print '                    });' . "\n";
print '                } else {' . "\n";
print '                    Swal.fire({' . "\n";
print '                        icon: "error",' . "\n";
print '                        title: "Error",' . "\n";
print '                        text: data.message' . "\n";
print '                    });' . "\n";
print '                }' . "\n";
print '            })' . "\n";
print '            .catch(function(error) {' . "\n";
print '                Swal.fire({' . "\n";
print '                    icon: "error",' . "\n";
print '                    title: "Error de conexión",' . "\n";
print '                    text: error.message' . "\n";
print '                });' . "\n";
print '            });' . "\n";
print '        }' . "\n";
print '    });' . "\n";
print '}' . "\n";
print 'function uploadFile() {' . "\n";
print '    // Mostrar opciones: archivos individuales o carpeta completa' . "\n";
print '    Swal.fire({' . "\n";
print '        title: "¿Qué deseas subir?",' . "\n";
print '        html: \'<div style="text-align: center; padding: 20px 0;"><button id="uploadFilesBtn" style="background: #007bff; color: white; border: none; padding: 15px 30px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: bold; margin: 10px; display: block; width: 100%;">📄 Archivos Individuales</button><button id="uploadFolderBtn" style="background: #28a745; color: white; border: none; padding: 15px 30px; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: bold; margin: 10px; display: block; width: 100%;">📁 Carpeta Completa (con todo su contenido)</button></div>\',' . "\n";
print '        showCancelButton: true,' . "\n";
print '        cancelButtonText: t("cancel"),' . "\n";
print '        showConfirmButton: false,' . "\n";
print '        allowOutsideClick: true,' . "\n";
print '        didOpen: function() {' . "\n";
print '            document.getElementById("uploadFilesBtn").onclick = function() {' . "\n";
print '                Swal.close();' . "\n";
print '                uploadFiles();' . "\n";
print '            };' . "\n";
print '            document.getElementById("uploadFolderBtn").onclick = function() {' . "\n";
print '                Swal.close();' . "\n";
print '                uploadFolder();' . "\n";
print '            };' . "\n";
print '        }' . "\n";
print '    });' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function uploadFiles() {' . "\n";
print '    var input = document.createElement("input");' . "\n";
print '    input.type = "file";' . "\n";
print '    input.multiple = true;' . "\n";
print '    input.onchange = function() {' . "\n";
print '        if (this.files.length === 0) return;' . "\n";
print '        uploadFilesToServer(this.files, false);' . "\n";
print '    };' . "\n";
print '    input.click();' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function uploadFolder() {' . "\n";
print '    var input = document.createElement("input");' . "\n";
print '    input.type = "file";' . "\n";
print '    input.webkitdirectory = true;' . "\n";
print '    input.multiple = true;' . "\n";
print '    input.onchange = function() {' . "\n";
print '        if (this.files.length === 0) return;' . "\n";
print '        uploadFilesToServer(this.files, true);' . "\n";
print '    };' . "\n";
print '    input.click();' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function uploadFilesToServer(files, isFolder) {' . "\n";
print '    // Añadir archivos al formulario usando FormData' . "\n";
print '    var formData = new FormData();' . "\n";
print '    formData.append("action", "upload");' . "\n";
print '    formData.append("current_path", ' . json_encode($current_path) . ');' . "\n";
print '    formData.append("token", FILEMANAGER_TOKEN);' . "\n";
print '    formData.append("is_folder", isFolder ? "1" : "0");' . "\n";
print '    ' . "\n";
print '    // Array para almacenar rutas relativas (solo para carpetas)' . "\n";
print '    var filePaths = [];' . "\n";
print '    ' . "\n";
print '    for (var i = 0; i < files.length; i++) {' . "\n";
print '        // Para carpetas, mantener la estructura de rutas relativas' . "\n";
print '        if (isFolder && files[i].webkitRelativePath) {' . "\n";
print '            filePaths.push(files[i].webkitRelativePath);' . "\n";
print '        }' . "\n";
print '        // Agregar archivo con índice correcto para PHP' . "\n";
print '        formData.append("file[" + i + "]", files[i]);' . "\n";
print '    }' . "\n";
print '    ' . "\n";
print '    // Agregar todas las rutas relativas' . "\n";
print '    if (isFolder && filePaths.length > 0) {' . "\n";
print '        for (var j = 0; j < filePaths.length; j++) {' . "\n";
print '            formData.append("file_paths[" + j + "]", filePaths[j]);' . "\n";
print '        }' . "\n";
print '    }' . "\n";
print '    ' . "\n";
print '    // Mostrar loading' . "\n";
print '    var uploadTitle = isFolder ? "Subiendo carpeta..." : "Subiendo archivo(s)...";' . "\n";
print '    Swal.fire({' . "\n";
print '        title: uploadTitle,' . "\n";
print '        text: "Por favor espera, esto puede tomar unos momentos...",' . "\n";
print '        allowOutsideClick: false,' . "\n";
print '        showConfirmButton: false,' . "\n";
print '        didOpen: function() {' . "\n";
print '            Swal.showLoading();' . "\n";
print '        }' . "\n";
print '    });' . "\n";
print '    ' . "\n";
print '    // Enviar usando fetch' . "\n";
print '    fetch("action.php", {' . "\n";
print '        method: "POST",' . "\n";
print '        body: formData' . "\n";
print '    })' . "\n";
print '    .then(function(response) {' . "\n";
print '        return response.json();' . "\n";
print '    })' . "\n";
print '    .then(function(data) {' . "\n";
print '        if (data.success) {' . "\n";
print '            var successTitle = isFolder ? "Carpeta subida" : "Archivo(s) subido(s)";' . "\n";
print '            Swal.fire({' . "\n";
print '                icon: "success",' . "\n";
print '                title: successTitle,' . "\n";
print '                text: data.message' . "\n";
print '            }).then(function() {' . "\n";
print '                window.location.reload();' . "\n";
print '            });' . "\n";
print '        } else {' . "\n";
print '            Swal.fire({' . "\n";
print '                icon: "error",' . "\n";
print '                title: "Error",' . "\n";
print '                text: data.message || "Error al subir archivos"' . "\n";
print '            });' . "\n";
print '        }' . "\n";
print '    })' . "\n";
print '    .catch(function(error) {' . "\n";
print '        Swal.fire({' . "\n";
print '            icon: "error",' . "\n";
print '            title: "Error de conexión",' . "\n";
print '            text: "Error al subir archivo: " + error.message' . "\n";
print '        });' . "\n";
print '    });' . "\n";
print '}' . "\n";
print '' . "\n";
print '// Variables para selección múltiple' . "\n";
print 'var allSelected = false;' . "\n";
print 'var selectedItems = [];' . "\n";
print '' . "\n";
print 'function toggleSelectAll() {' . "\n";
print '    var btn = document.getElementById("selectAllBtn");' . "\n";
print '    var checkboxes = document.querySelectorAll(".file-checkbox, .folder-checkbox");' . "\n";
print '    var items = document.querySelectorAll(".file-item, .folder-item");' . "\n";
print '    ' . "\n";
print '    if (checkboxes.length === 0) {' . "\n";
print '        // Si no hay checkboxes, seleccionar por clase' . "\n";
print '        if (!allSelected) {' . "\n";
print '            items.forEach(function(item) {' . "\n";
print '                item.classList.add("selected");' . "\n";
print '                item.style.background = "#e3f2fd";' . "\n";
print '                item.style.border = "2px solid #1976d2";' . "\n";
print '            });' . "\n";
print '            allSelected = true;' . "\n";
print '            btn.innerHTML = "☐ " + t("deselect_all");' . "\n";
print '            btn.style.background = "#dc3545";' . "\n";
print '            showSelectionActions(items.length);' . "\n";
print '        } else {' . "\n";
print '            items.forEach(function(item) {' . "\n";
print '                item.classList.remove("selected");' . "\n";
print '                item.style.background = "";' . "\n";
print '                item.style.border = "";' . "\n";
print '            });' . "\n";
print '            allSelected = false;' . "\n";
print '            btn.innerHTML = "☑️ " + t("select_all");' . "\n";
print '            btn.style.background = "#6c757d";' . "\n";
print '            hideSelectionActions();' . "\n";
print '        }' . "\n";
print '    } else {' . "\n";
print '        // Si hay checkboxes' . "\n";
print '        allSelected = !allSelected;' . "\n";
print '        checkboxes.forEach(function(cb) {' . "\n";
print '            cb.checked = allSelected;' . "\n";
print '            var item = cb.closest(".file-item, .folder-item");' . "\n";
print '            if (item) {' . "\n";
print '                if (allSelected) {' . "\n";
print '                    item.classList.add("selected");' . "\n";
print '                    item.style.background = "#e3f2fd";' . "\n";
print '                    item.style.border = "2px solid #1976d2";' . "\n";
print '                } else {' . "\n";
print '                    item.classList.remove("selected");' . "\n";
print '                    item.style.background = "";' . "\n";
print '                    item.style.border = "";' . "\n";
print '                }' . "\n";
print '            }' . "\n";
print '        });' . "\n";
print '        if (allSelected) {' . "\n";
print '            btn.innerHTML = "☐ " + t("deselect_all");' . "\n";
print '            btn.style.background = "#dc3545";' . "\n";
print '            showSelectionActions(checkboxes.length);' . "\n";
print '        } else {' . "\n";
print '            btn.innerHTML = "☑️ " + t("select_all");' . "\n";
print '            btn.style.background = "#6c757d";' . "\n";
print '            hideSelectionActions();' . "\n";
print '        }' . "\n";
print '    }' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function showSelectionActions(count) {' . "\n";
print '    var existing = document.getElementById("selectionActionsBar");' . "\n";
print '    if (existing) existing.remove();' . "\n";
print '    ' . "\n";
print '    var bar = document.createElement("div");' . "\n";
print '    bar.id = "selectionActionsBar";' . "\n";
print '    bar.style.cssText = "position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); padding: 15px 25px; border-radius: 12px; box-shadow: 0 8px 32px rgba(0,0,0,0.3); display: flex; gap: 15px; align-items: center; z-index: 9999;";' . "\n";
print '    bar.innerHTML = \'<span style="color: #fff; font-weight: 600;">\' + count + \' \' + t("selected") + \'</span>\' +' . "\n";
print '        \'<button onclick="deleteSelected()" style="background: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600;">🗑️ \' + t("delete") + \'</button>\' +' . "\n";
print '        \'<button onclick="cutSelected()" style="background: #ff9800; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600;">✂️ \' + t("cut") + \'</button>\' +' . "\n";
print '        \'<button onclick="copySelected()" style="background: #2196f3; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600;">📋 \' + t("copy") + \'</button>\' +' . "\n";
print '        \'<button onclick="toggleSelectAll()" style="background: #6c757d; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; font-weight: 600;">❌ \' + t("cancel") + \'</button>\';' . "\n";
print '    if (document.body) {
        document.body.appendChild(bar);
    }' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function hideSelectionActions() {' . "\n";
print '    var bar = document.getElementById("selectionActionsBar");' . "\n";
print '    if (bar) bar.remove();' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function getSelectedPaths() {' . "\n";
print '    var paths = [];' . "\n";
print '    var selected = document.querySelectorAll(".file-item.selected, .folder-item.selected");' . "\n";
print '    selected.forEach(function(item) {' . "\n";
print '        var path = item.getAttribute("data-path");' . "\n";
print '        if (path) paths.push(path);' . "\n";
print '    });' . "\n";
print '    return paths;' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function deleteSelected() {' . "\n";
print '    var paths = getSelectedPaths();' . "\n";
print '    if (paths.length === 0) {' . "\n";
print '        Swal.fire({icon: "info", title: "Info", text: "No hay elementos seleccionados"});' . "\n";
print '        return;' . "\n";
print '    }' . "\n";
print '    ' . "\n";
print '    Swal.fire({' . "\n";
print '        title: t("confirm_delete"),' . "\n";
print '        text: t("delete_items_confirm").replace("{count}", paths.length),' . "\n";
print '        icon: "warning",' . "\n";
print '        showCancelButton: true,' . "\n";
print '        confirmButtonColor: "#dc3545",' . "\n";
print '        confirmButtonText: t("delete"),' . "\n";
print '        cancelButtonText: t("cancel")' . "\n";
print '    }).then(function(result) {' . "\n";
print '        if (result.isConfirmed) {' . "\n";
print '            Swal.fire({title: "Eliminando...", text: "Por favor espera", allowOutsideClick: false, showConfirmButton: false, didOpen: function() { Swal.showLoading(); }});' . "\n";
print '            ' . "\n";
print '            // Usar bulk_delete_with_module_check para eliminar todos a la vez' . "\n";
print '            var formData = new FormData();' . "\n";
print '            formData.append("paths", JSON.stringify(paths));' . "\n";
print '            formData.append("token", FILEMANAGER_TOKEN);' . "\n";
print '            formData.append("action", "bulk_delete_with_module_check");' . "\n";
print '            ' . "\n";
print '            fetch("action.php", {' . "\n";
print '                method: "POST",' . "\n";
print '                body: formData' . "\n";
print '            })' . "\n";
print '            .then(function(response) { return response.json(); })' . "\n";
print '            .then(function(data) {' . "\n";
print '                if (data.success) {' . "\n";
print '                    Swal.fire({icon: "success", title: t("success"), text: data.message}).then(function() {' . "\n";
print '                        window.location.reload();' . "\n";
print '                    });' . "\n";
print '                } else {' . "\n";
print '                    Swal.fire({icon: "error", title: "Error", text: data.message});' . "\n";
print '                }' . "\n";
print '            })' . "\n";
print '            .catch(function(error) {' . "\n";
print '                Swal.fire({icon: "error", title: "Error", text: "Error de conexión: " + error.message});' . "\n";
print '            });' . "\n";
print '        }' . "\n";
print '    });' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function cutSelected() {' . "\n";
print '    var paths = getSelectedPaths();' . "\n";
print '    if (paths.length === 0) return;' . "\n";
print '    window.clipboardPaths = paths;' . "\n";
print '    window.clipboardAction = "cut";' . "\n";
print '    Swal.fire({' . "\n";
print '        icon: "success",' . "\n";
print '        title: t("cut"),' . "\n";
print '        text: paths.length + " " + t("items_cut"),' . "\n";
print '        timer: 1500,' . "\n";
print '        showConfirmButton: false' . "\n";
print '    });' . "\n";
print '    toggleSelectAll(); // Deseleccionar' . "\n";
print '    // Mostrar botón pegar' . "\n";
print '    var pasteBtn = document.getElementById("pasteButton");' . "\n";
print '    if (pasteBtn) pasteBtn.style.display = "flex";' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function copySelected() {' . "\n";
print '    var paths = getSelectedPaths();' . "\n";
print '    if (paths.length === 0) return;' . "\n";
print '    window.clipboardPaths = paths;' . "\n";
print '    window.clipboardAction = "copy";' . "\n";
print '    Swal.fire({' . "\n";
print '        icon: "success",' . "\n";
print '        title: t("copy"),' . "\n";
print '        text: paths.length + " " + t("items_copied"),' . "\n";
print '        timer: 1500,' . "\n";
print '        showConfirmButton: false' . "\n";
print '    });' . "\n";
print '    toggleSelectAll(); // Deseleccionar' . "\n";
print '    // Mostrar botón pegar' . "\n";
print '    var pasteBtn = document.getElementById("pasteButton");' . "\n";
print '    if (pasteBtn) pasteBtn.style.display = "flex";' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function refreshPage() {' . "\n";
print '    window.location.reload();' . "\n";
print '}' . "\n";
print 'function goBack() {' . "\n";
print '    history.back();' . "\n";
print '}' . "\n";
print 'function goForward() {' . "\n";
print '    history.forward();' . "\n";
print '}' . "\n";
print 'function goUp() {' . "\n";
print '    var currentPath = ' . json_encode($current_path) . ';' . "\n";
print '    var separator = ' . json_encode(DIRECTORY_SEPARATOR) . ';' . "\n";
print '    var parentPath = currentPath.substring(0, currentPath.lastIndexOf(separator));' . "\n";
print '    if (parentPath && parentPath !== currentPath) {' . "\n";
print '        navigateToPath(parentPath);' . "\n";
print '    } else {' . "\n";
print '        alert("Ya estás en el directorio raíz");' . "\n";
print '    }' . "\n";
print '}' . "\n";
print 'function cleanTrash() {' . "\n";
print '    Swal.fire({' . "\n";
print '        title: t("confirm_empty_trash"),' . "\n";
print '        text: t("clear_trash_text"),' . "\n";
print '        icon: "warning",' . "\n";
print '        showCancelButton: true,' . "\n";
print '        confirmButtonColor: "#dc3545",' . "\n";
print '        cancelButtonColor: "#6c757d",' . "\n";
print '        confirmButtonText: t("yes_delete_permanently"),' . "\n";
print '        cancelButtonText: t("cancel")' . "\n";
print '    }).then((result) => {' . "\n";
print '        if (result.isConfirmed) {' . "\n";
print '            window.location.href = "action.php?action=clean_trash";' . "\n";
print '        }' . "\n";
print '    });' . "\n";
print '}' . "\n";
print '// permanentDelete y restoreFromTrash ya están definidos arriba' . "\n";
print 'function selectAllTrash() {' . "\n";
print '    var checkboxes = document.querySelectorAll(".trash-item input[type=\'checkbox\']");' . "\n";
print '    for (var i = 0; i < checkboxes.length; i++) {' . "\n";
print '        checkboxes[i].checked = true;' . "\n";
print '    }' . "\n";
print '}' . "\n";
print 'function bulkPermanentDelete() {' . "\n";
print '    var selectedItems = [];' . "\n";
print '    var checkboxes = document.querySelectorAll(".trash-item input[type=\'checkbox\']:checked");' . "\n";
print '    for (var i = 0; i < checkboxes.length; i++) {' . "\n";
print '        var item = checkboxes[i].closest(".trash-item");' . "\n";
print '        if (item) {' . "\n";
print '            var nameElement = item.querySelector(".trash-name");' . "\n";
print '            var name = nameElement ? nameElement.textContent.trim() : "Elemento";' . "\n";
print '            var path = nameElement ? nameElement.getAttribute("data-path") : "";' . "\n";
print '            selectedItems.push({name: name, path: path, element: item});' . "\n";
print '        }' . "\n";
print '    }' . "\n";
print '    ' . "\n";
print '    if (selectedItems.length === 0) {' . "\n";
print '        Swal.fire({' . "\n";
print '            title: t("no_selection"),' . "\n";
print '            text: t("please_select_items"),' . "\n";
print '            icon: "warning",' . "\n";
print '            confirmButtonText: "Aceptar"' . "\n";
print '        });' . "\n";
print '        return;' . "\n";
print '    }' . "\n";
print '    ' . "\n";
print '    Swal.fire({' . "\n";
print '        title: t("delete_definitely_question"),' . "\n";
print '        text: t("delete_permanently_text").replace(" ", " " + selectedItems.length + " "),' . "\n";
print '        icon: "warning",' . "\n";
print '        showCancelButton: true,' . "\n";
print '        confirmButtonColor: "#dc3545",' . "\n";
print '        cancelButtonColor: "#6c757d",' . "\n";
print '        confirmButtonText: t("yes_delete_definitely"),' . "\n";
print '        cancelButtonText: t("cancel")' . "\n";
print '    }).then(function(result) {' . "\n";
print '        if (result.isConfirmed) {' . "\n";
print '            Swal.fire({' . "\n";
print '                title: "Eliminando...",' . "\n";
print '                text: "Eliminando " + selectedItems.length + " elementos...",' . "\n";
print '                allowOutsideClick: false,' . "\n";
print '                showConfirmButton: false,' . "\n";
print '                willOpen: function() {' . "\n";
print '                    Swal.showLoading();' . "\n";
print '                }' . "\n";
print '            });' . "\n";
print '            ' . "\n";
print '            // Obtener paths correctamente' . "\n";
print '            var paths = [];' . "\n";
print '            for (var i = 0; i < selectedItems.length; i++) {' . "\n";
print '                if (selectedItems[i].path) {' . "\n";
print '                    paths.push(selectedItems[i].path);' . "\n";
print '                }' . "\n";
print '            }' . "\n";
print '            ' . "\n";
print '            fetch("action.php?action=bulk_permanent_delete", {' . "\n";
print '                method: "POST",' . "\n";
print '                headers: {"Content-Type": "application/x-www-form-urlencoded"},' . "\n";
print '                body: "paths=" + encodeURIComponent(JSON.stringify(paths)) + "&token=" + FILEMANAGER_TOKEN' . "\n";
print '            })' . "\n";
print '            .then(function(response) {' . "\n";
print '                return response.json();' . "\n";
print '            })' . "\n";
print '            .then(function(data) {' . "\n";
print '                if (data.success) {' . "\n";
print '                    Swal.fire({' . "\n";
print '                        title: "¡Eliminados!",' . "\n";
print '                        text: data.message,' . "\n";
print '                        icon: "success",' . "\n";
print '                        confirmButtonText: "Aceptar"' . "\n";
print '                    }).then(function() {' . "\n";
print '                        location.reload();' . "\n";
print '                    });' . "\n";
print '                } else {' . "\n";
print '                    Swal.fire({' . "\n";
print '                        title: "Error",' . "\n";
print '                        text: data.message,' . "\n";
print '                        icon: "error",' . "\n";
print '                        confirmButtonText: "Aceptar"' . "\n";
print '                    });' . "\n";
print '                }' . "\n";
print '            })' . "\n";
print '            .catch(function(error) {' . "\n";
print '                Swal.fire({' . "\n";
print '                    title: "Error",' . "\n";
print '                    text: "Error de conexión: " + error.message,' . "\n";
print '                    icon: "error",' . "\n";
print '                    confirmButtonText: "Aceptar"' . "\n";
print '                });' . "\n";
print '            });' . "\n";
print '        }' . "\n";
print '    });' . "\n";
print '}' . "\n";
print 'function emptyTrash() {' . "\n";
print '    Swal.fire({' . "\n";
print '        title: t("confirm_empty_trash"),' . "\n";
print '        text: t("confirm_empty_trash_text"),' . "\n";
print '        icon: "warning",' . "\n";
print '        showCancelButton: true,' . "\n";
print '        confirmButtonColor: "#dc3545",' . "\n";
print '        cancelButtonColor: "#6c757d",' . "\n";
print '        confirmButtonText: "Sí, vaciar papelera",' . "\n";
print '        cancelButtonText: t("cancel")' . "\n";
print '    }).then(function(result) {' . "\n";
print '        if (result.isConfirmed) {' . "\n";
print '            Swal.fire({' . "\n";
print '                title: "Vaciando...",' . "\n";
print '                text: "Eliminando todo el contenido de la papelera...",' . "\n";
print '                allowOutsideClick: false,' . "\n";
print '                showConfirmButton: false,' . "\n";
print '                willOpen: function() {' . "\n";
print '                    Swal.showLoading();' . "\n";
print '                }' . "\n";
print '            });' . "\n";
print '            ' . "\n";
print '            fetch("action.php?action=empty_trash", {' . "\n";
print '                method: "POST",' . "\n";
print '                headers: {"Content-Type": "application/x-www-form-urlencoded"},' . "\n";
print '                body: "token=" + FILEMANAGER_TOKEN' . "\n";
print '            })' . "\n";
print '            .then(function(response) {' . "\n";
print '                return response.json();' . "\n";
print '            })' . "\n";
print '            .then(function(data) {' . "\n";
print '                if (data.success) {' . "\n";
print '                    Swal.fire({' . "\n";
print '                        title: "¡Papelera vaciada!",' . "\n";
print '                        text: data.message,' . "\n";
print '                        icon: "success",' . "\n";
print '                        confirmButtonText: "Aceptar"' . "\n";
print '                    }).then(function() {' . "\n";
print '                        location.reload();' . "\n";
print '                    });' . "\n";
print '                } else {' . "\n";
print '                    Swal.fire({' . "\n";
print '                        title: "Error",' . "\n";
print '                        text: data.message,' . "\n";
print '                        icon: "error",' . "\n";
print '                        confirmButtonText: "Aceptar"' . "\n";
print '                    });' . "\n";
print '                }' . "\n";
print '            })' . "\n";
print '            .catch(function(error) {' . "\n";
print '                Swal.fire({' . "\n";
print '                    title: "Error",' . "\n";
print '                    text: "Error de conexión: " + error.message,' . "\n";
print '                    icon: "error",' . "\n";
print '                    confirmButtonText: "Aceptar"' . "\n";
print '                });' . "\n";
print '            });' . "\n";
print '        }' . "\n";
print '    });' . "\n";
print '}' . "\n";
print 'function bulkDelete() {' . "\n";
print '    var selectedItems = [];' . "\n";
print '    var checkboxes = document.querySelectorAll("input[type=\'checkbox\']:checked");' . "\n";
print '    for (var i = 0; i < checkboxes.length; i++) {' . "\n";
print '        var item = checkboxes[i].closest(".file-item, .folder-item");' . "\n";
print '        if (item) {' . "\n";
print '            var nameElement = item.querySelector(".file-name, .folder-name");' . "\n";
print '            var name = nameElement ? nameElement.textContent.trim() : "Elemento";' . "\n";
print '            var path = nameElement ? nameElement.getAttribute("data-path") : "";' . "\n";
print '            var isFolder = item.classList.contains("folder-item");' . "\n";
print '            selectedItems.push({name: name, path: path, isFolder: isFolder, element: item});' . "\n";
print '        }' . "\n";
print '    }' . "\n";
print '    if (selectedItems.length === 0) {' . "\n";
print '        Swal.fire({' . "\n";
print '            icon: "warning",' . "\n";
print '            title: "Nada seleccionado",' . "\n";
print '            text: "Selecciona al menos un archivo o carpeta para enviar a la papelera"' . "\n";
print '        });' . "\n";
print '        return;' . "\n";
print '    }' . "\n";
print '    ' . "\n";
print '    var itemList = "";' . "\n";
print '    for (var i = 0; i < selectedItems.length; i++) {' . "\n";
print '        itemList += "• " + selectedItems[i].name + " (" + (selectedItems[i].isFolder ? "Carpeta" : "Archivo") + ")<br>";' . "\n";
print '    }' . "\n";
print '    ' . "\n";
print '    Swal.fire({' . "\n";
print '        title: t("move_to_trash_question"),' . "\n";
print '        html: t("delete_selected_items").replace(" ", " " + selectedItems.length + " ") + "<br><br>" + itemList + "<br><strong>" + t("items_will_move_to_trash") + "</strong>",' . "\n";
print '        icon: "question",' . "\n";
print '        showCancelButton: true,' . "\n";
print '        confirmButtonText: t("yes_move_to_trash"),' . "\n";
print '        cancelButtonText: t("cancel"),' . "\n";
print '        confirmButtonColor: "#dc3545",' . "\n";
print '        cancelButtonColor: "#6c757d"' . "\n";
print '    }).then(function(result) {' . "\n";
print '        if (result.isConfirmed) {' . "\n";
print '            // Proceder con la eliminación - usar data-path de cada elemento' . "\n";
print '            var paths = [];' . "\n";
print '            for (var i = 0; i < selectedItems.length; i++) {' . "\n";
print '                if (selectedItems[i].path) {' . "\n";
print '                    paths.push(selectedItems[i].path);' . "\n";
print '                }' . "\n";
print '            }' . "\n";
print '            ' . "\n";
print '            if (paths.length > 0) {' . "\n";
print '                Swal.fire({' . "\n";
print '                    title: "Enviando a papelera...",' . "\n";
print '                    text: "Procesando " + paths.length + " elemento(s)",' . "\n";
print '                    allowOutsideClick: false,' . "\n";
print '                    didOpen: function() {' . "\n";
print '                        Swal.showLoading();' . "\n";
print '                    }' . "\n";
print '                });' . "\n";
print '                ' . "\n";
print '                fetch("action.php?action=bulk_delete_with_module_check", {' . "\n";
print '                    method: "POST",' . "\n";
print '                    headers: {"Content-Type": "application/x-www-form-urlencoded"},' . "\n";
print '                    body: "paths=" + encodeURIComponent(JSON.stringify(paths)) + "&token=" + FILEMANAGER_TOKEN' . "\n";
print '                })' . "\n";
print '                .then(function(response) { return response.json(); })' . "\n";
print '                .then(function(data) {' . "\n";
print '                    if (data.success) {' . "\n";
print '                        Swal.fire({' . "\n";
print '                            icon: "success",' . "\n";
print '                            title: "Enviado a papelera",' . "\n";
print '                            text: data.message' . "\n";
print '                        }).then(function() {' . "\n";
print '                            location.reload();' . "\n";
print '                        });' . "\n";
print '                    } else {' . "\n";
print '                        Swal.fire({' . "\n";
print '                            icon: "error",' . "\n";
print '                            title: "Error",' . "\n";
print '                            text: data.message' . "\n";
print '                        });' . "\n";
print '                    }' . "\n";
print '                })' . "\n";
print '                .catch(function(error) {' . "\n";
print '                    Swal.fire({' . "\n";
print '                        icon: "error",' . "\n";
print '                        title: "Error de conexión",' . "\n";
print '                        text: "No se pudo completar la operación"' . "\n";
print '                    });' . "\n";
print '                });' . "\n";
print '            }' . "\n";
print '        }' . "\n";
print '    });' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function filterItems() {' . "\n";
print '    var search = document.getElementById("searchInput").value.toLowerCase();' . "\n";
print '    var show = document.getElementById("showFilter").value;' . "\n";
print '    var items = document.querySelectorAll(".file-item, .folder-item, .trash-item");' . "\n";
print '    var matchCount = 0;' . "\n";
print '    for (var i = 0; i < items.length; i++) {' . "\n";
print '        var item = items[i];' . "\n";
print '        // Buscar nombre en elementos con clase específica' . "\n";
print '        var nameElement = item.querySelector(".folder-name, .file-name");' . "\n";
print '        var name = nameElement ? nameElement.textContent.toLowerCase() : "";' . "\n";
print '        var isFolder = item.classList.contains("folder-item");' . "\n";
print '        var isProtected = item.querySelector("div[style*=\'background: #ffc107\']") !== null;' . "\n";
print '        var showItem = true;' . "\n";
print '        if (search && !name.includes(search)) {' . "\n";
print '            showItem = false;' . "\n";
print '        }' . "\n";
print '        if (show === "folders" && !isFolder) {' . "\n";
print '            showItem = false;' . "\n";
print '        }' . "\n";
print '        if (show === "files" && isFolder) {' . "\n";
print '            showItem = false;' . "\n";
print '        }' . "\n";
print '        if (show === "protected" && !isProtected) {' . "\n";
print '            showItem = false;' . "\n";
print '        }' . "\n";
print '        if (showItem) {' . "\n";
print '            item.style.display = "block";' . "\n";
print '            item.style.opacity = "1";' . "\n";
print '        } else {' . "\n";
print '            item.style.display = "none";' . "\n";
print '            item.style.opacity = "0";' . "\n";
print '        }' . "\n";
print '    }' . "\n";
print '    var visibleItems = document.querySelectorAll(".file-item, .folder-item, .trash-item[style*=\'display: block\']");' . "\n";
print '    var noResultsMsg = document.getElementById("noResultsMsg");' . "\n";
print '    if (visibleItems.length === 0 && (search || show !== "all")) {' . "\n";
print '        if (!noResultsMsg) {' . "\n";
print '            noResultsMsg = document.createElement("div");' . "\n";
print '            noResultsMsg.id = "noResultsMsg";' . "\n";
print '            noResultsMsg.style.cssText = "text-align: center; padding: 60px; color: #6c757d; grid-column: 1 / -1;";' . "\n";
print '            noResultsMsg.innerHTML = "<div style=\\"font-size: 64px; margin-bottom: 20px;\\">🔍</div><h3 style=\\"margin-bottom: 10px;\\">No se encontraron resultados</h3><p>Intenta con otros términos de búsqueda o filtros</p>";' . "\n";
print '            var fileGrid = document.querySelector(".file-grid");
            if (fileGrid) {
                fileGrid.appendChild(noResultsMsg);
            }' . "\n";
print '        }' . "\n";
print '    } else if (noResultsMsg) {' . "\n";
print '        noResultsMsg.remove();' . "\n";
print '    }' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function sortItems() {' . "\n";
print '    var sortBy = document.getElementById("sortFilter").value;' . "\n";
print '    var container = document.querySelector(".file-grid");' . "\n";
print '    if (!container) return;' . "\n";
print '    var items = [];' . "\n";
print '    for (var i = 0; i < container.children.length; i++) {' . "\n";
print '        var item = container.children[i];' . "\n";
print '        if (item.classList.contains("file-item") || item.classList.contains("folder-item") || item.classList.contains("trash-item")) {' . "\n";
print '            items.push(item);' . "\n";
print '        }' . "\n";
print '    }' . "\n";
print '    items.sort(function(a, b) {' . "\n";
print '        var nameA = "";' . "\n";
print '        var nameB = "";' . "\n";
print '        var dateA = new Date();' . "\n";
print '        var dateB = new Date();' . "\n";
print '        var sizeA = 0;' . "\n";
print '        var sizeB = 0;' . "\n";
print '        ' . "\n";
print '        // Obtener nombre del elemento' . "\n";
print '        var nameElementA = a.querySelector("div[style*=\'font-weight: bold\'], div[style*=\'font-weight:bold\']");' . "\n";
print '        var nameElementB = b.querySelector("div[style*=\'font-weight: bold\'], div[style*=\'font-weight:bold\']");' . "\n";
print '        if (nameElementA) nameA = nameElementA.textContent.toLowerCase();' . "\n";
print '        if (nameElementB) nameB = nameElementB.textContent.toLowerCase();' . "\n";
print '        ' . "\n";
print '        // Obtener fecha del elemento' . "\n";
print '        var dateElementA = a.querySelector("div[style*=\'color: #999\']");' . "\n";
print '        var dateElementB = b.querySelector("div[style*=\'color: #999\']");' . "\n";
print '        if (dateElementA) dateA = new Date(dateElementA.textContent);' . "\n";
print '        if (dateElementB) dateB = new Date(dateElementB.textContent);' . "\n";
print '        ' . "\n";
print '        // Obtener tamaño del elemento' . "\n";
print '        var sizeElementA = a.querySelector("div[style*=\'color: #6c757d\']");' . "\n";
print '        var sizeElementB = b.querySelector("div[style*=\'color: #6c757d\']");' . "\n";
print '        if (sizeElementA) sizeA = parseSize(sizeElementA.textContent);' . "\n";
print '        if (sizeElementB) sizeB = parseSize(sizeElementB.textContent);' . "\n";
print '        ' . "\n";
print '        var isFolderA = a.classList.contains("folder-item");' . "\n";
print '        var isFolderB = b.classList.contains("folder-item");' . "\n";
print '        if (isFolderA && !isFolderB) return -1;' . "\n";
print '        if (!isFolderA && isFolderB) return 1;' . "\n";
print '        if (sortBy === "name") {' . "\n";
print '            return nameA.localeCompare(nameB);' . "\n";
print '        }' . "\n";
print '        if (sortBy === "name_desc") {' . "\n";
print '            return nameB.localeCompare(nameA);' . "\n";
print '        }' . "\n";
print '        if (sortBy === "date") {' . "\n";
print '            return dateB - dateA;' . "\n";
print '        }' . "\n";
print '        if (sortBy === "date_old") {' . "\n";
print '            return dateA - dateB;' . "\n";
print '        }' . "\n";
print '        if (sortBy === "size") {' . "\n";
print '            return sizeB - sizeA;' . "\n";
print '        }' . "\n";
print '        if (sortBy === "size_small") {' . "\n";
print '            return sizeA - sizeB;' . "\n";
print '        }' . "\n";
print '        return 0;' . "\n";
print '    });' . "\n";
print '    for (var i = 0; i < items.length; i++) {' . "\n";
print '        if (container && items[i]) {
            container.appendChild(items[i]);
        }' . "\n";
print '    }' . "\n";
print '    filterItems();' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function clearSearch() {' . "\n";
print '    document.getElementById("searchInput").value = "";' . "\n";
print '    filterItems();' . "\n";
print '}' . "\n";
print '' . "\n";
print 'function parseSize(sizeText) {' . "\n";
print '    if (!sizeText || sizeText === "Carpeta (? archivos)") return 0;' . "\n";
print '    var size = parseFloat(sizeText);' . "\n";
print '    if (sizeText.includes("KB")) return size * 1024;' . "\n";
print '    if (sizeText.includes("MB")) return size * 1024 * 1024;' . "\n";
print '    if (sizeText.includes("GB")) return size * 1024 * 1024 * 1024;' . "\n";
print '    return size;' . "\n";
print '}' . "\n";
print '' . "\n";
// Las funciones deselectAllFiles, deselectAllTrash, bulkMoveToTrash y bulkPermanentDeleteSelected están definidas arriba
print '' . "\n";
print 'document.addEventListener("DOMContentLoaded", function() {' . "\n";
print '    filterItems();' . "\n";
print '    var searchInput = document.getElementById("searchInput");' . "\n";
print '    if (searchInput) {' . "\n";
print '        searchInput.addEventListener("input", filterItems);' . "\n";
print '    }' . "\n";
print '    var showFilter = document.getElementById("showFilter");' . "\n";
print '    if (showFilter) {' . "\n";
print '        showFilter.addEventListener("change", filterItems);' . "\n";
print '    }' . "\n";
print '});' . "\n";
print '</script>' . "\n";

// Cerrar página
llxFooter();
?>