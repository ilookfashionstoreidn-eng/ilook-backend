# Rencana Implementasi: Perubahan Logika Potong Stok Berdasarkan Keberadaan Kode Seri

Mempersingkat dan menyelaraskan logika pemotongan stok pada saat packing: Stok hanya akan dipotong jika kombinasi SKU dan kode seri (nomor seri/roll) terdeteksi ada di sistem stok gudang (berdasarkan data penempatan/placement terbaru). Jika tidak ada di stok, scan tetap bisa berhasil disubmit/dilanjutkan tanpa memotong stok dan tanpa memunculkan error stok kurang.

## User Review Required

> [!IMPORTANT]
> - Perubahan ini akan menonaktifkan pengecekan stok kurang yang memicu error (misal: "Stok gudang produk tidak mencukupi") di standard packing, packing random, packing pendingan, dan rekonsiliasi No Data Ginee.
> - Serial number yang tidak terdaftar di gudang tetap bisa disubmit/scan keluar tanpa memotong stok gudang.

## Proposed Changes

### Backend Components

---

#### [NEW] [TracksWarehouseSerials.php](file:///d:/Ilook-Project/ilook-backend/app/Traits/TracksWarehouseSerials.php)
Membuat trait baru untuk melacak apakah sebuah nomor seri untuk SKU tertentu sedang berada di stok salah satu slot gudang (FIFO).

#### [MODIFY] [OrderController.php](file:///d:/Ilook-Project/ilook-backend/app/Http/Controllers/OrderController.php)
- Gunakan trait `TracksWarehouseSerials`.
- Ubah logika `validateScan` pada bagian pengurangan stok agar memotong per serial number menggunakan pencarian slot (`findSlotForSerial`). Jika slot ditemukan, kurangi stok di slot tersebut sebanyak 1. Jika tidak, lewati pemotongan tanpa error.

#### [MODIFY] [PackingRandomController.php](file:///d:/Ilook-Project/ilook-backend/app/Http/Controllers/PackingRandomController.php)
- Gunakan trait `TracksWarehouseSerials`.
- Ubah pengumpulan serial per SKU pada `$stockRequestBySkuId` untuk menyimpan array `serials`.
- Ubah logika transaksi pengurangan stok di `validateScan` agar memotong per serial number menggunakan pencarian slot (`findSlotForSerial`). Jika slot ditemukan, kurangi stok di slot tersebut sebanyak 1. Jika tidak, lewati pemotongan tanpa error.

#### [MODIFY] [NoDataGineeSerialPackingService.php](file:///d:/Ilook-Project/ilook-backend/app/Services/NoDataGineeSerialPackingService.php)
- Gunakan trait `TracksWarehouseSerials`.
- Ubah logika `persistPackingResults` agar memotong per serial number menggunakan pencarian slot (`findSlotForSerial`). Jika slot ditemukan, kurangi stok di slot tersebut sebanyak 1. Jika tidak, lewati pemotongan tanpa error.

---

## Verification Plan

### Automated Tests
Kita bisa menjalankan test feature packing yang ada untuk memastikan fungsionalitas dasar tetap berjalan lancar:
- `php artisan test --filter=PackingNoDataGineeControllerTest`
- `php artisan test --filter=PackingLogsModeFilterTest`
- `php artisan test --filter=PackingBelumBarcodeControllerTest`

### Manual Verification
1. Lakukan scan packing untuk SKU yang memiliki stok 5 di sistem dengan nomor seri yang valid (ada di log placement teratas). Pastikan stok berkurang menjadi 4.
2. Lakukan scan packing untuk SKU dengan nomor seri fiktif yang tidak ada di stok/gudang. Pastikan transaksi packing berhasil disubmit, dan stok tidak terpotong (tetap 5).
