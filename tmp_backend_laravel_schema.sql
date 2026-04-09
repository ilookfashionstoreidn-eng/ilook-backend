
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `aksesoris`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `aksesoris` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama_aksesoris` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jenis_aksesoris` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `satuan` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jumlah_per_satuan` int DEFAULT NULL,
  `harga_jual` decimal(15,2) DEFAULT NULL,
  `harga_per_biji` decimal(15,2) DEFAULT NULL,
  `foto_aksesoris` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `bahan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `bahan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama_bahan` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `deskripsi` text COLLATE utf8mb4_unicode_ci,
  `harga` decimal(15,2) DEFAULT NULL,
  `satuan` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `bahan_nama_bahan_unique` (`nama_bahan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cashboan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cashboan` (
  `id_cashboan` bigint unsigned NOT NULL AUTO_INCREMENT,
  `jumlah_cashboan` decimal(10,2) NOT NULL,
  `status_pembayaran` enum('belum lunas','lunas','dibayar sebagian') COLLATE utf8mb4_unicode_ci NOT NULL,
  `tanggal_cashboan` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `id_penjahit` bigint unsigned DEFAULT NULL,
  `potongan_per_minggu` decimal(15,2) DEFAULT NULL,
  `bukti_transfer` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_cashboan`),
  KEY `cashboan_id_penjahit_foreign` (`id_penjahit`),
  CONSTRAINT `cashboan_id_penjahit_foreign` FOREIGN KEY (`id_penjahit`) REFERENCES `penjahit_cmt` (`id_penjahit`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cashboan_cutting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cashboan_cutting` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tukang_cutting_id` bigint unsigned NOT NULL,
  `jumlah_cashboan` decimal(12,2) NOT NULL,
  `status_pembayaran` enum('belum lunas','lunas') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'belum lunas',
  `tanggal_cashboan` date NOT NULL,
  `bukti_transfer` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cashboan_cutting_tukang_cutting_id_foreign` (`tukang_cutting_id`),
  CONSTRAINT `cashboan_cutting_tukang_cutting_id_foreign` FOREIGN KEY (`tukang_cutting_id`) REFERENCES `tukang_cutting` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cashboan_jasa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cashboan_jasa` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tukang_jasa_id` bigint unsigned NOT NULL,
  `jumlah_cashboan` decimal(12,2) NOT NULL,
  `status_pembayaran` enum('belum lunas','lunas') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'belum lunas',
  `tanggal_cashboan` date NOT NULL,
  `bukti_transfer` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `cashboan_jasa_tukang_jasa_id_foreign` (`tukang_jasa_id`),
  CONSTRAINT `cashboan_jasa_tukang_jasa_id_foreign` FOREIGN KEY (`tukang_jasa_id`) REFERENCES `tukang_jasa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `chat_readers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_readers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `chat_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `chat_readers_chat_id_foreign` (`chat_id`),
  KEY `chat_readers_user_id_foreign` (`user_id`),
  CONSTRAINT `chat_readers_chat_id_foreign` FOREIGN KEY (`chat_id`) REFERENCES `spk_chats` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chat_readers_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `detail_pesanan_aksesoris`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_pesanan_aksesoris` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `petugas_c_id` bigint unsigned NOT NULL,
  `aksesoris_id` bigint unsigned NOT NULL,
  `jumlah_dipesan` int NOT NULL,
  `total_harga` decimal(12,2) DEFAULT NULL,
  `sudah_dibayar` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `id_pendapatan` bigint unsigned DEFAULT NULL,
  `petugas_d_verif_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `detail_pesanan_aksesoris_petugas_c_id_foreign` (`petugas_c_id`),
  KEY `detail_pesanan_aksesoris_aksesoris_id_foreign` (`aksesoris_id`),
  KEY `detail_pesanan_aksesoris_id_pendapatan_foreign` (`id_pendapatan`),
  KEY `detail_pesanan_aksesoris_petugas_d_verif_id_foreign` (`petugas_d_verif_id`),
  CONSTRAINT `detail_pesanan_aksesoris_aksesoris_id_foreign` FOREIGN KEY (`aksesoris_id`) REFERENCES `aksesoris` (`id`) ON DELETE CASCADE,
  CONSTRAINT `detail_pesanan_aksesoris_id_pendapatan_foreign` FOREIGN KEY (`id_pendapatan`) REFERENCES `pendapatan` (`id_pendapatan`) ON DELETE SET NULL,
  CONSTRAINT `detail_pesanan_aksesoris_petugas_c_id_foreign` FOREIGN KEY (`petugas_c_id`) REFERENCES `petugas_c` (`id`) ON DELETE CASCADE,
  CONSTRAINT `detail_pesanan_aksesoris_petugas_d_verif_id_foreign` FOREIGN KEY (`petugas_d_verif_id`) REFERENCES `petugas_d_verif` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gudang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gudang` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama_gudang` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alamat` text COLLATE utf8mb4_unicode_ci,
  `pic` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gudang_produk`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gudang_produk` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `status` enum('draft','terverifikasi') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_by` bigint unsigned NOT NULL,
  `verified_by` bigint unsigned DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gudang_produk_activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gudang_produk_activity_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('placement','mutation') COLLATE utf8mb4_unicode_ci NOT NULL,
  `sku_id` bigint unsigned NOT NULL,
  `from_slot_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_slot_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qty` int NOT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `gudang_produk_activity_logs_type_created_at_index` (`type`,`created_at`),
  KEY `gudang_produk_activity_logs_sku_id_index` (`sku_id`),
  KEY `gudang_produk_activity_logs_from_slot_id_index` (`from_slot_id`),
  KEY `gudang_produk_activity_logs_to_slot_id_index` (`to_slot_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gudang_produk_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gudang_produk_detail` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `gudang_produk_id` bigint unsigned NOT NULL,
  `sku_id` bigint unsigned NOT NULL,
  `sku_rak` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `foto` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qty_acuan` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gudang_produk_detail_gudang_produk_id_sku_id_unique` (`gudang_produk_id`,`sku_id`),
  CONSTRAINT `gudang_produk_detail_gudang_produk_id_foreign` FOREIGN KEY (`gudang_produk_id`) REFERENCES `gudang_produk` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gudang_produk_detail_verifikasi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gudang_produk_detail_verifikasi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `gudang_produk_detail_id` bigint unsigned NOT NULL,
  `qty_verifikasi` int NOT NULL,
  `created_by` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `gudang_produk_detail_verifikasi_gudang_produk_detail_id_foreign` (`gudang_produk_detail_id`),
  CONSTRAINT `gudang_produk_detail_verifikasi_gudang_produk_detail_id_foreign` FOREIGN KEY (`gudang_produk_detail_id`) REFERENCES `gudang_produk_detail` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gudang_produk_layout_blocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gudang_produk_layout_blocks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uid` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `floor_id` bigint unsigned NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `layout_columns` tinyint unsigned NOT NULL DEFAULT '3',
  `layout_canvas_columns` int unsigned NOT NULL DEFAULT '12',
  `layout_canvas_rows` int unsigned NOT NULL DEFAULT '10',
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gudang_produk_layout_blocks_floor_id_code_unique` (`floor_id`,`code`),
  UNIQUE KEY `gudang_produk_layout_blocks_uid_unique` (`uid`),
  CONSTRAINT `gudang_produk_layout_blocks_floor_id_foreign` FOREIGN KEY (`floor_id`) REFERENCES `gudang_produk_layout_floors` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gudang_produk_layout_floors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gudang_produk_layout_floors` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uid` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `layout_id` bigint unsigned NOT NULL,
  `number` int unsigned NOT NULL,
  `label` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gudang_produk_layout_floors_layout_id_number_unique` (`layout_id`,`number`),
  UNIQUE KEY `gudang_produk_layout_floors_uid_unique` (`uid`),
  CONSTRAINT `gudang_produk_layout_floors_layout_id_foreign` FOREIGN KEY (`layout_id`) REFERENCES `gudang_produk_layouts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gudang_produk_layout_racks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gudang_produk_layout_racks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uid` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `block_id` bigint unsigned NOT NULL,
  `number` int unsigned NOT NULL,
  `rows` int unsigned NOT NULL DEFAULT '1',
  `label` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `position_x` int unsigned DEFAULT NULL,
  `position_y` int unsigned DEFAULT NULL,
  `width_cells` int unsigned DEFAULT NULL,
  `height_cells` int unsigned DEFAULT NULL,
  `sort_order` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gudang_produk_layout_racks_block_id_number_unique` (`block_id`,`number`),
  UNIQUE KEY `gudang_produk_layout_racks_uid_unique` (`uid`),
  CONSTRAINT `gudang_produk_layout_racks_block_id_foreign` FOREIGN KEY (`block_id`) REFERENCES `gudang_produk_layout_blocks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gudang_produk_layouts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gudang_produk_layouts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uid` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pic` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gudang_produk_layouts_uid_unique` (`uid`),
  KEY `gudang_produk_layouts_name_index` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gudang_produk_slot_aliases`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gudang_produk_slot_aliases` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `layout_id` bigint unsigned NOT NULL,
  `slot_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alias` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gudang_produk_slot_aliases_slot_id_unique` (`slot_id`),
  KEY `gudang_produk_slot_aliases_layout_id_slot_id_index` (`layout_id`,`slot_id`),
  CONSTRAINT `gudang_produk_slot_aliases_layout_id_foreign` FOREIGN KEY (`layout_id`) REFERENCES `gudang_produk_layouts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `gudang_produk_stock_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `gudang_produk_stock_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `layout_id` bigint unsigned NOT NULL,
  `sku_id` bigint unsigned NOT NULL,
  `slot_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `qty` int NOT NULL DEFAULT '0',
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `gudang_produk_stock_entries_slot_id_sku_id_unique` (`slot_id`,`sku_id`),
  KEY `gudang_produk_stock_entries_layout_id_slot_id_index` (`layout_id`,`slot_id`),
  CONSTRAINT `gudang_produk_stock_entries_layout_id_foreign` FOREIGN KEY (`layout_id`) REFERENCES `gudang_produk_layouts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hasil_cutting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hasil_cutting` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `spk_cutting_id` bigint unsigned DEFAULT NULL,
  `foto_komponen` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jumlah_komponen` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status_perbandingan_agregat` text COLLATE utf8mb4_unicode_ci,
  `total_bayar` decimal(15,2) DEFAULT NULL,
  `spk_cutting_bagian_id` bigint unsigned DEFAULT NULL,
  `nama_bagian` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `nama_bahan` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `warna` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qty` int DEFAULT NULL,
  `total_hasil_pendapatan` int DEFAULT NULL,
  `data_acuan` json DEFAULT NULL,
  `total_produk` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hasil_cutting_spk_cutting_id_foreign` (`spk_cutting_id`),
  KEY `hasil_cutting_spk_cutting_bagian_id_foreign` (`spk_cutting_bagian_id`),
  KEY `idx_hasil_cutting_created_at` (`created_at`),
  CONSTRAINT `hasil_cutting_spk_cutting_bagian_id_foreign` FOREIGN KEY (`spk_cutting_bagian_id`) REFERENCES `spk_cutting_bagian` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hasil_cutting_spk_cutting_id_foreign` FOREIGN KEY (`spk_cutting_id`) REFERENCES `spk_cutting` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hasil_cutting_bahan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hasil_cutting_bahan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `hasil_cutting_id` bigint unsigned NOT NULL,
  `spk_cutting_bahan_id` bigint unsigned NOT NULL,
  `berat` double(8,2) DEFAULT NULL,
  `berat_per_produk` decimal(10,2) DEFAULT NULL,
  `hasil` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `spk_cutting_bagian_id` bigint unsigned DEFAULT NULL,
  `produk_sku_id` bigint unsigned DEFAULT NULL,
  `jumlah_lembar` int DEFAULT NULL,
  `jumlah_produk` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hasil_cutting_bahan_hasil_cutting_id_foreign` (`hasil_cutting_id`),
  KEY `hasil_cutting_bahan_spk_cutting_bahan_id_foreign` (`spk_cutting_bahan_id`),
  KEY `hasil_cutting_bahan_spk_cutting_bagian_id_foreign` (`spk_cutting_bagian_id`),
  KEY `hasil_cutting_bahan_produk_sku_id_foreign` (`produk_sku_id`),
  CONSTRAINT `hasil_cutting_bahan_hasil_cutting_id_foreign` FOREIGN KEY (`hasil_cutting_id`) REFERENCES `hasil_cutting` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hasil_cutting_bahan_produk_sku_id_foreign` FOREIGN KEY (`produk_sku_id`) REFERENCES `produk_sku` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hasil_cutting_bahan_spk_cutting_bagian_id_foreign` FOREIGN KEY (`spk_cutting_bagian_id`) REFERENCES `spk_cutting_bagian` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hasil_cutting_bahan_spk_cutting_bahan_id_foreign` FOREIGN KEY (`spk_cutting_bahan_id`) REFERENCES `spk_cutting_bahan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hasil_jasa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hasil_jasa` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `spk_jasa_id` bigint unsigned NOT NULL,
  `tanggal` date NOT NULL,
  `jumlah_hasil` int NOT NULL DEFAULT '0',
  `jumlah_rusak` int NOT NULL DEFAULT '0',
  `total_pendapatan` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status_bayar` enum('belum_dibayar','sudah_dibayar') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'belum_dibayar',
  `pendapatan_jasa_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `bukti_transfer` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hasil_jasa_pendapatan_jasa_id_foreign` (`pendapatan_jasa_id`),
  KEY `idx_hasil_jasa_tanggal` (`tanggal`),
  KEY `idx_hasil_jasa_total_pendapatan` (`total_pendapatan`),
  KEY `idx_hasil_jasa_spk_tanggal` (`spk_jasa_id`,`tanggal`),
  CONSTRAINT `hasil_jasa_pendapatan_jasa_id_foreign` FOREIGN KEY (`pendapatan_jasa_id`) REFERENCES `pendapatan_jasa` (`id`) ON DELETE SET NULL,
  CONSTRAINT `hasil_jasa_spk_jasa_id_foreign` FOREIGN KEY (`spk_jasa_id`) REFERENCES `spk_jasa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hasil_markeran`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hasil_markeran` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `hasil_cutting_id` bigint unsigned NOT NULL,
  `nama_komponen` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_panjang` decimal(8,2) NOT NULL,
  `jumlah_hasil` int NOT NULL,
  `berat_per_pcs` decimal(8,3) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status_perbandingan` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hasil_markeran_hasil_cutting_id_foreign` (`hasil_cutting_id`),
  CONSTRAINT `hasil_markeran_hasil_cutting_id_foreign` FOREIGN KEY (`hasil_cutting_id`) REFERENCES `hasil_cutting` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hasil_pendapatan_cutting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hasil_pendapatan_cutting` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pendapatan_cutting_id` bigint unsigned NOT NULL,
  `hasil_cutting_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hasil_pendapatan_cutting_pendapatan_cutting_id_foreign` (`pendapatan_cutting_id`),
  KEY `hasil_pendapatan_cutting_hasil_cutting_id_foreign` (`hasil_cutting_id`),
  CONSTRAINT `hasil_pendapatan_cutting_hasil_cutting_id_foreign` FOREIGN KEY (`hasil_cutting_id`) REFERENCES `hasil_cutting` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hasil_pendapatan_cutting_pendapatan_cutting_id_foreign` FOREIGN KEY (`pendapatan_cutting_id`) REFERENCES `pendapatan_cutting` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hasil_pendapatan_jasa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hasil_pendapatan_jasa` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pendapatan_jasa_id` bigint unsigned NOT NULL,
  `hasil_jasa_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hasil_pendapatan_jasa_pendapatan_jasa_id_foreign` (`pendapatan_jasa_id`),
  KEY `hasil_pendapatan_jasa_hasil_jasa_id_foreign` (`hasil_jasa_id`),
  CONSTRAINT `hasil_pendapatan_jasa_hasil_jasa_id_foreign` FOREIGN KEY (`hasil_jasa_id`) REFERENCES `hasil_jasa` (`id`) ON DELETE CASCADE,
  CONSTRAINT `hasil_pendapatan_jasa_pendapatan_jasa_id_foreign` FOREIGN KEY (`pendapatan_jasa_id`) REFERENCES `pendapatan_jasa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `history_cashboan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `history_cashboan` (
  `id_history` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_cashboan` bigint unsigned NOT NULL,
  `jumlah_cashboan` decimal(15,2) NOT NULL,
  `perubahan_cashboan` decimal(15,2) NOT NULL,
  `jenis_perubahan` enum('penambahan','pengurangan') COLLATE utf8mb4_unicode_ci NOT NULL,
  `tanggal_perubahan` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `bukti_transfer` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_history`),
  KEY `history_cashboan_id_cashboan_foreign` (`id_cashboan`),
  CONSTRAINT `history_cashboan_id_cashboan_foreign` FOREIGN KEY (`id_cashboan`) REFERENCES `cashboan` (`id_cashboan`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `history_cashboan_cutting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `history_cashboan_cutting` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cashboan_cutting_id` bigint unsigned NOT NULL,
  `jumlah_cashboan` decimal(12,2) NOT NULL,
  `perubahan_cashboan` decimal(12,2) DEFAULT NULL,
  `jenis_perubahan` enum('penambahan','pengurangan') COLLATE utf8mb4_unicode_ci NOT NULL,
  `bukti_transfer` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tanggal_perubahan` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `history_cashboan_cutting_cashboan_cutting_id_foreign` (`cashboan_cutting_id`),
  CONSTRAINT `history_cashboan_cutting_cashboan_cutting_id_foreign` FOREIGN KEY (`cashboan_cutting_id`) REFERENCES `cashboan_cutting` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `history_cashboan_jasa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `history_cashboan_jasa` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `cashboan_jasa_id` bigint unsigned NOT NULL,
  `jumlah_cashboan` decimal(12,2) NOT NULL,
  `perubahan_cashboan` decimal(12,2) DEFAULT NULL,
  `jenis_perubahan` enum('penambahan','pengurangan') COLLATE utf8mb4_unicode_ci NOT NULL,
  `bukti_transfer` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tanggal_perubahan` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `history_cashboan_jasa_cashboan_jasa_id_foreign` (`cashboan_jasa_id`),
  CONSTRAINT `history_cashboan_jasa_cashboan_jasa_id_foreign` FOREIGN KEY (`cashboan_jasa_id`) REFERENCES `cashboan_jasa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `history_hutang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `history_hutang` (
  `id_history` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_hutang` bigint unsigned NOT NULL,
  `jumlah_hutang` decimal(15,2) NOT NULL,
  `perubahan_hutang` decimal(15,2) DEFAULT NULL,
  `jenis_perubahan` enum('penambahan','pengurangan') COLLATE utf8mb4_unicode_ci NOT NULL,
  `tanggal_perubahan` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `bukti_transfer` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_history`),
  KEY `history_hutang_id_hutang_foreign` (`id_hutang`),
  CONSTRAINT `history_hutang_id_hutang_foreign` FOREIGN KEY (`id_hutang`) REFERENCES `hutang` (`id_hutang`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `history_hutang_cutting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `history_hutang_cutting` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `hutang_cutting_id` bigint unsigned NOT NULL,
  `jumlah_hutang` decimal(12,2) NOT NULL,
  `perubahan_hutang` decimal(12,2) NOT NULL,
  `jenis_perubahan` enum('penambahan','pengurangan') COLLATE utf8mb4_unicode_ci NOT NULL,
  `bukti_transfer` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tanggal_perubahan` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `history_hutang_cutting_hutang_cutting_id_foreign` (`hutang_cutting_id`),
  CONSTRAINT `history_hutang_cutting_hutang_cutting_id_foreign` FOREIGN KEY (`hutang_cutting_id`) REFERENCES `hutang_cutting` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `history_hutang_jasa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `history_hutang_jasa` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `hutang_jasa_id` bigint unsigned NOT NULL,
  `jumlah_hutang` decimal(12,2) NOT NULL,
  `perubahan_hutang` decimal(12,2) NOT NULL,
  `jenis_perubahan` enum('penambahan','pengurangan') COLLATE utf8mb4_unicode_ci NOT NULL,
  `bukti_transfer` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tanggal_perubahan` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `history_hutang_jasa_hutang_jasa_id_foreign` (`hutang_jasa_id`),
  CONSTRAINT `history_hutang_jasa_hutang_jasa_id_foreign` FOREIGN KEY (`hutang_jasa_id`) REFERENCES `hutang_jasa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hutang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hutang` (
  `id_hutang` bigint unsigned NOT NULL AUTO_INCREMENT,
  `jumlah_hutang` decimal(10,2) NOT NULL,
  `status_pembayaran` enum('belum lunas','lunas','dibayar sebagian') COLLATE utf8mb4_unicode_ci NOT NULL,
  `tanggal_hutang` date NOT NULL,
  `jenis_hutang` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `id_penjahit` bigint unsigned DEFAULT NULL,
  `potongan_per_minggu` decimal(15,2) DEFAULT NULL,
  `is_potongan_persen` tinyint(1) NOT NULL DEFAULT '0',
  `persentase_potongan` decimal(5,2) DEFAULT NULL,
  `bukti_transfer` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_hutang`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hutang_cutting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hutang_cutting` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tukang_cutting_id` bigint unsigned NOT NULL,
  `jumlah_hutang` decimal(12,2) NOT NULL,
  `status_pembayaran` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'belum',
  `tanggal_hutang` date NOT NULL,
  `potongan_per_minggu` decimal(12,2) DEFAULT NULL,
  `is_potongan_persen` tinyint(1) NOT NULL DEFAULT '0',
  `persentase_potongan` decimal(5,2) DEFAULT NULL,
  `bukti_transfer` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hutang_cutting_tukang_cutting_id_foreign` (`tukang_cutting_id`),
  CONSTRAINT `hutang_cutting_tukang_cutting_id_foreign` FOREIGN KEY (`tukang_cutting_id`) REFERENCES `tukang_cutting` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `hutang_jasa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `hutang_jasa` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tukang_jasa_id` bigint unsigned NOT NULL,
  `jumlah_hutang` decimal(12,2) NOT NULL,
  `status_pembayaran` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'belum',
  `tanggal_hutang` date NOT NULL,
  `potongan_per_minggu` decimal(12,2) DEFAULT NULL,
  `is_potongan_persen` tinyint(1) NOT NULL DEFAULT '0',
  `persentase_potongan` decimal(5,2) DEFAULT NULL,
  `bukti_transfer` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `hutang_jasa_tukang_jasa_id_foreign` (`tukang_jasa_id`),
  CONSTRAINT `hutang_jasa_tukang_jasa_id_foreign` FOREIGN KEY (`tukang_jasa_id`) REFERENCES `tukang_jasa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `laporan_cmt`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `laporan_cmt` (
  `id_laporan` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_spk` bigint unsigned NOT NULL,
  `tgl_pengiriman` date NOT NULL,
  `jumlah_dikirim` int NOT NULL,
  `barang_rusak` int NOT NULL DEFAULT '0',
  `barang_hilang` int NOT NULL DEFAULT '0',
  `upah_per_barang` decimal(12,2) NOT NULL DEFAULT '0.00',
  `total_upah` decimal(12,2) NOT NULL DEFAULT '0.00',
  `potongan` decimal(12,2) NOT NULL DEFAULT '0.00',
  `cashbon` decimal(12,2) NOT NULL DEFAULT '0.00',
  `status_pembayaran` enum('Paid','Unpaid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Unpaid',
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_laporan`),
  KEY `laporan_cmt_id_spk_foreign` (`id_spk`),
  CONSTRAINT `laporan_cmt_id_spk_foreign` FOREIGN KEY (`id_spk`) REFERENCES `spk_cmt` (`id_spk`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `log_deadline`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `log_deadline` (
  `id_log` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_spk` bigint unsigned NOT NULL,
  `deadline_lama` date NOT NULL,
  `deadline_baru` date NOT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `tanggal_aktivitas` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_log`),
  KEY `log_deadline_id_spk_foreign` (`id_spk`),
  CONSTRAINT `log_deadline_id_spk_foreign` FOREIGN KEY (`id_spk`) REFERENCES `spk_cmt` (`id_spk`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `log_pembayaran_cashboan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `log_pembayaran_cashboan` (
  `id_log_pembayaran` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_cashboan` bigint unsigned NOT NULL,
  `jumlah_dibayar` decimal(10,2) NOT NULL,
  `tanggal_bayar` date NOT NULL,
  `catatan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_log_pembayaran`),
  KEY `log_pembayaran_cashboan_id_cashboan_foreign` (`id_cashboan`),
  CONSTRAINT `log_pembayaran_cashboan_id_cashboan_foreign` FOREIGN KEY (`id_cashboan`) REFERENCES `cashboan` (`id_cashboan`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `log_pembayaran_hutang`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `log_pembayaran_hutang` (
  `id_log_hutang` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_hutang` bigint unsigned NOT NULL,
  `jumlah_dibayar` decimal(10,2) NOT NULL,
  `tanggal_bayar` date NOT NULL,
  `catatan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_log_hutang`),
  KEY `log_pembayaran_hutang_id_hutang_foreign` (`id_hutang`),
  CONSTRAINT `log_pembayaran_hutang_id_hutang_foreign` FOREIGN KEY (`id_hutang`) REFERENCES `hutang` (`id_hutang`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `log_status`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `log_status` (
  `id_status` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_spk` bigint unsigned NOT NULL,
  `status_lama` enum('Pending','In Progress','Completed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `status_baru` enum('Pending','In Progress','Completed') COLLATE utf8mb4_unicode_ci NOT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `tanggal_aktivitas` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_status`),
  KEY `log_status_id_spk_foreign` (`id_spk`),
  CONSTRAINT `log_status_id_spk_foreign` FOREIGN KEY (`id_spk`) REFERENCES `spk_cmt` (`id_spk`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `log_status_spk_cmt`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `log_status_spk_cmt` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `spk_cmt_id` bigint unsigned NOT NULL,
  `status` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `log_status_spk_cmt_spk_cmt_id_foreign` (`spk_cmt_id`),
  CONSTRAINT `log_status_spk_cmt_spk_cmt_id_foreign` FOREIGN KEY (`spk_cmt_id`) REFERENCES `spk_cmt` (`id_spk`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `markeran_produk`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `markeran_produk` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `produk_id` bigint unsigned NOT NULL,
  `nama_komponen` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_panjang` decimal(8,2) NOT NULL,
  `jumlah_hasil` int NOT NULL,
  `berat_per_pcs` decimal(8,3) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `markeran_produk_produk_id_foreign` (`produk_id`),
  CONSTRAINT `markeran_produk_produk_id_foreign` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=289 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `model_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `model_has_roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `model_has_roles` (
  `role_id` bigint unsigned NOT NULL,
  `model_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `chat_id` bigint unsigned NOT NULL,
  `spk_id` bigint unsigned NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT '0',
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_user_id_foreign` (`user_id`),
  KEY `notifications_chat_id_foreign` (`chat_id`),
  KEY `notifications_spk_id_foreign` (`spk_id`),
  CONSTRAINT `notifications_chat_id_foreign` FOREIGN KEY (`chat_id`) REFERENCES `spk_chats` (`id`) ON DELETE CASCADE,
  CONSTRAINT `notifications_spk_id_foreign` FOREIGN KEY (`spk_id`) REFERENCES `spk_cmt` (`id_spk`) ON DELETE CASCADE,
  CONSTRAINT `notifications_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_number` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sku` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `tracking_number` varchar(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `platform` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `customer_phone` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `total_amount` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_qty` int NOT NULL DEFAULT '0',
  `status` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ready_to_pack',
  `is_packed` tinyint(1) NOT NULL DEFAULT '0',
  `label_print_status` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `label_print_time` timestamp NULL DEFAULT NULL,
  `picked_at` timestamp NULL DEFAULT NULL,
  `order_date` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `last_update_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_order_number_unique` (`order_number`),
  UNIQUE KEY `order_tracking_number_unique` (`tracking_number`),
  KEY `order_status_index` (`status`),
  KEY `order_is_packed_index` (`is_packed`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_item_serials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_item_serials` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_item_id` bigint unsigned NOT NULL,
  `serial_number` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_item_serials_order_item_id_index` (`order_item_id`),
  KEY `order_item_serials_serial_number_index` (`serial_number`),
  CONSTRAINT `order_item_serials_order_item_id_foreign` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `sku` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `product_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `price` decimal(15,2) NOT NULL DEFAULT '0.00',
  `image` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `nomor_seri` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_items_order_id_index` (`order_id`),
  KEY `order_items_sku_index` (`sku`),
  CONSTRAINT `order_items_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `order` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `action` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `performed_by` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_logs_order_id_foreign` (`order_id`),
  CONSTRAINT `order_logs_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `order` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_packing_result_serials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_packing_result_serials` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_packing_result_id` bigint unsigned NOT NULL,
  `serial_number` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_packing_result_serials_order_packing_result_id_foreign` (`order_packing_result_id`),
  KEY `idx_order_packing_result_serials_serial` (`serial_number`),
  CONSTRAINT `order_packing_result_serials_order_packing_result_id_foreign` FOREIGN KEY (`order_packing_result_id`) REFERENCES `order_packing_results` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `order_packing_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `order_packing_results` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `order_id` bigint unsigned NOT NULL,
  `order_item_id` bigint unsigned DEFAULT NULL,
  `actual_sku_id` bigint unsigned DEFAULT NULL,
  `line_type` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_sku` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_product_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `original_image` text COLLATE utf8mb4_unicode_ci,
  `actual_sku` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `actual_product_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `actual_image` text COLLATE utf8mb4_unicode_ci,
  `ordered_qty` int unsigned NOT NULL DEFAULT '0',
  `scanned_qty` int unsigned NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `order_packing_results_order_item_id_foreign` (`order_item_id`),
  KEY `order_packing_results_actual_sku_id_foreign` (`actual_sku_id`),
  KEY `idx_order_packing_results_order_status` (`order_id`,`status`),
  KEY `idx_order_packing_results_order_item` (`order_id`,`order_item_id`),
  KEY `idx_order_packing_results_actual_sku` (`actual_sku`),
  CONSTRAINT `order_packing_results_actual_sku_id_foreign` FOREIGN KEY (`actual_sku_id`) REFERENCES `skus` (`id`) ON DELETE SET NULL,
  CONSTRAINT `order_packing_results_order_id_foreign` FOREIGN KEY (`order_id`) REFERENCES `order` (`id`) ON DELETE CASCADE,
  CONSTRAINT `order_packing_results_order_item_id_foreign` FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pabrik`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pabrik` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama_pabrik` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lokasi` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kontak` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ktp` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_resets` (
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pembelian_aksesoris_a`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pembelian_aksesoris_a` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `aksesoris_id` bigint unsigned NOT NULL,
  `jumlah` int unsigned NOT NULL,
  `harga_satuan` decimal(10,2) NOT NULL,
  `total_harga` bigint DEFAULT NULL,
  `tanggal_pembelian` date NOT NULL,
  `bukti_pembelian` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pembelian_aksesoris_a_user_id_foreign` (`user_id`),
  KEY `pembelian_aksesoris_a_aksesoris_id_foreign` (`aksesoris_id`),
  CONSTRAINT `pembelian_aksesoris_a_aksesoris_id_foreign` FOREIGN KEY (`aksesoris_id`) REFERENCES `aksesoris` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pembelian_aksesoris_a_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pembelian_aksesoris_b`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pembelian_aksesoris_b` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pembelian_a_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `jumlah_terverifikasi` int unsigned NOT NULL,
  `status_verifikasi` enum('pending','valid','invalid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `barcode_downloaded` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `pembelian_aksesoris_b_pembelian_a_id_foreign` (`pembelian_a_id`),
  KEY `pembelian_aksesoris_b_user_id_foreign` (`user_id`),
  CONSTRAINT `pembelian_aksesoris_b_pembelian_a_id_foreign` FOREIGN KEY (`pembelian_a_id`) REFERENCES `pembelian_aksesoris_a` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pembelian_aksesoris_b_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pembelian_bahan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pembelian_bahan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `spk_bahan_id` bigint unsigned DEFAULT NULL,
  `keterangan` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gudang_id` bigint unsigned NOT NULL,
  `pabrik_id` bigint unsigned NOT NULL,
  `tanggal_kirim` date NOT NULL,
  `no_surat_jalan` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `foto_surat_jalan` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sku` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `harga` decimal(15,2) DEFAULT NULL,
  `status_bayar` enum('belum','sudah') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'belum',
  `total_harga` decimal(15,2) NOT NULL DEFAULT '0.00',
  `gramasi` decimal(10,2) NOT NULL,
  `lebar_kain` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `bahan_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pembelian_bahan_gudang_id_foreign` (`gudang_id`),
  KEY `pembelian_bahan_pabrik_id_foreign` (`pabrik_id`),
  KEY `pembelian_bahan_bahan_id_foreign` (`bahan_id`),
  KEY `pembelian_bahan_spk_bahan_id_foreign` (`spk_bahan_id`),
  CONSTRAINT `pembelian_bahan_bahan_id_foreign` FOREIGN KEY (`bahan_id`) REFERENCES `bahan` (`id`),
  CONSTRAINT `pembelian_bahan_gudang_id_foreign` FOREIGN KEY (`gudang_id`) REFERENCES `gudang` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pembelian_bahan_pabrik_id_foreign` FOREIGN KEY (`pabrik_id`) REFERENCES `pabrik` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pembelian_bahan_spk_bahan_id_foreign` FOREIGN KEY (`spk_bahan_id`) REFERENCES `spk_bahan` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pembelian_bahan_return`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pembelian_bahan_return` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pembelian_bahan_id` bigint unsigned NOT NULL,
  `pembelian_bahan_rol_id` bigint unsigned DEFAULT NULL,
  `tipe_return` enum('refund','return_barang') COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'refund = pengembalian uang, return_barang = pengembalian barang',
  `jumlah_rol` int NOT NULL DEFAULT '1' COMMENT 'Jumlah rol yang dikembalikan',
  `total_refund` decimal(15,2) DEFAULT NULL COMMENT 'Total uang yang direfund (jika tipe_return = refund)',
  `keterangan` text COLLATE utf8mb4_unicode_ci COMMENT 'Alasan return/refund',
  `tanggal_return` date NOT NULL COMMENT 'Tanggal return/refund dilakukan',
  `status` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT 'pending, approved, rejected, completed',
  `foto_bukti` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Foto bukti barang rusak',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pembelian_bahan_return_pembelian_bahan_id_foreign` (`pembelian_bahan_id`),
  KEY `pembelian_bahan_return_pembelian_bahan_rol_id_foreign` (`pembelian_bahan_rol_id`),
  CONSTRAINT `pembelian_bahan_return_pembelian_bahan_id_foreign` FOREIGN KEY (`pembelian_bahan_id`) REFERENCES `pembelian_bahan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pembelian_bahan_return_pembelian_bahan_rol_id_foreign` FOREIGN KEY (`pembelian_bahan_rol_id`) REFERENCES `pembelian_bahan_rol` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pembelian_bahan_rol`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pembelian_bahan_rol` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pembelian_bahan_warna_id` bigint unsigned NOT NULL,
  `berat` decimal(10,2) NOT NULL,
  `barcode` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tersedia',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `pembelian_bahan_rol_barcode_unique` (`barcode`),
  KEY `pembelian_bahan_rol_pembelian_bahan_warna_id_foreign` (`pembelian_bahan_warna_id`),
  CONSTRAINT `pembelian_bahan_rol_pembelian_bahan_warna_id_foreign` FOREIGN KEY (`pembelian_bahan_warna_id`) REFERENCES `pembelian_bahan_warna` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pembelian_bahan_warna`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pembelian_bahan_warna` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pembelian_bahan_id` bigint unsigned NOT NULL,
  `spk_bahan_warna_id` bigint unsigned DEFAULT NULL,
  `warna` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jumlah_rol` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pembelian_bahan_warna_pembelian_bahan_id_foreign` (`pembelian_bahan_id`),
  KEY `pembelian_bahan_warna_spk_bahan_warna_id_foreign` (`spk_bahan_warna_id`),
  CONSTRAINT `pembelian_bahan_warna_pembelian_bahan_id_foreign` FOREIGN KEY (`pembelian_bahan_id`) REFERENCES `pembelian_bahan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `pembelian_bahan_warna_spk_bahan_warna_id_foreign` FOREIGN KEY (`spk_bahan_warna_id`) REFERENCES `spk_bahan_warna` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pendapatan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pendapatan` (
  `id_pendapatan` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_penjahit` bigint unsigned NOT NULL,
  `total_pendapatan` decimal(15,2) NOT NULL,
  `total_claim` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_refund_claim` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_cashbon` decimal(15,2) NOT NULL DEFAULT '0.00',
  `potongan_aksesoris` int NOT NULL DEFAULT '0',
  `total_hutang` decimal(15,2) NOT NULL DEFAULT '0.00',
  `handtag` decimal(15,2) NOT NULL DEFAULT '0.00',
  `transportasi` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_transfer` decimal(15,2) NOT NULL,
  `status_pembayaran` enum('sudah dibayar','belum dibayar') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'belum dibayar',
  `kurangi_hutang` tinyint(1) NOT NULL DEFAULT '0',
  `kurangi_cashbon` tinyint(1) NOT NULL DEFAULT '0',
  `detail_aksesoris_ids` json DEFAULT NULL,
  `claim_ids` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `bukti_transfer` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_pendapatan`),
  KEY `pendapatan_id_penjahit_foreign` (`id_penjahit`),
  CONSTRAINT `pendapatan_id_penjahit_foreign` FOREIGN KEY (`id_penjahit`) REFERENCES `penjahit_cmt` (`id_penjahit`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pendapatan_cutting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pendapatan_cutting` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tukang_cutting_id` bigint unsigned NOT NULL,
  `total_pendapatan` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_claim` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_refund_claim` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_cashbon` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_hutang` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_transfer` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status_pembayaran` enum('sudah_dibayar','belum_dibayar') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'belum_dibayar',
  `bukti_transfer` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pendapatan_cutting_tukang_cutting_id_foreign` (`tukang_cutting_id`),
  CONSTRAINT `pendapatan_cutting_tukang_cutting_id_foreign` FOREIGN KEY (`tukang_cutting_id`) REFERENCES `tukang_cutting` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pendapatan_jasa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pendapatan_jasa` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tukang_jasa_id` bigint unsigned NOT NULL,
  `total_pendapatan` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_claim` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_refund_claim` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_cashbon` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_hutang` decimal(15,2) NOT NULL DEFAULT '0.00',
  `total_transfer` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status_pembayaran` enum('sudah_dibayar','belum_dibayar') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'belum_dibayar',
  `bukti_transfer` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pendapatan_jasa_tukang_jasa_id_foreign` (`tukang_jasa_id`),
  CONSTRAINT `pendapatan_jasa_tukang_jasa_id_foreign` FOREIGN KEY (`tukang_jasa_id`) REFERENCES `tukang_jasa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pendapatan_pabrik`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pendapatan_pabrik` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pabrik_id` bigint unsigned NOT NULL,
  `tanggal_bayar` date NOT NULL,
  `tanggal_jatuh_tempo` date DEFAULT NULL,
  `total_bayar` decimal(15,2) NOT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pendapatan_pabrik_pabrik_id_foreign` (`pabrik_id`),
  CONSTRAINT `pendapatan_pabrik_pabrik_id_foreign` FOREIGN KEY (`pabrik_id`) REFERENCES `pabrik` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pendapatan_pabrik_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pendapatan_pabrik_detail` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pendapatan_pabrik_id` bigint unsigned NOT NULL,
  `pembelian_bahan_id` bigint unsigned NOT NULL,
  `nominal` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pendapatan_pabrik_detail_pendapatan_pabrik_id_foreign` (`pendapatan_pabrik_id`),
  KEY `pendapatan_pabrik_detail_pembelian_bahan_id_foreign` (`pembelian_bahan_id`),
  CONSTRAINT `pendapatan_pabrik_detail_pembelian_bahan_id_foreign` FOREIGN KEY (`pembelian_bahan_id`) REFERENCES `pembelian_bahan` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `pendapatan_pabrik_detail_pendapatan_pabrik_id_foreign` FOREIGN KEY (`pendapatan_pabrik_id`) REFERENCES `pendapatan_pabrik` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pengiriman`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pengiriman` (
  `id_pengiriman` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_spk` bigint unsigned NOT NULL,
  `tanggal_pengiriman` date NOT NULL,
  `total_barang_dikirim` int NOT NULL,
  `sisa_barang` int DEFAULT NULL,
  `total_bayar` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `claim` decimal(15,2) NOT NULL DEFAULT '0.00',
  `refund_claim` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status_claim` enum('belum_dibayar','sudah_dibayar') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'belum_dibayar',
  `foto_nota` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_verifikasi` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_pengiriman`),
  KEY `pengiriman_id_spk_foreign` (`id_spk`),
  CONSTRAINT `pengiriman_id_spk_foreign` FOREIGN KEY (`id_spk`) REFERENCES `spk_cmt` (`id_spk`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pengiriman_pendapatan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pengiriman_pendapatan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_pendapatan` bigint unsigned NOT NULL,
  `id_pengiriman` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pengiriman_pendapatan_id_pendapatan_foreign` (`id_pendapatan`),
  KEY `pengiriman_pendapatan_id_pengiriman_foreign` (`id_pengiriman`),
  CONSTRAINT `pengiriman_pendapatan_id_pendapatan_foreign` FOREIGN KEY (`id_pendapatan`) REFERENCES `pendapatan` (`id_pendapatan`) ON DELETE CASCADE,
  CONSTRAINT `pengiriman_pendapatan_id_pengiriman_foreign` FOREIGN KEY (`id_pengiriman`) REFERENCES `pengiriman` (`id_pengiriman`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `pengiriman_warna`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pengiriman_warna` (
  `id_pengiriman_warna` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_pengiriman` bigint unsigned NOT NULL,
  `warna` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jumlah_dikirim` int NOT NULL,
  `sisa_barang_per_warna` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_pengiriman_warna`),
  KEY `pengiriman_warna_id_pengiriman_foreign` (`id_pengiriman`),
  CONSTRAINT `pengiriman_warna_id_pengiriman_foreign` FOREIGN KEY (`id_pengiriman`) REFERENCES `pengiriman` (`id_pengiriman`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `penjahit_cmt`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `penjahit_cmt` (
  `id_penjahit` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama_penjahit` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kontak` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `alamat` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `ktp` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `kategori_penjahit` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `jumlah_tim` int DEFAULT NULL,
  `mesin` json DEFAULT NULL,
  `no_rekening` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id_penjahit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `petugas_c`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `petugas_c` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `spk_cmt_id` bigint unsigned DEFAULT NULL,
  `penjahit_id` bigint unsigned DEFAULT NULL,
  `jumlah_dipesan` int NOT NULL,
  `status` enum('pending','diproses','selesai') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `total_harga` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `petugas_c_user_id_foreign` (`user_id`),
  KEY `petugas_c_penjahit_id_foreign` (`penjahit_id`),
  KEY `petugas_c_spk_cmt_id_foreign` (`spk_cmt_id`),
  CONSTRAINT `petugas_c_penjahit_id_foreign` FOREIGN KEY (`penjahit_id`) REFERENCES `penjahit_cmt` (`id_penjahit`) ON DELETE SET NULL,
  CONSTRAINT `petugas_c_spk_cmt_id_foreign` FOREIGN KEY (`spk_cmt_id`) REFERENCES `spk_cmt` (`id_spk`) ON DELETE CASCADE,
  CONSTRAINT `petugas_c_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `petugas_d_verif`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `petugas_d_verif` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `petugas_c_id` bigint unsigned NOT NULL,
  `barcode` json NOT NULL,
  `bukti_nota` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `status_pembayaran` enum('belum','sudah') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'belum',
  `bukti_pembayaran` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `petugas_d_verif_user_id_foreign` (`user_id`),
  KEY `petugas_d_verif_petugas_c_id_foreign` (`petugas_c_id`),
  CONSTRAINT `petugas_d_verif_petugas_c_id_foreign` FOREIGN KEY (`petugas_c_id`) REFERENCES `petugas_c` (`id`) ON DELETE CASCADE,
  CONSTRAINT `petugas_d_verif_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `produk`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `produk` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama_produk` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kategori_produk` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gambar_produk` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `jenis_produk` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `harga_jasa_cutting` decimal(15,2) NOT NULL DEFAULT '0.00',
  `harga_jasa_cmt` decimal(15,2) NOT NULL DEFAULT '0.00',
  `harga_jasa_aksesoris` decimal(15,2) NOT NULL DEFAULT '0.00',
  `harga_overhead` decimal(15,2) NOT NULL DEFAULT '0.00',
  `hpp` decimal(15,2) NOT NULL DEFAULT '0.00',
  `status_produk` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sementara',
  PRIMARY KEY (`id`),
  KEY `idx_produk_nama_produk` (`nama_produk`),
  FULLTEXT KEY `ft_produk_nama_produk` (`nama_produk`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `produk_komponen`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `produk_komponen` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `produk_id` bigint unsigned NOT NULL,
  `jenis_komponen` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sumber_komponen` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'bahan',
  `bahan_id` bigint unsigned DEFAULT NULL,
  `aksesoris_id` bigint unsigned DEFAULT NULL,
  `harga_bahan` decimal(15,2) NOT NULL DEFAULT '0.00',
  `jumlah_bahan` decimal(12,3) NOT NULL DEFAULT '0.000',
  `total_harga_bahan` decimal(15,2) NOT NULL DEFAULT '0.00',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `produk_komponen_produk_id_foreign` (`produk_id`),
  KEY `produk_komponen_bahan_id_foreign` (`bahan_id`),
  KEY `produk_komponen_aksesoris_id_foreign` (`aksesoris_id`),
  CONSTRAINT `produk_komponen_aksesoris_id_foreign` FOREIGN KEY (`aksesoris_id`) REFERENCES `aksesoris` (`id`) ON DELETE SET NULL,
  CONSTRAINT `produk_komponen_bahan_id_foreign` FOREIGN KEY (`bahan_id`) REFERENCES `bahan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `produk_komponen_produk_id_foreign` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `produk_sku`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `produk_sku` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `produk_id` bigint unsigned NOT NULL,
  `warna` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ukuran` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sku` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `produk_sku_produk_id_warna_ukuran_unique` (`produk_id`,`warna`,`ukuran`),
  UNIQUE KEY `produk_sku_sku_unique` (`sku`),
  CONSTRAINT `produk_sku_produk_id_foreign` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `produk_update_histories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `produk_update_histories` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `produk_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `action` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `old_data` json DEFAULT NULL,
  `new_data` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `produk_update_histories_produk_id_foreign` (`produk_id`),
  KEY `produk_update_histories_user_id_foreign` (`user_id`),
  CONSTRAINT `produk_update_histories_produk_id_foreign` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`) ON DELETE CASCADE,
  CONSTRAINT `produk_update_histories_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `qc_scan_lolos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qc_scan_lolos` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nomor_seri` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sku` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jumlah` int NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `qc_scan_lolos_nomor_seri_sku_index` (`nomor_seri`,`sku`),
  KEY `idx_qc_scan_lolos_created_at` (`created_at`),
  KEY `idx_qc_scan_lolos_sku` (`sku`),
  KEY `idx_qc_scan_lolos_sku_created_at` (`sku`,`created_at`),
  KEY `idx_qc_scan_lolos_seri_sku_created_at` (`nomor_seri`,`sku`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `qc_scan_reject`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `qc_scan_reject` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nomor_seri` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sku` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jumlah` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `qc_scan_reject_nomor_seri_sku_index` (`nomor_seri`,`sku`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `quality_control_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quality_control_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quality_control_id` bigint unsigned NOT NULL,
  `status` enum('lolos','reject') COLLATE utf8mb4_unicode_ci NOT NULL,
  `sku` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jumlah` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quality_control_items_quality_control_id_foreign` (`quality_control_id`),
  CONSTRAINT `quality_control_items_quality_control_id_foreign` FOREIGN KEY (`quality_control_id`) REFERENCES `quality_controls` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `quality_controls`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `quality_controls` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `kode_seri` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jumlah_barang_nota` int NOT NULL,
  `jumlah_diterima` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_has_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_has_permissions` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_name_guard_name_unique` (`name`,`guard_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `seri`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `seri` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nomor_seri` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sku` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jumlah` int unsigned NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `skus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `skus` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sku` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `skus_sku_unique` (`sku`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `spk_bahan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `spk_bahan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pabrik_id` bigint unsigned NOT NULL,
  `bahan_id` bigint unsigned NOT NULL,
  `jumlah` int NOT NULL,
  `jenis_pembayaran` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tanggal_pembayaran` date NOT NULL,
  `status` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lama_pemesanan` int DEFAULT NULL COMMENT 'Selisih hari dari SPK dibuat sampai Pembelian Bahan dibuat',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_spk_bahan_pabrik_id` (`pabrik_id`),
  KEY `idx_spk_bahan_bahan_id` (`bahan_id`),
  KEY `idx_spk_bahan_status` (`status`),
  KEY `idx_spk_bahan_tanggal_pembayaran` (`tanggal_pembayaran`),
  KEY `idx_spk_bahan_jenis_pembayaran` (`jenis_pembayaran`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `spk_bahan_warna`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `spk_bahan_warna` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `spk_bahan_id` bigint unsigned NOT NULL,
  `warna` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jumlah_rol` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_spk_bahan_warna_spk_bahan_id` (`spk_bahan_id`),
  KEY `idx_spk_bahan_warna_warna` (`warna`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `spk_chat_invites`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `spk_chat_invites` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `staff_id` bigint unsigned NOT NULL,
  `spk_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `spk_chat_invites_staff_id_foreign` (`staff_id`),
  KEY `spk_chat_invites_spk_id_foreign` (`spk_id`),
  CONSTRAINT `spk_chat_invites_spk_id_foreign` FOREIGN KEY (`spk_id`) REFERENCES `spk_cmt` (`id_spk`) ON DELETE CASCADE,
  CONSTRAINT `spk_chat_invites_staff_id_foreign` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `spk_chats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `spk_chats` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_spk` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `image_url` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `video_url` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vn_url` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `spk_chats_id_spk_foreign` (`id_spk`),
  KEY `spk_chats_user_id_foreign` (`user_id`),
  CONSTRAINT `spk_chats_id_spk_foreign` FOREIGN KEY (`id_spk`) REFERENCES `spk_cmt` (`id_spk`) ON DELETE CASCADE,
  CONSTRAINT `spk_chats_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `spk_cmt`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `spk_cmt` (
  `id_spk` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sku_id` bigint unsigned DEFAULT NULL,
  `source_type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'cutting | jasa',
  `source_id` bigint unsigned NOT NULL,
  `harga_per_barang` decimal(15,2) DEFAULT NULL,
  `total_harga` decimal(15,2) DEFAULT NULL,
  `deadline` date NOT NULL,
  `id_penjahit` bigint unsigned NOT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `catatan` text COLLATE utf8mb4_unicode_ci,
  `markeran` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aksesoris` text COLLATE utf8mb4_unicode_ci,
  `handtag` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `waktu_pengerjaan_terakhir` int DEFAULT NULL,
  `sisa_hari_terakhir` int DEFAULT NULL,
  `merek` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `harga_barang_dasar` decimal(15,2) NOT NULL,
  `jenis_harga_barang` enum('per_pcs','per_lusin') COLLATE utf8mb4_unicode_ci NOT NULL,
  `harga_per_jasa` decimal(10,2) DEFAULT NULL,
  `status` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Pending',
  `pending_at` timestamp NULL DEFAULT NULL,
  `pending_until` date DEFAULT NULL,
  `alasan_pending` text COLLATE utf8mb4_unicode_ci,
  `jenis_harga_jasa` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'per_barang',
  `harga_jasa_awal` decimal(10,2) DEFAULT NULL,
  PRIMARY KEY (`id_spk`),
  KEY `spk_cmt_id_penjahit_foreign` (`id_penjahit`),
  KEY `spk_cmt_source_type_source_id_index` (`source_type`,`source_id`),
  KEY `spk_cmt_sku_id_foreign` (`sku_id`),
  CONSTRAINT `spk_cmt_id_penjahit_foreign` FOREIGN KEY (`id_penjahit`) REFERENCES `penjahit_cmt` (`id_penjahit`) ON DELETE CASCADE,
  CONSTRAINT `spk_cmt_sku_id_foreign` FOREIGN KEY (`sku_id`) REFERENCES `skus` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `spk_cmt_items`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `spk_cmt_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `spk_cmt_id` bigint unsigned NOT NULL,
  `sku_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `spk_cmt_items_spk_cmt_id_foreign` (`spk_cmt_id`),
  KEY `spk_cmt_items_sku_id_foreign` (`sku_id`),
  CONSTRAINT `spk_cmt_items_sku_id_foreign` FOREIGN KEY (`sku_id`) REFERENCES `skus` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `spk_cmt_items_spk_cmt_id_foreign` FOREIGN KEY (`spk_cmt_id`) REFERENCES `spk_cmt` (`id_spk`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `spk_cmt_warna`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `spk_cmt_warna` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `spk_cmt_id` bigint unsigned NOT NULL,
  `nama_warna` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `qty` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `spk_cmt_warna_spk_cmt_id_foreign` (`spk_cmt_id`),
  CONSTRAINT `spk_cmt_warna_spk_cmt_id_foreign` FOREIGN KEY (`spk_cmt_id`) REFERENCES `spk_cmt` (`id_spk`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `spk_cutting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `spk_cutting` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_spk_cutting` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `barcode` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `produk_id` bigint unsigned NOT NULL,
  `tanggal_batas_kirim` date NOT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `harga_jasa` decimal(10,2) NOT NULL,
  `satuan_harga` enum('Lusin','Pcs') COLLATE utf8mb4_unicode_ci NOT NULL,
  `jumlah_asumsi_produk` int unsigned DEFAULT NULL,
  `jenis_spk` enum('Terjual','Fittingan','Habisin Bahan') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `harga_per_pcs` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `tukang_cutting_id` bigint unsigned DEFAULT NULL,
  `tukang_pola_id` bigint unsigned DEFAULT NULL,
  `status_cutting` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in progress',
  `sisa_hari_terakhir` int DEFAULT NULL,
  `waktu_pengerjaan_terakhir` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_spk_cutting_status` (`status_cutting`),
  KEY `idx_spk_cutting_jenis_spk` (`jenis_spk`),
  KEY `idx_spk_cutting_created_at` (`created_at`),
  KEY `idx_spk_cutting_tukang_cutting_id` (`tukang_cutting_id`),
  KEY `idx_spk_cutting_status_created` (`status_cutting`,`created_at`),
  KEY `idx_spk_cutting_tukang_spk` (`tukang_cutting_id`,`id_spk_cutting`),
  KEY `idx_spk_cutting_id_spk` (`id_spk_cutting`),
  KEY `idx_spk_cutting_produk_id` (`produk_id`),
  KEY `idx_spk_cutting_tukang_pola_id` (`tukang_pola_id`),
  KEY `idx_spk_cutting_tanggal_batas_kirim` (`tanggal_batas_kirim`),
  CONSTRAINT `spk_cutting_produk_id_foreign` FOREIGN KEY (`produk_id`) REFERENCES `produk` (`id`),
  CONSTRAINT `spk_cutting_tukang_cutting_id_foreign` FOREIGN KEY (`tukang_cutting_id`) REFERENCES `tukang_cutting` (`id`) ON DELETE SET NULL,
  CONSTRAINT `spk_cutting_tukang_pola_id_foreign` FOREIGN KEY (`tukang_pola_id`) REFERENCES `tukang_pola` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `spk_cutting_bagian`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `spk_cutting_bagian` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `spk_cutting_id` bigint unsigned NOT NULL,
  `nama_bagian` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `spk_cutting_bagian_spk_cutting_id_foreign` (`spk_cutting_id`),
  CONSTRAINT `spk_cutting_bagian_spk_cutting_id_foreign` FOREIGN KEY (`spk_cutting_id`) REFERENCES `spk_cutting` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `spk_cutting_bahan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `spk_cutting_bahan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `spk_cutting_bagian_id` bigint unsigned NOT NULL,
  `bahan_id` bigint unsigned NOT NULL,
  `warna` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qty` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `berat` double(8,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `spk_cutting_bahan_spk_cutting_bagian_id_foreign` (`spk_cutting_bagian_id`),
  KEY `spk_cutting_bahan_bahan_id_foreign` (`bahan_id`),
  CONSTRAINT `spk_cutting_bahan_bahan_id_foreign` FOREIGN KEY (`bahan_id`) REFERENCES `bahan` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `spk_cutting_bahan_spk_cutting_bagian_id_foreign` FOREIGN KEY (`spk_cutting_bagian_id`) REFERENCES `spk_cutting_bagian` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `spk_cutting_distribusi`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `spk_cutting_distribusi` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `spk_cutting_id` bigint unsigned NOT NULL,
  `hasil_cutting_id` bigint unsigned DEFAULT NULL,
  `kode_seri` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `jumlah_produk` int NOT NULL,
  `status` enum('draft','assigned') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `spk_cutting_distribusi_spk_cutting_id_kode_seri_unique` (`spk_cutting_id`,`kode_seri`),
  KEY `spk_cutting_distribusi_hasil_cutting_id_foreign` (`hasil_cutting_id`),
  KEY `idx_spk_cutting_distribusi_kode_seri` (`kode_seri`),
  KEY `idx_spk_cutting_distribusi_kode_seri_id` (`kode_seri`,`id`),
  CONSTRAINT `spk_cutting_distribusi_hasil_cutting_id_foreign` FOREIGN KEY (`hasil_cutting_id`) REFERENCES `hasil_cutting` (`id`) ON DELETE CASCADE,
  CONSTRAINT `spk_cutting_distribusi_spk_cutting_id_foreign` FOREIGN KEY (`spk_cutting_id`) REFERENCES `spk_cutting` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `spk_cutting_distribusi_detail`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `spk_cutting_distribusi_detail` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `spk_cutting_distribusi_id` bigint unsigned NOT NULL,
  `warna` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `jumlah_produk` int NOT NULL,
  `produk_sku_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `spk_cutting_distribusi_detail_spk_cutting_distribusi_id_foreign` (`spk_cutting_distribusi_id`),
  KEY `spk_cutting_distribusi_detail_produk_sku_id_foreign` (`produk_sku_id`),
  CONSTRAINT `spk_cutting_distribusi_detail_produk_sku_id_foreign` FOREIGN KEY (`produk_sku_id`) REFERENCES `produk_sku` (`id`) ON DELETE SET NULL,
  CONSTRAINT `spk_cutting_distribusi_detail_spk_cutting_distribusi_id_foreign` FOREIGN KEY (`spk_cutting_distribusi_id`) REFERENCES `spk_cutting_distribusi` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `spk_cutting_skus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `spk_cutting_skus` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `spk_cutting_id` bigint unsigned NOT NULL,
  `produk_sku_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `spk_cutting_skus_spk_cutting_id_produk_sku_id_unique` (`spk_cutting_id`,`produk_sku_id`),
  KEY `spk_cutting_skus_produk_sku_id_foreign` (`produk_sku_id`),
  CONSTRAINT `spk_cutting_skus_produk_sku_id_foreign` FOREIGN KEY (`produk_sku_id`) REFERENCES `produk_sku` (`id`) ON DELETE CASCADE,
  CONSTRAINT `spk_cutting_skus_spk_cutting_id_foreign` FOREIGN KEY (`spk_cutting_id`) REFERENCES `spk_cutting` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `spk_cutting_status_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `spk_cutting_status_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `spk_cutting_id` bigint unsigned NOT NULL,
  `status` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `keterangan` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `spk_cutting_status_logs_spk_cutting_id_foreign` (`spk_cutting_id`),
  CONSTRAINT `spk_cutting_status_logs_spk_cutting_id_foreign` FOREIGN KEY (`spk_cutting_id`) REFERENCES `spk_cutting` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `spk_jasa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `spk_jasa` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tukang_jasa_id` bigint unsigned NOT NULL,
  `spk_cutting_distribusi_id` bigint unsigned DEFAULT NULL,
  `deadline` date NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `jumlah` int NOT NULL DEFAULT '0',
  `harga` decimal(12,2) DEFAULT NULL,
  `opsi_harga` enum('pcs','lusin') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `harga_per_pcs` decimal(10,2) DEFAULT NULL,
  `status_jasa` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in progress',
  `tanggal_ambil` date DEFAULT NULL,
  `foto` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_pengambilan` enum('belum_diambil','sudah_diambil','batal_diambil','selesai') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'belum_diambil',
  PRIMARY KEY (`id`),
  UNIQUE KEY `spk_jasa_distribusi_unique` (`spk_cutting_distribusi_id`),
  KEY `spk_jasa_tukang_jasa_id_foreign` (`tukang_jasa_id`),
  KEY `spk_jasa_status_pengambilan_index` (`status_pengambilan`),
  CONSTRAINT `spk_jasa_spk_cutting_distribusi_id_foreign` FOREIGN KEY (`spk_cutting_distribusi_id`) REFERENCES `spk_cutting_distribusi` (`id`) ON DELETE CASCADE,
  CONSTRAINT `spk_jasa_tukang_jasa_id_foreign` FOREIGN KEY (`tukang_jasa_id`) REFERENCES `tukang_jasa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `spk_jasa_status_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `spk_jasa_status_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `spk_cmt_id` bigint unsigned NOT NULL,
  `status` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `spk_jasa_status_log_spk_cmt_id_foreign` (`spk_cmt_id`),
  CONSTRAINT `spk_jasa_status_log_spk_cmt_id_foreign` FOREIGN KEY (`spk_cmt_id`) REFERENCES `spk_cmt` (`id_spk`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `spk_jasa_warna`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `spk_jasa_warna` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `spk_jasa_id` bigint unsigned NOT NULL,
  `warna` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `qty` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `spk_jasa_warna_spk_jasa_id_foreign` (`spk_jasa_id`),
  CONSTRAINT `spk_jasa_warna_spk_jasa_id_foreign` FOREIGN KEY (`spk_jasa_id`) REFERENCES `spk_jasa` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `spk_samples`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `spk_samples` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama_sample` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kategori_sample` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `detail` text COLLATE utf8mb4_unicode_ci,
  `status_spk` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Draft',
  `tahap_proses` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status_proses` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `keterangan_sample` text COLLATE utf8mb4_unicode_ci,
  `foto` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tukang_sample_id` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `spk_samples_tukang_sample_id_foreign` (`tukang_sample_id`),
  CONSTRAINT `spk_samples_tukang_sample_id_foreign` FOREIGN KEY (`tukang_sample_id`) REFERENCES `tukang_samples` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stok_aksesoris`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stok_aksesoris` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pembelian_aksesoris_b_id` bigint unsigned NOT NULL,
  `aksesoris_id` bigint unsigned NOT NULL,
  `barcode` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('tersedia','terpakai') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tersedia',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stok_aksesoris_barcode_unique` (`barcode`),
  KEY `stok_aksesoris_pembelian_aksesoris_b_id_foreign` (`pembelian_aksesoris_b_id`),
  KEY `stok_aksesoris_aksesoris_id_foreign` (`aksesoris_id`),
  CONSTRAINT `stok_aksesoris_aksesoris_id_foreign` FOREIGN KEY (`aksesoris_id`) REFERENCES `aksesoris` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stok_aksesoris_pembelian_aksesoris_b_id_foreign` FOREIGN KEY (`pembelian_aksesoris_b_id`) REFERENCES `pembelian_aksesoris_b` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stok_bahan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stok_bahan` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `pembelian_bahan_id` bigint unsigned NOT NULL,
  `pembelian_bahan_warna_id` bigint unsigned NOT NULL,
  `pembelian_bahan_rol_id` bigint unsigned NOT NULL,
  `gudang_id` bigint unsigned NOT NULL,
  `pabrik_id` bigint unsigned NOT NULL,
  `barcode` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `berat` decimal(12,3) DEFAULT NULL,
  `scanned_at` timestamp NOT NULL,
  `status` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tersedia',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stok_bahan_barcode_unique` (`barcode`),
  KEY `stok_bahan_pembelian_bahan_id_foreign` (`pembelian_bahan_id`),
  KEY `stok_bahan_pembelian_bahan_warna_id_foreign` (`pembelian_bahan_warna_id`),
  KEY `stok_bahan_pembelian_bahan_rol_id_foreign` (`pembelian_bahan_rol_id`),
  KEY `stok_bahan_gudang_id_foreign` (`gudang_id`),
  KEY `stok_bahan_pabrik_id_foreign` (`pabrik_id`),
  CONSTRAINT `stok_bahan_gudang_id_foreign` FOREIGN KEY (`gudang_id`) REFERENCES `gudang` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stok_bahan_pabrik_id_foreign` FOREIGN KEY (`pabrik_id`) REFERENCES `pabrik` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stok_bahan_pembelian_bahan_id_foreign` FOREIGN KEY (`pembelian_bahan_id`) REFERENCES `pembelian_bahan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stok_bahan_pembelian_bahan_rol_id_foreign` FOREIGN KEY (`pembelian_bahan_rol_id`) REFERENCES `pembelian_bahan_rol` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stok_bahan_pembelian_bahan_warna_id_foreign` FOREIGN KEY (`pembelian_bahan_warna_id`) REFERENCES `pembelian_bahan_warna` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stok_bahan_keluar`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stok_bahan_keluar` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `spk_cutting_id` bigint unsigned NOT NULL,
  `spk_cutting_bahan_id` bigint unsigned NOT NULL,
  `stok_bahan_id` bigint unsigned NOT NULL,
  `barcode` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `berat` decimal(10,2) DEFAULT NULL,
  `scanned_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `stok_bahan_keluar_spk_cutting_id_foreign` (`spk_cutting_id`),
  KEY `stok_bahan_keluar_spk_cutting_bahan_id_foreign` (`spk_cutting_bahan_id`),
  KEY `stok_bahan_keluar_stok_bahan_id_foreign` (`stok_bahan_id`),
  CONSTRAINT `stok_bahan_keluar_spk_cutting_bahan_id_foreign` FOREIGN KEY (`spk_cutting_bahan_id`) REFERENCES `spk_cutting_bahan` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stok_bahan_keluar_spk_cutting_id_foreign` FOREIGN KEY (`spk_cutting_id`) REFERENCES `spk_cutting` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stok_bahan_keluar_stok_bahan_id_foreign` FOREIGN KEY (`stok_bahan_id`) REFERENCES `stok_bahan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `stok_gudang_produk`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `stok_gudang_produk` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `sku_id` bigint unsigned NOT NULL,
  `qty` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stok_gudang_produk_sku_id_unique` (`sku_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sync_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sync_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_sync_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sync_logs_type_unique` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tukang_cutting`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tukang_cutting` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama_tukang_cutting` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `kontak` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `no_rekening` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alamat` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tukang_jasa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tukang_jasa` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kontak` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alamat` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `no_rekening` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `jenis_jasa` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tukang_jasa_nama` (`nama`),
  FULLTEXT KEY `ft_tukang_jasa_nama` (`nama`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tukang_pola`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tukang_pola` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tukang_potong`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tukang_potong` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `kontak` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alamat` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bank` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `no_rekening` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ktp` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tukang_samples`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tukang_samples` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama_tukang_sample` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nomor_hp` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `id_penjahit` bigint unsigned DEFAULT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `invited_by_supervisor` tinyint(1) NOT NULL DEFAULT '0',
  `invited_spk_id` bigint unsigned DEFAULT NULL,
  `foto` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_id_penjahit_foreign` (`id_penjahit`),
  KEY `users_invited_spk_id_foreign` (`invited_spk_id`),
  CONSTRAINT `users_id_penjahit_foreign` FOREIGN KEY (`id_penjahit`) REFERENCES `penjahit_cmt` (`id_penjahit`) ON DELETE SET NULL,
  CONSTRAINT `users_invited_spk_id_foreign` FOREIGN KEY (`invited_spk_id`) REFERENCES `spk_cmt` (`id_spk`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `warna`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `warna` (
  `id_warna` bigint unsigned NOT NULL AUTO_INCREMENT,
  `nama_warna` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `qty` int NOT NULL DEFAULT '0',
  `id_spk` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id_warna`),
  KEY `warna_id_spk_foreign` (`id_spk`),
  CONSTRAINT `warna_id_spk_foreign` FOREIGN KEY (`id_spk`) REFERENCES `spk_cmt` (`id_spk`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `websockets_statistics_entries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `websockets_statistics_entries` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `app_id` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `peak_connections_count` int NOT NULL,
  `websocket_messages_count` int NOT NULL,
  `api_messages_count` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

