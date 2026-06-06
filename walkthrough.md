# Walkthrough Perubahan: Logika Potong Stok Berdasarkan Keberadaan Nomor Seri

Saya telah mengimplementasikan logika baru untuk pemotongan stok pada proses packing. Sekarang, pemotongan stok hanya akan dilakukan apabila nomor seri yang di-scan **ada di stok gudang produk**. Jika tidak ada, scan keluar tetap bisa dilakukan (*bypass*) tetapi **stok tidak terpotong**.

## Perubahan yang Dibuat

1. **`TracksWarehouseSerials` Trait (`app/Traits/TracksWarehouseSerials.php`)**
   - Menambahkan trait untuk melacak slot keberadaan nomor seri secara dinamis berdasarkan data activity logs `placement` dan membandingkannya dengan sisa kuantitas stok di tabel `gudang_produk_stock_entries`.

2. **`OrderController.php`**
   - Menambahkan penggunaan trait `TracksWarehouseSerials`.
   - Mengubah logika `validateScan` agar memotong stok per serial number. Jika nomor seri ditemukan di slot tertentu, stok pada slot tersebut berkurang `1`. Jika tidak ditemukan, pemotongan dilewati tanpa melempar error.

3. **`PackingRandomController.php`**
   - Menambahkan penggunaan trait `TracksWarehouseSerials`.
   - Mengubah penyimpanan data scan pada `$stockRequestBySkuId` agar menyimpan seluruh nomor seri dari baris tersebut.
   - Mengubah transaksi pengurangan stok di `validateScan` agar memotong per serial number. Jika ditemukan di slot, stok slot tersebut berkurang `1`. Jika tidak, dilewati tanpa memunculkan error stok kurang.

4. **`NoDataGineeSerialPackingService.php`**
   - Menambahkan penggunaan trait `TracksWarehouseSerials`.
   - Mengubah logika `persistPackingResults` untuk melakukan pemotongan stok per nomor seri secara FIFO pada slot tempat nomor seri tersebut terdeteksi di gudang. Jika tidak ada di gudang, dilewati.

## Cara Melakukan Verifikasi Manual

Untuk menguji perubahan ini di staging/development Anda, ikuti langkah berikut:

### Skenario A: Nomor Seri Terdaftar di Gudang (Harus Memotong Stok)
1. Siapkan 1 SKU (misal: `SET AZHIMAH - LAVENDER XL`) dengan stok awal sebanyak `5` di sistem gudang.
2. Pastikan nomor seri `666.1.1` terdaftar dalam log `placement` untuk slot/lokasi terkait.
3. Masuk ke halaman **Packing**, lalu scan barcode format `SET AZHIMAH - LAVENDER XL | 666.1.1`.
4. Submit validasi packing.
5. **Verifikasi:** Stok SKU `SET AZHIMAH - LAVENDER XL` di sistem gudang harus **berkurang menjadi 4**.

### Skenario B: Nomor Seri TIDAK Terdaftar di Gudang (Tidak Memotong Stok & Tetap Sukses)
1. Siapkan 1 SKU (misal: `SET AZHIMAH - LAVENDER XL`) dengan stok awal sebanyak `5` di sistem gudang.
2. Gunakan nomor seri fiktif `666.1.2` yang **tidak pernah diinput** ke log `placement` gudang manapun.
3. Masuk ke halaman **Packing**, lalu scan barcode format `SET AZHIMAH - LAVENDER XL | 666.1.2`.
4. Submit validasi packing.
5. **Verifikasi:**
   - Scan dan submit packing **harus tetap berhasil** disubmit.
   - Stok SKU `SET AZHIMAH - LAVENDER XL` di sistem gudang harus **tetap 5** (tidak terpotong).
