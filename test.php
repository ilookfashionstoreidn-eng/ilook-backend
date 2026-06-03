<?php
$spkcmt = App\Models\SpkCmt::with(['items.sku'])->latest()->first();

print_r($spkcmt->items->toArray());
