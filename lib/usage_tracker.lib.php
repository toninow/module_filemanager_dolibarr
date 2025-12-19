<?php
/**
 * Librería para registrar y analizar el uso de Dolibarr
 */

/**
 * Registrar inicio de sesión de usuario
 */
function registerUserSession($user_id, $user_name, $ip_address) {
    global $db;
    
    if (!$db || empty($db->database_name)) {
        return false;
    }
    
    $sql = "INSERT INTO llx_filemanager_usage_analytics (user_id, user_name, session_start, ip_address, last_activity) VALUES (?, ?, NOW(), ?, NOW())";
    
    return $db->query($sql, array($user_id, $user_name, $ip_address));
}

/**
 * Registrar actividad de usuario
 */
function registerUserActivity($user_id) {
    global $db;
    
    if (!$db || empty($db->database_name)) {
        return false;
    }
    
    // Actualizar última actividad de la sesión más reciente
    $sql = "UPDATE llx_filemanager_usage_analytics SET last_activity = NOW(), actions_count = actions_count + 1 
            WHERE user_id = ? AND session_end IS NULL ORDER BY session_start DESC LIMIT 1";
    
    return $db->query($sql, array($user_id));
}

/**
 * Cerrar sesión de usuario
 */
function endUserSession($user_id) {
    global $db;
    
    if (!$db || empty($db->database_name)) {
        return false;
    }
    
    $sql = "UPDATE llx_filemanager_usage_analytics SET session_end = NOW() 
            WHERE user_id = ? AND session_end IS NULL";
    
    return $db->query($sql, array($user_id));
}

/**
 * Obtener estadísticas de uso desde llx_actioncomm
 */
function getUsageStats($days = 7) {
    global $db;
    
    if (!$db || empty($db->database_name)) {
        return array();
    }
    
    // Usar tabla llx_actioncomm que registra todas las acciones en Dolibarr
    $sql = "SELECT 
                DATE(datec) as date,
                HOUR(datec) as hour,
                COUNT(*) as sessions,
                COUNT(DISTINCT fk_user_author) as unique_users,
                COUNT(*) as total_actions
            FROM llx_actioncomm 
            WHERE datec >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(datec), HOUR(datec)
            ORDER BY date DESC, hour DESC";
    
    $resql = $db->query($sql, array($days));
    $stats = array();
    
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $stats[] = array(
                'date' => $obj->date,
                'hour' => (int)$obj->hour,
                'sessions' => (int)$obj->sessions,
                'unique_users' => (int)$obj->unique_users,
                'total_actions' => (int)$obj->total_actions
            );
        }
    }
    
    return $stats;
}

/**
 * Obtener horas de mayor uso desde llx_actioncomm
 */
function getPeakHours($days = 7) {
    global $db;
    
    if (!$db || empty($db->database_name)) {
        return array();
    }
    
    // Usar tabla llx_actioncomm para obtener horas pico de actividad
    $sql = "SELECT 
                HOUR(datec) as hour,
                COUNT(*) as total_sessions,
                COUNT(DISTINCT fk_user_author) as unique_users,
                AVG(1) as avg_actions
            FROM llx_actioncomm 
            WHERE datec >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY hour
            ORDER BY total_sessions DESC";
    
    $resql = $db->query($sql, array($days));
    $peaks = array();
    
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $peaks[] = array(
                'hour' => (int)$obj->hour,
                'total_sessions' => (int)$obj->total_sessions,
                'unique_users' => (int)$obj->unique_users,
                'avg_actions' => 1
            );
        }
    }
    
    return $peaks;
}

/**
 * Detectar si hay usuarios activos
 */
function hasActiveUsers($inactivity_minutes = 30) {
    global $db;
    
    if (!$db || empty($db->database_name)) {
        return true; // Por seguridad, asumir que hay usuarios activos
    }
    
    $sql = "SELECT COUNT(*) as count 
            FROM llx_filemanager_usage_analytics 
            WHERE session_end IS NULL 
            AND last_activity >= DATE_SUB(NOW(), INTERVAL ? MINUTE)";
    
    $resql = $db->query($sql, array($inactivity_minutes));
    
    if ($resql && $obj = $db->fetch_object($resql)) {
        return $obj->count > 0;
    }
    
    return true; // Por seguridad
}


/**
 * Obtener estadísticas de uso por usuario desde llx_actioncomm
 */
function getUserUsageStats($user_id = null, $days = 30) {
    global $db;
    
    if (!$db || empty($db->database_name)) {
        return array();
    }
    
    // Usar tabla llx_actioncomm + llx_user para obtener actividad por usuario
    $sql = "SELECT 
                COALESCE(u.login, CONCAT('Usuario ', a.fk_user_author)) as user_name,
                COUNT(*) as total_sessions,
                COUNT(*) as total_actions,
                MIN(a.datec) as first_session,
                MAX(a.datec) as last_session,
                COUNT(*) / NULLIF(TIMESTAMPDIFF(DAY, MIN(a.datec), MAX(a.datec)), 0) as avg_session_minutes
            FROM llx_actioncomm a
            LEFT JOIN llx_user u ON a.fk_user_author = u.rowid
            WHERE a.datec >= DATE_SUB(NOW(), INTERVAL ? DAY)";
    
    $params = array($days);
    
    if ($user_id) {
        $sql .= " AND a.fk_user_author = ?";
        $params[] = $user_id;
    }
    
    $sql .= " GROUP BY a.fk_user_author ORDER BY total_actions DESC LIMIT 20";
    
    $resql = $db->query($sql, $params);
    $stats = array();
    
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $stats[] = array(
                'user_name' => $obj->user_name,
                'total_sessions' => (int)$obj->total_sessions,
                'total_actions' => (int)$obj->total_actions,
                'first_session' => $obj->first_session,
                'last_session' => $obj->last_session,
                'avg_session_minutes' => round((float)$obj->avg_session_minutes, 2)
            );
        }
    }
    
    return $stats;
}

