CREATE DATABASE IF NOT EXISTS kelompok2;

USE kelompok2;

# 1. Auth Service

CREATE TABLE auth_oauthClient(
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id VARCHAR(80) UNIQUE NOT NULL,
    client_secret VARCHAR(255) NOT NULL,
    grant_types VARCHAR(80),
    redirect_uris TEXT
);

CREATE TABLE auth_oauthToken(
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id VARCHAR(80) NOT NULL,
    user_id INT,
    access_token VARCHAR(255) UNIQUE NOT NULL,
    refresh_token VARCHAR(255) UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    INDEX idx_auth_token_access (access_token)
);

# 2. php-river
CREATE TABLE river_zones(
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama_kota VARCHAR(100) NOT NULL
);

CREATE TABLE river_sungai(
    id INT AUTO_INCREMENT PRIMARY KEY,
    zoneId INT,
    lokasiSungai VARCHAR(100),
    FOREIGN KEY (zoneId) REFERENCES river_zones(id),
    INDEX idx_river_sungai_zones (zoneId)
);

CREATE TABLE river_sensorNode(
    id INT AUTO_INCREMENT PRIMARY KEY,
    idSungai INT,
    idStation INT UNIQUE NOT NULL,
    namaNode VARCHAR(50) NOT NULL,
    posisi ENUM('hulu', 'hilir') NOT NULL,
    elevasi FLOAT NOT NULL DEFAULT 0.0,
    FOREIGN KEY (idSungai) REFERENCES river_sungai(id),
    INDEX idx_river_node_sungai (idSungai)
);

CREATE TABLE river_sensorReading(
    id INT AUTO_INCREMENT PRIMARY KEY,
    idNode INT,
    tinggiAir FLOAT NOT NULL,
    kelembapanTanah FLOAT NOT NULL,
    curahHujan FLOAT NOT NULL,
    suhuRataRata FLOAT NOT NULL,
    kelembapanUdara FLOAT NOT NULL,
    kecepatanAngin FLOAT NOT NULL,
    arahAngin VARCHAR(10),
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (idNode) REFERENCES river_sensorNode(id),
    INDEX idx_river_reading_node (idNode),
    INDEX idx_river_reading_recorded (recorded_at)
);

# 3. php-user
CREATE TABLE user_user(
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'pengguna') DEFAULT 'pengguna'
);

CREATE TABLE user_laporan(
    id INT AUTO_INCREMENT PRIMARY KEY,
    idPengguna INT,
    deskripsiLaporan TEXT NOT NULL,
    waktuDibuat TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (idPengguna) REFERENCES user_user(id),
    INDEX idx_user_laporan_pengguna (idPengguna)
);

CREATE TABLE user_riwayatBanjir(
    id INT AUTO_INCREMENT PRIMARY KEY,
    idSungai INT,
    tinggiAir FLOAT NOT NULL,
    status ENUM('ringan', 'sedang', 'tinggi') DEFAULT 'ringan',
    waktuTerjadi DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (idSungai) REFERENCES river_sungai(id),
    INDEX idx_user_riwayat_sungai (idSungai),
    INDEX idx_user_riwayat_waktu (waktuTerjadi)
);

# 4. php-analytics
CREATE TABLE analytics_peringatan(
    id INT AUTO_INCREMENT PRIMARY KEY,
    idSungai INT,
    tipePeringatan ENUM('normal', 'waspada', 'bencana') DEFAULT 'normal',
    nilaiProbabilitas FLOAT DEFAULT 0.0,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (idSungai) REFERENCES river_sungai(id),
    INDEX idx_analytics_sungai (idSungai),
    INDEX idx_analytics_recorded (recorded_at)
);

CREATE USER IF NOT EXISTS 'river'@'%' IDENTIFIED BY 'RiverSecret';
GRANT SELECT, INSERT, UPDATE, DELETE ON kelompok2.river_zones TO 'river'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON kelompok2.river_sungai TO 'river'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON kelompok2.river_sensorNode TO 'river'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON kelompok2.river_sensorReading TO 'river'@'%';

CREATE USER IF NOT EXISTS 'user'@'%' IDENTIFIED BY 'UserSecret';
GRANT SELECT, INSERT, UPDATE, DELETE ON kelompok2.user_user TO 'user'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON kelompok2.user_laporan TO 'user'@'%';
GRANT SELECT, INSERT, UPDATE, DELETE ON kelompok2.user_riwayatBanjir TO 'user'@'%';
GRANT SELECT ON kelompok2.river_sungai TO 'user'@'%';

CREATE USER IF NOT EXISTS 'analytics'@'%' IDENTIFIED BY 'AnalyticSecret';
GRANT SELECT, INSERT, UPDATE, DELETE ON kelompok2.analytics_peringatan TO 'analytics'@'%';
GRANT SELECT ON kelompok2.river_sungai TO 'analytics'@'%';
GRANT SELECT ON kelompok2.river_sensorReading TO 'analytics'@'%';
GRANT SELECT ON kelompok2.river_sensorNode TO 'analytics'@'%';

FLUSH PRIVILEGES;