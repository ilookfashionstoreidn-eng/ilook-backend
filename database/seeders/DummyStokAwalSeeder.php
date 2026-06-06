<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\GudangProdukActivityLog;
use App\Models\Sku;

class DummyStokAwalSeeder extends Seeder
{
    public function run()
    {
        $sku = Sku::first();
        if (!$sku) return;

        $slotId = 'rack_ulnam3_mnsjjrva:1';
        $slotId2 = 'rack_ulnam3_mnsjjrva:2';

        GudangProdukActivityLog::create([
            'type' => 'placement',
            'sku_id' => $sku->id,
            'from_slot_id' => null,
            'to_slot_id' => $slotId,
            'qty' => 5,
            'notes' => 'Stok awal | Kode seri: S.1.1, S.1.2, M.1.1, M.1.2, M.1.3',
            'created_by' => 9,
            'created_at' => now()->subDays(1),
            'updated_at' => now()->subDays(1),
        ]);

        GudangProdukActivityLog::create([
            'type' => 'placement',
            'sku_id' => $sku->id,
            'from_slot_id' => null,
            'to_slot_id' => $slotId2,
            'qty' => 3,
            'notes' => 'Stok awal | Kode seri: L.1.1, L.1.2, XL.1.1',
            'created_by' => 9,
            'created_at' => now()->subHours(5),
            'updated_at' => now()->subHours(5),
        ]);
        
        // Example for another SKU
        $sku2 = Sku::skip(1)->first() ?? clone $sku;
        if ($sku2->id !== $sku->id) {
            GudangProdukActivityLog::create([
                'type' => 'placement',
                'sku_id' => $sku2->id,
                'from_slot_id' => null,
                'to_slot_id' => $slotId,
                'qty' => 12,
                'notes' => 'Stok awal | Kode seri: A.1, A.2, B.1, B.2, B.3, B.4, C.1, C.2, C.3, C.4, C.5, C.6',
                'created_by' => 9,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
             GudangProdukActivityLog::create([
                'type' => 'placement',
                'sku_id' => $sku->id,
                'from_slot_id' => null,
                'to_slot_id' => 'rack_ulnam3_mnsjjrva:3',
                'qty' => 12,
                'notes' => 'Stok awal | Kode seri: A.1, A.2, B.1, B.2, B.3, B.4, C.1, C.2, C.3, C.4, C.5, C.6',
                'created_by' => 9,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
