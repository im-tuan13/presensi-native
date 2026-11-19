-- ==========================================================
-- SKEMA DATABASE APLIKASI PRESENSI SISWA PKL (2 ROLE: SISWA & ADMIN)
-- Nama Database: presensi
-- Format: MySQL, utf8mb4, InnoDB
-- ==========================================================

CREATE DATABASE IF NOT EXISTS `presensi` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `presensi`;

-- Hapus tabel lama jika sudah ada (sesuai urutan relasi foreign key)
DROP TABLE IF EXISTS `presensi`;
DROP TABLE IF EXISTS `siswa`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `lokasi_pkl`;
DROP TABLE IF EXISTS `lokasi_kantor`;

-- ----------------------------------------------------------
-- 1. Tabel: lokasi_pkl
-- Menyimpan titik koordinat kantor/tempat PKL dan radius toleransi (meter)
-- ----------------------------------------------------------
CREATE TABLE `lokasi_pkl` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama_tempat` VARCHAR(100) NOT NULL,
  `latitude` DECIMAL(10,8) NOT NULL,
  `longitude` DECIMAL(11,8) NOT NULL,
  `radius` INT NOT NULL COMMENT 'Batas radius maksimal dalam satuan meter'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 2. Tabel: users (Administrator)
-- Menyimpan kredensial login administrator sistem
-- ----------------------------------------------------------
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nama` VARCHAR(100) NOT NULL,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin') NOT NULL DEFAULT 'admin',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 3. Tabel: siswa
-- Menyimpan data 10 siswa PKL dan penempatan lokasi PKL-nya
-- ----------------------------------------------------------
CREATE TABLE `siswa` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `nis` VARCHAR(30) NOT NULL UNIQUE,
  `nama` VARCHAR(100) NOT NULL,
  `password` VARCHAR(255) NOT NULL COMMENT 'Hash password menggunakan password_hash()',
  `lokasi_id` INT NOT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_siswa_lokasi` FOREIGN KEY (`lokasi_id`) 
    REFERENCES `lokasi_pkl` (`id`) 
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------
-- 4. Tabel: presensi
-- Mencatat presensi harian siswa (masuk & pulang)
-- UNIQUE KEY (siswa_id, tanggal) memastikan 1 siswa hanya punya 1 baris per hari
-- ----------------------------------------------------------
CREATE TABLE `presensi` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `siswa_id` INT NOT NULL,
  `tanggal` DATE NOT NULL,
  `jam_masuk` TIME NOT NULL,
  `jam_pulang` TIME NULL,
  `status` ENUM('Hadir', 'Terlambat') NOT NULL,
  `lat_masuk` DECIMAL(10,8) NOT NULL,
  `lng_masuk` DECIMAL(11,8) NOT NULL,
  `lat_pulang` DECIMAL(10,8) NULL,
  `lng_pulang` DECIMAL(11,8) NULL,
  UNIQUE KEY `unique_siswa_tanggal` (`siswa_id`, `tanggal`),
  CONSTRAINT `fk_presensi_siswa` FOREIGN KEY (`siswa_id`) 
    REFERENCES `siswa` (`id`) 
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==========================================================
-- DATA CONTOH (SEED DATA)
-- Password default semua akun di bawah: password123
-- Hash: $2y$10$dLy0VHZZIS28fbrnbfai9.KtjhpMJow56rii2eXO/sx.oGqtyW5eS
-- ==========================================================

-- Lokasi PKL (2 Lokasi Contoh)
INSERT INTO `lokasi_pkl` (`id`, `nama_tempat`, `latitude`, `longitude`, `radius`) VALUES
(1, 'PT Solusi Teknologi Indonesia (Kantor PKL)', -6.17539240, 106.82715380, 100),
(2, 'CV Media Kreasi Digital', -6.19000000, 106.82000000, 150);

-- Data Akun Admin
INSERT INTO `users` (`id`, `nama`, `username`, `password`, `role`) VALUES
(1, 'Administrator Sistem', 'admin', '$2y$10$dLy0VHZZIS28fbrnbfai9.KtjhpMJow56rii2eXO/sx.oGqtyW5eS', 'admin');

-- Data 10 Siswa PKL (NIS 12345 s/d 12354)
INSERT INTO `siswa` (`id`, `nis`, `nama`, `password`, `lokasi_id`) VALUES
(1, '12345', 'Budi Santoso', '$2y$10$dLy0VHZZIS28fbrnbfai9.KtjhpMJow56rii2eXO/sx.oGqtyW5eS', 1),
(2, '12346', 'Siti Rahmawati', '$2y$10$dLy0VHZZIS28fbrnbfai9.KtjhpMJow56rii2eXO/sx.oGqtyW5eS', 1),
(3, '12347', 'Ahmad Fauzan', '$2y$10$dLy0VHZZIS28fbrnbfai9.KtjhpMJow56rii2eXO/sx.oGqtyW5eS', 1),
(4, '12348', 'Dewi Lestari', '$2y$10$dLy0VHZZIS28fbrnbfai9.KtjhpMJow56rii2eXO/sx.oGqtyW5eS', 1),
(5, '12349', 'Rizky Pratama', '$2y$10$dLy0VHZZIS28fbrnbfai9.KtjhpMJow56rii2eXO/sx.oGqtyW5eS', 1),
(6, '12350', 'Nurul Hidayah', '$2y$10$dLy0VHZZIS28fbrnbfai9.KtjhpMJow56rii2eXO/sx.oGqtyW5eS', 2),
(7, '12351', 'Fajar Nugraha', '$2y$10$dLy0VHZZIS28fbrnbfai9.KtjhpMJow56rii2eXO/sx.oGqtyW5eS', 2),
(8, '12352', 'Putri Indah', '$2y$10$dLy0VHZZIS28fbrnbfai9.KtjhpMJow56rii2eXO/sx.oGqtyW5eS', 2),
(9, '12353', 'Bagas Saputra', '$2y$10$dLy0VHZZIS28fbrnbfai9.KtjhpMJow56rii2eXO/sx.oGqtyW5eS', 2),
(10, '12354', 'Anisa Rahayu', '$2y$10$dLy0VHZZIS28fbrnbfai9.KtjhpMJow56rii2eXO/sx.oGqtyW5eS', 2);
