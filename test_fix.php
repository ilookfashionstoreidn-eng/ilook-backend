<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$entry = \App\Models\GudangProdukWorkspaceStockEntry::find(56);
if ($entry) {
    echo "Before: ID: {$entry->id} Qty: {$entry->qty}\n";
    $entry->qty = 0;
    // Simulate what the controller does: it doesn't delete anymore, it saves
    $entry->save();
} else {
    echo "Entry 56 not found!\n";
}

$after = \App\Models\GudangProdukWorkspaceStockEntry::find(56);
if ($after) {
    echo "After: ID: {$after->id} Qty: {$after->qty}\n";
} else {
    echo "Entry 56 was deleted!\n";
}
