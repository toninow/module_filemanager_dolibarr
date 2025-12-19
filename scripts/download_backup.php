<?php
require '../../../main.inc.php';
if (empty($user->admin) && empty($user->rights->filemanager->createzip)) accessforbidden();

// Verificar token CSRF - usar el token que viene por GET
$token = GETPOST('token','alpha');
if (empty($token)) {
    // Token no proporcionado
    http_response_code(403);
    print json_encode(['success' => false, 'message' => 'Token requerido']);
    exit;
}

$backupDir = DOL_DOCUMENT_ROOT.'/custom/filemanager/backups';
$filename = GETPOST('filename','alpha'); // restricción básica

// Seguridad básica
if (!$filename || strpos($filename,'..')!==false || strpos($filename,'/')!==false || strpos($filename,'\\')!==false) {
    accessforbidden();
}

$fullpath = $backupDir.'/'.$filename;
if (!is_file($fullpath)) {
    http_response_code(404);
    print 'Archivo no encontrado';
    exit;
}

@set_time_limit(0);
clearstatcache(true, $fullpath);
$filesize = filesize($fullpath);

while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="'.basename($fullpath).'"');
header('Content-Length: '.$filesize);
header('Content-Transfer-Encoding: binary');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: public');

$chunkSize = 8 * 1024 * 1024;
$handle = fopen($fullpath, 'rb');
if ($handle === false) {
    http_response_code(500);
    print 'No se pudo abrir el archivo';
    exit;
}

while (!feof($handle)) {
    echo fread($handle, $chunkSize);
    flush();
    if (connection_status() != CONNECTION_NORMAL) {
        break;
    }
}

fclose($handle);
exit;
?>