<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\SpkCmtController;
use App\Http\Controllers\PenjahitController;
use App\Http\Controllers\LaporanCmtController;
use App\Http\Controllers\WarnaController;
use App\Http\Controllers\PengirimanController;
use App\Http\Controllers\CashboanController;
use App\Http\Controllers\LogPembayaranCashbonController;
use App\Http\Controllers\HutangController;
use App\Http\Controllers\LogPembayaranHutangController;
use App\Http\Controllers\PendapatanController;

use App\Http\Controllers\SpkChatController;
use App\Http\Controllers\SpkChatInvite;
use App\Http\Controllers\StaffController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProdukController;
use App\Http\Controllers\ProductListController;
use App\Http\Controllers\AksesorisController;
use App\Http\Controllers\PembelianAController;
use App\Http\Controllers\PembelianBController;
use App\Http\Controllers\StokAksesorisController;
use App\Http\Controllers\PetugasCController;
use App\Http\Controllers\PetugasDVerifController;
use App\Http\Controllers\SpkCuttingController;
use App\Http\Controllers\TukangCuttingController;
use App\Http\Controllers\TukangPolaController;
use App\Http\Controllers\HasilCuttingController;
use App\Http\Controllers\MarkeranProdukController;
use App\Http\Controllers\HutangCuttingController;
use App\Http\Controllers\CashboanCuttingController;
use App\Http\Controllers\PendapatanCuttingController;
use App\Http\Controllers\TukangJasaController;
use App\Http\Controllers\SpkJasaController;
use App\Http\Controllers\HasilJasaController;
use App\Http\Controllers\HutangJasaController;
use App\Http\Controllers\CashboanJasaController;
use App\Http\Controllers\PendapatanJasaController;
use App\Http\Controllers\GudangController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\GineeSyncController;
use App\Http\Controllers\PabrikController;
use App\Http\Controllers\PembelianBahanController;
use App\Http\Controllers\ReturnBahanController;
use App\Http\Controllers\SeriController;
use App\Http\Controllers\BahanController;
use App\Http\Controllers\StokBahanController;
use App\Http\Controllers\StokBahanKeluarController;
use App\Http\Controllers\SpkCuttingDistribusiController;
use App\Http\Controllers\SpkDistribusiHistoryController;
use App\Http\Controllers\LaporanDailyProduksiController;
use App\Http\Controllers\SpkBahanController;
use App\Http\Controllers\PendapatanPabrikController;
use App\Http\Controllers\SkuController;
use App\Http\Controllers\GudangProdukController;
use App\Http\Controllers\GudangProdukWorkspaceController;
use App\Http\Controllers\StokGudangProdukController;
use App\Http\Controllers\QcLolosController;
use App\Http\Controllers\QcRejectController;
use App\Http\Controllers\QualityControlController;
use App\Http\Controllers\TukangSampleController;
use App\Http\Controllers\SpkSampleController;
use App\Http\Controllers\PackingBelumBarcodeController;
use App\Http\Controllers\PackingRandomController;
use App\Http\Controllers\PackingNoDataGineeController;







Route::get('/spkcmt', [SpkCmtController::class, 'index']);
Route::get('/spkcmt/pendapatan', [SpkCmtController::class, 'pendapatanSummary']);
Route::get('/test-ping', function () {
    return response()->json(['message' => 'Pong!']);
});
Route::get('/test-laporan', [LaporanDailyProduksiController::class, 'index']);

Route::get('/', function () {
    return response()->json(['message' => 'API is working!']);
});

Route::get('/some-endpoint', function () {
    return 'Hello, world!';
});

Route::get('/db-ping', function () {
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        return response()->json(['status' => 'ok']);
    } catch (\Throwable $e) {
        return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
});

Route::get('/spk-cmt/{id}/download-pdf', [SpkCmtController::class, 'downloadPdf']);
Route::get('/spk-cmt/{id}/download-staff-pdf', [SpkCmtController::class, 'downloadStaffPdf'])->name('spk.downloadStaffPdf');
Route::get('/spk-cmt/{id}/barcode-pdf', [SpkCmtController::class, 'downloadBarcodePdf']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('login', [AuthController::class, 'login']);
Route::get('/users/kasir', [AuthController::class, 'getKasir']);


Route::middleware('auth:api')->group(function () {

    Route::apiResource('penjahit', PenjahitController::class);
    Route::get('/spkcmt', [SpkCmtController::class, 'index']);
    Route::get('/spkcmt/{spkcmt}', [SpkCmtController::class, 'show']);


    Route::middleware(['auth:api', 'role:supervisor|super-admin'])->group(function () {
        Route::post('/spk/{spkId}/invite-staff/{staffId}', [StaffController::class, 'inviteStaff']);

        Route::get('/spk/{spkId}/check-invitation', [SpkChatController::class, 'checkInvitation']);
        Route::get('/spk/{spkId}/check-invite', [SpkChatController::class, 'checkInvite']);
        Route::get('/spk/{spkId}/staff-list', [StaffController::class, 'getStaffList']);
    });


    // Cek barcode aksesoris - accessible oleh semua role terautentikasi
    Route::get('/cek-barcode/{barcode}', [StokAksesorisController::class, 'cekBarcode']);

    Route::middleware('role:super-admin|supervisor|staff|owner|penjahit|staff_bawah|kasir')->group(function () {

        Route::apiResource('produk', ProdukController::class);
        Route::get('/produk/{id}/histories', [ProdukController::class, 'histories']);
        Route::get('/produk/{id}/download-pdf', [ProdukController::class, 'downloadPdf']);
        Route::post('/product-list/import', [ProductListController::class, 'import']);
        Route::apiResource('product-list', ProductListController::class);

        Route::apiResource('bahan', BahanController::class);
        Route::get('/stok-bahan', [StokBahanController::class, 'index']);
        Route::get('/stok-bahan/barcode/{barcode}', [StokBahanController::class, 'getByBarcode']);
        Route::get('/stok-bahan/per-bahan', [StokBahanController::class, 'stokPerBahan']);
        Route::get('/stok-bahan/dashboard-stats', [StokBahanController::class, 'getDashboardStats']);
        Route::get('/stok-bahan/summary-total-roll', [StokBahanController::class, 'getSummaryTotalRoll']);
        Route::get('/stok-bahan/warna-dengan-stok', [StokBahanController::class, 'getWarnaDenganStok']);
        Route::post('/stok-bahan/scan', [StokBahanController::class, 'scan']);

        Route::get('/stok-bahan-keluar', [StokBahanKeluarController::class, 'index']);
        Route::get('/stok-bahan-keluar/spk-cutting/{id}', [StokBahanKeluarController::class, 'getSpkCuttingDetail']);
        Route::post('/stok-bahan-keluar/scan', [StokBahanKeluarController::class, 'scanBarcode']);

        Route::post('/spkcmt', [SpkCmtController::class, 'store']);
        Route::put('/spkcmt/{spkcmt}', [SpkCmtController::class, 'update']);
        Route::patch('/spkcmt/{spkcmt}', [SpkCmtController::class, 'update']);
        Route::delete('/spkcmt/{spkcmt}', [SpkCmtController::class, 'destroy']);
        Route::get('/spk-chats/{spkId}', [SpkChatController::class, 'index']);
        Route::post('/send-message', [SpkChatController::class, 'sendMessage']);
        Route::post('/invite-staff/{staffId}', [StaffController::class, 'inviteStaff']);
        Route::get('/kemampuan-cmt', [SpkCmtController::class, 'getKemampuanCmt']);
        Route::get('/spk-cmt/available-sources', [SpkCmtController::class, 'getAvailableSources']);
        Route::get('/spk-cmt/status-count', [SpkCmtController::class, 'getStatusCount']);
        Route::get('/spkcmt/pendapatan', [SpkCmtController::class, 'pendapatanSummary']);
        Route::patch('/spk-cmt/{id}/status', [SpkCmtController::class, 'updateStatus']);

        Route::get('/spk-chats/{chatId}/messages', [SpkChatController::class, 'getChatMessages']);
        Route::post('/spk-chats/{chatId}/mark-as-read', [SpkChatController::class, 'markAsRead']);

        Route::get('/spk-chats/{chatId}/readers', [SpkChatController::class, 'getChatReaders']);

        Route::get('/notifications', [NotificationController::class, 'getNotifications']);
        Route::get('/notifications/unread', [NotificationController::class, 'getUnreadNotifications']);
        Route::post('/notifications/mark-as-read', [NotificationController::class, 'markAsRead']);
        Route::get('/kinerja-cmt/kategori-count', [SpkCmtController::class, 'getKategoriCount']);
        Route::get('/kinerja-cmt/kategori-count-by-penjahit', [SpkCmtController::class, 'getKategoriCountByPenjahit']);
        Route::get('/debug-deadlines', [SpkCmtController::class, 'debugDeadlines']);
        Route::get('/kinerja-cmt', [SpkCmtController::class, 'getKinerjaCmt']);
        Route::resource('spkcmt.warna', WarnaController::class)->shallow();
        Route::put('/spk/{id}/deadline', [SpkCmtController::class, 'updateDeadline']);
        Route::get('/log-deadlines', [SpkCmtController::class, 'getAllLogDeadlines']);
        Route::get('/spk/{id}/log-deadline', [SpkCmtController::class, 'getLogDeadline']);
        Route::get('/log-status', [SpkCmtController::class, 'getAllLogStatus']);
        Route::put('/spk/{id}/status', [SpkCmtController::class, 'updateStatus']);
        Route::get('/spk/{id}/log-status', [SpkCmtController::class, 'getLogStatus']);
        Route::get('/spk-cmt/{id}/warna', [SpkCmtController::class, 'getWarna']);
        Route::get('/kode-seri-belum-dikerjakan', [\App\Http\Controllers\KodeSeriBelumDikerjakanOptimizedController::class, 'index']);


        Route::post('/pengiriman', [PengirimanController::class, 'store']);
        Route::get('/pengiriman', [PengirimanController::class, 'index']);
        Route::get('/pengiriman/{id}', [PengirimanController::class, 'show']);
        Route::post('/pengiriman/petugas-bawah', [PengirimanController::class, 'storePetugasBawah']);
        Route::put('/pengiriman/petugas-atas/{id_pengiriman}', [PengirimanController::class, 'updatePetugasAtas']);
        Route::put('/pengiriman/{id_pengiriman}/status-claim', [PengirimanController::class, 'updateStatusClaim']);
        Route::delete('/pengiriman/{id_pengiriman}', [PengirimanController::class, 'destroy']);
        Route::post('/petugas-d-verif/{id}/update-pembayaran', [PetugasDVerifController::class, 'updatePembayaran']);



        Route::resource('cashboan', CashboanController::class);
        Route::resource('log-pembayaran-cashboan', LogPembayaranCashbonController::class);
        Route::post('/log-pembayaran-cashboan/{id_cashboan}', [LogPembayaranCashbonController::class, 'createLogPembayaran']);
        Route::get('/log-pembayaran-cashboan/{id_cashboan}', [LogPembayaranCashbonController::class, 'show']);
        Route::post('/cashboan/tambah', [CashboanController::class, 'tambahCashboan']);
        Route::post('/cashboan/tambah/{id_penjahit}', [CashboanController::class, 'tambahCashboanLama']);
        Route::get('/cashboan/history/{id}', [CashboanController::class, 'getHistoryByCashboanId']);

        Route::resource('hutang', HutangController::class);
        Route::post('/hutang/tambah', [HutangController::class, 'tambahHutang']);
        Route::post('/hutang/tambah/{id_penjahit}', [HutangController::class, 'tambahHutangLama']);
        Route::get('/history/{id}', [HutangController::class, 'getHistoryByHutangId']);
        Route::get('/hutang/{id_hutang}/hitung-potongan', [HutangController::class, 'hitungPotongan']);

        Route::resource('log-pembayaran-hutang', LogPembayaranHutangController::class);
        Route::post('/log-pembayaran-hutang/{id_hutang}', [LogPembayaranHutangController::class, 'createLogPembayaran']);
        Route::get('/log-pembayaran-hutang/{id_hutang}', [LogPembayaranHutangController::class, 'show']);


        Route::get('/pendapatan/history', [PendapatanController::class, 'history']); // Route untuk history pendapatan
        Route::get('/pendapatan', [PendapatanController::class, 'index']); // Untuk melihat daftar pendapatan belum dibayar dengan filter periode
        Route::get('pendapatan/{id}/pengiriman', [PendapatanController::class, 'showPengiriman']);
        Route::get('/pendapatan/{id}/download-nota', [PendapatanController::class, 'downloadNota']);
        Route::get('/pendapatan/{id}/download-invoice', [PendapatanController::class, 'downloadInvoice']);
        Route::post('/pendapatan/download-invoice-preview', [PendapatanController::class, 'downloadInvoicePreview']);
        Route::get('/penjahit-list', [PendapatanController::class, 'getPenjahitList']);
        Route::get('/pendapatan/claim-belum-dibayar/{id_penjahit}', [PendapatanController::class, 'getClaimBelumDibayar']);
        Route::post('/pendapatan', [PendapatanController::class, 'tambahPendapatan']); // Untuk bayar pendapatan
        Route::post('/simulasi-pendapatan', [PendapatanController::class, 'simulasiPendapatan']);
        Route::post('/pendapatan/create-invoice', [PendapatanController::class, 'createInvoice']); // Buat invoice baru
        Route::get('/pendapatan/{id}/invoice', [PendapatanController::class, 'getInvoice']); // Get detail invoice untuk edit
        Route::put('/pendapatan/{id}/update-invoice', [PendapatanController::class, 'updateInvoice']); // Update invoice
        Route::put('/pendapatan/{id}/bayar', [PendapatanController::class, 'bayarInvoice']); // Bayar invoice

        Route::resource('laporancmt', LaporanCmtController::class);
        Route::get('/cmt/data-dikerjakan-pengiriman', [LaporanCmtController::class, 'getDataDikerjakanDanPengiriman']);
        Route::get('/cmt/data-dikerjakan-pengiriman/export/excel', [LaporanCmtController::class, 'exportExcel']);

        Route::apiResource('aksesoris', AksesorisController::class);
        Route::post('/aksesoris/{id}/reset-stok', [AksesorisController::class, 'resetStok']);
        Route::get('/aksesoris/options', function () {
            dd('Options route is working!');
        });




        Route::apiResource('pembelian-aksesoris-a', PembelianAController::class);
        Route::apiResource('pembelian-aksesoris-b', PembelianBController::class);
        Route::get('/stok-aksesoris/pembelian-b/{id}', [StokAksesorisController::class, 'showByPembelianB']);
        Route::apiResource('petugas-c', PetugasCController::class);
        Route::apiResource('verifikasi-aksesoris', PetugasDVerifController::class);
        Route::apiResource('stok-aksesoris', StokAksesorisController::class);
        Route::get('/barcode-download/{pembelianB}', [PembelianBController::class, 'downloadBarcodes'])->name('barcode.download');
        Route::get('/detail-pesanan-aksesoris', [PetugasDVerifController::class, 'getDetailPesananAksesoris']);
        // Route dipindahkan ke level auth:api (lihat di atas)


        // Route spesifik harus didefinisikan SEBELUM apiResource agar tidak tertangkap oleh route resource
        Route::post('/spk_cutting/generate-number', [SpkCuttingController::class, 'getGeneratedSpkNumber']);
        Route::get('/spk_cutting/{id}/download-qr', [SpkCuttingController::class, 'downloadQrCode']);
        Route::get('/spk_cutting/export/excel', [SpkCuttingController::class, 'exportExcel']);
        Route::patch('/spk-cutting/{id}/status', [SpkCuttingController::class, 'updateStatus']);
        Route::apiResource('spk_cutting', SpkCuttingController::class);
        Route::apiResource('tukang_cutting', TukangCuttingController::class);
        Route::apiResource('tukang_pola', TukangPolaController::class);
        Route::apiResource('tukang-sample', TukangSampleController::class);
        Route::apiResource('spk-sample', SpkSampleController::class);
        Route::get('/hasil_cutting/detail-spk', [HasilCuttingController::class, 'getSpkCuttingDetail']);
        Route::apiResource('hasil_cutting', HasilCuttingController::class);
        Route::get('/hasil-cutting/history-by-produk', [HasilCuttingController::class, 'historyGroupedByProduk']);
        Route::apiResource('markeran_produk', MarkeranProdukController::class);
        Route::get(
            '/hasil-cutting/laporan-periode',
            [HasilCuttingController::class, 'laporanPeriodePerHari']
        );
        Route::get(
            '/laporan-daily-produksi',
            [LaporanDailyProduksiController::class, 'index']
        );


        Route::post('/hutang/tambah_cutting', [HutangCuttingController::class, 'tambahHutangCutting']);
        Route::get('/hutang_cutting', [HutangCuttingController::class, 'index']);
        Route::post('/hutang_cutting/tambah/{tukangCuttingId}', [HutangCuttingController::class, 'tambahHutangLama']);
        Route::get('/history_cutting/{id}', [HutangCuttingController::class, 'getHistoryByHutangId']);
       
        Route::get(
            '/spk-cutting-distribusi/{id}/history',
            [SpkDistribusiHistoryController::class, 'history']
        );

        Route::get(
            '/spk-cutting-distribusi/history',
            [SpkDistribusiHistoryController::class, 'historyAll']
        );
                

        Route::post('/cashboan/tambah_cutting', [CashboanCuttingController::class, 'tambahCashboanCutting']);
        Route::get('/cashboan_cutting', [CashboanCuttingController::class, 'index']);
        Route::post('/cashboan_cutting/tambah/{id}', [CashboanCuttingController::class, 'tambahCashboanLama']);
        Route::get('/history_cashboan_cutting/{id}', [CashboanCuttingController::class, 'getHistoryByCashboanId']);

        Route::get('/pendapatan/mingguan/cutting', [PendapatanCuttingController::class, 'getPendapatanMingguIni']);
        Route::post('/pendapatan/simulasi/cutting', [PendapatanCuttingController::class, 'simulasiPendapatanCutting']);
        Route::post('/pendapatan/cutting', [PendapatanCuttingController::class, 'tambahPendapatanCutting']);
        Route::get('/pendapatan/cutting', [PendapatanCuttingController::class, 'history']);
        Route::get('pendapatan/{id}/cutting', [PendapatanCuttingController::class, 'showPengiriman']);
        Route::get('/pendapatan/cutting/{id}/download-invoice', [PendapatanCuttingController::class, 'downloadInvoice']);
        Route::post('/pendapatan/cutting/download-invoice-preview', [PendapatanCuttingController::class, 'downloadInvoicePreview']);


        Route::apiResource('tukang-jasa', TukangJasaController::class);
        
        Route::get('/SpkJasa/dashboard', [SpkJasaController::class, 'dashboard']);
        Route::get('/SpkJasa/statistics', [SpkJasaController::class, 'statistics']);
        Route::get('/SpkJasa/available-distributions', [SpkJasaController::class, 'getAvailableDistributions']);
        Route::apiResource('SpkJasa', SpkJasaController::class);
        Route::apiResource('HasilJasa', HasilJasaController::class);
        Route::post('/hutang/tambah_jasa', [HutangJasaController::class, 'tambahHutangJasa']);
        Route::post('/cashboan/tambah_jasa', [CashboanJasaController::class, 'tambahCashboanJasa']);
        Route::get('/cashboan_jasa', [CashboanJasaController::class, 'index']);
        Route::get('/hutang_jasa', [HutangJasaController::class, 'index']);
        Route::post('/hutang_jasa/tambah/{id}', [HutangJasaController::class, 'tambahHutangLama']);
        Route::post('/cashboan_jasa/tambah/{id}', [CashboanJasaController::class, 'tambahCashboanLama']);
        Route::get('/history_jasa/{id}', [HutangJasaController::class, 'getHistoryByHutangId']);
        Route::get('/history_cashboan_jasa/{id}', [CashboanJasaController::class, 'getHistoryByCashboanId']);
        Route::get('preview/{spk_cutting_distribusi_id}', [SpkJasaController::class, 'preview']);
        Route::get('/pendapatan/mingguan/jasa', [PendapatanJasaController::class, 'getPendapatanMingguIni']);
        Route::post('/pendapatan/simulasi/jasa', [PendapatanJasaController::class, 'simulasiPendapatanJasa']);
        Route::post('/pendapatan/jasa', [PendapatanJasaController::class, 'tambahPendapatanJasa']);
        Route::get('/pendapatan/jasa/history', [PendapatanJasaController::class, 'history']);
        Route::get('pendapatan/{id}/jasa', [PendapatanJasaController::class, 'showPengiriman']);
        Route::get('/pendapatan/jasa', [PendapatanJasaController::class, 'index']);
        Route::get('/pendapatan/jasa/{id}/download-invoice', [PendapatanJasaController::class, 'downloadInvoice']);
        Route::post('/pendapatan/jasa/download-invoice-preview', [PendapatanJasaController::class, 'downloadInvoicePreview']);
        Route::patch(
            '/spk-jasa/{id}/status-pengambilan',
            [SpkJasaController::class, 'updateStatusPengambilan']
        );
        Route::get('/spk-jasa/{id}', [SpkJasaController::class, 'show']);
        Route::put('/spk-jasa/{id}', [SpkJasaController::class, 'update']);
        Route::apiResource('gudang', GudangController::class);

        Route::get('/orders/tracking/{trackingNumber}', [OrderController::class, 'showByTracking']);
        Route::post('/orders/scan/{trackingNumber}', [OrderController::class, 'validateScan']);
        Route::get('/packing-belum-barcode/orders/tracking/{trackingNumber}', [PackingBelumBarcodeController::class, 'showByTracking']);
        Route::post('/packing-belum-barcode/orders/submit', [PackingBelumBarcodeController::class, 'submit']);
        Route::get('/packing-random/orders/tracking/{trackingNumber}', [PackingRandomController::class, 'showByTracking']);
        Route::get('/packing-random/sku/{sku}', [PackingRandomController::class, 'resolveSku']);
        Route::post('/packing-random/orders/scan/{trackingNumber}', [PackingRandomController::class, 'validateScan']);
        Route::get('/packing-no-data-ginee/check/{trackingNumber}', [PackingNoDataGineeController::class, 'check']);
        Route::post('/packing-no-data-ginee/submit', [PackingNoDataGineeController::class, 'submit']);


        Route::post('/ginee/list-orders', [GineeSyncController::class, 'listOrders']);
        Route::post('/ginee/list-orders/detail', [GineeSyncController::class, 'orderDetails']);
        Route::post('/ginee/orders/sync', [GineeSyncController::class, 'syncRecentOrders']);

        Route::get('/orders/logs', [OrderController::class, 'getAllLogs']);
        Route::get('/orders/logs/{sourceType}/{sourceId}/detail', [OrderController::class, 'getLogDetail']);
        Route::post('/orders/summary', [OrderController::class, 'getSummaryReport']);

        Route::get('/ginee/test-order/{orderId}', [GineeSyncController::class, 'testSingleOrder']);
        Route::get('/orders/logs/export', [OrderController::class, 'exportLogsToExcel']);
        Route::post('/orders/logs/export', [OrderController::class, 'requestLogsExport']);
        Route::get('/orders/logs/export/{exportId}', [OrderController::class, 'showLogsExport']);
        Route::get('/orders/logs/export/{exportId}/download', [OrderController::class, 'downloadLogsExport']);

        Route::get('/pabrik', [PabrikController::class, 'index']);
        Route::post('/pabrik', [PabrikController::class, 'store']);

        Route::get('/bahan', [BahanController::class, 'index']);
        Route::post('/bahan', [BahanController::class, 'store']);
        Route::get('/bahan/{id}', [BahanController::class, 'show']);
        Route::put('/bahan/{id}', [BahanController::class, 'update']);
        Route::delete('/bahan/{id}', [BahanController::class, 'destroy']);

        Route::get('/pembelian-bahan', [PembelianBahanController::class, 'index']);
        Route::post('/pembelian-bahan', [PembelianBahanController::class, 'store']);
        Route::get('/pembelian-bahan/{id}', [PembelianBahanController::class, 'show']);
        Route::put('/pembelian-bahan/{id}', [PembelianBahanController::class, 'update']);
        Route::get('/pembelian-bahan/{id}/download-barcode', [PembelianBahanController::class, 'downloadBarcodes']);
        Route::get('/pembelian-bahan/{id}/barcodes-debug', [PembelianBahanController::class, 'barcodesDebug']);
        Route::post('/pembelian-bahan/{id}/generate-barcodes', [PembelianBahanController::class, 'generateBarcodes']);
        Route::get('/pembelian-bahan/scan-barcode/{barcode}', [PembelianBahanController::class, 'getRollByBarcode']);
        Route::put('/pembelian-bahan/scan-barcode/{barcode}/update-berat', [PembelianBahanController::class, 'updateBeratByBarcode']);
        Route::get('/pembelian-bahan/rolls-zero-berat', [PembelianBahanController::class, 'getRollsWithZeroBerat']);
        
        // Return/Refund routes
        Route::get('/return-bahan', [ReturnBahanController::class, 'index']);
        Route::post('/return-bahan', [ReturnBahanController::class, 'store']);
        Route::put('/return-bahan/{id}/status', [ReturnBahanController::class, 'updateStatus']);



        Route::get('/seri', [SeriController::class, 'index']);
        Route::post('/seri', [SeriController::class, 'store']);
        Route::get('/seri/{id}', [SeriController::class, 'show']);
        Route::get('/seri/{id}/download', [SeriController::class, 'download']);


        Route::apiResource('pabrik', PabrikController::class);

        Route::get('/spk-cutting-distribusi', [SpkCuttingDistribusiController::class, 'index']);
        Route::get('/spk-cutting-distribusi/{id}', [SpkCuttingDistribusiController::class, 'show']);
        Route::get('/spk-jasa/dropdown', [SpkJasaController::class, 'getForDropdown']);
        Route::get('/spk-cmt/spkjasa-dropdown', [SpkCmtController::class, 'getSpkJasaForDropdown']);
        Route::post('logout', [AuthController::class, 'logout']);


        Route::get('/spk-bahan/authorize', [SpkBahanController::class, 'authorizeAccess'])->middleware('throttle:spk-bahan-read');
        Route::get('/spk-bahan', [SpkBahanController::class, 'index'])->middleware('throttle:spk-bahan-read');
        Route::post('/spk-bahan', [SpkBahanController::class, 'store'])->middleware('throttle:spk-bahan-write');


        Route::prefix('pendapatan-pabrik')->group(function () {
        // 1ï¸âƒ£ List pabrik + total hutang
        Route::get('/', [PendapatanPabrikController::class, 'index']);

        // 4ï¸âƒ£ Riwayat Pendapatan
        Route::get('/history/all', [PendapatanPabrikController::class, 'history']);

        // 2ï¸âƒ£ Detail pembelian belum dibayar per pabrik
        Route::get('/{pabrikId}', [PendapatanPabrikController::class, 'show']);

        // 3ï¸âƒ£ Proses bayar
        Route::post('/', [PendapatanPabrikController::class, 'store']);
        });

        Route::get('/skus', [SkuController::class, 'index']);
        Route::post('/skus', [SkuController::class, 'store']);
        Route::patch('/skus/{id}', [SkuController::class, 'update']);

        Route::get('/gudang-produk', [GudangProdukController::class, 'index']);
        Route::get('/gudang-produk/rak-options', [GudangProdukController::class, 'getRakOptions']);
        Route::post('/gudang-produk', [GudangProdukController::class, 'store']);
        Route::post('/gudang-produk/{id}/verify', [GudangProdukController::class, 'verify']);
        Route::get('/gudang-produk-workspace', [GudangProdukWorkspaceController::class, 'index']);
        Route::post('/gudang-produk-workspace/layouts', [GudangProdukWorkspaceController::class, 'storeLayout']);
        Route::put('/gudang-produk-workspace/layouts/{layoutUid}', [GudangProdukWorkspaceController::class, 'updateLayout']);
        Route::post('/gudang-produk-workspace/placements', [GudangProdukWorkspaceController::class, 'placeStock']);
        Route::post('/gudang-produk-workspace/mutations', [GudangProdukWorkspaceController::class, 'mutateStock']);

        Route::get('/stok-gudang-produk', [StokGudangProdukController::class, 'index']);

        Route::get('/picking-queue', [OrderController::class, 'pickingQueue']);
        Route::post('/orders/{id}/mark-picked', [OrderController::class, 'markPicked']);
        Route::post('/orders/batch-pick', [OrderController::class, 'batchMarkPicked']);

        // Quality Control - Lolos (scan barcode)
        Route::get('/qc-lolos', [QcLolosController::class, 'index']);
        Route::get('/qc-lolos/report', [QcLolosController::class, 'report']);
        Route::post('/qc-lolos/scan', [QcLolosController::class, 'scan']);
        Route::delete('/qc-lolos/undo', [QcLolosController::class, 'destroy']);

        // Quality Control - Reject (manual input)
        Route::get('/qc-reject', [QcRejectController::class, 'index']);
        Route::post('/qc-reject', [QcRejectController::class, 'store']);

        // Quality Control - Legacy (form input)
        Route::get('/quality-control', [QualityControlController::class, 'index']);
        Route::post('/quality-control', [QualityControlController::class, 'store']);

        // Seri list (untuk dropdown di QC)
        Route::get('/seri-list', [SeriController::class, 'index']);
        // Sample Management
        Route::apiResource('tukang-sample', TukangSampleController::class);
        Route::apiResource('spk-sample', SpkSampleController::class);
        Route::post('spk-sample/{id}/assign-tukang', [SpkSampleController::class, 'assignTukang']);
        Route::patch('spk-sample/{id}/status-proses', [SpkSampleController::class, 'updateStatusProses']);
        Route::patch('spk-sample/{id}/tahap-proses', [SpkSampleController::class, 'updateTahapProses']);
    });
});

// PUBLIC TEST ROUTE (No Auth) - Temporary
Route::get('/test-barcode-public', function () {
    return response()->json([
        'message' => 'PUBLIC ROUTE WORKS!',
        'timestamp' => now()->format('Y-m-d H:i:s'),
        'server' => 'Laragon Apache'
    ]);
});


