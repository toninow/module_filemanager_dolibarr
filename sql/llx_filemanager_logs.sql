-- Tabla para logs del FileManager
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

