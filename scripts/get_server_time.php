<?php
// Obtener hora del servidor
require_once '../../../main.inc.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

$now = new DateTime();
echo json_encode(array(
    'success' => true,
    'time' => $now->format('H:i:s'),
    'date' => $now->format('Y-m-d'),
    'datetime' => $now->format('Y-m-d H:i:s'),
    'timezone' => date_default_timezone_get(),
    'timestamp' => $now->getTimestamp()
));
exit;
?>

