<?php
$pengirimans = \App\Models\Pengiriman::whereNull('id_spk')->get();
foreach ($pengirimans as $p) {
    if ($p->no_seri_pengiriman) {
        $parts = explode('.', $p->no_seri_pengiriman);
        if (count($parts) >= 2) {
            array_pop($parts); // remove penjahit ID
            $noSeri = implode('.', $parts);
            $spk = \App\Models\SpkCmt::where('nomor_seri', $noSeri)->first();
            if ($spk) {
                $p->id_spk = $spk->id_spk;
                
                // Recalculate Sisa Barang
                $totalTarget = \DB::table('spk_cmt_warna')->where('spk_cmt_id', $spk->id_spk)->sum('qty');
                $totalKirimSebelum = \DB::table('pengiriman')->where('id_spk', $spk->id_spk)->where('id_pengiriman', '<', $p->id_pengiriman)->sum('total_barang_dikirim');
                $sisa = max(0, $totalTarget - $totalKirimSebelum - $p->total_barang_dikirim);
                $p->sisa_barang = $sisa;
                
                $p->save();
                echo 'Fixed Pengiriman ID ' . $p->id_pengiriman . ' with id_spk = ' . $spk->id_spk . ' and sisa_barang = ' . $sisa . PHP_EOL;
            } else {
                echo 'SPK not found for no_seri: ' . $noSeri . PHP_EOL;
            }
        }
    }
}
