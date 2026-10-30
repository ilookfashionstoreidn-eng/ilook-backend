<?php

namespace App\Http\Controllers;

use App\Models\Bahan;
use App\Models\ProductList;
use App\Models\SpkCutting;
use App\Models\SpkCuttingBagian;
use App\Models\SpkCuttingBahan;
use App\Models\SpkCuttingStatusLog;
use App\Models\StokBahanKeluar;
use App\Models\TukangCutting;
use App\Models\TukangPola;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Terima SPK Cutting dari project Accurate (antar-server, header X-Api-Key, lihat middleware "integration").
 *
 * Accurate mengirim NAMA (SKU, tukang, bahan); ID master dicari di sini. Nomor seri ("ref") = id_spk_cutting, jadi SPK yang
 * sama dikirim ulang akan DIPERBARUI, bukan dobel. Stok bahan tidak disentuh sama sekali (stok kedua sistem terpisah):
 * SPK yang sudah berjalan di ilook (sudah diambil, sudah ada hasil/distribusi lanjutan, atau sudah ada bahan keluar) tidak diubah.
 */
class IntegrationSpkCuttingController extends SpkCuttingController
{
    private const JENIS_SPK = ['Terjual', 'Fittingan', 'Habisin Bahan'];

    /** Nama tukang cutting di Accurate → nama di tabel tukang_cutting ilook (huruf besar). */
    private const TUKANG_ALIAS = [
        'ERIK' => 'ERIKSON',
    ];

    public function upsert(Request $request)
    {
        try {
            $v = $request->validate([
                'ref' => 'required|string|max:255',
                'mode' => 'required|in:biasa,potong_kecil',
                'tanggal' => 'required|date',
                'batas_kirim' => 'required|date',
                'pic' => 'nullable|string|max:255',
                'tukang_cutting' => 'required|string|max:255',
                'tukang_pola' => 'nullable|string|max:255',
                'jenis_spk' => 'nullable|string|max:255',
                'keterangan' => 'nullable|string',
                'harga_jasa' => 'required|numeric|min:0',
                'satuan_harga' => 'required|in:Lusin,Pcs',
                'jumlah_asumsi_produk' => 'nullable|integer|min:0',
                'bagian' => 'required|array|min:1',
                'bagian.*.nama_bagian' => 'required|string|max:255',
                'bagian.*.bahan' => 'required|array|min:1',
                'bagian.*.bahan.*.bahan' => 'nullable|string|max:255',
                'bagian.*.bahan.*.warna_bahan' => 'nullable|string|max:255',
                'bagian.*.bahan.*.warna' => 'nullable|string|max:255',
                'bagian.*.bahan.*.qty' => 'required|numeric|min:0',
                'bagian.*.bahan.*.skus' => 'required|array|min:1',
                'bagian.*.bahan.*.skus.*.sku' => 'required|string|max:255',
                'bagian.*.bahan.*.skus.*.qty' => 'required|numeric|min:0',
            ]);

            $warnings = [];
            $mode = $v['mode'];

            // ---- SKU: semua nama harus ada di product_lists dan satu product_group
            $skuNames = [];
            foreach ($v['bagian'] as $b) {
                foreach ($b['bahan'] as $x) {
                    foreach ($x['skus'] as $s) {
                        $skuNames[$this->up($s['sku'])] = $s['sku'];
                    }
                }
            }
            $skuRows = ProductList::query()
                ->whereIn(DB::raw('UPPER(TRIM(sku_name))'), array_keys($skuNames))
                ->orderBy('id')
                ->get(['id', 'sku_name', 'product_group']);
            $skuId = [];
            foreach ($skuRows as $r) {
                $skuId[$this->up($r->sku_name)] ??= $r;
            }
            $hilang = array_values(array_diff_key($skuNames, $skuId));
            if ($hilang) {
                return $this->gagal('SKU belum ada di ilook (Product List): ' . implode(', ', $hilang), 422);
            }
            $grup = collect($skuId)->pluck('product_group')->unique()->values();
            if ($grup->count() > 1) {
                return $this->gagal('SKU berasal dari lebih dari satu product group di ilook: ' . $grup->implode(', '), 422);
            }
            $productListId = (int) collect($skuId)->first()->id;
            $skuIds = array_values(array_unique(collect($skuId)->pluck('id')->map(fn ($i) => (int) $i)->all()));

            // ---- tukang
            $tukang = $this->cariTukangCutting($v['tukang_cutting']);
            if (is_string($tukang)) {
                return $this->gagal($tukang, 422);
            }
            $pola = null;
            if (trim((string) ($v['tukang_pola'] ?? '')) !== '') {
                $pola = TukangPola::query()->whereRaw('UPPER(TRIM(nama)) = ?', [$this->up($v['tukang_pola'])])->first();
                if (!$pola) {
                    $warnings[] = "Tukang pola '{$v['tukang_pola']}' tidak ada di ilook, dikosongkan";
                }
            }

            // ---- jenis SPK: ilook hanya mengenal 3 nilai; selain itu dicatat di keterangan
            $jenis = trim((string) ($v['jenis_spk'] ?? ''));
            $keterangan = trim((string) ($v['keterangan'] ?? ''));
            if ($jenis !== '' && !in_array($jenis, self::JENIS_SPK, true)) {
                $keterangan = trim("Jenis SPK: {$jenis}" . ($keterangan !== '' ? "\n{$keterangan}" : ''));
                $jenis = '';
            }

            // ---- bagian + bahan dalam bentuk yang sama dengan SpkCuttingController::store
            $bagian = [];
            foreach ($v['bagian'] as $b) {
                $rows = [];
                foreach ($b['bahan'] as $x) {
                    $bahan = $this->cariBahan($x['bahan'] ?? null, $x['warna_bahan'] ?? null);
                    if ($mode === 'biasa' && !$bahan) {
                        return $this->gagal(
                            "Bahan belum ada di ilook: " . trim(($x['bahan'] ?? '-') . ' ' . ($x['warna_bahan'] ?? '')),
                            422
                        );
                    }
                    $rows[] = [
                        'sumber_komponen' => 'bahan',
                        'bahan_id' => $bahan?->id,
                        'aksesoris_id' => null,
                        'warna' => $x['warna'] ?? ($x['warna_bahan'] ?? null),
                        'berat' => null,
                        'qty' => $x['qty'],
                        'skus' => array_map(
                            fn ($s) => ['sku_id' => (int) $skuId[$this->up($s['sku'])]->id, 'qty' => $s['qty']],
                            $x['skus']
                        ),
                    ];
                }
                $bagian[] = ['nama_bagian' => $b['nama_bagian'], 'bahan' => $rows];
            }

            $ref = trim($v['ref']);
            $harga = (float) $v['harga_jasa'];
            $attrs = [
                'id_spk_cutting' => $ref,
                'pic' => $v['pic'] ?? null,
                'produk_id' => null,
                'product_list_id' => $productListId,
                'tanggal_batas_kirim' => $v['batas_kirim'],
                'keterangan' => $keterangan !== '' ? $keterangan : null,
                'harga_jasa' => $harga,
                'satuan_harga' => $v['satuan_harga'],
                'harga_per_pcs' => $v['satuan_harga'] === 'Lusin' ? $harga / 12 : $harga,
                'jumlah_asumsi_produk' => (int) ($v['jumlah_asumsi_produk'] ?? 0),
                'jenis_spk' => $jenis !== '' ? $jenis : null,
                'tukang_cutting_id' => $tukang->id,
                'tukang_pola_id' => $pola?->id,
                'mode' => $mode,
            ];

            $spk = SpkCutting::where('id_spk_cutting', $ref)->first();
            if ($spk && ($alasan = $this->alasanTerkunci($spk))) {
                return $this->gagal($alasan, 409);
            }

            DB::beginTransaction();
            try {
                $baru = !$spk;
                if ($baru) {
                    $spk = SpkCutting::create($attrs + [
                        'status_cutting' => 'belum_diambil',
                        'barcode' => 'SPKC-' . strtoupper(uniqid()),
                    ]);
                    SpkCuttingStatusLog::create([
                        'spk_cutting_id' => $spk->id,
                        'status' => $spk->status_cutting,
                        'keterangan' => 'SPK Cutting dibuat (dikirim dari Accurate)',
                    ]);
                } else {
                    $spk->update($attrs);
                    foreach ($spk->bagian as $old) {
                        $old->bahan()->delete();
                    }
                    $spk->bagian()->delete();
                }

                $spk->created_at = Carbon::parse($v['tanggal'])->format('Y-m-d H:i:s');
                $spk->save();

                $spk->productListSkus()->sync($skuIds);
                $this->syncSkuDistributions($spk, $skuIds);

                foreach ($bagian as $bagianData) {
                    $bg = SpkCuttingBagian::create(['spk_cutting_id' => $spk->id, 'nama_bagian' => $bagianData['nama_bagian']]);
                    foreach ($bagianData['bahan'] as $bahanData) {
                        $row = SpkCuttingBahan::create($this->makeSpkCuttingBahanPayload($bg->id, $bagianData, $bahanData));
                        $pivot = [];
                        foreach ($bahanData['skus'] as $s) {
                            $pivot[$s['sku_id']] = ['qty' => $s['qty']];
                        }
                        $row->skus()->sync($pivot);
                    }
                }
                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                throw $e;
            }

            return response()->json(['ok' => true, 'id' => $spk->id, 'created' => $baru, 'warnings' => $warnings], $baru ? 201 : 200);
        } catch (ValidationException $e) {
            $first = collect($e->errors())->flatten()->first();
            return response()->json(['ok' => false, 'error' => $first ?: 'Validasi gagal', 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return $this->gagal('Gagal menyimpan di ilook: ' . $e->getMessage(), 500);
        }
    }

    /** DELETE ?ref=NOSERI : tarik SPK yang pernah dikirim (SPK yang sudah berjalan di ilook tidak dihapus). */
    public function withdraw(Request $request)
    {
        $ref = trim((string) $request->query('ref', ''));
        if ($ref === '') {
            return $this->gagal('ref wajib diisi', 422);
        }
        $spk = SpkCutting::where('id_spk_cutting', $ref)->first();
        if (!$spk) {
            return response()->json(['ok' => true, 'deleted' => false]);
        }
        if ($alasan = $this->alasanTerkunci($spk)) {
            return $this->gagal($alasan, 409);
        }
        try {
            DB::transaction(function () use ($spk) {
                foreach ($spk->bagian as $b) {
                    $b->bahan()->delete();
                }
                $spk->bagian()->delete();
                $spk->delete(); // distribusi, detail, status log, dan pivot SKU ikut terhapus lewat cascade
            });
        } catch (\Throwable $e) {
            return $this->gagal('Gagal menghapus di ilook: ' . $e->getMessage(), 500);
        }
        return response()->json(['ok' => true, 'deleted' => true]);
    }

    private function gagal(string $pesan, int $status)
    {
        return response()->json(['ok' => false, 'error' => $pesan], $status);
    }

    private function up(?string $s): string
    {
        return mb_strtoupper(trim((string) preg_replace('/\s+/', ' ', (string) $s)));
    }

    /** null = boleh diubah/dihapus; selain itu alasannya. */
    private function alasanTerkunci(SpkCutting $spk): ?string
    {
        if ($spk->status_cutting !== 'belum_diambil') {
            return "SPK {$spk->id_spk_cutting} di ilook sudah berstatus {$spk->status_cutting}, tidak bisa diubah dari Accurate";
        }
        if (in_array($spk->tahap_terakhir, ['SPK Jasa', 'SPK CMT', 'Hasil Cutting'], true)) {
            return "SPK {$spk->id_spk_cutting} di ilook sudah berlanjut ke tahap {$spk->tahap_terakhir}";
        }
        if (StokBahanKeluar::where('spk_cutting_id', $spk->id)->exists()) {
            return "SPK {$spk->id_spk_cutting} di ilook sudah punya pengeluaran bahan (scan)";
        }
        return null;
    }

    /** @return TukangCutting|string model, atau pesan error */
    private function cariTukangCutting(string $nama)
    {
        $kunci = $this->up($nama);
        $kunci = self::TUKANG_ALIAS[$kunci] ?? $kunci;

        $exact = TukangCutting::query()->whereRaw('UPPER(TRIM(nama_tukang_cutting)) = ?', [$kunci])->first();
        if ($exact) {
            return $exact;
        }
        // cadangan: awalan nama yang cocok dengan tepat satu tukang
        $like = TukangCutting::query()
            ->whereRaw('UPPER(TRIM(nama_tukang_cutting)) LIKE ?', [str_replace(['%', '_'], ['\\%', '\\_'], $kunci) . '%'])
            ->get();
        if ($like->count() === 1) {
            return $like->first();
        }
        return $like->count() > 1
            ? "Tukang cutting '{$nama}' cocok dengan lebih dari satu nama di ilook"
            : "Tukang cutting '{$nama}' belum ada di ilook";
    }

    private function cariBahan(?string $nama, ?string $warna): ?Bahan
    {
        $nama = $this->up($nama);
        if ($nama === '') {
            return null;
        }
        $q = Bahan::query()->whereRaw('UPPER(TRIM(nama_bahan)) = ?', [$nama]);
        $warna = $this->up($warna);
        if ($warna !== '') {
            $dengan = (clone $q)->whereRaw('UPPER(TRIM(warna_bahan)) = ?', [$warna])->orderBy('id')->first();
            if ($dengan) {
                return $dengan;
            }
        }
        // warna belum terdaftar di ilook: pakai bahan yang namanya sama (warna tetap tercatat di kolom warna SPK)
        return $q->orderBy('id')->first();
    }
}
