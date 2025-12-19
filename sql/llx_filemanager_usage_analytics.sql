-- Tabla para analítica de uso de Dolibarr
CREATE TABLE IF NOT EXISTS llx_filemanager_usage_analytics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    user_name VARCHAR(255),
    session_start DATETIME NOT NULL,
    session_end DATETIME,
    ip_address VARCHAR(45),
    actions_count INT DEFAULT 0,
    last_activity DATETIME,
    INDEX idx_user_id (user_id),
    INDEX idx_session_start (session_start),
    INDEX idx_user_session (user_id, session_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla para configuración de backup automático
CREATE TABLE IF NOT EXISTS llx_filemanager_auto_backup_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    enabled TINYINT(1) DEFAULT 0,
    backup_type VARCHAR(50) DEFAULT 'complete',
    schedule_time TIME DEFAULT '03:00:00',
    schedule_days VARCHAR(20) DEFAULT 'MON,TUE,WED,THU,FRI',
    min_inactivity_minutes INT DEFAULT 30,
    max_backup_age_days INT DEFAULT 7,
    last_backup_date DATETIME,
    next_backup_date DATETIME,
    updated_by VARCHAR(255),
    updated_date DATETIME,
    INDEX idx_enabled (enabled),
    INDEX idx_next_backup (next_backup_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar configuración por defecto
INSERT INTO llx_filemanager_auto_backup_config (enabled, backup_type, schedule_time, schedule_days, min_inactivity_minutes, max_backup_age_days) 
VALUES (0, 'complete', '03:00:00', 'MON,TUE,WED,THU,FRI', 30, 7)
ON DUPLICATE KEY UPDATE id=id;




