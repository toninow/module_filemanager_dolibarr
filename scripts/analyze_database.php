<?php
ob_clean();
require '../../../main.inc.php';

if (empty($user->admin) && empty($user->rights->filemanager->createzip)) accessforbidden();
if (function_exists('checkToken') && !checkToken()) accessforbidden();

header('Content-Type: application/json');

try {
    // Usar las credenciales de Dolibarr (OPTIMIZADO y RÁPIDO)
    global $db, $conf;
    
    $stats = [
        'total_tables' => 0,
        'total_records' => 0,
        'estimated_size_mb' => 0,
        'estimated_zip_mb' => 0,
        'tables_detail' => []
    ];

    // Obtener nombre de la base de datos DESDE LA CONEXIÓN ACTIVA
    $database_name = $db->database_name;
    
    // Obtener lista de tablas USANDO SHOW TABLES (MÁS SIMPLE Y DIRECTO)
    $sql = "SHOW TABLES";
    $resql = $db->query($sql);
    
    $tables = array();
    if ($resql) {
        // SHOW TABLES devuelve una columna con el nombre dinámico
        // Usar el primer campo del resultado
        while ($obj = $db->fetch_object($resql)) {
            // Obtener el valor del primer campo
            $table_name = array_values((array)$obj)[0];
            $tables[] = $table_name;
        }
    }
    
    $stats['total_tables'] = count($tables);

    // Analizar TODAS las tablas para estadísticas precisas
    $analyzed_tables = $tables;
    
    foreach ($analyzed_tables as $table) {
        // Usar aproximación rápida desde information_schema
        $sql = "SELECT 
                    table_rows as row_count, 
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb 
                FROM information_schema.TABLES 
                WHERE table_schema = '" . $db->escape($database_name) . "' 
                AND table_name = '" . $db->escape($table) . "'";
        
        $resql = $db->query($sql);
        
        if ($resql && $obj = $db->fetch_object($resql)) {
            $rowCount = (int)$obj->row_count;
            $tableSize = (float)$obj->size_mb;
            
            $stats['total_records'] += $rowCount;
            $stats['estimated_size_mb'] += $tableSize;
            
            $stats['tables_detail'][] = [
                'name' => $table,
                'records' => $rowCount,
                'size_mb' => $tableSize
            ];
        }
    }

    // Estimar tamaño del ZIP (comprimido) - compresión típica 30%
    $stats['estimated_zip_mb'] = round($stats['estimated_size_mb'] * 0.3, 2);
    
    // Ordenar tablas por tamaño y obtener solo top 5
    usort($stats['tables_detail'], function($a, $b) {
        return $b['records'] <=> $a['records'];
    });
    
    // Crear array largest_tables con formato esperado por frontend
    $stats['largest_tables'] = [];
    foreach (array_slice($stats['tables_detail'], 0, 5) as $table) {
        $stats['largest_tables'][] = [
            'name' => $table['name'],
            'rows' => $table['records'],
            'size_mb' => $table['size_mb']
        ];
    }

    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error analizando base de datos: ' . $e->getMessage()
    ]);
}
exit;
?>
