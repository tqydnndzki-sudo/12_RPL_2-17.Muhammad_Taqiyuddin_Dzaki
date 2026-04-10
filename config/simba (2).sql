-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Apr 10, 2026 at 06:50 AM
-- Server version: 9.3.0
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `simba`
--

-- --------------------------------------------------------

--
-- Table structure for table `barangkeluar`
--

CREATE TABLE `barangkeluar` (
  `idkeluar` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `iduserprocurementcreated` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `iduserprocurementapproved` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tgl_keluar` datetime DEFAULT NULL,
  `keterangan` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `barangkeluar`
--

INSERT INTO `barangkeluar` (`idkeluar`, `iduserprocurementcreated`, `iduserprocurementapproved`, `tgl_keluar`, `keterangan`) VALUES
('BK001', 'USR-005', 'USR-003', '2025-01-22 09:30:00', 'Barang keluar untuk project'),
('BK002', 'USR-005', 'USR-003', '2025-01-28 15:45:00', 'Barang keluar untuk maintenance');

-- --------------------------------------------------------

--
-- Table structure for table `barangmasuk`
--

CREATE TABLE `barangmasuk` (
  `idmasuk` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `idpurchaseorder` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `iduserprocurementcreate` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `iduserprocurementapproval` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tgl_masuk` datetime DEFAULT NULL,
  `keterangan` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `barangmasuk`
--

INSERT INTO `barangmasuk` (`idmasuk`, `idpurchaseorder`, `iduserprocurementcreate`, `iduserprocurementapproval`, `tgl_masuk`, `keterangan`) VALUES
('BM001', 'PO001', 'USR-004', 'USR-003', '2025-01-20 10:00:00', 'Barang masuk untuk PR001'),
('BM002', 'PO002', 'USR-004', 'USR-003', '2025-01-25 14:30:00', 'Barang masuk untuk PR002');

-- --------------------------------------------------------

--
-- Table structure for table `detailkeluar`
--

CREATE TABLE `detailkeluar` (
  `iddetailkeluar` int NOT NULL,
  `idbarang` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `idkategori` int DEFAULT NULL,
  `idkeluar` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `qty` int DEFAULT '0',
  `harga` decimal(15,2) DEFAULT '0.00',
  `total` decimal(20,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `detailkeluar`
--

INSERT INTO `detailkeluar` (`iddetailkeluar`, `idbarang`, `idkategori`, `idkeluar`, `qty`, `harga`, `total`) VALUES
(1, 'B001', 2, 'BK001', 1, '15000000.00', '15000000.00'),
(2, 'B002', 2, 'BK001', 1, '2500000.00', '2500000.00'),
(3, 'B005', 2, 'BK002', 1, '2200000.00', '2200000.00');

-- --------------------------------------------------------

--
-- Table structure for table `detailmasuk`
--

CREATE TABLE `detailmasuk` (
  `iddetailmasuk` int NOT NULL,
  `idbarang` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `idmasuk` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `qty` int DEFAULT '0',
  `harga` decimal(15,2) DEFAULT '0.00',
  `total` decimal(20,2) DEFAULT '0.00',
  `idkategori` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `detailmasuk`
--

INSERT INTO `detailmasuk` (`iddetailmasuk`, `idbarang`, `idmasuk`, `qty`, `harga`, `total`, `idkategori`) VALUES
(1, 'B001', 'BM001', 2, '15000000.00', '30000000.00', 2),
(2, 'B002', 'BM001', 2, '2500000.00', '5000000.00', 2),
(3, 'B003', 'BM001', 5, '300000.00', '1500000.00', 5),
(4, 'B005', 'BM002', 1, '2200000.00', '2200000.00', 2),
(5, 'B006', 'BM002', 10, '50000.00', '500000.00', 5);

-- --------------------------------------------------------

--
-- Table structure for table `detailorder`
--

CREATE TABLE `detailorder` (
  `iddetailorder` int NOT NULL,
  `idpurchaseorder` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `idbarang` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `qty` int DEFAULT '0',
  `harga` decimal(15,2) DEFAULT '0.00',
  `total` decimal(20,2) DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `detailorder`
--

INSERT INTO `detailorder` (`iddetailorder`, `idpurchaseorder`, `idbarang`, `qty`, `harga`, `total`) VALUES
(1, 'PO001', 'B001', 2, '15000000.00', '30000000.00'),
(2, 'PO001', 'B002', 2, '2500000.00', '5000000.00'),
(3, 'PO001', 'B003', 5, '300000.00', '1500000.00'),
(4, 'PO002', 'B005', 1, '2200000.00', '2200000.00'),
(5, 'PO002', 'B006', 10, '50000.00', '500000.00'),
(6, 'PO20250001', 'B009', 2, '800000.00', '1600000.00'),
(7, 'PO20250001', 'BRG-001', 1, '9000000.00', '9000000.00'),
(8, 'PO20260001', 'B005', 1, '2200000.00', '2200000.00'),
(9, 'PO20260001', 'B003', 1, '300000.00', '300000.00'),
(10, 'PO20260002', 'B005', 1, '2200000.00', '2200000.00'),
(11, 'PO20260002', 'B006', 10, '50000.00', '500000.00'),
(12, 'PO20260003', 'B004', 1, '200000.00', '200000.00'),
(13, 'PO20260003', '5', 1, '40000.00', '40000.00');

-- --------------------------------------------------------

--
-- Table structure for table `detailrequest`
--

CREATE TABLE `detailrequest` (
  `iddetailrequest` int NOT NULL,
  `idbarang` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `idrequest` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `linkpembelian` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `namaitem` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `deskripsi` text COLLATE utf8mb4_general_ci,
  `harga` decimal(15,2) DEFAULT '0.00',
  `qty` int DEFAULT '0',
  `total` decimal(20,2) DEFAULT '0.00',
  `kodeproject` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `detailrequest`
--

INSERT INTO `detailrequest` (`iddetailrequest`, `idbarang`, `idrequest`, `linkpembelian`, `namaitem`, `deskripsi`, `harga`, `qty`, `total`, `kodeproject`) VALUES
(1, 'B001', 'PR001', 'https://www.tokopedia.com/laptop', 'Laptop Dell', 'Laptop Dell Core i7, 16GB RAM', '15000000.00', 2, '30000000.00', 'PROJ-001'),
(2, 'B002', 'PR001', 'https://www.tokopedia.com/monitor', 'Monitor 24 inch', 'Monitor LED 24 inch Full HD', '2500000.00', 2, '5000000.00', 'PROJ-001'),
(3, 'B003', 'PR001', 'https://www.tokopedia.com/keyboard', 'Keyboard Wireless', 'Keyboard Wireless Logitech', '300000.00', 5, '1500000.00', 'PROJ-001'),
(4, 'B005', 'PR002', 'https://www.tokopedia.com/printer', 'Printer HP', 'Printer LaserJet Pro M404n', '2200000.00', 1, '2200000.00', 'PROJ-002'),
(5, 'B006', 'PR002', 'https://www.tokopedia.com/kabel', 'Kabel USB', 'Kabel USB Type-C 2M', '50000.00', 10, '500000.00', 'PROJ-002'),
(6, 'B007', 'PR003', 'https://www.tokopedia.com/ssd', 'Hard Drive 1TB', 'SSD 1TB Kingston', '1200000.00', 3, '3600000.00', 'PROJ-003'),
(7, 'B010', 'PR004', 'https://www.tokopedia.com/speaker', 'Speaker', 'Speaker Bluetooth JBL', '750000.00', 2, '1500000.00', 'PROJ-004'),
(8, 'B008', 'PR006', 'https//robot', 'RAM 8GB', 'RAM DDR4 8GB', '600000.00', 2, '1200000.00', 'PROJ-003'),
(9, 'B009', 'PR007', 'https//robot', 'Router WiFi', 'Router WiFi 6 AX1800', '800000.00', 2, '1600000.00', 'PROJ-004'),
(10, 'BRG-001', 'PR007', 'Huwawei.id', 'Laptop Huwawei Matebook D14', 'kebutuhan pekerjaan', '9000000.00', 1, '9000000.00', 'PROJ-006'),
(11, 'B008', 'PR008', 'https//tokped', 'RAM 8GB', 'RAM DDR4 8GB', '600000.00', 2, '1200000.00', 'PROJ-003'),
(12, 'B005', 'PR009', 'HP', 'Printer HP', 'Printer LaserJet Pro M404n', '2200000.00', 1, '2200000.00', 'PROJ-002'),
(13, 'B003', 'PR009', 'Robot', 'Keyboard Wireless', 'Keyboard Wireless Logitech', '300000.00', 1, '300000.00', 'PROJ-001'),
(14, 'B007', 'PR010', 'https//tokped', 'Hard Drive 1TB', 'SSD 1TB Kingston', '1200000.00', 2, '2400000.00', 'PROJ-003'),
(15, 'B002', 'PR011', 'https//robot', 'Monitor 24 inch', 'Monitor LED 24 inch Full HD', '2500000.00', 2, '5000000.00', 'PROJ-001'),
(16, 'B009', 'PR20260003', 'link.io', 'Router WiFi', 'Router WiFi 6 AX1800', '800000.00', 3, '2400000.00', 'PROJ-004'),
(17, 'BRG-002', 'PR20260003', 'tokped', 'Roll kabel', 'Kabel', '20000.00', 3, '60000.00', 'PRJ0028'),
(18, 'B009', 'PR20260004', 'https', 'Router WiFi', 'Router WiFi 6 AX1800', '800000.00', 1, '800000.00', 'PROJ-004'),
(19, 'BRG-003', 'PR20260004', 'tokped', 'kampas kopling', 'kampas', '57000.00', 3, '171000.00', 'PRJ-028'),
(20, 'B005', 'PR20260005', 'tokped', 'Printer HP', 'Printer LaserJet Pro M404n', '2200000.00', 1, '2200000.00', 'PROJ-002'),
(21, 'BRG-004', 'PR20260005', 'Ibox', 'Iphone 17 PM 2TB', 'smartphone', '23000000.00', 2, '46000000.00', 'PRJ-0123'),
(22, 'B009', 'PR20260006', 'tokped', 'Router WiFi', 'Router WiFi 6 AX1800', '800000.00', 1, '800000.00', 'PROJ-004'),
(23, 'BRG-005', 'PR20260006', 'pkw', 'TPlink', 'router', '300000.00', 2, '600000.00', 'PRJ-089'),
(24, 'B005', 'PR20260007', 'tokped', 'Printer HP', 'Printer LaserJet Pro M404n', '2200000.00', 1, '2200000.00', 'PROJ-002'),
(25, 'BRG-006', 'PR20260007', 'tokped', 'sapu', 'sapu lidi', '20000.00', 1, '20000.00', 'PROJ-002'),
(26, 'BRG-003', 'PR20260008', 'https', 'kampas kopling', 'kampas', '57000.00', 1, '57000.00', 'PRJ-028'),
(27, 'BRG-004', 'PR20260009', 'tokped', 'Iphone 17 PM 2TB', 'smartphone', '23000000.00', 1, '23000000.00', 'PRJ-0123'),
(28, 'BRG-005', 'PR20260010', 'tokped', 'TPlink', 'router', '300000.00', 1, '300000.00', 'PRJ-089'),
(29, 'BRG-007', 'PR20260010', 'inf', 'Infinix', 'smartphone', '1900000.00', 1, '1900000.00', 'PR2026'),
(30, 'B009', 'PR20260011', 'tokped', 'Router WiFi', 'Router WiFi 6 AX1800', '800000.00', 1, '800000.00', 'PROJ-004'),
(31, NULL, 'TEST1775718629', '', 'Test Item', 'Test', '10000.00', 1, '10000.00', 'PRJ-TEST'),
(32, NULL, 'TEST1775718637', '', 'Test Item', 'Test', '10000.00', 1, '10000.00', 'PRJ-TEST'),
(33, 'B007', 'TEST1775718793', '', 'Test Item', 'Test detail with valid barang', '10000.00', 1, '10000.00', 'PRJ-TEST'),
(34, 'B007', 'TEST1775718794', '', 'Test Item', 'Test detail with valid barang', '10000.00', 1, '10000.00', 'PRJ-TEST'),
(35, 'B007', 'TEST1775719028', '', 'Test Item', 'Test detail with valid barang', '10000.00', 1, '10000.00', 'PRJ-TEST'),
(36, 'B007', 'TEST1775719251', '', 'Test Item', 'Test detail with valid barang', '10000.00', 1, '10000.00', 'PRJ-TEST'),
(37, 'B007', 'TEST1775719252', '', 'Test Item', 'Test detail with valid barang', '10000.00', 1, '10000.00', 'PRJ-TEST'),
(38, 'B007', 'TEST1775719253', '', 'Test Item', 'Test detail with valid barang', '10000.00', 1, '10000.00', 'PRJ-TEST'),
(39, 'B007', 'TEST1775719255', '', 'Test Item', 'Test detail with valid barang', '10000.00', 1, '10000.00', 'PRJ-TEST'),
(40, 'B007', 'TEST1775719256', '', 'Test Item', 'Test detail with valid barang', '10000.00', 1, '10000.00', 'PRJ-TEST'),
(41, 'B007', 'TEST1775719257', '', 'Test Item', 'Test detail with valid barang', '10000.00', 1, '10000.00', 'PRJ-TEST'),
(42, 'B007', 'TEST1775719258', '', 'Test Item', 'Test detail with valid barang', '10000.00', 1, '10000.00', 'PRJ-TEST'),
(43, 'B007', 'TEST1775719261', '', 'Test Item', 'Test detail with valid barang', '10000.00', 1, '10000.00', 'PRJ-TEST'),
(44, 'B007', 'TEST1775719312', '', 'Test Item', 'Test detail with valid barang', '10000.00', 1, '10000.00', 'PRJ-TEST'),
(45, 'B007', 'TEST1775720549', '', 'Test Item', 'Test detail with valid barang', '10000.00', 1, '10000.00', 'PRJ-TEST'),
(46, 'B007', 'TEST_EXISTING_1775720555', '', 'Test Existing Item', 'Testing with existing barang', '50000.00', 2, '100000.00', 'PRJ-TEST-1'),
(47, 'B007', 'TEST_EXISTING_1775721149', '', 'Test Existing Item', 'Testing with existing barang', '50000.00', 2, '100000.00', 'PRJ-TEST-1'),
(50, '1', 'TEST1775750247', '', 'Test Item', 'Test detail with valid barang', '10000.00', 1, '10000.00', 'PRJ-TEST'),
(51, '1', 'TEST1775753072', '', 'Test Item', 'Test detail with valid barang', '10000.00', 1, '10000.00', 'PRJ-TEST'),
(52, '1', 'TEST1775753073', '', 'Test Item', 'Test detail with valid barang', '10000.00', 1, '10000.00', 'PRJ-TEST'),
(53, '1', 'TEST1775753074', '', 'Test Item', 'Test detail with valid barang', '10000.00', 1, '10000.00', 'PRJ-TEST'),
(54, '1', 'TEST1775753075', '', 'Test Item', 'Test detail with valid barang', '10000.00', 1, '10000.00', 'PRJ-TEST'),
(55, '1', 'TEST1775753076', '', 'Test Item', 'Test detail with valid barang', '10000.00', 1, '10000.00', 'PRJ-TEST'),
(56, '1', 'TEST1775753077', '', 'Test Item', 'Test detail with valid barang', '10000.00', 1, '10000.00', 'PRJ-TEST'),
(63, 'BRG20260409022106', 'PR20260046', 'https://example.comTP', 'Iphone 17 PM 2 Tb', 'bom', '23000000.00', 1, '23000000.00', 'PRJ-01'),
(64, '2', 'PR20260046', 'https://example.comTP', 'Spatula', 'e', '20000.00', 1, '20000.00', 'PRJ-01'),
(65, 'BRG-002', 'PR20260047', 'https://example.comTP', 'Roll kabel', 'panjang 5 m', '20000.00', 7, '140000.00', 'PRJ-01'),
(66, '3', 'PR20260047', 'https://example.comTP', 'Tinta ptinter', 'variasi warna', '46000.00', 6, '276000.00', 'PRJ-01'),
(67, 'B010', 'PR20260047', 'https://example.comTP', 'Speaker', 'JBL', '750000.00', 1, '750000.00', 'PRJ-01'),
(68, '4', 'PR20260047', 'https://example.comTP', 'flashdisk', 'ukuran 512GB', '450000.00', 1, '450000.00', 'PRJ-01'),
(69, 'B004', 'PR20260048', 'https://example.comTP', 'Mouse Wireless', 'butuh', '200000.00', 1, '200000.00', 'PRJ-01'),
(70, '5', 'PR20260048', '', 'palu', 'alat', '40000.00', 1, '40000.00', 'PRJ-01');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `idinventory` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `idbarang` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `kodebarang` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `idkategori` int DEFAULT NULL,
  `lokasi` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `kodeproject` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `nama_barang` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `harga` decimal(15,2) DEFAULT '0.00',
  `stok_awal` int DEFAULT '0',
  `stok_akhir` int DEFAULT '0',
  `qty_in` int DEFAULT '0',
  `qty_out` int DEFAULT '0',
  `total` decimal(20,2) DEFAULT '0.00',
  `keterangan` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`idinventory`, `idbarang`, `kodebarang`, `idkategori`, `lokasi`, `kodeproject`, `nama_barang`, `harga`, `stok_awal`, `stok_akhir`, `qty_in`, `qty_out`, `total`, `keterangan`) VALUES
('INV001', 'B001', 'KB001', 2, 'Gudang A', 'PROJ-001', 'Laptop Dell', '15000000.00', 10, 8, 10, 2, '120000000.00', 'Laptop untuk kebutuhan kantor'),
('INV002', 'B002', 'KB002', 2, 'Gudang A', 'PROJ-001', 'Monitor 24 inch', '2500000.00', 20, 18, 20, 2, '45000000.00', 'Monitor untuk kebutuhan kantor'),
('INV003', 'B003', 'KB003', 5, 'Gudang B', 'PROJ-001', 'Keyboard Wireless', '300000.00', 50, 45, 50, 5, '13500000.00', 'Keyboard sparepart'),
('INV004', 'B004', 'KB004', 5, 'Gudang B', 'PROJ-001', 'Mouse Wireless', '200000.00', 50, 46, 50, 4, '9200000.00', 'Mouse sparepart'),
('INV005', 'B005', 'KB005', 2, 'Gudang A', 'PROJ-002', 'Printer HP', '2200000.00', 5, 4, 5, 1, '8800000.00', 'Printer untuk divisi administrasi'),
('INV006', 'KB-05', 'BRG-1001', 4, 'Gudang atas', 'PRJ-0009', 'flashdisk', '4500000.00', 4, 3, 0, 0, '13500000.00', 'r');

-- --------------------------------------------------------

--
-- Table structure for table `kategoribarang`
--

CREATE TABLE `kategoribarang` (
  `idkategori` int NOT NULL,
  `nama_kategori` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `keterangan` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kategoribarang`
--

INSERT INTO `kategoribarang` (`idkategori`, `nama_kategori`, `keterangan`) VALUES
(1, 'Inventory', 'Item diatas 5000 dan dipergunakan untuk kebutuhan project'),
(2, 'Asset', 'Item diatas 100.000, mendukung proses produksi, dipakai untuk operasional dan tidak dijual'),
(3, 'WIP', 'Work in Progress (belum selesai dirakit)'),
(4, 'Finish Good', 'Barang sudah jadi dan siap dijual'),
(5, 'RAKO', 'Komponen dibawah 5000, tidak disimpan di gudang, digunakan utk PR & PO');

-- --------------------------------------------------------

--
-- Table structure for table `logstatusbarang`
--

CREATE TABLE `logstatusbarang` (
  `idlogstatusbarang` int NOT NULL,
  `iddetailrequest` int DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `date` datetime DEFAULT NULL,
  `keterangan` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `logstatusbarang`
--

INSERT INTO `logstatusbarang` (`idlogstatusbarang`, `iddetailrequest`, `status`, `date`, `keterangan`) VALUES
(1, 1, 'Ordered', '2025-01-18 10:00:00', 'Item ordered'),
(2, 2, 'Ordered', '2025-01-18 10:00:00', 'Item ordered'),
(3, 3, 'Ordered', '2025-01-18 10:00:00', 'Item ordered'),
(4, 4, 'Ordered', '2025-01-23 10:00:00', 'Item ordered'),
(5, 5, 'Ordered', '2025-01-23 10:00:00', 'Item ordered');

-- --------------------------------------------------------

--
-- Table structure for table `logstatusorder`
--

CREATE TABLE `logstatusorder` (
  `idlogstatusorder` int NOT NULL,
  `status` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `date` datetime NOT NULL,
  `keterangan` text COLLATE utf8mb4_general_ci,
  `idpurchaseorder` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `logstatusorder`
--

INSERT INTO `logstatusorder` (`idlogstatusorder`, `status`, `date`, `keterangan`, `idpurchaseorder`) VALUES
(1, 'Process Order', '2025-01-18 10:00:00', 'PO created', 'PO001'),
(2, 'Process Payment', '2025-01-19 11:30:00', 'Payment initiated', 'PO001'),
(3, 'Process Delivery', '2025-01-20 13:00:00', 'Delivery scheduled', 'PO001'),
(4, 'Arrived', '2025-01-20 15:00:00', 'Items received', 'PO001'),
(5, 'Process Order', '2025-01-23 10:00:00', 'PO created', 'PO002'),
(6, 'Process Payment', '2025-01-24 11:00:00', 'Payment initiated', 'PO002'),
(7, 'Process Order', '2025-12-31 01:16:30', 'New purchase order created', 'PO20250001'),
(8, 'Process Order', '2025-12-31 01:17:21', '', 'PO20250001'),
(9, 'Arrived', '2025-12-31 01:17:26', '', 'PO20250001'),
(10, 'Process Order', '2026-01-12 00:27:30', 'New purchase order created', 'PO20260001'),
(11, 'Arrived', '2026-02-03 08:53:02', '', 'PO20260001'),
(12, 'Arrived', '2026-04-07 09:10:25', '', 'PO20260001'),
(13, 'Process Order', '2026-04-09 09:24:01', 'New purchase order created', 'PO20260002'),
(14, 'Process Payment', '2026-04-09 09:24:24', 'dalam proses pembayaran', 'PO20260002'),
(15, 'Process Order', '2026-04-10 11:20:35', 'New purchase order created', 'PO20260003'),
(16, 'Process Delivery', '2026-04-10 11:21:23', 'kemungkinan barang akan sampai tujuan dengan tanggal yang terpisah', 'PO20260003');

-- --------------------------------------------------------

--
-- Table structure for table `logstatusreq`
--

CREATE TABLE `logstatusreq` (
  `idlogstatusreq` int NOT NULL,
  `status` int NOT NULL,
  `date` datetime NOT NULL,
  `note_reject` text COLLATE utf8mb4_general_ci,
  `idrequest` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `logstatusreq`
--

INSERT INTO `logstatusreq` (`idlogstatusreq`, `status`, `date`, `note_reject`, `idrequest`) VALUES
(1, 1, '2025-01-15 09:15:00', NULL, 'PR001'),
(2, 2, '2025-01-16 10:00:00', NULL, 'PR001'),
(3, 3, '2025-01-17 14:30:00', NULL, 'PR001'),
(4, 1, '2025-01-20 10:45:00', NULL, 'PR002'),
(5, 2, '2025-01-21 11:30:00', NULL, 'PR002'),
(6, 1, '2025-01-22 14:15:00', NULL, 'PR003'),
(7, 5, '2025-01-25 13:20:00', 'Budget tidak mencukupi', 'PR004'),
(8, 3, '2025-12-31 01:15:54', NULL, 'PR007'),
(9, 3, '2026-01-11 23:59:33', NULL, 'PR008'),
(10, 3, '2026-01-12 00:27:09', NULL, 'PR009'),
(11, 3, '2026-02-03 08:47:24', NULL, 'PR009'),
(12, 3, '2026-02-03 08:47:41', NULL, 'PR010'),
(13, 3, '2026-02-03 08:49:37', NULL, 'PR010'),
(14, 3, '2026-02-03 08:51:06', NULL, 'PR011'),
(15, 1, '2026-04-06 08:59:27', 'Submitted for approval', 'PR20260001'),
(17, 1, '2026-04-07 09:02:55', 'Submitted for leader approval', 'PR20260003'),
(18, 3, '2026-04-07 09:04:15', NULL, 'PR20260003'),
(19, 5, '2026-04-07 09:04:31', NULL, 'PR20260001'),
(20, 5, '2026-04-07 09:04:34', NULL, 'PR20260001'),
(21, 1, '2026-04-07 09:39:42', 'Submitted for leader approval', 'PR20260004'),
(22, 1, '2026-04-07 10:06:02', 'Submitted for leader approval', 'PR20260005'),
(23, 2, '2026-04-07 10:08:05', NULL, 'PR20260005'),
(24, 1, '2026-04-07 10:23:45', 'Submitted for leader approval', 'PR20260006'),
(25, 2, '2026-04-07 10:25:16', NULL, 'PR20260006'),
(26, 1, '2026-04-07 10:59:47', 'Submitted for leader approval', 'PR20260007'),
(27, 5, '2026-04-07 11:02:35', NULL, 'PR20260007'),
(28, 5, '2026-04-07 14:05:59', NULL, 'PR20260004'),
(29, 1, '2026-04-07 14:08:12', 'Submitted for leader approval', 'PR20260008'),
(30, 5, '2026-04-07 14:09:29', NULL, 'PR20260008'),
(31, 1, '2026-04-08 08:18:22', 'Submitted for leader approval', 'PR20260009'),
(32, 5, '2026-04-08 08:18:44', NULL, 'PR20260009'),
(33, 5, '2026-04-08 08:20:23', NULL, 'PR20260006'),
(34, 3, '2026-04-08 08:20:37', NULL, 'PR20260005'),
(35, 5, '2026-04-08 09:07:09', 'Testing', 'PR20260001'),
(36, 5, '2026-04-08 09:07:33', 'Testing', 'PR20260001'),
(37, 5, '2026-04-08 09:07:41', 'Testing', 'PR20260001'),
(38, 2, '2026-04-08 09:07:57', NULL, 'PR20260001'),
(39, 1, '2026-04-08 09:10:44', 'Submitted for leader approval', 'PR20260010'),
(40, 5, '2026-04-08 09:11:23', 'gada', 'PR20260010'),
(41, 1, '2026-04-08 10:49:55', 'Submitted for leader approval', 'PR20260011'),
(42, 2, '2026-04-10 09:10:38', NULL, 'PR20260046'),
(43, 2, '2026-04-10 11:03:41', NULL, 'PR20260047'),
(44, 2, '2026-04-10 11:06:06', NULL, 'PR20260048'),
(45, 3, '2026-04-10 11:06:41', NULL, 'PR20260048'),
(46, 5, '2026-04-10 11:07:00', 'barang tidak butuh', 'PR20260046'),
(47, 3, '2026-04-10 11:07:13', NULL, 'PR20260047');

-- --------------------------------------------------------

--
-- Table structure for table `master_status_barang`
--

CREATE TABLE `master_status_barang` (
  `idstatus` int NOT NULL,
  `nama_status` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `keterangan` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `master_status_barang`
--

INSERT INTO `master_status_barang` (`idstatus`, `nama_status`, `keterangan`) VALUES
(1, 'Process Order', 'Barang diproses oleh procurement'),
(2, 'Process Payment', 'Pembayaran sedang diproses'),
(3, 'Process Delivery', 'Barang dalam pengiriman'),
(4, 'Arrived', 'Barang telah diterima');

-- --------------------------------------------------------

--
-- Table structure for table `master_status_pr`
--

CREATE TABLE `master_status_pr` (
  `idstatus` int NOT NULL,
  `nama_status` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `keterangan` text COLLATE utf8mb4_general_ci
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `master_status_pr`
--

INSERT INTO `master_status_pr` (`idstatus`, `nama_status`, `keterangan`) VALUES
(1, 'Process Approval Leader', 'Requestor submit PR, sistem kirim email ke Leader'),
(2, 'Process Approval Manager', 'Leader approved, sistem kirim email ke Manager'),
(3, 'Approved', 'Manager menyetujui request'),
(4, 'Hold', 'Request di-hold oleh Leader / Manager'),
(5, 'Reject', 'Request ditolak dengan catatan'),
(6, 'Done', 'Semua barang terpenuhi (100%)');

-- --------------------------------------------------------

--
-- Table structure for table `m_barang`
--

CREATE TABLE `m_barang` (
  `idbarang` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `kodebarang` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `nama_barang` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `deskripsi` text COLLATE utf8mb4_general_ci,
  `harga` decimal(15,2) DEFAULT '0.00',
  `satuan` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `kodeproject` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `idkategori` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `m_barang`
--

INSERT INTO `m_barang` (`idbarang`, `kodebarang`, `nama_barang`, `deskripsi`, `harga`, `satuan`, `kodeproject`, `idkategori`, `created_at`) VALUES
('1', 'BRG-999', 'Manual Test Barang', NULL, '75000.00', 'PCS', NULL, NULL, '2026-04-09 07:55:09'),
('2', 'BRG-1000', 'Spatula', NULL, '20000.00', 'PCS', NULL, NULL, '2026-04-10 02:10:05'),
('3', 'BRG-1000', 'Tinta ptinter', NULL, '46000.00', 'PCS', NULL, 1, '2026-04-10 04:02:10'),
('4', 'BRG-1001', 'flashdisk', NULL, '450000.00', 'PCS', NULL, 2, '2026-04-10 04:02:10'),
('5', 'BRG-1000', 'palu', NULL, '40000.00', 'PCS', NULL, 1, '2026-04-10 04:05:41'),
('B001', 'KB001', 'Laptop Dell', 'Laptop Dell Core i7, 16GB RAM', '15000000.00', 'unit', 'PROJ-001', 2, '2025-12-29 09:20:55'),
('B002', 'KB002', 'Monitor 24 inch', 'Monitor LED 24 inch Full HD', '2500000.00', 'unit', 'PROJ-001', 2, '2025-12-29 09:20:55'),
('B003', 'KB003', 'Keyboard Wireless', 'Keyboard Wireless Logitech', '300000.00', 'pcs', 'PROJ-001', 5, '2025-12-29 09:20:55'),
('B004', 'KB004', 'Mouse Wireless', 'Mouse Wireless Logitech', '200000.00', 'pcs', 'PROJ-001', 5, '2025-12-29 09:20:55'),
('B005', 'KB005', 'Printer HP', 'Printer LaserJet Pro M404n', '2200000.00', 'unit', 'PROJ-002', 2, '2025-12-29 09:20:55'),
('B006', 'KB006', 'Kabel USB', 'Kabel USB Type-C 2M', '50000.00', 'pcs', 'PROJ-002', 5, '2025-12-29 09:20:55'),
('B007', 'KB007', 'Hard Drive 1TB', 'SSD 1TB Kingston', '1200000.00', 'unit', 'PROJ-003', 1, '2025-12-29 09:20:55'),
('B008', 'KB008', 'RAM 8GB', 'RAM DDR4 8GB', '600000.00', 'pcs', 'PROJ-003', 1, '2025-12-29 09:20:55'),
('B009', 'KB009', 'Router WiFi', 'Router WiFi 6 AX1800', '800000.00', 'unit', 'PROJ-004', 1, '2025-12-29 09:20:55'),
('B010', 'KB010', 'Speaker', 'Speaker Bluetooth JBL', '750000.00', 'unit', 'PROJ-004', 1, '2025-12-29 09:20:55'),
('BRG-001', 'BR-011', 'Laptop Huwawei Matebook D14', 'kebutuhan pekerjaan', '9000000.00', 'unit', 'PROJ-006', 1, '2025-12-30 18:15:11'),
('BRG-002', 'BR-012', 'Roll kabel', 'Kabel', '20000.00', 'unit', 'PRJ0028', 1, '2026-04-07 02:02:55'),
('BRG-003', 'BR-013', 'kampas kopling', 'kampas', '57000.00', 'pcs', 'PRJ-028', 1, '2026-04-07 02:39:42'),
('BRG-004', 'BR-014', 'Iphone 17 PM 2TB', 'smartphone', '23000000.00', 'pcs', 'PRJ-0123', 1, '2026-04-07 03:06:02'),
('BRG-005', 'BR-015', 'TPlink', 'router', '300000.00', 'pcs', 'PRJ-089', 1, '2026-04-07 03:23:45'),
('BRG-006', 'BR-016', 'sapu', 'sapu lidi', '20000.00', 'pcs', 'PROJ-002', 1, '2026-04-07 03:59:47'),
('BRG-007', 'BR-017', 'Infinix', 'smartphone', '1900000.00', 'pcs', 'PR2026', 1, '2026-04-08 02:10:44'),
('BRG20260409021043', 'KB009', 'Maple', 'logistik', '40000.00', 'set', 'PRJ-0098', 5, '2026-04-09 02:10:43'),
('BRG20260409021051', 'KB009', 'Maple', 'logistik', '40000.00', 'set', 'PRJ-0098', 5, '2026-04-09 02:10:51'),
('BRG20260409021548', 'KB003', 'Maple', 'mas', '200.00', 'unit', 'PRJ-004', 2, '2026-04-09 02:15:48'),
('BRG20260409021734', 'BR-003', 'Maple', 'mama', '1111.00', 'unit', 'PRJ-006', 2, '2026-04-09 02:17:34'),
('BRG20260409022053', 'BR-014', 'Iphone 17 PM 2 Tb', 'barang masuk untuk PRJ-0123', '23000000.00', 'pcs', 'PRJ-0123', 1, '2026-04-09 02:20:53'),
('BRG20260409022102', 'BR-014', 'Iphone 17 PM 2 Tb', 'barang masuk untuk PRJ-0123', '23000000.00', 'pcs', 'PRJ-0123', 1, '2026-04-09 02:21:02'),
('BRG20260409022106', 'BR-014', 'Iphone 17 PM 2 Tb', 'barang masuk untuk PRJ-0123', '23000000.00', 'pcs', 'PRJ-0123', 1, '2026-04-09 02:21:06'),
('BRG20260410022005', 'BRG-1001', 'Poco X8 pro', 'smartphone', '5800000.00', 'unit', 'PRJ-109', 4, '2026-04-10 02:20:05'),
('BRG20260410062811', 'BRG-1001', 'flashdisk', 'sandisk', '450000.00', 'pcs', '', 4, '2026-04-10 06:28:11'),
('KB-05', 'BRG-1001', 'flashdisk', NULL, '4500000.00', 'PCS', NULL, 4, '2026-04-10 06:44:11');

-- --------------------------------------------------------

--
-- Table structure for table `purchaseorder`
--

CREATE TABLE `purchaseorder` (
  `idpurchaseorder` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `idrequest` varchar(30) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `supplier` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tgl_po` date DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchaseorder`
--

INSERT INTO `purchaseorder` (`idpurchaseorder`, `idrequest`, `supplier`, `tgl_po`, `created_at`) VALUES
('PO001', 'PR001', 'PT. Teknologi Jaya', '2025-01-18', '2025-12-29 09:20:55'),
('PO002', 'PR002', 'CV. Elektronik Makmur', '2025-01-23', '2025-12-29 09:20:55'),
('PO20250001', 'PR007', 'Tokopedia.ID', '2025-12-31', '2025-12-30 18:16:30'),
('PO20260001', 'PR009', 'PT. Screen', '2026-01-15', '2026-01-11 17:27:30'),
('PO20260002', 'PR002', 'PT. Peripheral', '2026-04-09', '2026-04-09 02:24:01'),
('PO20260003', 'PR20260048', 'PT.DELL, CV.Jaya Abadi', '2026-04-10', '2026-04-10 04:20:35');

-- --------------------------------------------------------

--
-- Table structure for table `purchaserequest`
--

CREATE TABLE `purchaserequest` (
  `idrequest` varchar(30) COLLATE utf8mb4_general_ci NOT NULL,
  `iduserrequest` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `namarequestor` varchar(200) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `keterangan` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `tgl_req` datetime DEFAULT NULL,
  `tgl_butuh` date DEFAULT NULL,
  `idsupervisor` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `purchaserequest`
--

INSERT INTO `purchaserequest` (`idrequest`, `iduserrequest`, `namarequestor`, `keterangan`, `tgl_req`, `tgl_butuh`, `idsupervisor`, `status`, `created_at`) VALUES
('PR001', 'USR-007', 'Staff', 'Pengadaan peralatan kantor', '2025-01-15 09:00:00', '2025-01-25', 'USR-002', 'Approved', '2025-12-29 09:20:55'),
('PR002', 'USR-007', 'Staff', 'Pengadaan peralatan IT', '2025-01-20 10:30:00', '2025-01-30', 'USR-002', 'Process Approval Manager', '2025-12-29 09:20:55'),
('PR003', 'USR-004', 'Procurement', 'Pengadaan suku cadang', '2025-01-22 14:00:00', '2025-02-05', 'USR-003', 'Pending', '2025-12-29 09:20:55'),
('PR004', 'USR-005', 'Inventory', 'Pengadaan perlengkapan kantor', '2025-01-25 11:15:00', '2025-02-10', 'USR-002', 'Reject', '2025-12-29 09:20:55'),
('PR005', NULL, 'mamad', 'kebutuhan', '2025-12-29 16:26:00', '2026-01-08', 'USR-003', '1', '2025-12-29 09:27:11'),
('PR006', NULL, 'mamad', 'kebutuhan', '2025-12-29 16:26:00', '2026-01-08', 'USR-003', '1', '2025-12-29 09:37:31'),
('PR007', NULL, 'arifin', 'Untuk Kebutuhan Mendesa', '2025-12-31 01:12:00', '2026-01-02', 'USR-003', '1', '2025-12-30 18:15:11'),
('PR008', NULL, 'Dimas', 'kebutuhan\r\n', '2026-01-11 23:58:00', '2026-01-19', 'USR-002', '1', '2026-01-11 16:58:59'),
('PR009', NULL, 'Kelpa', 'butuh', '2026-01-12 00:25:00', '2026-01-19', 'USR-002', '1', '2026-01-11 17:26:39'),
('PR010', NULL, 'arif', 'butuh', '2026-02-03 08:45:00', '2026-02-28', 'USR-002', '1', '2026-02-03 01:46:26'),
('PR011', NULL, 'dimas', 'butuh', '2026-02-03 08:50:00', '2026-02-27', 'USR-002', '1', '2026-02-03 01:50:40'),
('PR20260001', 'USR-MAR-01', 'diana', 'bbb', '2026-04-06 00:00:00', '2026-04-08', 'USR-008', '2', '2026-04-06 01:59:27'),
('PR20260003', 'USR-OFF-01', 'gunawan', 'kebutuhan kantor', '2026-04-07 00:00:00', '2026-04-24', 'USR-OFF-L01', '1', '2026-04-07 02:02:55'),
('PR20260004', 'USR-MAR-02', 'Eko', 'butuh', '2026-04-07 00:00:00', '2026-04-11', 'USR-008', '5', '2026-04-07 02:39:42'),
('PR20260005', 'USR-OFF-02', 'Hana', 'Kebutuhan', '2026-04-07 00:00:00', '2026-04-15', 'USR-OFF-L01', '3', '2026-04-07 03:06:02'),
('PR20260006', 'USR-OFF-03', 'indra', 'butuh', '2026-04-07 00:00:00', '2026-04-11', 'USR-OFF-L01', '5', '2026-04-07 03:23:45'),
('PR20260007', 'USR-MAR-02', 'Eko', 'butuh', '2026-04-07 00:00:00', '2026-04-16', 'USR-008', '5', '2026-04-07 03:59:47'),
('PR20260008', 'USR-TEK-03', 'Citra', 'butuh', '2026-04-07 00:00:00', '2026-04-17', 'USR-TEK-L01', '5', '2026-04-07 07:08:12'),
('PR20260009', 'USR-MAR-02', 'Eko', 'Butuh banget', '2026-04-08 00:00:00', '2026-04-18', 'USR-008', '5', '2026-04-08 01:18:22'),
('PR20260010', 'USR-OFF-02', 'Hana', 'Kebutuhan', '2026-04-08 00:00:00', '2026-04-11', 'USR-OFF-L01', '5', '2026-04-08 02:10:44'),
('PR20260011', 'USR-OFF-02', 'Hana', 'butuh', '2026-04-08 00:00:00', '2026-04-17', 'USR-OFF-L01', '1', '2026-04-08 03:49:55'),
('PR20260046', NULL, 'Eko', 'Budaphest', '2026-04-10 01:58:00', '2026-04-17', 'USR-008', '5', '2026-04-10 02:10:05'),
('PR20260047', NULL, 'indra', 'butuh', '2026-04-10 03:57:00', '2026-04-17', 'USR-OFF-L01', '3', '2026-04-10 04:02:10'),
('PR20260048', NULL, 'budi', 'butuh', '2026-04-10 04:04:00', '2026-04-17', 'USR-TEK-L01', '3', '2026-04-10 04:05:41'),
('TEST1775718629', NULL, 'Test User', 'Database test', '2026-04-09 14:10:29', '2026-04-09', 'USR-002', '1', '2026-04-09 07:10:29'),
('TEST1775718637', NULL, 'Test User', 'Database test', '2026-04-09 14:10:37', '2026-04-09', 'USR-002', '1', '2026-04-09 07:10:37'),
('TEST1775718793', NULL, 'Test User', 'Database test', '2026-04-09 14:13:13', '2026-04-09', 'USR-002', '1', '2026-04-09 07:13:13'),
('TEST1775718794', NULL, 'Test User', 'Database test', '2026-04-09 14:13:14', '2026-04-09', 'USR-002', '1', '2026-04-09 07:13:14'),
('TEST1775719028', NULL, 'Test User', 'Database test', '2026-04-09 14:17:08', '2026-04-09', 'USR-002', '1', '2026-04-09 07:17:08'),
('TEST1775719251', NULL, 'Test User', 'Database test', '2026-04-09 14:20:51', '2026-04-09', 'USR-002', '1', '2026-04-09 07:20:51'),
('TEST1775719252', NULL, 'Test User', 'Database test', '2026-04-09 14:20:52', '2026-04-09', 'USR-002', '1', '2026-04-09 07:20:52'),
('TEST1775719253', NULL, 'Test User', 'Database test', '2026-04-09 14:20:53', '2026-04-09', 'USR-002', '1', '2026-04-09 07:20:53'),
('TEST1775719255', NULL, 'Test User', 'Database test', '2026-04-09 14:20:55', '2026-04-09', 'USR-002', '1', '2026-04-09 07:20:55'),
('TEST1775719256', NULL, 'Test User', 'Database test', '2026-04-09 14:20:56', '2026-04-09', 'USR-002', '1', '2026-04-09 07:20:56'),
('TEST1775719257', NULL, 'Test User', 'Database test', '2026-04-09 14:20:57', '2026-04-09', 'USR-002', '1', '2026-04-09 07:20:57'),
('TEST1775719258', NULL, 'Test User', 'Database test', '2026-04-09 14:20:58', '2026-04-09', 'USR-002', '1', '2026-04-09 07:20:58'),
('TEST1775719261', NULL, 'Test User', 'Database test', '2026-04-09 14:21:01', '2026-04-09', 'USR-002', '1', '2026-04-09 07:21:01'),
('TEST1775719312', NULL, 'Test User', 'Database test', '2026-04-09 14:21:52', '2026-04-09', 'USR-002', '1', '2026-04-09 07:21:52'),
('TEST1775720549', NULL, 'Test User', 'Database test', '2026-04-09 14:42:29', '2026-04-09', 'USR-002', '1', '2026-04-09 07:42:29'),
('TEST1775750247', NULL, 'Test User', 'Database test', '2026-04-09 22:57:27', '2026-04-09', 'USR-002', '1', '2026-04-09 15:57:27'),
('TEST1775753072', NULL, 'Test User', 'Database test', '2026-04-09 23:44:32', '2026-04-09', 'USR-002', '1', '2026-04-09 16:44:32'),
('TEST1775753073', NULL, 'Test User', 'Database test', '2026-04-09 23:44:33', '2026-04-09', 'USR-002', '1', '2026-04-09 16:44:33'),
('TEST1775753074', NULL, 'Test User', 'Database test', '2026-04-09 23:44:34', '2026-04-09', 'USR-002', '1', '2026-04-09 16:44:34'),
('TEST1775753075', NULL, 'Test User', 'Database test', '2026-04-09 23:44:35', '2026-04-09', 'USR-002', '1', '2026-04-09 16:44:35'),
('TEST1775753076', NULL, 'Test User', 'Database test', '2026-04-09 23:44:36', '2026-04-09', 'USR-002', '1', '2026-04-09 16:44:36'),
('TEST1775753077', NULL, 'Test User', 'Database test', '2026-04-09 23:44:37', '2026-04-09', 'USR-002', '1', '2026-04-09 16:44:37'),
('TEST_EXISTING_1775720555', NULL, 'USR-002', 'Test PR Existing', '2026-04-09 14:42:35', '2026-04-09', 'USR-002', '1', '2026-04-09 07:42:35'),
('TEST_EXISTING_1775721149', NULL, 'USR-002', 'Test PR Existing', '2026-04-09 14:52:29', '2026-04-09', 'USR-002', '1', '2026-04-09 07:52:29');

-- --------------------------------------------------------

--
-- Table structure for table `sequences`
--

CREATE TABLE `sequences` (
  `name` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `last_no` int NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sequences`
--

INSERT INTO `sequences` (`name`, `last_no`) VALUES
('barangkeluar', 2),
('barangmasuk', 2),
('inventory', 5),
('m_barang', 10),
('purchaseorder', 2),
('purchaserequest', 4);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `iduser` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `username` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `nama` varchar(200) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `email` varchar(150) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `roletype` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `leader_type` varchar(50) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`iduser`, `username`, `nama`, `password`, `email`, `roletype`, `leader_type`, `created_at`) VALUES
('USR-001', 'admin', 'Admin User', 'admin123', 'admin@example.com', 'Admin', NULL, '2025-12-29 09:12:49'),
('USR-002', 'leader', 'Leader One', 'leader123', 'leader@example.com', 'Leader', 'Teknisi', '2025-12-29 09:12:49'),
('USR-003', 'manager', 'Manager Ops', 'manager123', 'manager@example.com', 'Manager', NULL, '2025-12-29 09:12:49'),
('USR-004', 'procure', 'Procurement', 'procure123', 'procure@example.com', 'Procurement', NULL, '2025-12-29 09:12:49'),
('USR-005', 'inventory', 'Inventory', 'inventory123', 'inventory@example.com', 'Inventory', NULL, '2025-12-29 09:12:49'),
('USR-006', 'nur', 'Nur', 'nur123', 'nur@example.com', 'Procurement', NULL, '2025-12-29 09:12:49'),
('USR-007', 'staff', 'Staff', 'staff123', 'staff@example.com', 'Staff', NULL, '2025-12-29 09:12:49'),
('USR-008', 'leader_marketing', 'Marketing Leader ', 'marketing_123\r\n', 'marketing@example.com', 'Leader', 'Marketing', '2026-03-31 06:59:40'),
('USR-MAR-01', 'Diana', 'marketing_01', 'diana20', 'diana.marketing@example.com', 'Staff', NULL, '2026-03-31 07:25:46'),
('USR-MAR-02', 'Eko', 'marketing_02', 'eko20', 'eko.prasetyo@example.com', 'Staff', NULL, '2026-03-31 07:25:46'),
('USR-MAR-03', 'Fitri', 'marketing_03', 'fitri20', 'fitri.handayani@example.com', 'Staff', NULL, '2026-03-31 07:25:46'),
('USR-MAR-L01', 'marketing_leader', 'Marketing ', 'marketing123', 'marketing_leader@example.com', 'Leader', 'Marketing', '2026-03-31 07:25:46'),
('USR-OFF-01', 'gunawan', 'office_01', 'gunawan20\r\n', 'gunawan.office@example.com', 'Staff', NULL, '2026-03-31 07:25:46'),
('USR-OFF-02', 'Hana', 'office_02', 'hana20', 'hana.puspita@example.com', 'Staff', NULL, '2026-03-31 07:25:46'),
('USR-OFF-03', 'indra', 'office_03', 'indra20', 'indra.wijaya@example.com', 'Staff', NULL, '2026-03-31 07:25:46'),
('USR-OFF-L01', 'office_leader', 'office', 'office123', 'office_leader@example.com', 'Leader', 'Office', '2026-03-31 07:25:46'),
('USR-TEK-01', 'ahmad', 'teknisi_01', 'ahmad20', 'ahmad.teknisi@example.com', 'Staff', NULL, '2026-03-31 07:25:46'),
('USR-TEK-02', 'budi', 'teknisi_02', 'budi20', 'budi.santoso@example.com', 'Staff', NULL, '2026-03-31 07:25:46'),
('USR-TEK-03', 'citra', 'teknisi_03', 'citra20', 'citra.dewi@example.com', 'Staff', NULL, '2026-03-31 07:25:46'),
('USR-TEK-L01', 'teknisi_leader', 'Teknisi Leader', 'teknisi123', 'teknisi_leader@example.com', 'Leader', 'Teknisi', '2026-03-31 07:25:46');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `barangkeluar`
--
ALTER TABLE `barangkeluar`
  ADD PRIMARY KEY (`idkeluar`),
  ADD KEY `fk_bk_user_created` (`iduserprocurementcreated`),
  ADD KEY `fk_bk_user_approved` (`iduserprocurementapproved`);

--
-- Indexes for table `barangmasuk`
--
ALTER TABLE `barangmasuk`
  ADD PRIMARY KEY (`idmasuk`),
  ADD KEY `fk_bm_po` (`idpurchaseorder`),
  ADD KEY `fk_bm_usercreate` (`iduserprocurementcreate`),
  ADD KEY `fk_bm_userapproval` (`iduserprocurementapproval`);

--
-- Indexes for table `detailkeluar`
--
ALTER TABLE `detailkeluar`
  ADD PRIMARY KEY (`iddetailkeluar`),
  ADD KEY `fk_dk_barang` (`idbarang`),
  ADD KEY `fk_dk_kategori` (`idkategori`),
  ADD KEY `fk_dk_keluar` (`idkeluar`);

--
-- Indexes for table `detailmasuk`
--
ALTER TABLE `detailmasuk`
  ADD PRIMARY KEY (`iddetailmasuk`),
  ADD KEY `fk_dm_barang` (`idbarang`),
  ADD KEY `fk_dm_masuk` (`idmasuk`),
  ADD KEY `fk_dm_kategori` (`idkategori`);

--
-- Indexes for table `detailorder`
--
ALTER TABLE `detailorder`
  ADD PRIMARY KEY (`iddetailorder`),
  ADD KEY `fk_do_po` (`idpurchaseorder`),
  ADD KEY `fk_do_barang` (`idbarang`);

--
-- Indexes for table `detailrequest`
--
ALTER TABLE `detailrequest`
  ADD PRIMARY KEY (`iddetailrequest`),
  ADD KEY `fk_dr_barang` (`idbarang`),
  ADD KEY `fk_dr_request` (`idrequest`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`idinventory`),
  ADD KEY `fk_inventory_barang` (`idbarang`),
  ADD KEY `fk_inventory_kategori` (`idkategori`);

--
-- Indexes for table `kategoribarang`
--
ALTER TABLE `kategoribarang`
  ADD PRIMARY KEY (`idkategori`);

--
-- Indexes for table `logstatusbarang`
--
ALTER TABLE `logstatusbarang`
  ADD PRIMARY KEY (`idlogstatusbarang`),
  ADD KEY `fk_lsb_detailrequest` (`iddetailrequest`);

--
-- Indexes for table `logstatusorder`
--
ALTER TABLE `logstatusorder`
  ADD PRIMARY KEY (`idlogstatusorder`),
  ADD KEY `fk_lso_po` (`idpurchaseorder`);

--
-- Indexes for table `logstatusreq`
--
ALTER TABLE `logstatusreq`
  ADD PRIMARY KEY (`idlogstatusreq`),
  ADD KEY `fk_lsr_request` (`idrequest`);

--
-- Indexes for table `master_status_barang`
--
ALTER TABLE `master_status_barang`
  ADD PRIMARY KEY (`idstatus`);

--
-- Indexes for table `master_status_pr`
--
ALTER TABLE `master_status_pr`
  ADD PRIMARY KEY (`idstatus`);

--
-- Indexes for table `m_barang`
--
ALTER TABLE `m_barang`
  ADD PRIMARY KEY (`idbarang`),
  ADD KEY `fk_m_barang_kategori` (`idkategori`);

--
-- Indexes for table `purchaseorder`
--
ALTER TABLE `purchaseorder`
  ADD PRIMARY KEY (`idpurchaseorder`),
  ADD KEY `fk_po_request` (`idrequest`);

--
-- Indexes for table `purchaserequest`
--
ALTER TABLE `purchaserequest`
  ADD PRIMARY KEY (`idrequest`),
  ADD KEY `fk_pr_userrequest` (`iduserrequest`),
  ADD KEY `fk_pr_supervisor` (`idsupervisor`);

--
-- Indexes for table `sequences`
--
ALTER TABLE `sequences`
  ADD PRIMARY KEY (`name`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`iduser`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `detailkeluar`
--
ALTER TABLE `detailkeluar`
  MODIFY `iddetailkeluar` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `detailmasuk`
--
ALTER TABLE `detailmasuk`
  MODIFY `iddetailmasuk` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `detailorder`
--
ALTER TABLE `detailorder`
  MODIFY `iddetailorder` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `detailrequest`
--
ALTER TABLE `detailrequest`
  MODIFY `iddetailrequest` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

--
-- AUTO_INCREMENT for table `logstatusbarang`
--
ALTER TABLE `logstatusbarang`
  MODIFY `idlogstatusbarang` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `logstatusorder`
--
ALTER TABLE `logstatusorder`
  MODIFY `idlogstatusorder` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `logstatusreq`
--
ALTER TABLE `logstatusreq`
  MODIFY `idlogstatusreq` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `barangkeluar`
--
ALTER TABLE `barangkeluar`
  ADD CONSTRAINT `fk_bk_user_approved` FOREIGN KEY (`iduserprocurementapproved`) REFERENCES `users` (`iduser`),
  ADD CONSTRAINT `fk_bk_user_created` FOREIGN KEY (`iduserprocurementcreated`) REFERENCES `users` (`iduser`);

--
-- Constraints for table `barangmasuk`
--
ALTER TABLE `barangmasuk`
  ADD CONSTRAINT `fk_bm_po` FOREIGN KEY (`idpurchaseorder`) REFERENCES `purchaseorder` (`idpurchaseorder`),
  ADD CONSTRAINT `fk_bm_userapproval` FOREIGN KEY (`iduserprocurementapproval`) REFERENCES `users` (`iduser`),
  ADD CONSTRAINT `fk_bm_usercreate` FOREIGN KEY (`iduserprocurementcreate`) REFERENCES `users` (`iduser`);

--
-- Constraints for table `detailkeluar`
--
ALTER TABLE `detailkeluar`
  ADD CONSTRAINT `fk_dk_barang` FOREIGN KEY (`idbarang`) REFERENCES `m_barang` (`idbarang`),
  ADD CONSTRAINT `fk_dk_kategori` FOREIGN KEY (`idkategori`) REFERENCES `kategoribarang` (`idkategori`),
  ADD CONSTRAINT `fk_dk_keluar` FOREIGN KEY (`idkeluar`) REFERENCES `barangkeluar` (`idkeluar`);

--
-- Constraints for table `detailmasuk`
--
ALTER TABLE `detailmasuk`
  ADD CONSTRAINT `fk_dm_barang` FOREIGN KEY (`idbarang`) REFERENCES `m_barang` (`idbarang`),
  ADD CONSTRAINT `fk_dm_kategori` FOREIGN KEY (`idkategori`) REFERENCES `kategoribarang` (`idkategori`),
  ADD CONSTRAINT `fk_dm_masuk` FOREIGN KEY (`idmasuk`) REFERENCES `barangmasuk` (`idmasuk`);

--
-- Constraints for table `detailorder`
--
ALTER TABLE `detailorder`
  ADD CONSTRAINT `fk_do_barang` FOREIGN KEY (`idbarang`) REFERENCES `m_barang` (`idbarang`),
  ADD CONSTRAINT `fk_do_po` FOREIGN KEY (`idpurchaseorder`) REFERENCES `purchaseorder` (`idpurchaseorder`);

--
-- Constraints for table `detailrequest`
--
ALTER TABLE `detailrequest`
  ADD CONSTRAINT `fk_dr_barang` FOREIGN KEY (`idbarang`) REFERENCES `m_barang` (`idbarang`),
  ADD CONSTRAINT `fk_dr_request` FOREIGN KEY (`idrequest`) REFERENCES `purchaserequest` (`idrequest`);

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `fk_inventory_barang` FOREIGN KEY (`idbarang`) REFERENCES `m_barang` (`idbarang`),
  ADD CONSTRAINT `fk_inventory_kategori` FOREIGN KEY (`idkategori`) REFERENCES `kategoribarang` (`idkategori`);

--
-- Constraints for table `logstatusbarang`
--
ALTER TABLE `logstatusbarang`
  ADD CONSTRAINT `fk_lsb_detailrequest` FOREIGN KEY (`iddetailrequest`) REFERENCES `detailrequest` (`iddetailrequest`);

--
-- Constraints for table `logstatusorder`
--
ALTER TABLE `logstatusorder`
  ADD CONSTRAINT `fk_lso_po` FOREIGN KEY (`idpurchaseorder`) REFERENCES `purchaseorder` (`idpurchaseorder`);

--
-- Constraints for table `logstatusreq`
--
ALTER TABLE `logstatusreq`
  ADD CONSTRAINT `fk_lsr_request` FOREIGN KEY (`idrequest`) REFERENCES `purchaserequest` (`idrequest`);

--
-- Constraints for table `m_barang`
--
ALTER TABLE `m_barang`
  ADD CONSTRAINT `fk_m_barang_kategori` FOREIGN KEY (`idkategori`) REFERENCES `kategoribarang` (`idkategori`);

--
-- Constraints for table `purchaseorder`
--
ALTER TABLE `purchaseorder`
  ADD CONSTRAINT `fk_po_request` FOREIGN KEY (`idrequest`) REFERENCES `purchaserequest` (`idrequest`);

--
-- Constraints for table `purchaserequest`
--
ALTER TABLE `purchaserequest`
  ADD CONSTRAINT `fk_pr_supervisor` FOREIGN KEY (`idsupervisor`) REFERENCES `users` (`iduser`),
  ADD CONSTRAINT `fk_pr_userrequest` FOREIGN KEY (`iduserrequest`) REFERENCES `users` (`iduser`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
