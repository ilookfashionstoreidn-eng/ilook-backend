<?php
$spk = \App\Models\SpkCmt::where('nomor_seri', 'EK-3299-A')->first();
if ($spk) {
    echo 'SPK ID: ' . $spk->id_spk . PHP_EOL;
    echo 'Total Target: ' . \App\Models\SpkCmtWarna::where('spk_cmt_id', $spk->id_spk)->sum('qty') . PHP_EOL;
    echo 'Total Pengiriman: ' . \App\Models\Pengiriman::where('id_spk', $spk->id_spk)->sum('total_barang_dikirim') . PHP_EOL;
    $pengirimans = \App\Models\Pengiriman::where('id_spk', $spk->id_spk)->get();
    foreach ($pengirimans as $p) {
        echo 'Pengiriman ID: ' . $p->id_pengiriman . ', Sisa: ' . $p->sisa_barang . ', Qty: ' . $p->total_barang_dikirim . PHP_EOL;
        $warnas = \App\Models\PengirimanWarna::where('id_pengiriman', $p->id_pengiriman)->get();
        foreach ($warnas as $w) {
            echo '  Warna: ' . $w->warna . ', Qty: ' . $w->jumlah_dikirim . PHP_EOL;
        }
    }
} else {
    echo 'SPK not found';
}
