<?php
$pengirimans = \App\Models\Pengiriman::all();
foreach ($pengirimans as $p) {
    echo 'Pengiriman ID: ' . $p->id_pengiriman . ', id_spk: ' . ($p->id_spk ?? 'null') . ', no_seri: ' . $p->no_seri_pengiriman . PHP_EOL;
}
