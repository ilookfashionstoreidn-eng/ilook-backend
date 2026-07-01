<?php

namespace App\Http\Controllers;

use App\Models\Sku;
use App\Models\StokGudangProduk;
use App\Models\GudangProdukSample;
use App\Models\GudangProdukActivityLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GudangProdukSampleController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status'); // dipinjam, dikembalikan, or all

        $query = GudangProdukSample::with(['sku', 'creator'])->orderBy('created_at', 'desc');

        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        $samples = $query->get();

        return response()->json([
            'status' => 'success',
            'data' => $samples
        ]);
    }

    public function scanKeluar(Request $request)
    {
        $request->validate([
            'peminjam' => 'required|string',
            'tujuan' => 'nullable|string',
            'tanggal_pinjam' => 'nullable|date',
            'items' => 'required|array|min:1',
            'items.*.barcode' => 'required|string',
            'items.*.qty' => 'required|integer|min:1',
        ]);

        try {
            DB::beginTransaction();

            $processedSamples = [];
            $tanggalPinjam = $request->tanggal_pinjam ?? now();

            foreach ($request->items as $item) {
                // Find SKU by sku string or kode_seri
                $sku = $this->resolveSku($item['barcode']);
                if (!$sku) {
                    throw new \Exception("Produk/SKU tidak ditemukan dengan barcode: " . $item['barcode']);
                }

                $stokGudang = StokGudangProduk::where('sku_id', $sku->id)->sum('qty');
                $stokWorkspace = DB::table('gudang_produk_stock_entries')->where('sku_id', $sku->id)->sum('qty');
                
                $totalStock = $stokGudang + $stokWorkspace;

                if ($totalStock < $item['qty']) {
                    throw new \Exception("Stok tidak mencukupi untuk SKU: " . $sku->sku . " (Sisa: " . $totalStock . ")");
                }

                // Kurangi stok dari gudang_produk_stock_entries atau StokGudangProduk
                $qtyToDeduct = $item['qty'];
                
                $stokWorkspace = DB::table('gudang_produk_stock_entries')->where('sku_id', $sku->id)->where('qty', '>', 0)->get();
                foreach ($stokWorkspace as $sw) {
                    if ($qtyToDeduct <= 0) break;
                    
                    $deduct = min($sw->qty, $qtyToDeduct);
                    DB::table('gudang_produk_stock_entries')->where('id', $sw->id)->update(['qty' => $sw->qty - $deduct]);
                    
                    \App\Models\GudangProdukActivityLog::create([
                        'type' => 'mutation',
                        'sku_id' => $sku->id,
                        'from_slot_id' => $sw->slot_id,
                        'to_slot_id' => null,
                        'qty' => $deduct,
                        'notes' => 'Peminjaman sampel | Kode seri: ' . $item['barcode'],
                        'created_by' => auth()->id() ?? 1,
                    ]);

                    $qtyToDeduct -= $deduct;
                }

                if ($qtyToDeduct > 0) {
                    $stok = StokGudangProduk::where('sku_id', $sku->id)->first();
                    if ($stok) {
                        $stok->qty -= $qtyToDeduct;
                        $stok->save();
                    } else {
                        StokGudangProduk::create(['sku_id' => $sku->id, 'qty' => -$qtyToDeduct]);
                    }
                    
                    \App\Models\GudangProdukActivityLog::create([
                        'type' => 'mutation',
                        'sku_id' => $sku->id,
                        'from_slot_id' => null,
                        'to_slot_id' => null,
                        'qty' => $qtyToDeduct,
                        'notes' => 'Peminjaman sampel (Stok Gudang) | Kode seri: ' . $item['barcode'],
                        'created_by' => auth()->id() ?? 1,
                    ]);
                }

                // Catat peminjaman
                $sample = GudangProdukSample::create([
                    'sku_id' => $sku->id,
                    'qty' => $item['qty'],
                    'peminjam' => $request->peminjam,
                    'tujuan' => $request->tujuan,
                    'status' => 'dipinjam',
                    'tanggal_pinjam' => $tanggalPinjam,
                    'created_by' => auth()->id() ?? 1, // fallback to 1 if not auth
                ]);

                $processedSamples[] = $sample;
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Sample berhasil dipinjam dan stok telah dikurangi.',
                'data' => $processedSamples
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function scanMasuk(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.barcode' => 'required|string',
            'items.*.qty' => 'nullable|integer|min:1', 
        ]);

        try {
            DB::beginTransaction();

            $processedSamples = [];

            foreach ($request->items as $item) {
                $sku = $this->resolveSku($item['barcode']);
                if (!$sku) {
                    throw new \Exception("Produk/SKU tidak ditemukan dengan barcode: " . $item['barcode']);
                }

                // Cari data peminjaman yang masih berstatus 'dipinjam'
                $sample = GudangProdukSample::where('sku_id', $sku->id)
                    ->where('status', 'dipinjam')
                    ->orderBy('created_at', 'asc')
                    ->first();

                if (!$sample) {
                    throw new \Exception("Tidak ada data peminjaman (dipinjam) untuk SKU: " . $sku->sku);
                }

                $returnQty = $item['qty'] ?? $sample->qty;
                if ($returnQty > $sample->qty) {
                     throw new \Exception("Jumlah yang dikembalikan melebihi jumlah yang dipinjam untuk SKU: " . $sku->sku);
                }

                // Cari riwayat keluarnya untuk tahu dari slot mana dia diambil
                $outLog = \App\Models\GudangProdukActivityLog::where('type', 'mutation')
                    ->where('sku_id', $sku->id)
                    ->where('notes', 'LIKE', '%Kode seri: ' . $item['barcode'] . '%')
                    ->orderBy('id', 'desc')
                    ->first();
                
                $targetSlotId = $outLog ? $outLog->from_slot_id : null;
                $targetLayoutId = null;
                
                if ($targetSlotId) {
                    // Cari layout_id untuk slot ini
                    $existingEntry = DB::table('gudang_produk_stock_entries')->where('slot_id', $targetSlotId)->first();
                    if ($existingEntry) {
                        $targetLayoutId = $existingEntry->layout_id;
                    }
                }
                
                // Fallback: Cari sembarang slot yang ada barang ini
                if (!$targetSlotId || !$targetLayoutId) {
                    $existingEntry = DB::table('gudang_produk_stock_entries')->where('sku_id', $sku->id)->first();
                    if ($existingEntry) {
                        $targetSlotId = $existingEntry->slot_id;
                        $targetLayoutId = $existingEntry->layout_id;
                    }
                }
                
                // Kembalikan ke slot atau StokGudangProduk
                if ($targetSlotId && $targetLayoutId) {
                    $stokWorkspace = DB::table('gudang_produk_stock_entries')
                        ->where('sku_id', $sku->id)
                        ->where('slot_id', $targetSlotId)
                        ->first();
                        
                    if ($stokWorkspace) {
                        DB::table('gudang_produk_stock_entries')->where('id', $stokWorkspace->id)->update([
                            'qty' => $stokWorkspace->qty + $returnQty
                        ]);
                    } else {
                        DB::table('gudang_produk_stock_entries')->insert([
                            'layout_id' => $targetLayoutId,
                            'sku_id' => $sku->id,
                            'slot_id' => $targetSlotId,
                            'qty' => $returnQty,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                    
                    \App\Models\GudangProdukActivityLog::create([
                        'type' => 'mutation',
                        'sku_id' => $sku->id,
                        'from_slot_id' => null,
                        'to_slot_id' => $targetSlotId,
                        'qty' => $returnQty,
                        'notes' => 'Pengembalian sampel | Kode seri: ' . $item['barcode'],
                        'created_by' => auth()->id() ?? 1,
                    ]);
                } else {
                    // Fallback jika gudang_produk_stock_entries tidak punya data slot/layout sama sekali
                    $stok = StokGudangProduk::firstOrCreate(['sku_id' => $sku->id], ['qty' => 0]);
                    $stok->qty += $returnQty;
                    $stok->save();
                    
                    \App\Models\GudangProdukActivityLog::create([
                        'type' => 'mutation',
                        'sku_id' => $sku->id,
                        'from_slot_id' => null,
                        'to_slot_id' => null,
                        'qty' => $returnQty,
                        'notes' => 'Pengembalian sampel (Stok Gudang) | Kode seri: ' . $item['barcode'],
                        'created_by' => auth()->id() ?? 1,
                    ]);
                }

                if ($returnQty < $sample->qty) {
                    // Update qty yang masih dipinjam
                    $sample->qty -= $returnQty;
                    $sample->save();

                    // Buat record baru untuk yang dikembalikan
                    $returnedSample = $sample->replicate();
                    $returnedSample->qty = $returnQty;
                    $returnedSample->status = 'dikembalikan';
                    $returnedSample->tanggal_kembali = now();
                    $returnedSample->save();
                    $processedSamples[] = $returnedSample;
                } else {
                    $sample->status = 'dikembalikan';
                    $sample->tanggal_kembali = now();
                    $sample->save();
                    $processedSamples[] = $sample;
                }
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Sample berhasil dikembalikan dan stok telah ditambahkan.',
                'data' => $processedSamples
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Terjadi kesalahan: ' . $e->getMessage()], 500);
        }
    }

    public function checkBarcode(Request $request)
    {
        $request->validate([
            'barcode' => 'required|string',
            'mode' => 'required|in:keluar,masuk'
        ]);

        $sku = $this->resolveSku($request->barcode);
        if (!$sku) {
            return response()->json(['message' => 'Barcode Produk/SKU tidak ditemukan.'], 404);
        }

        $lokasiRak = '-';
        $totalStock = 0;

        if ($request->mode === 'keluar') {
            $stokGudang = StokGudangProduk::where('sku_id', $sku->id)->sum('qty');
            $stokWorkspace = DB::table('gudang_produk_stock_entries')->where('sku_id', $sku->id)->sum('qty');
            
            $totalStock = $stokGudang + $stokWorkspace;

            if ($totalStock < 1) {
                return response()->json(['message' => 'Stok kosong untuk SKU: ' . $sku->sku], 400);
            }
            
            // Cari lokasi rak
            $stockEntry = DB::table('gudang_produk_stock_entries')
                ->where('sku_id', $sku->id)
                ->where('qty', '>', 0)
                ->first();
            
            if ($stockEntry && $stockEntry->slot_id) {
                // Format slot_id: layout_xyz__F4__BB -> F4/BB
                $lokasiRak = preg_replace('/^layout_[a-z0-9]+__/', '', $stockEntry->slot_id);
                $lokasiRak = str_replace('__', '/', $lokasiRak);
            }
        } else {
            $sample = GudangProdukSample::where('sku_id', $sku->id)
                ->where('status', 'dipinjam')
                ->first();
            if (!$sample) {
                return response()->json(['message' => 'Tidak ada data peminjaman untuk SKU: ' . $sku->sku], 400);
            }
            
            // Cari lokasi rak kembaliannya
            $outLog = \App\Models\GudangProdukActivityLog::where('type', 'mutation')
                ->where('sku_id', $sku->id)
                ->where('notes', 'LIKE', '%Kode seri: ' . $request->barcode . '%')
                ->orderBy('id', 'desc')
                ->first();
                
            $targetSlotId = $outLog ? $outLog->from_slot_id : null;
            if (!$targetSlotId) {
                $existingEntry = DB::table('gudang_produk_stock_entries')->where('sku_id', $sku->id)->first();
                if ($existingEntry) {
                    $targetSlotId = $existingEntry->slot_id;
                }
            }

            if ($targetSlotId) {
                $lokasiRak = preg_replace('/^layout_[a-z0-9]+__/', '', $targetSlotId);
                $lokasiRak = str_replace('__', '/', $lokasiRak);
            }
        }

        // Coba cari nama produk jika ada
        $skuName = $sku->sku;
        $produkSku = \App\Models\ProdukSku::with('produk')->where('sku', $sku->sku)->first();
        if ($produkSku && $produkSku->produk) {
            $skuName = $produkSku->produk->nama_produk . ' (' . $sku->sku . ')';
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'sku_id' => $sku->id,
                'sku_name' => $skuName,
                'lokasi_rak' => $lokasiRak,
                'total_stock' => $totalStock,
                'is_unique' => ($request->barcode !== $sku->sku)
            ]
        ]);
    }

    private function resolveSku($barcode)
    {
        $barcode = trim($barcode);

        // 1. Coba cari langsung di tabel Sku
        $sku = Sku::where('sku', $barcode)->first();
        if ($sku) {
            return $sku;
        }

        // 2. Coba cari sebagai kode_seri di activity logs (karena kode seri bisa mengandung SKU ID)
        $log = GudangProdukActivityLog::where('notes', 'like', "%{$barcode}%")->first();
        if ($log && $log->sku_id) {
            return Sku::find($log->sku_id);
        }

        return null;
    }
}
