CREATE DATABASE IF NOT EXISTS `db_stockbarang` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `db_stockbarang`;

-- Table for users (admin access)
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for items
CREATE TABLE IF NOT EXISTS `barang` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `kode_barang` VARCHAR(20) NOT NULL UNIQUE,
  `nama_barang` VARCHAR(150) NOT NULL,
  `kategori` VARCHAR(50) NOT NULL,
  `stok` INT NOT NULL DEFAULT 0,
  `stok_minimum` INT NOT NULL DEFAULT 5,
  `harga` DECIMAL(12, 2) NOT NULL DEFAULT 0.00,
  `keterangan` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table for transactions (stock logs)
CREATE TABLE IF NOT EXISTS `transaksi` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `barang_id` INT NOT NULL,
  `tipe_transaksi` ENUM('masuk', 'keluar') NOT NULL,
  `jumlah` INT NOT NULL,
  `keterangan` TEXT DEFAULT NULL,
  `tanggal_transaksi` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`barang_id`) REFERENCES `barang`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed default admin user (Username: admin | Password: admin)
INSERT INTO `users` (`username`, `password`, `name`) 
SELECT 'admin', '$2y$12$5w2Gv3fiJ2F4tLN9JJDN6ej.JSjHmD4Ymu0tyI80gZOcz/MlVLNzG', 'Administrator'
FROM dual
WHERE NOT EXISTS (SELECT 1 FROM `users` WHERE `username` = 'admin');

-- Seed dummy items for presentation/testing
INSERT INTO `barang` (`kode_barang`, `nama_barang`, `kategori`, `stok`, `stok_minimum`, `harga`, `keterangan`) VALUES
('BRG-0001', 'Processor Intel Core i7-13700K', 'Hardware', 15, 3, 6200000.00, 'Processor Generasi ke-13 LGA1700'),
('BRG-0002', 'ASUS ROG Strix RTX 4070 Ti', 'Hardware', 8, 2, 14500000.00, 'Graphics Card 12GB GDDR6X'),
('BRG-0003', 'Corsair Vengeance DDR5 32GB', 'RAM', 4, 5, 1850000.00, 'RAM DDR5 2x16GB 6000MHz (Low Stock)'),
('BRG-0004', 'Samsung 990 PRO M.2 NVMe 1TB', 'Storage', 25, 5, 1650000.00, 'SSD NVMe PCIe 4.0 High Speed'),
('BRG-0005', 'Corsair RM850x 850W PSU', 'Power Supply', 12, 3, 2100000.00, '850 Watt 80 Plus Gold Fully Modular');

-- Seed dummy transactions
INSERT INTO `transaksi` (`barang_id`, `tipe_transaksi`, `jumlah`, `keterangan`) VALUES
(1, 'masuk', 15, 'Stok awal setup sistem'),
(2, 'masuk', 10, 'Stok awal setup sistem'),
(3, 'masuk', 4, 'Stok awal setup sistem'),
(4, 'masuk', 25, 'Stok awal setup sistem'),
(5, 'masuk', 12, 'Stok awal setup sistem'),
(2, 'keluar', 2, 'Penjualan unit ke Toko A');
