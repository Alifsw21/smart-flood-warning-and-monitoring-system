-- Incremental migration for existing kelompok2 DB volumes (fresh installs use schema.sql only).
-- Run: make migrate

USE kelompok2;

SET @col_exists := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = 'kelompok2' AND TABLE_NAME = 'user_laporan' AND COLUMN_NAME = 'status'
);
SET @sql := IF(@col_exists = 0,
  "ALTER TABLE user_laporan ADD COLUMN status ENUM('pending', 'in_progress', 'resolved', 'rejected') DEFAULT 'pending' AFTER deskripsiLaporan",
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS user_notifications(
    id INT AUTO_INCREMENT PRIMARY KEY,
    idPengguna INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (idPengguna) REFERENCES user_user(id),
    INDEX idx_user_notif_pengguna (idPengguna),
    INDEX idx_user_notif_read (is_read)
);

SET @refresh_col := (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = 'kelompok2' AND TABLE_NAME = 'auth_oauthToken' AND COLUMN_NAME = 'refresh_expires_at'
);
SET @sql_refresh := IF(@refresh_col = 0,
  'ALTER TABLE auth_oauthToken ADD COLUMN refresh_expires_at TIMESTAMP NULL AFTER expires_at',
  'SELECT 1'
);
PREPARE stmt_refresh FROM @sql_refresh;
EXECUTE stmt_refresh;
DEALLOCATE PREPARE stmt_refresh;

GRANT SELECT, INSERT, UPDATE, DELETE ON kelompok2.user_notifications TO 'user'@'%';
GRANT SELECT ON kelompok2.river_zones TO 'user'@'%';
FLUSH PRIVILEGES;
