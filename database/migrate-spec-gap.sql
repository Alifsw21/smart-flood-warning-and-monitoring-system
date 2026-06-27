-- Incremental migration for feat/spec-gap-closure
-- Run on existing kelompok2 DB: docker exec -i smartcity-mysql mysql -uroot -pRootSecret kelompok2 < database/migrate-spec-gap.sql

USE kelompok2;

-- Add laporan status column (ignore error if already exists)
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

GRANT SELECT, INSERT, UPDATE, DELETE ON kelompok2.user_notifications TO 'user'@'%';
FLUSH PRIVILEGES;
