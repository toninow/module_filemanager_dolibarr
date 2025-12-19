<?php
/**
 * Script para eliminar backups
 */

// Incluir el entorno de Dolibarr
require_once '../../../main.inc.php';

// Verificar permisos
if (!$user->admin) {
    http_response_code(403);
    echo json_encode(array('success' => false, 'message' => 'Acceso denegado'));
    exit;
}

// Verificar token
$token = $_POST['token'] ?? '';
if (!newToken() || $token !== newToken()) {
    http_response_code(403);
    echo json_encode(array('success' => false, 'message' => 'Token inválido'));
    exit;
}

// Obtener nombre del archivo
$filename = $_POST['filename'] ?? '';
if (empty($filename)) {
    echo json_encode(array('success' => false, 'message' => 'Nombre de archivo no especificado'));
    exit;
}

// Validar nombre del archivo (solo archivos .zip en el directorio de backups)
if (!preg_match('/^[a-zA-Z0-9_\-\.]+\.zip$/', $filename)) {
    echo json_encode(array('success' => false, 'message' => 'Nombre de archivo inválido'));
    exit;
}

// Ruta del archivo
$backupDir = DOL_DOCUMENT_ROOT . '/custom/filemanager/backups';
$filePath = $backupDir . '/' . $filename;

// Verificar que el archivo existe y está en el directorio correcto
if (!file_exists($filePath)) {
    echo json_encode(array('success' => false, 'message' => 'El archivo no existe'));
    exit;
}

// Verificar que el archivo está dentro del directorio de backups (seguridad)
$realPath = realpath($filePath);
$realBackupDir = realpath($backupDir);
if (!$realPath || strpos($realPath, $realBackupDir) !== 0) {
    echo json_encode(array('success' => false, 'message' => 'Ruta de archivo inválida'));
    exit;
}

// Intentar eliminar el archivo
if (unlink($filePath)) {
    // Log de la actividad
    dol_syslog("FileManager: Backup eliminado - $filename por usuario " . $user->login, LOG_INFO);
    
    echo json_encode(array('success' => true, 'message' => 'Backup eliminado correctamente'));
} else {
    echo json_encode(array('success' => false, 'message' => 'No se pudo eliminar el archivo'));
}
?>
