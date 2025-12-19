<?php
/**
 * Crear tabla de logs del FileManager si no existe
 */

require_once '../../../main.inc.php';

if (!$user->admin) {
    die("Acceso denegado");
}

$sql = "
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

try {
    $result = $db->query($sql);
    if ($result) {
        echo "✅ Tabla llx_filemanager_logs creada correctamente<br>";
    } else {
        echo "❌ Error: " . $db->lasterror();
    }
} catch (Exception $e) {
    echo "❌ Excepción: " . $e->getMessage();
}

// Probar insertar un log de prueba
try {
    $test_sql = "INSERT INTO llx_filemanager_logs (user_id, user_name, action, file_path, file_name, ip_address, timestamp, details) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $test_result = $db->query($test_sql, array(
        $user->id,
        $user->login,
        'test',
        '/test/path',
        'test.txt',
        '127.0.0.1',
        date('Y-m-d H:i:s'),
        'Log de prueba'
    ));
    
    if ($test_result) {
        echo "✅ Log de prueba insertado correctamente<br>";
        
        // Eliminar el log de prueba
        $db->query("DELETE FROM llx_filemanager_logs WHERE action = 'test'");
        echo "✅ Log de prueba eliminado<br>";
        
        echo "<br><strong>✅ LA TABLA DE LOGS ESTÁ FUNCIONANDO CORRECTAMENTE</strong>";
    } else {
        echo "❌ Error insertando log de prueba: " . $db->lasterror();
    }
} catch (Exception $e) {
    echo "❌ Excepción insertando log: " . $e->getMessage();
}

$db->close();
?>




