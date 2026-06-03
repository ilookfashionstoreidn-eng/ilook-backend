<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Models\Produk;
use App\Models\SpkCuttingDistribusi;
use App\Models\SpkJasa;

class SpkCmt extends Model
{
    use HasFactory;
    protected $table = 'spk_cmt'; 
    protected $primaryKey = 'id_spk'; 
    protected $appends = [
        'waktu_pengerjaan',
        'sisa_hari',
        'sisa_hari_status',
        'sumber_pekerjaan',
        'nomor_seri',
        'nama_produk',
        'gambar_produk',
        'product_size',
    ];



    protected $fillable = [
        // SOURCE
        'source_type',
        'source_id',

        // EXISTING
        'deadline',
        'tanggal_ambil',
        'id_penjahit',
        'keterangan',
        'status',
        'catatan',
        'markeran',
        'aksesoris',
        'handtag',
        'merek',
        'harga_per_barang',
        'total_harga',
        'harga_per_jasa',
        'waktu_pengerjaan_terakhir',
        'sisa_hari_terakhir',
        'jenis_harga_jasa',
        'harga_jasa_awal',
        'harga_barang_dasar',
        'jenis_harga_barang',
        'pending_at',
        'pending_until',
        'alasan_pending',
        'sku_id',

    ];
    public function items()
{
    return $this->hasMany(SpkCmtItem::class, 'spk_cmt_id', 'id_spk');
}

    public function sku()
    {
        return $this->belongsTo(Sku::class, 'sku_id');
    }
    public function spkJasa()
    {
        return $this->belongsTo(SpkJasa::class, 'source_id');
    }

   public function spkCuttingDistribusi() { return $this->belongsTo( SpkCuttingDistribusi::class, 'source_id' ); }


    public function getSumberPekerjaanAttribute()
    {
        return match ($this->source_type) {
            'cutting' => $this->spkCuttingDistribusi,
            'jasa'    => $this->spkJasa,
            default   => null,
        };
    }

    public function getNomorSeriAttribute()
    {
        $sumber = $this->sumber_pekerjaan;
        if ($sumber) {
            if ($this->source_type === 'cutting') {
                return $sumber->kode_seri;
            } elseif ($this->source_type === 'jasa') {
                return $sumber->spkCuttingDistribusi->kode_seri ?? null;
            }
        }
        return null;
    }

    public function getNamaProdukAttribute()
    {
        $sumber = $this->sumber_pekerjaan;
        if ($sumber) {
            if ($this->source_type === 'cutting') {
                return $sumber->spkCutting->productList->product ?? $sumber->spkCutting->produk->nama_produk ?? null;
            } elseif ($this->source_type === 'jasa') {
                return $sumber->spkCuttingDistribusi->spkCutting->productList->product ?? $sumber->spkCuttingDistribusi->spkCutting->produk->nama_produk ?? null;
            }
        }
        return null;
    }

    public function getGambarProdukAttribute()
    {
        $sumber = $this->sumber_pekerjaan;
        if ($sumber) {
            if ($this->source_type === 'cutting') {
                $image = $sumber->spkCutting->productList->productListImage->image_path 
                    ?? $sumber->spkCutting->produk->gambar_produk 
                    ?? null;
                if ($image) return $image;
            } elseif ($this->source_type === 'jasa') {
                $image = $sumber->spkCuttingDistribusi->spkCutting->productList->productListImage->image_path 
                    ?? $sumber->spkCuttingDistribusi->spkCutting->produk->gambar_produk 
                    ?? null;
                if ($image) return $image;
            }
        }
        
        // Fallback: check SpkCmt items for SKU image
        $firstItem = $this->items->first();
        if ($firstItem && $firstItem->sku && $firstItem->sku->productList && $firstItem->sku->productList->productListImage) {
            return $firstItem->sku->productList->productListImage->image_path;
        }

        return null;
    }

    public function getProductSizeAttribute()
    {
        $sumber = $this->sumber_pekerjaan;
        if ($sumber) {
            if ($this->source_type === 'cutting') {
                return $sumber->spkCutting->productList->product_size ?? null;
            } elseif ($this->source_type === 'jasa') {
                return $sumber->spkCuttingDistribusi->spkCutting->productList->product_size ?? null;
            }
        }
        return null;
    }

    // Relasi ke tabel penjahit
    public function penjahit()
    {
        return $this->belongsTo(Penjahit::class, 'id_penjahit');
    }

    // Relasi ke tabel produk
    public function produk()
    {
        return $this->belongsTo(Produk::class, 'id_produk');
    }

    public function warna()
    {
        return $this->hasMany(SpkCmtWarna::class, 'spk_cmt_id', 'id_spk');
    }



    public function logDeadlines()
    {
        return $this->hasMany(LogDeadline::class, 'id_spk');
    }

    public function logStatus()
    {
        return $this->hasMany(LogStatus::class, 'id_spk', 'id_spk');
    }


    public function pengiriman()
    {
        return $this->hasMany(Pengiriman::class, 'id_spk', 'id_spk')->orderByDesc('tanggal_pengiriman');
    }

    public function getWaktuPengerjaanAttribute()
    {
        if (in_array($this->status, ['pending', 'completed'])) {
            return $this->waktu_pengerjaan_terakhir;
        }

        if ($this->deadline && $this->tanggal_ambil) {
            $d1 = Carbon::parse($this->deadline)->startOfDay();
            $d2 = Carbon::parse($this->tanggal_ambil)->startOfDay();
            return $d2->diffInDays($d1);
        }

        $tanggalMulai = Carbon::parse($this->created_at);
        $tanggalSelesai = now();

        return $tanggalMulai->diffInDays($tanggalSelesai);
    }



    public function getStatusWithColorAttribute()
    {

        $sisaHari = $this->sisa_hari ?? 0;

        if (in_array($this->status, ['sudah_diambil', 'pending'])) {

            $color = match (true) {
                $sisaHari >= 14 => 'green',
                $sisaHari >= 7 => 'yellow',
                default => 'red',
            };

            return [
                'status' => $this->status,
                'color' => $color,
            ];
        }


        return [
            'status' => $this->status,
            'color' => 'gray',
        ];
    }


    // Di model SpkCmt
    public function getTotalBarangDikirimAttribute()
    {
        return $this->pengiriman()->sum('total_barang_dikirim');
    }

    public function setStatus($newStatus)
    {
        // Simpan nilai terakhir jika status berubah menjadi Pending atau Completed
        if (in_array($newStatus, ['pending', 'completed'])) {
            $this->sisa_hari_terakhir = $this->getSisaHariAttribute();
            $this->waktu_pengerjaan_terakhir = $this->getWaktuPengerjaanAttribute();
        }

        // Ubah status
        $this->status = $newStatus;

        // Simpan perubahan ke database
        $this->save();
    }

    public function getSisaHariAttribute()
    {
        if (in_array($this->status, ['pending', 'completed'])) {
            \Log::info('Status Pending atau Completed - Mengembalikan sisa_hari_terakhir', [
                'statusX' => $this->status,
                'sisa_hari_terakhir' => $this->sisa_hari_terakhir,
            ]);
            return $this->sisa_hari_terakhir; // Gunakan nilai terakhir
        }

        if (!$this->deadline) {
            \Log::info('Deadline tidak ada - Mengembalikan null');
            return null; // Jika tidak ada deadline, return null
        }

        $deadline = Carbon::parse($this->deadline)->startOfDay();
        $now = now()->startOfDay();

        $sisaHari = (int) $now->diffInDays($deadline, false);

        \Log::info('Menghitung sisa_hari', [
            'deadline' => $this->deadline,
            'tanggal_sekarang' => now(),
            'sisaHari' => $sisaHari,
        ]);

        return $sisaHari;
    }
    public function getSisaHariStatusAttribute()
    {
        if ($this->sisa_hari <= 3) {
            return 'danger'; // Tampilkan warna merah
        } elseif ($this->sisa_hari > 3 && $this->sisa_hari <= 7) {
            return 'warning'; // Warna kuning (opsional)
        } else {
            return 'safe'; // Warna hijau
        }
    }

    public function statusLogs()
    {
        return $this->hasMany(
            LogStatusSpkCmt::class,
            'spk_cmt_id',
            'id_spk'
        )->orderBy('created_at', 'asc');
    }
}
