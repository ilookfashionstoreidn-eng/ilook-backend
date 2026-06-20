<?php
require 'vendor/autoload.php';
require 'bootstrap/app.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;

$bahan = App\Models\Bahan::where('nama_bahan', 'like', '%VISKIN HYGET%')->first();
if (!$bahan) {
    echo "Bahan not found\n";
} else {
    echo "Bahan ID: " . $bahan->id . "\n";
    $req = Request::create('/api/stok-bahan/warna-dengan-stok', 'GET', ['bahan_id' => $bahan->id]);
    $res = app()->handle($req);
    echo $res->getContent() . "\n";
}
