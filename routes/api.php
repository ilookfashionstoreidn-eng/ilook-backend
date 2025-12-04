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
    use App\Http\Controllers\AksesorisController;
    use App\Http\Controllers\PembelianAController;
    use App\Http\Controllers\PembelianBController;
    use App\Http\Controllers\StokAksesorisController;
    use App\Http\Controllers\PetugasCController;
    use App\Http\Controllers\PetugasDVerifController;
    use App\Http\Controllers\SpkCuttingController;
    use App\Http\Controllers\TukangCuttingController;
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
    use App\Http\Controllers\SeriController;






Route::get('/spkcmt', [SpkCmtController::class, 'index']); 
Route::get('/', function () {
    return response()->json(['message' => 'API is working!']);
});

Route::get('/some-endpoint', function () {
    return 'Hello, world!';
});

Route::get('/spk-cmt/{id}/download-pdf', [SpkCmtController::class, 'downloadPdf']);
Route::get('/spk-cmt/{id}/download-staff-pdf', [SpkCmtController::class, 'downloadStaffPdf'])->name('spk.downloadStaffPdf');
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


        Route::middleware('role:super-admin|supervisor|staff|owner|penjahit|staff_bawah|kasir')->group(function () {

            Route::apiResource('produk', ProdukController::class);

            Route::post('/spkcmt', [SpkCmtController::class, 'store']);
            Route::put('/spkcmt/{spkcmt}', [SpkCmtController::class, 'update']);
            Route::patch('/spkcmt/{spkcmt}', [SpkCmtController::class, 'update']);
            Route::delete('/spkcmt/{spkcmt}', [SpkCmtController::class, 'destroy']);
            Route::get('/spk-chats/{spkId}', [SpkChatController::class, 'index']);   
            Route::post('/send-message', [SpkChatController::class, 'sendMessage']);
            Route::post('/invite-staff/{staffId}', [StaffController::class, 'inviteStaff']);
            Route::get('/kemampuan-cmt', [SpkCmtController::class, 'getKemampuanCmt']);

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


            Route::post('/pengiriman', [PengirimanController::class, 'store']);
            Route::get('/pengiriman', [PengirimanController::class, 'index']);
            Route::get('/pengiriman/{id}', [PengirimanController::class, 'show']);
            Route::post('/pengiriman/petugas-bawah', [PengirimanController::class, 'storePetugasBawah']);
            Route::put('/pengiriman/petugas-atas/{id_pengiriman}', [PengirimanController::class, 'updatePetugasAtas']);
            Route::delete('/pengiriman/{id_pengiriman}', [PengirimanController::class, 'destroy']);



            Route::resource('cashboan', CashboanController::class);
            Route::resource('log-pembayaran-cashboan', LogPembayaranCashbonController::class);
            Route::post('/log-pembayaran-cashboan/{id_cashboan}', [LogPembayaranCashbonController::class, 'createLogPembayaran']);
            Route::get('/log-pembayaran-cashboan/{id_cashboan}', [LogPembayaranCashbonController::class, 'show']);
            Route::post('/cashboan/tambah', [CashboanController::class, 'tambahCashboan']);
            Route::post('/cashboan/tambah/{id_cashboan}', [CashboanController::class, 'tambahCashboanLama']);
            Route::get('/cashboan/history/{id}', [CashboanController::class, 'getHistoryByCashboanId']);

            Route::resource('hutang', HutangController::class);
            Route::post('/hutang/tambah', [HutangController::class, 'tambahHutang']);
            Route::post('/hutang/tambah/{id_hutang}', [HutangController::class, 'tambahHutangLama']);
            Route::get('/history/{id}', [HutangController::class, 'getHistoryByHutangId']);
            Route::get('/hutang/{id_hutang}/hitung-potongan', [HutangController::class, 'hitungPotongan']);

            Route::resource('log-pembayaran-hutang', LogPembayaranHutangController::class);
            Route::post('/log-pembayaran-hutang/{id_hutang}', [LogPembayaranHutangController::class, 'createLogPembayaran']);
            Route::get('/log-pembayaran-hutang/{id_hutang}', [LogPembayaranHutangController::class, 'show']);


            Route::get('/pendapatan', [PendapatanController::class, 'index']); // Untuk melihat daftar pendapatan atau form
            Route::post('pendapatan/calculate', [PendapatanController::class, 'calculate']);
            Route::post('/pendapatan', [PendapatanController::class, 'store']);
            Route::get('pendapatan/{id}/pengiriman', [PendapatanController::class, 'showPengiriman']);
            Route::get('/pendapatan/{id}/download-nota', [PendapatanController::class, 'downloadNota']);
            Route::get('/penjahit-list', [PendapatanController::class, 'getPenjahitList']);
            Route::get('/pendapatan/mingguan', [PendapatanController::class, 'getPendapatanMingguIni']);
            Route::post('/bayar-pendapatan', [PendapatanController::class, 'tambahPendapatan']);
            Route::post('/simulasi-pendapatan', [PendapatanController::class, 'simulasiPendapatan']);

            Route::resource('laporancmt', LaporanCmtController::class);
        
            Route::apiResource('aksesoris', AksesorisController::class);
            Route::put('/{id}', [AksesorisController::class, 'update']); 
            Route::get('/aksesoris/options', function() {
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
            Route::get('/cek-barcode/{barcode}', [StokAksesorisController::class, 'cekBarcode']);


            Route::apiResource('spk_cutting', SpkCuttingController::class);
            Route::apiResource('tukang_cutting', TukangCuttingController::class);
            Route::apiResource('hasil_cutting', HasilCuttingController::class);
            Route::apiResource('markeran_produk', MarkeranProdukController::class);
            Route::get('/spk_cutting/{id}', [SpkCuttingController::class, 'show']);

            Route::post('/hutang/tambah_cutting', [HutangCuttingController::class, 'tambahHutangCutting']);
            Route::get('/hutang_cutting', [HutangCuttingController::class, 'index']);
            Route::post('/hutang_cutting/tambah/{id}', [HutangCuttingController::class, 'tambahHutangLama']);
            Route::get('/history_cutting/{id}', [HutangCuttingController::class, 'getHistoryByHutangId']);


            Route::post('/cashboan/tambah_cutting', [CashboanCuttingController::class, 'tambahCashboanCutting']);
            Route::get('/cashboan_cutting', [CashboanCuttingController::class, 'index']);
            Route::post('/cashboan_cutting/tambah/{id}', [CashboanCuttingController::class, 'tambahCashboanLama']);
            Route::get('/history_cashboan_cutting/{id}', [CashboanCuttingController::class, 'getHistoryByCashboanId']);

            Route::get('/pendapatan/mingguan/cutting', [PendapatanCuttingController::class, 'getPendapatanMingguIni']);
            Route::post('/pendapatan/simulasi/cutting', [PendapatanCuttingController::class, 'simulasiPendapatanCutting']);
            Route::post('/pendapatan/cutting', [PendapatanCuttingController::class, 'tambahPendapatanCutting']);
            Route::get('/pendapatan/cutting', [PendapatanCuttingController::class, 'index']);
            Route::get('pendapatan/{id}/cutting', [PendapatanCuttingController::class, 'showPengiriman']);
    

            Route::apiResource('tukang-jasa', TukangJasaController::class);

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
        
            Route::get('/pendapatan/mingguan/jasa', [PendapatanJasaController::class, 'getPendapatanMingguIni']);
            Route::post('/pendapatan/simulasi/jasa', [PendapatanJasaController::class, 'simulasiPendapatanCutting']);
            Route::post('/pendapatan/jasa', [PendapatanJasaController::class, 'tambahPendapatanJasa']);
            Route::get('pendapatan/{id}/jasa', [PendapatanJasaController::class, 'showPengiriman']);
            Route::get('/pendapatan/jasa', [PendapatanJasaController::class, 'index']);
        
            Route::apiResource('gudang', GudangController::class);

            Route::get('/orders/tracking/{trackingNumber}', [OrderController::class, 'showByTracking']);
            Route::post('/orders/scan/{trackingNumber}', [OrderController::class, 'validateScan']);
            
            
            Route::post('/ginee/list-orders', [GineeSyncController::class, 'listOrders']);
            Route::post('/ginee/list-orders/detail', [GineeSyncController::class, 'orderDetails']);
            Route::post('/ginee/orders/sync', [GineeSyncController::class, 'syncRecentOrders']);

            Route::get('/orders/logs', [OrderController::class, 'getAllLogs']);
            Route::post('/orders/summary', [OrderController::class, 'getSummaryReport']);
            
            Route::get('/ginee/test-order/{orderId}', [GineeSyncController::class, 'testSingleOrder']);
            Route::get('/orders/logs/export', [OrderController::class, 'exportLogsToExcel']);

            Route::get('/pabrik', [PabrikController::class, 'index']);
            Route::post('/pabrik', [PabrikController::class, 'store']);
            Route::get('/pembelian-bahan', [PembelianBahanController::class, 'index']);
            Route::post('/pembelian-bahan', [PembelianBahanController::class, 'store']);

            Route::get('/seri', [SeriController::class, 'index']);
            Route::post('/seri', [SeriController::class, 'store']);
            Route::get('/seri/{id}', [SeriController::class, 'show']); 
            Route::get('/seri/{id}/download', [SeriController::class, 'download']);

            
        });

   
});
