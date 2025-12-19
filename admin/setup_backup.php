<?php
/**
 * Setup del módulo FileManager - Versión funcional
 */

// Incluir el entorno de Dolibarr
require_once '../../../main.inc.php';

// Verificar permisos
if (!$user->admin) {
    accessforbidden();
}

$langs->loadLangs(array('admin','other'));
$token = newToken();

// Configuración por defecto
$config = array(
    'FILEMANAGER_ROOT_PATH' => DOL_DOCUMENT_ROOT,
    'FILEMANAGER_ALLOW_DELETE' => 1,
    'FILEMANAGER_ALLOW_UPLOAD' => 1,
    'FILEMANAGER_SHOW_DELETE_BUTTON' => 1,
    'FILEMANAGER_MAX_FILES' => 100,
    'FILEMANAGER_ALLOWED_EXTENSIONS' => array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip', 'rar'),
    'FILEMANAGER_ALLOW_PROTECTED_ACTIONS' => 0
);

// Procesar formulario si se envió
if ($_POST) {
    if (isset($_POST['save_config'])) {
        $config_updated = false;
        
        // Procesar ruta raíz
        if (isset($_POST['root_path']) && !empty($_POST['root_path'])) {
            $new_path = trim($_POST['root_path']);
            if (is_dir($new_path) && is_writable($new_path)) {
                $config['FILEMANAGER_ROOT_PATH'] = $new_path;
                $config_updated = true;
            } else {
                setEventMessages('Error: La ruta especificada no existe o no tiene permisos de escritura', null, 'errors');
            }
        }
        
        // Procesar extensiones permitidas
        if (isset($_POST['allowed_extensions']) && is_array($_POST['allowed_extensions'])) {
            $extensions = array_map('strtolower', $_POST['allowed_extensions']);
            $extensions = array_unique($extensions);
            $config['FILEMANAGER_ALLOWED_EXTENSIONS'] = $extensions;
            $config_updated = true;
        } else {
            $config['FILEMANAGER_ALLOWED_EXTENSIONS'] = array();
            $config_updated = true;
        }
        
        // Procesar otras configuraciones
        $config['FILEMANAGER_ALLOW_DELETE'] = isset($_POST['allow_delete']) ? 1 : 0;
        $config['FILEMANAGER_ALLOW_UPLOAD'] = isset($_POST['allow_upload']) ? 1 : 0;
        $config['FILEMANAGER_SHOW_DELETE_BUTTON'] = isset($_POST['show_delete_button']) ? 1 : 0;
        $config['FILEMANAGER_MAX_FILES'] = isset($_POST['max_files']) ? intval($_POST['max_files']) : 100;
        $config['FILEMANAGER_ALLOW_PROTECTED_ACTIONS'] = isset($_POST['allow_protected_actions']) ? 1 : 0;
        
        if ($config_updated) {
            setEventMessages('Configuraciones guardadas correctamente', null, 'mesgs');
        }
    }
}

// Iniciar página
llxHeader('', 'FileManager - Configuraciones');

// Título de la página
print load_fiche_titre('⚙️ Configuraciones del FileManager', '', 'title_filemanager.png');


// Pestañas para configuración, backup y logs
print '<br>';
print '<div style="margin-bottom: 20px;">';
print '<div style="display: flex; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; overflow: hidden;">';
print '<button id="configTab" class="setup-tab active" onclick="switchSetupTab(\'config\')" style="padding: 10px 20px; border: none; background: #007bff; color: white; cursor: pointer; font-size: 13px; border-right: 1px solid #dee2e6;"><i class="fas fa-cog"></i> Configuración</button>';
print '<button id="backupTab" class="setup-tab" onclick="switchSetupTab(\'backup\')" style="padding: 10px 20px; border: none; background: #f8f9fa; color: #495057; cursor: pointer; font-size: 13px; border-right: 1px solid #dee2e6;"><i class="fas fa-server"></i> Backups</button>';
print '<button id="logsTab" class="setup-tab" onclick="switchSetupTab(\'logs\')" style="padding: 10px 20px; border: none; background: #f8f9fa; color: #495057; cursor: pointer; font-size: 13px;"><i class="fas fa-file-alt"></i> Logs de Actividad</button>';
print '<button onclick="window.open(\'../index.php\', \'_blank\')" style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-size: 13px; margin-left: auto;"><i class="fas fa-folder-open"></i> Administrador de archivos</button>';
print '</div>';
print '</div>';

// Contenido de configuración (activo por defecto)
print '<div id="configContent">';
print '<form method="POST" action="">';
print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td colspan="2">Configuración del FileManager</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td class="fieldrequired">Ruta raíz del FileManager:</td>';
print '<td>';
print '<input type="text" name="root_path" value="' . htmlspecialchars($config['FILEMANAGER_ROOT_PATH']) . '" class="minwidth200" id="rootPathInput">';
print '<button type="button" onclick="autoDetectPath()" style="background: #28a745; color: white; border: none; padding: 4px 8px; border-radius: 3px; margin-left: 5px; cursor: pointer;">🔍 Auto-detectar</button>';
print '</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>Permitir eliminación de archivos:</td>';
print '<td>';
print '<input type="checkbox" name="allow_delete" value="1"' . ($config['FILEMANAGER_ALLOW_DELETE'] ? ' checked' : '') . '> Sí';
print '</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>Permitir subida de archivos:</td>';
print '<td>';
print '<input type="checkbox" name="allow_upload" value="1"' . ($config['FILEMANAGER_ALLOW_UPLOAD'] ? ' checked' : '') . '> Sí';
print '</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>Mostrar botón de eliminación:</td>';
print '<td>';
print '<input type="checkbox" name="show_delete_button" value="1"' . ($config['FILEMANAGER_SHOW_DELETE_BUTTON'] ? ' checked' : '') . '> Sí';
print '</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>Permitir acciones en archivos protegidos:</td>';
print '<td>';
print '<input type="checkbox" name="allow_protected_actions" value="1"' . ($config['FILEMANAGER_ALLOW_PROTECTED_ACTIONS'] ? ' checked' : '') . '> Sí';
print '<br><small style="color: #dc3545; font-weight: bold;">⚠️ ADVERTENCIA: Habilitar esta opción permite eliminar y descargar archivos protegidos del sistema. Úsalo bajo tu propio riesgo.</small>';
print '</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td>Límite de archivos por carpeta:</td>';
print '<td>';
print '<input type="number" name="max_files" value="' . intval($config['FILEMANAGER_MAX_FILES']) . '" min="1" max="1000" style="width: 100px;">';
print '</td>';
print '</tr>';

print '<tr class="liste_titre">';
print '<td colspan="2">Tipos de archivos permitidos</td>';
print '</tr>';

print '<tr class="oddeven">';
print '<td colspan="2">';
print '<div style="margin-bottom: 10px;">';
print '<button type="button" onclick="selectAllExtensions()" style="background: #28a745; color: white; border: none; padding: 4px 8px; border-radius: 3px; margin-right: 5px; cursor: pointer;">✅ Seleccionar Todo</button>';
print '<button type="button" onclick="deselectAllExtensions()" style="background: #dc3545; color: white; border: none; padding: 4px 8px; border-radius: 3px; cursor: pointer;">❌ Deseleccionar Todo</button>';
print '</div>';

// Generar checkboxes para extensiones
$allExtensions = array_unique($config['FILEMANAGER_ALLOWED_EXTENSIONS']);
sort($allExtensions);

print '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; max-height: 300px; overflow-y: auto; border: 1px solid #dee2e6; border-radius: 4px; padding: 15px;">';

foreach ($allExtensions as $ext) {
    $ext = strtolower(trim($ext));
    if (empty($ext)) continue;
    
    $checked = in_array($ext, $config['FILEMANAGER_ALLOWED_EXTENSIONS']) ? ' checked' : '';
    
    print '<label style="display: flex; align-items: center; cursor: pointer; padding: 5px; border-radius: 3px; background: #f8f9fa;">';
    print '<input type="checkbox" name="allowed_extensions[]" value="' . htmlspecialchars($ext) . '"' . $checked . ' style="margin-right: 8px;">';
    print '<span style="font-weight: 500;">.' . htmlspecialchars($ext) . '</span>';
        print '</label>';
    }
    
print '</div>';

print '<div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #dee2e6; font-size: 12px; color: #6c757d;">';
print '<strong>💡 Consejo:</strong> Selecciona solo las extensiones que realmente necesites para mejorar la seguridad';
print '</div>';
print '</div>';
print '</td>';
print '</tr>';

print '<tr class="liste_titre">';
print '<td colspan="2" class="center">';
print '<button type="submit" name="save_config" class="butAction">💾 Guardar Configuración</button>';
print '</td>';
print '</tr>';

print '</table>';
print '</form>';
print '</div>';

// Contenido de backups (oculto por defecto)
print '<div id="backupContent" style="display: none;">';

// Estado del sistema de backup (siempre disponible con ZipWriter PHP puro)
$zipAvailable = true; // ZipWriter funciona sin dependencias externas

// Cargar backups existentes
$backupDir = DOL_DOCUMENT_ROOT.'/custom/filemanager/backups';
dol_mkdir($backupDir);
$backups = array();
if (is_dir($backupDir)) {
    foreach (glob($backupDir.'/*.zip') as $f) {
        $backups[] = array(
            'file' => basename($f),
            'size' => filesize($f),
            'time' => filemtime($f)
        );
    }
    usort($backups, function($a,$b){ return $b['time'] <=> $a['time']; });
}


// Botones de acción según especificaciones del TXT
$createUrl = dol_buildpath('/custom/filemanager/scripts/create_backup.php', 1);
$downloadUrl = dol_buildpath('/custom/filemanager/scripts/download_backup.php', 1);
$disabled = $zipAvailable ? '' : ' disabled';

print '<div class="tabsAction" style="margin-bottom: 20px;">';
print '<button class="butAction" onclick="analyzeDatabase()" id="btnAnalyze">📊 Analizar Base de Datos</button>';
print '<button class="butAction" onclick="startBackup(\'database_only\')" id="btnDatabase" style="display:none;">Solo Base de Datos</button>';
print '<button class="butAction" onclick="startBackup(\'files_only\')" id="btnFiles">Solo Archivos</button>';
    print '</div>';

// Panel de estadísticas de base de datos
print '<div id="databaseStats" style="display: none; background: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; border-radius: 8px; margin-bottom: 20px;">';
print '<h4 style="margin: 0 0 15px 0; color: #495057;"><i class="fas fa-database"></i> Análisis de Base de Datos</h4>';
print '<div id="statsContent"></div>';
print '<div style="margin-top: 15px; text-align: center;">';
print '<button onclick="confirmBackup()" style="background: #28a745; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin-right: 10px;">✅ Confirmar Backup</button>';
print '<button onclick="cancelBackup()" style="background: #dc3545; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">❌ Cancelar</button>';
    print '</div>';
print '</div>';

// Barra de progreso y log
print '<div id="backupProgress" style="display: none; background: #f8f9fa; padding: 20px; border: 1px solid #dee2e6; border-radius: 8px; margin-bottom: 20px;">';
print '<h4 style="margin: 0 0 15px 0; color: #495057;"><i class="fas fa-cogs"></i> Generando Backup</h4>';
print '<div style="background: #e9ecef; border-radius: 10px; height: 25px; margin-bottom: 15px; overflow: hidden;">';
print '<div id="progressBar" style="background: linear-gradient(90deg, #007bff, #28a745); height: 100%; width: 0%; border-radius: 10px; transition: width 0.5s ease; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold; font-size: 12px;">0%</div>';
print '</div>';
print '<div style="display: flex; justify-content: space-between; margin-bottom: 15px; font-size: 14px; color: #666;">';
print '<span id="progressText">Iniciando...</span>';
print '<span id="progressTime">Tiempo: 0s</span>';
print '</div>';
print '<div id="backupLog" style="background: #000; color: #00ff00; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 12px; height: 200px; overflow-y: auto; white-space: pre-wrap;"></div>';
print '<div style="margin-top: 15px; text-align: center;">';
print '<button onclick="stopBackup()" style="background: #dc3545; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">⏹️ Detener</button>';
print '</div>';
print '</div>';


// Listado de backups
print '<br>';
print load_fiche_titre('🗂️ Backups disponibles', '', 'title_setup');

print '<div class="div-table-responsive">';
print '<table class="liste centpercent">';
print '<tr class="liste_titre">';
print '<th>Archivo</th><th>Tamaño</th><th>Fecha</th><th class="right">Acciones</th>';
print '</tr>';

if (empty($backups)) {
    print '<tr class="oddeven"><td colspan="4" class="opacitymedium center">No hay backups disponibles</td></tr>';
} else {
    $var=true;
    foreach ($backups as $b) {
        $var=!$var;
        $href = $downloadUrl.'?filename='.rawurlencode($b['file']).'&token='.$token;
        $deleteUrl = dol_buildpath('/custom/filemanager/scripts/delete_backup.php', 1);
        
        print '<tr class="'.($var?'oddeven':'even').'">';
        print '<td><strong>'.dol_escape_htmltag($b['file']).'</strong></td>';
        print '<td>'.round($b['size'] / 1024 / 1024, 2).' MB</td>';
        print '<td>'.dol_print_date($b['time'],'dayhour').'</td>';
        print '<td class="right">';
        print '<a class="butAction" href="'.$href.'" title="Descargar backup"><i class="fas fa-download"></i> Descargar</a>';
        print ' <a class="butActionDelete" href="javascript:void(0)" onclick="deleteBackup(\''.dol_escape_js($b['file']).'\', \''.$deleteUrl.'\')" title="Eliminar backup"><i class="fas fa-trash"></i> Borrar</a>';
        print '</td>';
        print '</tr>';
    }
}
print '</table>';
print '</div>';

print '</div>';

// Obtener filtros de la URL
$filter_action = $_GET['action'] ?? '';
$filter_user = $_GET['user'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

// Contenido de logs (oculto por defecto)
print '<div id="logsContent" style="display: none;">';
print '<div style="background: #f8f9fa; padding: 15px; border: 1px solid #dee2e6; border-radius: 4px; margin-bottom: 15px;">';
print '<h4 style="margin: 0 0 10px 0; color: #495057;"><i class="fas fa-file-alt"></i> Logs de Actividad del FileManager</h4>';
print '<p style="margin: 0 0 15px 0; color: #6c757d; font-size: 14px;">Revisa la actividad reciente del administrador de archivos.</p>';

// Filtros de logs
print '<div style="background: white; padding: 15px; border: 1px solid #dee2e6; border-radius: 4px; margin-bottom: 15px;">';
print '<h5 style="margin: 0 0 15px 0; color: #495057;"><i class="fas fa-filter"></i> Filtros</h5>';

print '<div style="display: flex; gap: 15px; flex-wrap: wrap; align-items: end;">';

// Filtro por acción
print '<div style="flex: 1; min-width: 200px;">';
print '<label style="display: block; margin-bottom: 5px; font-weight: bold; color: #495057;">Tipo de Acción:</label>';
print '<select id="filterAction" style="width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px;">';
print '<option value="">Todas las acciones</option>';
$actions = array(
    'create_folder' => 'Crear carpeta',
    'upload_file' => 'Subir archivo',
    'move_to_trash' => 'Enviar a papelera',
    'bulk_move_to_trash' => 'Envío masivo a papelera',
    'permanent_delete_file' => 'Eliminar archivo permanentemente',
    'permanent_delete_folder' => 'Eliminar carpeta permanentemente',
    'bulk_permanent_delete' => 'Eliminación masiva permanente',
    'restore_from_trash' => 'Restaurar desde papelera',
    'empty_trash' => 'Vaciar papelera',
    'clean_trash' => 'Limpiar papelera'
);
foreach ($actions as $value => $label) {
    $selected = ($filter_action === $value) ? ' selected' : '';
    print '<option value="' . htmlspecialchars($value) . '"' . $selected . '>' . htmlspecialchars($label) . '</option>';
}
print '</select>';
print '</div>';

// Filtro por usuario
print '<div style="flex: 1; min-width: 200px;">';
print '<label style="display: block; margin-bottom: 5px; font-weight: bold; color: #495057;">Usuario:</label>';
print '<select id="filterUser" style="width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px;">';
print '<option value="">Todos los usuarios</option>';
foreach ($users as $user_name) {
    $selected = ($filter_user === $user_name) ? ' selected' : '';
    print '<option value="' . htmlspecialchars($user_name) . '"' . $selected . '>' . htmlspecialchars($user_name) . '</option>';
}
print '</select>';
print '</div>';

// Filtro por fecha desde
print '<div style="flex: 1; min-width: 150px;">';
print '<label style="display: block; margin-bottom: 5px; font-weight: bold; color: #495057;">Desde:</label>';
print '<input type="date" id="filterDateFrom" value="' . htmlspecialchars($filter_date_from) . '" style="width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px;">';
print '</div>';

// Filtro por fecha hasta
print '<div style="flex: 1; min-width: 150px;">';
print '<label style="display: block; margin-bottom: 5px; font-weight: bold; color: #495057;">Hasta:</label>';
print '<input type="date" id="filterDateTo" value="' . htmlspecialchars($filter_date_to) . '" style="width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px; font-size: 14px;">';
print '</div>';

// Botones de filtro
print '<div style="flex: 0 0 auto; display: flex; gap: 10px;">';
print '<button onclick="applyLogFilters()" style="background: #007bff; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 14px;"><i class="fas fa-search"></i> Filtrar</button>';
print '<button onclick="clearLogFilters()" style="background: #6c757d; color: white; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer; font-size: 14px;"><i class="fas fa-times"></i> Limpiar</button>';
print '</div>';

print '</div>';
print '</div>';
print '</div>';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre">';
print '<td colspan="5">Logs de Actividad</td>';
print '</tr>';
    print '<tr class="liste_titre">';
    print '<td>Fecha/Hora</td>';
    print '<td>Usuario</td>';
    print '<td>Acción</td>';
    print '<td>Ruta</td>';
    print '<td>Detalles</td>';
    print '</tr>';
    
// Consultar logs de la base de datos con filtros
$logs = array();
if ($db && !empty($db->database_name)) {
    $sql = "SELECT * FROM llx_filemanager_logs WHERE 1=1";
    $params = array();
    
    // Filtro por acción
    if (!empty($filter_action)) {
        $sql .= " AND action = ?";
        $params[] = $filter_action;
    }
    
    // Filtro por usuario
    if (!empty($filter_user)) {
        $sql .= " AND user_name = ?";
        $params[] = $filter_user;
    }
    
    // Filtro por fecha desde
    if (!empty($filter_date_from)) {
        $sql .= " AND DATE(date_action) >= ?";
        $params[] = $filter_date_from;
    }
    
    // Filtro por fecha hasta
    if (!empty($filter_date_to)) {
        $sql .= " AND DATE(date_action) <= ?";
        $params[] = $filter_date_to;
    }
    
    $sql .= " ORDER BY date_action DESC LIMIT 100";
    
    $resql = $db->query($sql, $params);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $logs[] = $obj;
        }
    }
}

// Obtener lista de usuarios únicos para el filtro
$users = array();
if ($db && !empty($db->database_name)) {
    $sql_users = "SELECT DISTINCT user_name FROM llx_filemanager_logs ORDER BY user_name";
    $resql_users = $db->query($sql_users);
    if ($resql_users) {
        while ($obj = $db->fetch_object($resql_users)) {
            $users[] = $obj->user_name;
        }
    }
}

if (empty($logs)) {
    print '<tr class="oddeven">';
    print '<td colspan="5" style="text-align: center; color: #6c757d; padding: 20px;">No hay logs disponibles</td>';
    print '</tr>';
} else {
    foreach ($logs as $log) {
        print '<tr class="oddeven">';
        print '<td>' . dol_print_date($log->date_action, 'dayhour') . '</td>';
        print '<td>' . htmlspecialchars($log->user_name ?? 'Usuario desconocido') . '</td>';
        print '<td>' . htmlspecialchars($log->action) . '</td>';
        print '<td>' . htmlspecialchars($log->file_path) . '</td>';
        print '<td>' . htmlspecialchars($log->ip_address) . '</td>';
        print '</tr>';
    }
}

print '</table>';

print '</div>';

llxFooter();
$db->close();
?>

<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
function switchSetupTab(tabName) {
    console.log("Cambiando a pestaña:", tabName);
    
    // Actualizar URL con el parámetro de pestaña
    var url = new URL(window.location);
    url.searchParams.set('tab', tabName);
    window.history.pushState({}, '', url);
    
    // Obtener elementos
    var configTab = document.getElementById("configTab");
    var backupTab = document.getElementById("backupTab");
    var logsTab = document.getElementById("logsTab");
    var configContent = document.getElementById("configContent");
    var backupContent = document.getElementById("backupContent");
    var logsContent = document.getElementById("logsContent");
    
    // Verificar que existen los elementos
    if (!configTab || !backupTab || !logsTab) {
        console.error("No se encontraron los elementos de pestañas");
        return false;
    }
    
    // Reset all tabs
    [configTab, backupTab, logsTab].forEach(function(tab) {
        if (tab) {
            tab.style.background = "#f8f9fa";
            tab.style.color = "#495057";
            tab.classList.remove("active");
        }
    });
    
    // Hide all content
    [configContent, backupContent, logsContent].forEach(function(content) {
        if (content) content.style.display = "none";
    });
    
    // Show selected tab and content
    if (tabName === "config" && configTab && configContent) {
        configTab.style.background = "#007bff";
        configTab.style.color = "white";
        configTab.classList.add("active");
        configContent.style.display = "block";
    } else if (tabName === "backup" && backupTab && backupContent) {
        backupTab.style.background = "#007bff";
        backupTab.style.color = "white";
        backupTab.classList.add("active");
        backupContent.style.display = "block";
    } else if (tabName === "logs" && logsTab && logsContent) {
        logsTab.style.background = "#007bff";
        logsTab.style.color = "white";
        logsTab.classList.add("active");
        logsContent.style.display = "block";
    }
    
    return true;
}

function selectAllExtensions() {
    var checkboxes = document.querySelectorAll('input[name="allowed_extensions[]"]');
    checkboxes.forEach(function(checkbox) {
        checkbox.checked = true;
    });
}

function deselectAllExtensions() {
    var checkboxes = document.querySelectorAll('input[name="allowed_extensions[]"]');
    checkboxes.forEach(function(checkbox) {
        checkbox.checked = false;
    });
}

function autoDetectPath() {
    document.getElementById("rootPathInput").value = "<?php echo htmlspecialchars(DOL_DOCUMENT_ROOT); ?>";
}

// Variables globales para el backup
let currentBackupId = null;
let progressInterval = null;
let startTime = null;
let databaseStats = null;
let immediateProgressInterval = null;

function startBackup(type) {
    console.log('DEBUG: Iniciando backup tipo:', type);
    
    // Deshabilitar botones
    document.getElementById('btnDatabase').disabled = true;
    document.getElementById('btnFiles').disabled = true;
    
    // Mostrar progreso
    document.getElementById('backupProgress').style.display = 'block';
    document.getElementById('progressBar').style.width = '0%';
    document.getElementById('progressBar').textContent = '0%';
    document.getElementById('progressText').textContent = 'Iniciando backup...';
    document.getElementById('backupLog').textContent = '';
    document.getElementById('progressTime').textContent = 'Tiempo: 0s';
    
    startTime = Date.now();
    
    // Generar ID de backup
    currentBackupId = new Date().toISOString().replace(/[-:T]/g, '').substring(0, 14);
    console.log('DEBUG: Backup ID generado:', currentBackupId);
    
    // Iniciar progreso inmediato
    startImmediateProgress();
    
    // Iniciar monitoreo de progreso inmediatamente
    startProgressMonitoring();
    
    // Ejecutar backup en background usando iframe oculto
    const iframe = document.createElement('iframe');
    iframe.style.display = 'none';
    iframe.src = '<?php echo dol_buildpath("/custom/filemanager/scripts/create_backup_simple.php", 1); ?>?backup_type=' + type + '&backup_id=' + currentBackupId + '&automatic=0';
    document.body.appendChild(iframe);
    
    console.log('DEBUG: Iframe creado para backup en background');
    
    // Limpiar iframe después de 10 segundos
    setTimeout(() => {
        if (iframe.parentNode) {
            iframe.parentNode.removeChild(iframe);
            console.log('DEBUG: Iframe removido');
        }
    }, 10000);
}

function startProgressMonitoring() {
    console.log('DEBUG: Iniciando monitoreo de progreso para ID:', currentBackupId);
    
    // Detener progreso inmediato
    if (immediateProgressInterval) {
        clearInterval(immediateProgressInterval);
        immediateProgressInterval = null;
    }
    
    progressInterval = setInterval(() => {
        const progressUrl = '<?php echo dol_buildpath("/custom/filemanager/scripts/get_progress.php", 1); ?>?backup_id=' + currentBackupId;
        console.log('DEBUG: Consultando progreso:', progressUrl);
        
        fetch(progressUrl)
        .then(response => {
            console.log('DEBUG: Progress response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('DEBUG: Progress data:', data);
            updateProgress(data.progress, data.log);
            
            if (data.completed) {
        clearInterval(progressInterval);
                if (data.error) {
                    showError('Error en el backup');
                } else {
                    showSuccess('Backup completado exitosamente');
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                }
            }
        })
        .catch(error => {
            console.error('DEBUG: Error obteniendo progreso:', error);
        });
    }, 1000);
}

function updateProgress(percent, log) {
    document.getElementById('progressBar').style.width = percent + '%';
    document.getElementById('progressBar').textContent = percent + '%';
    document.getElementById('progressText').textContent = 'Progreso: ' + percent + '%';
    
    if (log) {
        document.getElementById('backupLog').textContent = log;
        document.getElementById('backupLog').scrollTop = document.getElementById('backupLog').scrollHeight;
    }
    
    if (startTime) {
        const elapsed = Math.floor((Date.now() - startTime) / 1000);
        document.getElementById('progressTime').textContent = 'Tiempo: ' + elapsed + 's';
    }
    
    // Habilitar botones cuando se complete
    if (percent >= 100) {
        document.getElementById('btnDatabase').disabled = false;
        document.getElementById('btnFiles').disabled = false;
    }
}

function stopBackup() {
    if (progressInterval) {
        clearInterval(progressInterval);
    }
    
    if (immediateProgressInterval) {
        clearInterval(immediateProgressInterval);
        immediateProgressInterval = null;
    }
    
    // Rehabilitar botones
    document.getElementById('btnDatabase').disabled = false;
    document.getElementById('btnFiles').disabled = false;
    
    // Ocultar progreso
    document.getElementById('backupProgress').style.display = 'none';
    
    currentBackupId = null;
}

function showError(message) {
    document.getElementById('backupLog').textContent += '\n❌ ERROR: ' + message;
    document.getElementById('backupLog').scrollTop = document.getElementById('backupLog').scrollHeight;
}

function showSuccess(message) {
    document.getElementById('backupLog').textContent += '\n✅ ' + message;
    document.getElementById('backupLog').scrollTop = document.getElementById('backupLog').scrollHeight;
}

function analyzeDatabase() {
    console.log('Analizando base de datos...');
    
    // Mostrar panel de estadísticas
    document.getElementById('databaseStats').style.display = 'block';
    document.getElementById('statsContent').innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Analizando base de datos...</div>';
    
    // Ocultar botón de análisis
    document.getElementById('btnAnalyze').style.display = 'none';
    
    fetch('<?php echo dol_buildpath("/custom/filemanager/scripts/analyze_database.php", 1); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'token=<?php echo $token; ?>'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            databaseStats = data.stats;
            displayStats(data.stats);
                } else {
            showError('Error analizando base de datos: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showError('Error de conexión: ' + error.message);
    });
}

function startImmediateProgress() {
    let progress = 0;
    immediateProgressInterval = setInterval(() => {
        progress += Math.random() * 3; // Incremento aleatorio entre 0-3%
        if (progress > 15) progress = 15; // Máximo 15% hasta que llegue el progreso real
        
        document.getElementById('progressBar').style.width = progress + '%';
        document.getElementById('progressBar').textContent = Math.round(progress) + '%';
        
        // Actualizar tiempo
        const elapsed = Math.floor((Date.now() - startTime) / 1000);
        document.getElementById('progressTime').textContent = 'Tiempo: ' + elapsed + 's';
        
        // Agregar mensajes de progreso
        if (progress < 5) {
            document.getElementById('progressText').textContent = 'Iniciando backup...';
        } else if (progress < 10) {
            document.getElementById('progressText').textContent = 'Preparando proceso...';
            } else {
            document.getElementById('progressText').textContent = 'Conectando a base de datos...';
        }
    }, 500); // Actualizar cada 500ms
}

function displayStats(stats) {
    let html = '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">';
    
    // Estadísticas generales
    html += '<div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #dee2e6;">';
    html += '<h5 style="margin: 0 0 10px 0; color: #495057;">📊 Estadísticas Generales</h5>';
    html += '<p style="margin: 5px 0;"><strong>Total de Tablas:</strong> ' + stats.total_tables + '</p>';
    html += '<p style="margin: 5px 0;"><strong>Total de Registros:</strong> ' + stats.total_records.toLocaleString() + '</p>';
    html += '<p style="margin: 5px 0;"><strong>Tamaño Base de Datos:</strong> ' + stats.estimated_size_mb + ' MB</p>';
    html += '<p style="margin: 5px 0;"><strong>Tamaño ZIP Estimado:</strong> ' + stats.estimated_zip_mb + ' MB</p>';
    html += '</div>';
    
    // Distribución de tablas
    html += '<div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #dee2e6;">';
    html += '<h5 style="margin: 0 0 10px 0; color: #495057;">📈 Distribución de Tablas</h5>';
    html += '<p style="margin: 5px 0;"><strong>Todas las Tablas:</strong> ' + stats.total_tables + ' (se exportarán TODOS los datos)</p>';
    html += '<p style="margin: 5px 0;"><strong>Sin Límites:</strong> Todas las tablas exportarán datos completos</p>';
    html += '</div>';
    html += '</div>';
    
    // Top 10 tablas más grandes
    html += '<div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #dee2e6;">';
    html += '<h5 style="margin: 0 0 10px 0; color: #495057;">🔝 Top 10 Tablas Más Grandes</h5>';
    html += '<div style="max-height: 200px; overflow-y: auto;">';
    html += '<table style="width: 100%; font-size: 12px;">';
    html += '<tr style="background: #f8f9fa;"><th style="padding: 5px; text-align: left;">Tabla</th><th style="padding: 5px; text-align: right;">Registros</th><th style="padding: 5px; text-align: right;">Tamaño</th><th style="padding: 5px; text-align: center;">Exportar</th></tr>';
    
    for (let i = 0; i < Math.min(10, stats.tables_detail.length); i++) {
        const table = stats.tables_detail[i];
        const willExport = '✅ TODOS los datos';
        const rowColor = i % 2 === 0 ? '#f8f9fa' : 'white';
        
        html += '<tr style="background: ' + rowColor + ';">';
        html += '<td style="padding: 5px;">' + table.name + '</td>';
        html += '<td style="padding: 5px; text-align: right;">' + table.records.toLocaleString() + '</td>';
        html += '<td style="padding: 5px; text-align: right;">' + table.size_mb + ' MB</td>';
        html += '<td style="padding: 5px; text-align: center;">' + willExport + '</td>';
        html += '</tr>';
    }
    
    html += '</table>';
    html += '</div>';
    html += '</div>';
    
    document.getElementById('statsContent').innerHTML = html;
}

function confirmBackup() {
    // Ocultar panel de estadísticas
    document.getElementById('databaseStats').style.display = 'none';
    
    // Mostrar botón de backup y ejecutar
    document.getElementById('btnDatabase').style.display = 'inline-block';
    startBackup('database_only');
}

function cancelBackup() {
    // Ocultar panel de estadísticas
    document.getElementById('databaseStats').style.display = 'none';
    
    // Mostrar botón de análisis
    document.getElementById('btnAnalyze').style.display = 'inline-block';
}

function deleteBackup(filename, deleteUrl) {
    // Confirmación con SweetAlert2
    Swal.fire({
        title: '¿Eliminar backup?',
        html: '<div style="text-align: left; margin: 20px 0;">' +
              '<p><strong>Archivo:</strong> ' + filename + '</p>' +
              '<p style="color: #dc3545; font-weight: bold;">⚠️ ADVERTENCIA: Esta acción NO se puede revertir</p>' +
              '<p>El backup será eliminado permanentemente del servidor.</p>' +
              '</div>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        reverseButtons: true
    }).then(function(result) {
        if (result.isConfirmed) {
            // Mostrar loading
            Swal.fire({
                title: 'Eliminando...',
                text: 'Eliminando backup del servidor',
                allowOutsideClick: false,
                didOpen: function() {
                    Swal.showLoading();
                }
            });
            
            // Enviar petición de eliminación
            fetch(deleteUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'filename=' + encodeURIComponent(filename) + '&token=<?php echo $token; ?>'
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Backup eliminado',
                        text: 'El backup ha sido eliminado correctamente'
                    }).then(function() {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'No se pudo eliminar el backup'
                    });
                }
            })
            .catch(function(error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de conexión',
                    text: 'No se pudo conectar con el servidor'
                });
            });
        }
    });
}

// Inicializar cuando se carga la página
document.addEventListener("DOMContentLoaded", function() {
    console.log("DOM cargado, inicializando pestañas...");
    
    // Detectar pestaña activa desde la URL
    var urlParams = new URLSearchParams(window.location.search);
    var activeTab = urlParams.get('tab');
    
    if (activeTab && ['config', 'backup', 'logs'].includes(activeTab)) {
        console.log("Pestaña activa desde URL:", activeTab);
        switchSetupTab(activeTab);
    } else {
        console.log("Usando pestaña por defecto: config");
        switchSetupTab('config');
    }
    
    console.log("Función switchSetupTab disponible");
});

// Funciones para filtros de logs
function applyLogFilters() {
    var action = document.getElementById('filterAction').value;
    var user = document.getElementById('filterUser').value;
    var dateFrom = document.getElementById('filterDateFrom').value;
    var dateTo = document.getElementById('filterDateTo').value;
    
    var url = new URL(window.location);
    url.searchParams.set('tab', 'logs');
    
    if (action) url.searchParams.set('action', action);
    else url.searchParams.delete('action');
    
    if (user) url.searchParams.set('user', user);
    else url.searchParams.delete('user');
    
    if (dateFrom) url.searchParams.set('date_from', dateFrom);
    else url.searchParams.delete('date_from');
    
    if (dateTo) url.searchParams.set('date_to', dateTo);
    else url.searchParams.delete('date_to');
    
    window.location.href = url.toString();
}

function clearLogFilters() {
    var url = new URL(window.location);
    url.searchParams.set('tab', 'logs');
    url.searchParams.delete('action');
    url.searchParams.delete('user');
    url.searchParams.delete('date_from');
    url.searchParams.delete('date_to');
    
    window.location.href = url.toString();
}
</script>