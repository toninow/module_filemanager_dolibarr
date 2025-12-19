<?php
/**
 * FileManager - Acciones del backend
 */

// Configurar manejo de errores para JSON
error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Suprimir warnings de propiedades dinámicas deprecadas (PHP 8.2+)
ini_set('error_reporting', E_ALL & ~E_DEPRECATED & ~E_STRICT);

// Función para manejar errores y devolver JSON
function returnJsonError($message, $code = 500) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

function returnJsonSuccess($message, $data = null) {
    header('Content-Type: application/json');
    $response = ['success' => true, 'message' => $message];
    if ($data !== null) {
        $response['data'] = $data;
    }
    echo json_encode($response);
    exit;
}

// Manejar errores fatales
set_error_handler(function($severity, $message, $file, $line) {
    // Solo ignorar warnings de deprecación relacionados con propiedades dinámicas de Dolibarr
    if (strpos($message, 'Creation of dynamic property') !== false || strpos($message, 'dynamic property') !== false) {
        return false; // Dejar que Dolibarr lo maneje
    }
    // Para errores críticos, reportarlos
    if ($severity === E_ERROR || $severity === E_PARSE || $severity === E_CORE_ERROR || $severity === E_COMPILE_ERROR) {
        returnJsonError("Error PHP: $message en $file línea $line");
    }
    // Para otros errores, permitir que continúe
    return false;
});

set_exception_handler(function($exception) {
    returnJsonError("Excepción: " . $exception->getMessage());
});

// Incluir entorno de Dolibarr
require_once '../../main.inc.php';
require_once 'lib/filemanager.lib.php';

// Verificar token CSRF (permitir GET o POST, excepto para previews)
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// No requerir token para previews (pero validar ruta)
if ($action !== 'preview' && empty($token)) {
    returnJsonError('Token requerido', 403);
}

// Verificar permisos
if (!$user->admin) {
    returnJsonError('Acceso denegado', 403);
}

// Obtener configuración y ruta raíz
$config = getFileManagerConfig();
$FILEMANAGER_ROOT = $config['FILEMANAGER_ROOT_PATH'];
$DOLIBARR_ROOT = DOL_DOCUMENT_ROOT;

// Función de seguridad: verificar que la ruta esté dentro de Dolibarr
function isPathSafe($path) {
    global $DOLIBARR_ROOT;
    
    // SEGURIDAD: Rechazar rutas con path traversal
    if (strpos($path, '..') !== false) {
        return false;
    }
    
    // SEGURIDAD: Rechazar caracteres nulos
    if (strpos($path, "\0") !== false) {
        return false;
    }
    
    // Normalizar rutas
    $realDolibarrRoot = realpath($DOLIBARR_ROOT);
    if (!$realDolibarrRoot) {
        return false;
    }
    
    // Normalizar la ruta actual
    $realPath = realpath($path);
    
    // Si la ruta no existe, validar la ruta del directorio padre
    if (!$realPath) {
        $dirPath = dirname($path);
        $realDirPath = realpath($dirPath);
        
        if (!$realDirPath) {
            return false;
        }
        
        // Verificar que el directorio padre esté dentro de Dolibarr
        return strpos($realDirPath, $realDolibarrRoot) === 0;
    }
    
    // Verificar que la ruta esté dentro de Dolibarr
    return strpos($realPath, $realDolibarrRoot) === 0;
}

// Función de seguridad: sanitizar nombres de archivo/carpeta
function sanitizeFileName($name) {
    // Eliminar caracteres peligrosos
    $name = preg_replace('/[<>:"\/\\\\|?*\x00-\x1f]/', '', $name);
    // Eliminar puntos al inicio y final
    $name = trim($name, '.');
    // Limitar longitud
    $name = substr($name, 0, 255);
    // Si queda vacío, usar nombre genérico
    if (empty($name)) {
        $name = 'unnamed_' . time();
    }
    return $name;
}

// El action ya fue obtenido arriba para la validación de token

if (empty($action)) {
    returnJsonError('Acción no especificada');
}

switch ($action) {
    case 'create_folder':
        $folder_name = $_POST['folder_name'] ?? '';
        $current_path = $_POST['current_path'] ?? '';
        
        if (empty($folder_name) || empty($current_path)) {
            returnJsonError('Parámetros requeridos faltantes');
        }
        
        // SEGURIDAD: Sanitizar nombre de carpeta
        $folder_name = sanitizeFileName($folder_name);
        
        // Validar que la ruta esté dentro de Dolibarr
        if (!isPathSafe($current_path)) {
            returnJsonError('Ruta no válida: debe estar dentro de Dolibarr', 403);
        }
        
        $new_folder_path = $current_path . '/' . $folder_name;
        
        if (mkdir($new_folder_path, 0755, true)) {
            logFileManagerActivity('create_folder', $new_folder_path, $user->id, "Carpeta creada: $folder_name");
            returnJsonSuccess('Carpeta creada correctamente');
        } else {
            returnJsonError('No se pudo crear la carpeta');
        }
        break;
        
    case 'upload':
        $current_path = $_POST['current_path'] ?? '';
        
        if (empty($current_path)) {
            returnJsonError('Ruta actual no especificada');
        }
        
        // Validar que la ruta esté dentro de Dolibarr
        if (!isPathSafe($current_path)) {
            returnJsonError('Ruta no válida: debe estar dentro de Dolibarr', 403);
        }
        
        // Normalizar la ruta destino antes de validar
        $normalized_target = realpath($current_path);
        if ($normalized_target === false) {
            returnJsonError('La carpeta destino no existe: ' . $current_path);
        }
        
        $is_folder = isset($_POST['is_folder']) && $_POST['is_folder'] == '1';
        $file_paths = isset($_POST['file_paths']) ? $_POST['file_paths'] : [];
        
        $uploaded_count = 0;
        $errors = [];
        $folder_name = null;
        
        // Determinar si es un array de archivos (múltiples) o un solo archivo
        // PHP recibe archivos múltiples como array cuando se usa file[0], file[1], etc.
        if (isset($_FILES['file']) && is_array($_FILES['file']['name'])) {
            // Múltiples archivos (puede ser carpeta o archivos individuales)
            $files = $_FILES['file'];
            $file_count = count($files['name']);
            
            // Reorganizar estructura de archivos si viene como array indexado
            $file_array = [];
            if (isset($files['name'][0])) {
                // Estructura normal: file[0], file[1], etc.
                for ($i = 0; $i < $file_count; $i++) {
                    $file_array[] = [
                        'name' => $files['name'][$i],
                        'type' => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error' => $files['error'][$i],
                        'size' => $files['size'][$i]
                    ];
                }
            } else {
                // Estructura alternativa: puede venir diferente
                $file_array = $files;
            }
            
            for ($i = 0; $i < $file_count; $i++) {
                $file = isset($file_array[$i]) ? $file_array[$i] : [
                    'name' => $files['name'][$i] ?? '',
                    'error' => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                    'tmp_name' => $files['tmp_name'][$i] ?? ''
                ];
                
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $errors[] = 'Error en archivo: ' . ($file['name'] ?? 'desconocido');
                    continue;
                }
                
                $file_name = $file['name'];
                $file_tmp = $file['tmp_name'];
                
                if ($is_folder && isset($file_paths[$i])) {
                    // Es una carpeta: mantener estructura de directorios
                    $relative_path = $file_paths[$i];
                    
                    // Extraer el nombre de la carpeta raíz (primer segmento)
                    $path_parts = explode('/', $relative_path);
                    if (count($path_parts) > 1) {
                        $folder_name = $path_parts[0];
                    } else {
                        $folder_name = $file_name;
                    }
                    
                    // Construir ruta completa manteniendo estructura
                    $target_path = $normalized_target . '/' . $relative_path;
                    
                    // Crear directorios intermedios si no existen
                    $target_dir = dirname($target_path);
                    if (!is_dir($target_dir)) {
                        if (!mkdir($target_dir, 0755, true)) {
                            $errors[] = 'No se pudo crear directorio: ' . $target_dir;
                            continue;
                        }
                    }
                } else {
                    // Es un archivo individual
                    $target_path = $normalized_target . '/' . $file_name;
                }
                
                // Validar que la ruta destino esté dentro de Dolibarr
                if (!isPathSafe($target_path)) {
                    $errors[] = 'Ruta destino no válida para: ' . $file_name;
                    continue;
                }
                
                // Mover archivo
                if (move_uploaded_file($file_tmp, $target_path)) {
                    $uploaded_count++;
                    logFileManagerActivity('upload_file', $target_path, $user->id, "Archivo subido: " . $file_name);
                } else {
                    $errors[] = 'No se pudo subir: ' . $file_name;
                }
            }
        } elseif (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            // Un solo archivo (compatibilidad con código anterior)
            $file = $_FILES['file'];
            $target_path = $normalized_target . '/' . basename($file['name']);
            
            // Validar que la ruta destino esté dentro de Dolibarr
            if (!isPathSafe($target_path)) {
                returnJsonError('Ruta destino no válida: debe estar dentro de Dolibarr', 403);
            }
            
            if (move_uploaded_file($file['tmp_name'], $target_path)) {
                logFileManagerActivity('upload_file', $target_path, $user->id, "Archivo subido: " . basename($file['name']));
                returnJsonSuccess('Archivo subido correctamente');
            } else {
                returnJsonError('No se pudo subir el archivo');
            }
            return; // Salir temprano para archivo único
        } else {
            returnJsonError('No se recibieron archivos para subir');
            return;
        }
        
        // Respuesta para múltiples archivos
        if ($uploaded_count > 0) {
            $message = '';
            if ($is_folder) {
                $message = "Carpeta '{$folder_name}' subida correctamente con {$uploaded_count} archivo(s)";
            } else {
                $message = "{$uploaded_count} archivo(s) subido(s) correctamente";
            }
            
            if (!empty($errors)) {
                $message .= ". Errores: " . implode(', ', $errors);
            }
            
            returnJsonSuccess($message);
        } else {
            $error_msg = !empty($errors) ? implode(', ', $errors) : 'No se pudo subir ningún archivo';
            returnJsonError($error_msg);
        }
        break;
        
    case 'move_to_trash':
        $path = $_POST['path'] ?? '';
        $type = $_POST['type'] ?? 'file';
        
        if (empty($path)) {
            returnJsonError('Ruta no especificada');
        }
        
        // SEGURIDAD: Validar que la ruta esté dentro de Dolibarr
        if (!isPathSafe($path)) {
            returnJsonError('Ruta no válida: debe estar dentro de Dolibarr', 403);
        }
        
        if (!file_exists($path)) {
            returnJsonError('El archivo o carpeta no existe');
        }
        
        // Verificar que no se mueva la carpeta Papelera a sí misma
        if (basename($path) === 'Papelera') {
            returnJsonError('No se puede mover la carpeta Papelera a sí misma');
        }
        
        // Verificar que no se mueva algo que ya está en la papelera
        if (strpos($path, '/Papelera/') !== false || strpos($path, '\\Papelera\\') !== false) {
            returnJsonError('No se puede mover elementos que ya están en la papelera');
        }
        
        $trash_dir = dirname(__FILE__) . '/Papelera';
        if (!is_dir($trash_dir)) {
            mkdir($trash_dir, 0755, true);
        }
        
        $name = basename($path);
        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $name_without_ext = pathinfo($name, PATHINFO_FILENAME);
        
        // Crear nombre único en la papelera
        $counter = 1;
        $trash_path = $trash_dir . '/' . $name;
        while (file_exists($trash_path)) {
            if ($extension) {
                $trash_path = $trash_dir . '/' . $name_without_ext . '_' . $counter . '.' . $extension;
            } else {
                $trash_path = $trash_dir . '/' . $name . '_' . $counter;
            }
            $counter++;
        }
        
        // Normalizar rutas para Windows
        $normalized_path = str_replace('\\', '/', $path);
        $normalized_trash_path = str_replace('\\', '/', $trash_path);
        
        if (realpath($path) === realpath($trash_path)) {
            returnJsonError('No se puede mover un elemento a sí mismo');
        }
        
        if (rename($path, $trash_path)) {
            // Crear información de restauración
            createRestoreInfo($trash_path, $path, $name, $type);
            
            logFileManagerActivity('move_to_trash', $path, $user->id, "Movido a papelera: $name");
            returnJsonSuccess('Enviado a la papelera correctamente');
        } else {
            returnJsonError('No se pudo mover a la papelera');
        }
        break;
        
    case 'bulk_delete_with_module_check':
        // Obtener paths de POST (puede venir como FormData o URL encoded)
        $paths_json = $_POST['paths'] ?? '';
        
        if (empty($paths_json)) {
            returnJsonError('No se especificaron rutas para eliminar');
        }
        
        // Decodificar JSON
        $paths = json_decode($paths_json, true);
        
        // Si no es un array válido, puede ser que ya venga como array
        if (!is_array($paths)) {
            if (is_string($paths_json)) {
                $paths = json_decode(urldecode($paths_json), true);
            }
            if (!is_array($paths)) {
                returnJsonError('Formato de rutas inválido. Recibido: ' . substr($paths_json, 0, 100));
            }
        }
        
        if (empty($paths) || !is_array($paths)) {
            returnJsonError('No se especificaron rutas válidas para eliminar');
        }
        
        $deleted_count = 0;
        $errors = array();
        
        foreach ($paths as $path) {
            if (!file_exists($path)) {
                $errors[] = "No existe: $path";
                continue;
            }
            
            // Detectar si es un módulo externo
            $module_name = detectModuleFromPath($path);
            if ($module_name) {
                // Desactivar módulo en la base de datos
                deactivateModule($module_name);
            }
            
            // Mover a papelera
            $trash_dir = dirname(__FILE__) . '/Papelera';
            if (!is_dir($trash_dir)) {
                mkdir($trash_dir, 0755, true);
            }
            
            $name = basename($path);
            $extension = pathinfo($name, PATHINFO_EXTENSION);
            $name_without_ext = pathinfo($name, PATHINFO_FILENAME);
            
            $counter = 1;
            $trash_path = $trash_dir . '/' . $name;
            while (file_exists($trash_path)) {
                if ($extension) {
                    $trash_path = $trash_dir . '/' . $name_without_ext . '_' . $counter . '.' . $extension;
                } else {
                    $trash_path = $trash_dir . '/' . $name . '_' . $counter;
                }
                $counter++;
            }
            
            $normalized_path = str_replace('\\', '/', $path);
            $normalized_trash_path = str_replace('\\', '/', $trash_path);
            
            if (realpath($path) === realpath($trash_path)) {
                $errors[] = "No se puede mover a sí mismo: $path";
                continue;
            }
            
            if (rename($path, $trash_path)) {
                $type = is_dir($path) ? 'folder' : 'file';
                createRestoreInfo($trash_path, $path, $name, $type);
                logFileManagerActivity('bulk_move_to_trash', $path, $user->id, "Movido a papelera: $name");
                $deleted_count++;
            } else {
                $errors[] = "No se pudo mover: $path";
            }
        }
        
        $message = "Se procesaron " . count($paths) . " elementos. ";
        $message .= "Enviados a papelera: $deleted_count";
        
        if (!empty($errors)) {
            $message .= ". Errores: " . implode(', ', $errors);
        }
        
        returnJsonSuccess($message);
        break;
        
    case 'permanent_delete':
        $path = $_POST['path'] ?? '';
        
        if (empty($path)) {
            returnJsonError('Ruta no especificada');
        }
        
        if (!file_exists($path)) {
            returnJsonError('El archivo o carpeta no existe');
        }
        
        // Detectar si es un módulo externo
        $module_name = detectModuleFromPath($path);
        if ($module_name) {
            deactivateModule($module_name);
        }
        
        if (is_dir($path)) {
            if (removeDirectory($path)) {
                logFileManagerActivity('permanent_delete_folder', $path, $user->id, "Carpeta eliminada permanentemente");
                returnJsonSuccess('Carpeta eliminada permanentemente');
            } else {
                returnJsonError('No se pudo eliminar la carpeta');
            }
        } else {
            if (unlink($path)) {
                logFileManagerActivity('permanent_delete_file', $path, $user->id, "Archivo eliminado permanentemente");
                returnJsonSuccess('Archivo eliminado permanentemente');
            } else {
                returnJsonError('No se pudo eliminar el archivo');
            }
        }
        break;
        
    case 'bulk_restore':
        $paths_json = $_POST['paths'] ?? '';
        
        if (empty($paths_json)) {
            returnJsonError('No se especificaron rutas para restaurar');
        }
        
        $paths = json_decode($paths_json, true);
        if (!is_array($paths)) {
            returnJsonError('Formato de rutas inválido');
        }
        
        $restored_count = 0;
        $errors = array();
        $trash_base_dir = dirname(__FILE__) . '/Papelera';
        
        foreach ($paths as $trash_path) {
            if (!file_exists($trash_path)) {
                $errors[] = "No existe en papelera: $trash_path";
                continue;
            }
            
            // Determinar si es carpeta o archivo
            $is_folder = is_dir($trash_path);
            
            if ($is_folder) {
                // Si es carpeta, buscar .metadata.json OCULTO DENTRO de la carpeta
                $metadata_file = $trash_path . '/.metadata.json';
            } else {
                // Si es archivo, buscar .metadata.json OCULTO AL LADO del archivo
                $metadata_file = $trash_path . '.metadata.json';
            }
            
            $original_path = null;
            
            // Intentar leer metadata si existe
            if (file_exists($metadata_file)) {
                $metadata = json_decode(file_get_contents($metadata_file), true);
                if ($metadata && isset($metadata['original_path'])) {
                    $original_path = $metadata['original_path'];
                }
            }
            
            // Si no hay metadata, intentar inferir la ruta original
            if (!$original_path) {
                $file_name = basename($trash_path);
                
                // Intentar buscar en la raíz de Dolibarr como respaldo
                $possible_restore_path = DOL_DOCUMENT_ROOT . '/' . $file_name;
                
                // Si el archivo existe ya en la raíz, restaurar a una subcarpeta de respaldo
                if (file_exists($possible_restore_path)) {
                    $backup_restore_dir = DOL_DOCUMENT_ROOT . '/restored_from_trash';
                    if (!is_dir($backup_restore_dir)) {
                        mkdir($backup_restore_dir, 0755, true);
                    }
                    $original_path = $backup_restore_dir . '/' . $file_name;
                } else {
                    // Intentar restaurar a la raíz de Dolibarr
                    $original_path = $possible_restore_path;
                }
            }
            
            if (!$original_path || !isPathSafe(dirname($original_path))) {
                $errors[] = "No se puede determinar ruta original segura para: " . basename($trash_path);
                continue;
            }
            
            $original_dir = dirname($original_path);
            
            // Verificar si la ruta original existe (si existe, agregar contador)
            $counter = 1;
            $final_path = $original_path;
            while (file_exists($final_path)) {
                $path_parts = pathinfo($original_path);
                $dir = $path_parts['dirname'];
                $name_without_ext = $path_parts['filename'];
                $ext = isset($path_parts['extension']) ? '.' . $path_parts['extension'] : '';
                $final_path = $dir . '/' . $name_without_ext . '_' . $counter . $ext;
                $counter++;
            }
            
            // Crear directorio destino si no existe
            if (!is_dir($original_dir)) {
                mkdir($original_dir, 0755, true);
            }
            
            if (rename($trash_path, $final_path)) {
                // Eliminar archivos de metadata si existen
                if (file_exists($metadata_file)) {
                    unlink($metadata_file);
                }
                if ($is_folder && file_exists($trash_path . '/RESTAURAR.txt')) {
                    unlink($trash_path . '/RESTAURAR.txt');
                }
                
                $action_type = $is_folder ? 'bulk_restore_folder' : 'bulk_restore_file';
                logFileManagerActivity($action_type, $final_path, $user->id, "Restaurado desde papelera");
                $restored_count++;
            } else {
                $errors[] = "No se pudo restaurar: " . basename($trash_path);
            }
        }
        
        $message = "Se procesaron " . count($paths) . " elemento(s). ";
        $message .= "Restaurados: $restored_count";
        
        if (!empty($errors)) {
            $message .= ". Errores: " . count($errors);
            if (count($errors) <= 5) {
                $message .= " (" . implode(", ", $errors) . ")";
            }
        }
        
        returnJsonSuccess($message, array('restored' => $restored_count, 'errors' => $errors));
        break;
    
    case 'bulk_permanent_delete':
        $paths_json = $_POST['paths'] ?? '';
        
        if (empty($paths_json)) {
            returnJsonError('No se especificaron rutas para eliminar');
        }
        
        $paths = json_decode($paths_json, true);
        if (!is_array($paths)) {
            returnJsonError('Formato de rutas inválido');
        }
        
        $deleted_count = 0;
        $errors = array();
        
        foreach ($paths as $path) {
            if (!file_exists($path)) {
                $errors[] = "No existe: $path";
                continue;
            }
            
            // Detectar si es un módulo externo
            $module_name = detectModuleFromPath($path);
            if ($module_name) {
                deactivateModule($module_name);
            }
            
            if (is_dir($path)) {
                if (removeDirectory($path)) {
                    logFileManagerActivity('bulk_permanent_delete_folder', $path, $user->id, "Carpeta eliminada permanentemente");
                    $deleted_count++;
                } else {
                    $errors[] = "No se pudo eliminar carpeta: $path";
                }
            } else {
                if (unlink($path)) {
                    logFileManagerActivity('bulk_permanent_delete_file', $path, $user->id, "Archivo eliminado permanentemente");
                    $deleted_count++;
                } else {
                    $errors[] = "No se pudo eliminar archivo: $path";
                }
            }
        }
        
        $message = "Se procesaron " . count($paths) . " elementos. ";
        $message .= "Eliminados permanentemente: $deleted_count";
        
        if (!empty($errors)) {
            $message .= ". Errores: " . implode(', ', $errors);
        }
        
        returnJsonSuccess($message);
        break;
        
    case 'restore_from_trash':
        $path = $_POST['path'] ?? '';
        
        if (empty($path)) {
            returnJsonError('Ruta no especificada');
        }
        
        if (!file_exists($path)) {
            returnJsonError('El archivo o carpeta no existe en la papelera');
        }
        
        // Determinar si es carpeta o archivo
        $is_folder = is_dir($path);
        
        if ($is_folder) {
            // Si es carpeta, buscar .metadata.json OCULTO DENTRO de la carpeta
            $metadata_file = $path . '/.metadata.json';
        } else {
            // Si es archivo, buscar .metadata.json OCULTO AL LADO del archivo
            $metadata_file = $path . '.metadata.json';
        }
        
        $original_path = null;
        
        // Intentar leer metadata si existe
        if (file_exists($metadata_file)) {
            $metadata = json_decode(file_get_contents($metadata_file), true);
            if ($metadata && isset($metadata['original_path'])) {
                $original_path = $metadata['original_path'];
            }
        }
        
        // Si no hay metadata, intentar inferir la ruta original
        if (!$original_path) {
            $file_name = basename($path);
            // Intentar buscar en la raíz de Dolibarr como respaldo
            $possible_restore_path = DOL_DOCUMENT_ROOT . '/' . $file_name;
            
            // Si el archivo existe ya en la raíz, restaurar a una subcarpeta de respaldo
            if (file_exists($possible_restore_path)) {
                $backup_restore_dir = DOL_DOCUMENT_ROOT . '/restored_from_trash';
                if (!is_dir($backup_restore_dir)) {
                    mkdir($backup_restore_dir, 0755, true);
                }
                $original_path = $backup_restore_dir . '/' . $file_name;
            } else {
                // Intentar restaurar a la raíz de Dolibarr
                $original_path = $possible_restore_path;
            }
        }
        
        if (!$original_path || !isPathSafe(dirname($original_path))) {
            returnJsonError('No se puede determinar ruta original segura para: ' . basename($path));
        }
        
        $original_dir = dirname($original_path);
        
        // Si el archivo destino ya existe, eliminarlo primero (forzar)
        if (file_exists($original_path)) {
            if (is_dir($original_path)) {
                removeDirectory($original_path);
            } else {
                unlink($original_path);
            }
        }
        
        // Verificar si la ruta original existe después de eliminar (si existe, agregar contador)
        $counter = 1;
        $final_path = $original_path;
        while (file_exists($final_path)) {
            $path_parts = pathinfo($original_path);
            $dir = $path_parts['dirname'];
            $name_without_ext = $path_parts['filename'];
            $ext = isset($path_parts['extension']) ? '.' . $path_parts['extension'] : '';
            $final_path = $dir . '/' . $name_without_ext . '_' . $counter . $ext;
            $counter++;
        }
        
        // Crear directorio destino si no existe
        if (!is_dir($original_dir)) {
            mkdir($original_dir, 0755, true);
        }
        
        if (rename($path, $final_path)) {
            // Eliminar archivos de metadata si existen
            if (file_exists($metadata_file)) {
                unlink($metadata_file);
            }
            if ($is_folder && file_exists($path . '/RESTAURAR.txt')) {
                unlink($path . '/RESTAURAR.txt');
            }
            
            logFileManagerActivity('restore_from_trash', $final_path, $user->id, "Restaurado desde papelera");
            returnJsonSuccess('Restaurado correctamente');
        } else {
            returnJsonError('No se pudo restaurar. Verifique los permisos del directorio destino.');
        }
        break;
        
    case 'empty_trash':
        $trash_dir = dirname(__FILE__) . '/Papelera';
        
        if (!is_dir($trash_dir)) {
            returnJsonSuccess('La papelera ya está vacía');
        }
        
        $items = scandir($trash_dir);
        $deleted_count = 0;
        $errors = array();
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $item_path = $trash_dir . '/' . $item;
            
            // Detectar si es un módulo externo
            $module_name = detectModuleFromPath($item_path);
            if ($module_name) {
                deactivateModule($module_name);
            }
            
            if (is_dir($item_path)) {
                if (removeDirectory($item_path)) {
                    logFileManagerActivity('empty_trash_folder', $item_path, $user->id, "Carpeta eliminada de papelera");
                    $deleted_count++;
                } else {
                    $errors[] = "No se pudo eliminar carpeta: $item";
                }
            } else {
                if (unlink($item_path)) {
                    logFileManagerActivity('empty_trash_file', $item_path, $user->id, "Archivo eliminado de papelera");
                    $deleted_count++;
                } else {
                    $errors[] = "No se pudo eliminar archivo: $item";
                }
            }
        }
        
        $message = "Papelera vaciada. Elementos eliminados: $deleted_count";
        if (!empty($errors)) {
            $message .= ". Errores: " . implode(', ', $errors);
        }
        
        returnJsonSuccess($message);
        break;
        
    case 'clean_trash':
        $trash_dir = dirname(__FILE__) . '/Papelera';
        
        if (!is_dir($trash_dir)) {
            returnJsonSuccess('La papelera ya está vacía');
        }
        
        if (removeDirectory($trash_dir)) {
            mkdir($trash_dir, 0755, true);
            logFileManagerActivity('clean_trash', $trash_dir, $user->id, "Papelera limpiada completamente");
            returnJsonSuccess('Papelera limpiada correctamente');
        } else {
            returnJsonError('No se pudo limpiar la papelera');
        }
        break;
        
    case 'preview':
        $path = $_GET['path'] ?? '';
        
        if (empty($path)) {
            returnJsonError('Ruta no especificada');
        }
        
        // SEGURIDAD: Validar que la ruta esté dentro de Dolibarr
        if (!isPathSafe($path)) {
            returnJsonError('Ruta no válida: debe estar dentro de Dolibarr', 403);
        }
        
        if (!file_exists($path)) {
            returnJsonError('El archivo no existe');
        }
        
        // No bloquear archivos protegidos para administradores en preview
        // Permitir a administradores ver cualquier archivo del sistema
        
        $file_info = pathinfo($path);
        $extension = strtolower($file_info['extension'] ?? '');
        
        // Determinar el tipo MIME según la extensión
        $mime_types = array(
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf'
        );
        
        // Si es imagen o PDF, devolver con el MIME correcto
        if (isset($mime_types[$extension])) {
            $mime_type = $mime_types[$extension];
            header('Content-Type: ' . $mime_type);
            header('Content-Length: ' . filesize($path));
            header('Cache-Control: public, max-age=3600');
            readfile($path);
            exit;
        }
        
        // Para otros archivos, devolver como texto plano
        header('Content-Type: text/plain');
        header('Cache-Control: public, max-age=3600');
        
        if (in_array($extension, ['txt', 'log', 'md', 'json', 'xml', 'csv', 'sql', 'php', 'js', 'css', 'html', 'htm'])) {
            $max_size = 1024 * 1024; // 1MB
            if (filesize($path) > $max_size) {
                $content = file_get_contents($path, false, null, 0, $max_size);
                echo htmlspecialchars($content) . "\n\n... (archivo truncado, tamaño: " . formatBytes(filesize($path)) . ")";
            } else {
                echo htmlspecialchars(file_get_contents($path));
            }
        } else {
            echo htmlspecialchars(file_get_contents($path));
        }
        exit;
    
    case 'download':
        $path = $_GET['path'] ?? '';
        
        if (empty($path)) {
            returnJsonError('Ruta no especificada');
        }
        
        if (!file_exists($path)) {
            returnJsonError('El archivo no existe');
        }
        
        // Verificar que la ruta esté dentro de Dolibarr
        if (!isPathSafe($path)) {
            returnJsonError('Ruta no válida: debe estar dentro de Dolibarr', 403);
        }
        
        // Obtener información del archivo
        $filename = basename($path);
        $mime_type = getMimeType($path);
        
        // Enviar el archivo
        header('Content-Type: ' . $mime_type);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($path));
        
        readfile($path);
        exit;
    
    case 'download_folder':
        try {
            $path = $_GET['path'] ?? '';
            
            if (empty($path)) {
                returnJsonError('Ruta no especificada', 400);
            }
            
            // Decodificar la ruta si viene codificada
            $path = urldecode($path);
            
            if (!isPathSafe($path)) {
                returnJsonError('Ruta no válida o fuera del directorio permitido', 403);
            }
            
            if (!is_dir($path)) {
                returnJsonError('No es una carpeta válida', 404);
            }
            
            if (!is_readable($path)) {
                returnJsonError('No se tiene permisos para leer la carpeta', 403);
            }
            
            // Crear ZIP temporal
            $zip_name = basename($path) . '_' . date('YmdHis') . '.zip';
            $temp_dir = sys_get_temp_dir();
            if (!is_writable($temp_dir)) {
                returnJsonError('No se puede escribir en el directorio temporal', 500);
            }
            
            $zip_path = $temp_dir . '/' . $zip_name;
            
            // Verificar que ZipArchive esté disponible
            if (!class_exists('ZipArchive')) {
                returnJsonError('La extensión ZipArchive no está disponible en el servidor', 500);
            }
            
            $zip = new ZipArchive();
            $zip_result = $zip->open($zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            
            if ($zip_result !== TRUE) {
                $error_msg = 'No se pudo crear el archivo ZIP';
                switch ($zip_result) {
                    case ZipArchive::ER_EXISTS:
                        $error_msg = 'El archivo ZIP ya existe';
                        break;
                    case ZipArchive::ER_INCONS:
                        $error_msg = 'El archivo ZIP es inconsistente';
                        break;
                    case ZipArchive::ER_INVAL:
                        $error_msg = 'Argumento inválido para crear ZIP';
                        break;
                    case ZipArchive::ER_MEMORY:
                        $error_msg = 'Error de memoria al crear ZIP';
                        break;
                    case ZipArchive::ER_NOZIP:
                        $error_msg = 'No es un archivo ZIP válido';
                        break;
                    case ZipArchive::ER_OPEN:
                        $error_msg = 'No se pudo abrir el archivo ZIP';
                        break;
                    case ZipArchive::ER_READ:
                        $error_msg = 'Error de lectura al crear ZIP';
                        break;
                    case ZipArchive::ER_SEEK:
                        $error_msg = 'Error de búsqueda al crear ZIP';
                        break;
                }
                returnJsonError($error_msg . ' (código: ' . $zip_result . ')', 500);
            }
            
            // Función recursiva para añadir archivos
            if (!function_exists('addDirectoryToZip')) {
                function addDirectoryToZip($dir, $zip, $baseDir = '') {
                    if (!is_readable($dir)) {
                        return false;
                    }
                    $files = @scandir($dir);
                    if ($files === false) {
                        return false;
                    }
                    foreach ($files as $file) {
                        if ($file == '.' || $file == '..') continue;
                        $filePath = $dir . DIRECTORY_SEPARATOR . $file;
                        $localPath = ($baseDir ? $baseDir . '/' : '') . $file;
                        
                        if (is_dir($filePath)) {
                            if ($zip->addEmptyDir($localPath)) {
                                addDirectoryToZip($filePath, $zip, $localPath);
                            }
                        } else {
                            if (is_readable($filePath)) {
                                $zip->addFile($filePath, $localPath);
                            }
                        }
                    }
                    return true;
                }
            }
            
            addDirectoryToZip($path, $zip);
            $zip->close();
            
            if (!file_exists($zip_path)) {
                returnJsonError('El archivo ZIP no se creó correctamente', 500);
            }
            
            $zip_size = filesize($zip_path);
            if ($zip_size === false || $zip_size == 0) {
                @unlink($zip_path);
                returnJsonError('El archivo ZIP está vacío o no se pudo leer', 500);
            }
            
            // Enviar el ZIP
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $zip_name . '"');
            header('Content-Length: ' . $zip_size);
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            
            // Limpiar buffer de salida
            if (ob_get_level()) {
                ob_end_clean();
            }
            
            readfile($zip_path);
            
            // Eliminar archivo temporal
            @unlink($zip_path);
            exit;
        } catch (Exception $e) {
            error_log("Error en download_folder: " . $e->getMessage());
            if (isset($zip_path) && file_exists($zip_path)) {
                @unlink($zip_path);
            }
            returnJsonError('Error al crear el archivo ZIP: ' . $e->getMessage(), 500);
        }
        break;
        
    case 'cut_paste':
    case 'copy_paste':
        $dest_path = $_POST['dest_path'] ?? '';
        $items = $_POST['items'] ?? [];
        
        if (empty($dest_path)) {
            returnJsonError('Ruta destino no especificada');
        }
        
        if (empty($items) || !is_array($items)) {
            returnJsonError('No hay elementos para pegar');
        }
        
        // Validar que la ruta destino esté dentro de Dolibarr
        if (!isPathSafe($dest_path)) {
            returnJsonError('Ruta destino no válida: debe estar dentro de Dolibarr', 403);
        }
        
        if (!is_dir($dest_path)) {
            returnJsonError('La carpeta destino no existe');
        }
        
        $results = [];
        $errors = [];
        
        foreach ($items as $item) {
            $source_path = $item['path'] ?? '';
            $item_type = $item['type'] ?? 'file';
            
            if (empty($source_path) || !file_exists($source_path)) {
                $errors[] = "No existe: " . basename($source_path);
                continue;
            }
            
            // Verificar que el origen esté dentro de Dolibarr
            if (!isPathSafe($source_path)) {
                $errors[] = "Origen no válido: " . basename($source_path);
                continue;
            }
            
            // Verificar si está protegido (solo si no está permitido trabajar con protegidos)
            require_once __DIR__ . '/lib/filemanager.lib.php';
            $config = getFileManagerConfig();
            $config_val = isset($config['FILEMANAGER_ALLOW_PROTECTED_ACTIONS']) ? $config['FILEMANAGER_ALLOW_PROTECTED_ACTIONS'] : 0;
            $allow_protected = ($config_val == 1 || $config_val === '1' || $config_val === true || $config_val === 'true' || (int)$config_val > 0);
            
            $is_protected = isProtectedPath($source_path);
            if ($is_protected && !$allow_protected) {
                $errors[] = "Protegido (no se puede " . ($action === 'cut_paste' ? 'cortar' : 'copiar') . "): " . basename($source_path);
                continue;
            }
            
            // Verificar que no se mueva la carpeta Papelera
            if (basename($source_path) === 'Papelera') {
                $errors[] = "No se puede mover la carpeta Papelera";
                continue;
            }
            
            $name = basename($source_path);
            $target_path = $dest_path . DIRECTORY_SEPARATOR . $name;
            
            // Si ya existe, agregar contador
            $counter = 1;
            $original_target = $target_path;
            while (file_exists($target_path)) {
                $pathinfo = pathinfo($original_target);
                $extension = isset($pathinfo['extension']) ? '.' . $pathinfo['extension'] : '';
                $name_without_ext = $pathinfo['filename'];
                if ($item_type === 'folder') {
                    $target_path = $dest_path . DIRECTORY_SEPARATOR . $name . '_' . $counter;
                } else {
                    $target_path = $dest_path . DIRECTORY_SEPARATOR . $name_without_ext . '_' . $counter . $extension;
                }
                $counter++;
            }
            
            // Copiar o mover según la acción
            try {
                if ($action === 'cut_paste') {
                    // Mover (renombrar si está en el mismo sistema de archivos)
                    if (rename($source_path, $target_path)) {
                        logFileManagerActivity('cut_paste', $target_path, $user->id, "Elemento movido de " . dirname($source_path) . " a " . $dest_path);
                        $results[] = basename($target_path) . " movido correctamente";
                    } else {
                        $errors[] = "No se pudo mover: " . basename($source_path);
                    }
                } else {
                    // Copiar
                    if ($item_type === 'folder') {
                        // Copiar carpeta recursivamente
                        // Usar copyDirectory de la librería si existe, sino usar la función local
                        if (function_exists('copyDirectory')) {
                            $copy_result = copyDirectory($source_path, $target_path);
                        } else {
                            $copy_result = copyDirectoryAction($source_path, $target_path);
                        }
                        if ($copy_result) {
                            logFileManagerActivity('copy_paste', $target_path, $user->id, "Carpeta copiada de " . dirname($source_path) . " a " . $dest_path);
                            $results[] = basename($target_path) . " copiado correctamente";
                        } else {
                            $errors[] = "No se pudo copiar carpeta: " . basename($source_path);
                        }
                    } else {
                        // Copiar archivo
                        if (copy($source_path, $target_path)) {
                            logFileManagerActivity('copy_paste', $target_path, $user->id, "Archivo copiado de " . dirname($source_path) . " a " . $dest_path);
                            $results[] = basename($target_path) . " copiado correctamente";
                        } else {
                            $errors[] = "No se pudo copiar: " . basename($source_path);
                        }
                    }
                }
            } catch (Exception $e) {
                $errors[] = "Error: " . $e->getMessage() . " - " . basename($source_path);
            }
        }
        
        $message = '';
        if (!empty($results)) {
            $message .= "Éxito: " . implode(", ", $results) . ". ";
        }
        if (!empty($errors)) {
            $message .= "Errores: " . implode(", ", $errors);
        }
        
        if (empty($results) && !empty($errors)) {
            returnJsonError($message);
        } else {
            returnJsonSuccess($message, ['results' => $results, 'errors' => $errors]);
        }
        break;
        
    case 'delete_log':
        // SEGURIDAD: Verificar que el log_id sea un número válido
        $log_id = isset($_POST['log_id']) ? (int)$_POST['log_id'] : 0;
        
        if ($log_id <= 0) {
            returnJsonError('ID de log no válido');
        }
        
        // SEGURIDAD: Solo administradores pueden eliminar logs
        if (!$user->admin) {
            returnJsonError('Acceso denegado: solo administradores', 403);
        }
        
        // Eliminar el log de la base de datos
        $sql = "DELETE FROM llx_filemanager_logs WHERE rowid = " . ((int)$log_id);
        $resql = $db->query($sql);
        
        if ($resql) {
            if ($db->affected_rows($resql) > 0) {
                returnJsonSuccess('Registro eliminado correctamente');
            } else {
                returnJsonError('No se encontró el registro');
            }
        } else {
            returnJsonError('Error al eliminar el registro: ' . $db->lasterror());
        }
        break;
        
    case 'delete_all_logs':
        // SEGURIDAD: Solo administradores pueden eliminar todos los logs
        if (!$user->admin) {
            returnJsonError('Acceso denegado: solo administradores', 403);
        }
        
        // Eliminar TODOS los logs de la base de datos
        $sql = "DELETE FROM llx_filemanager_logs";
        $resql = $db->query($sql);
        
        if ($resql) {
            $affected = $db->affected_rows($resql);
            returnJsonSuccess("Se eliminaron $affected registros correctamente");
        } else {
            returnJsonError('Error al eliminar los registros: ' . $db->lasterror());
        }
        break;
        
    default:
        returnJsonError('Acción no válida');
}

// Función auxiliar para copiar directorio recursivamente
// Verificar si ya existe antes de definirla
if (!function_exists('copyDirectoryAction')) {
function copyDirectoryAction($source, $dest) {
    if (!is_dir($dest)) {
        if (!mkdir($dest, 0755, true)) {
            return false;
        }
    }
    
    $items = scandir($source);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        
        $source_item = $source . DIRECTORY_SEPARATOR . $item;
        $dest_item = $dest . DIRECTORY_SEPARATOR . $item;
        
        if (is_dir($source_item)) {
            if (!copyDirectoryAction($source_item, $dest_item)) {
                return false;
            }
        } else {
            if (!copy($source_item, $dest_item)) {
                return false;
            }
        }
    }
    
    return true;
}
} // Cerrar if function_exists
?>
