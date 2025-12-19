-- Tabla para backups del FileManager
CREATE TABLE IF NOT EXISTS llx_filemanager_backups (
    id VARCHAR(50) PRIMARY KEY,
    filename VARCHAR(255),
    filepath VARCHAR(512),
    filesize BIGINT,
    file_count INT DEFAULT 0,
    folder_count INT DEFAULT 0,
    progress INT DEFAULT 0,
    status VARCHAR(50) NOT NULL DEFAULT 'in_progress',
    error_message TEXT,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    tms DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_created_by (created_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

