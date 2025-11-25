<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Models\Produk;

class SpkCmt extends Model
{
    use HasFactory;
    protected $table = 'spk_cmt'; // Nama tabel
    protected $primaryKey = 'id_spk'; // Primary key
    protected $appends = ['waktu_pengerjaan', 'sisa_hari_status'];
    

    protected $fillable = [
        'tgl_spk',
        'id_produk',
        'jumlah_produk',
        'deadline',
        'id_penjahit',
        'keterangan',
        'status',
        'gambar_produk', 
        'tanggal_ambil', 
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
        'nomor_seri',
    ];

    public function produk()
    {
        return $this->belongsTo(Produk::class, 'id_produk', 'id');
    }
    

    public function getTotalHargaAttribute()
    {
        return $this->harga_per_barang * $this->jumlah_produk;
    }
    
    
    // Relasi ke tabel penjahit
    public function penjahit()
    {
        return $this->belongsTo(Penjahit::class, 'id_penjahit');
    }


    // Relasi ke tabel warna (One-to-Many)
    public function warna()
    {
        return $this->hasMany(Warna::class, 'id_spk', 'id_spk');
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
        if (in_array($this->status, ['Pending', 'Completed'])) {
            \Log::info('Status Pending atau Completed - Mengembalikan waktu_pengerjaan_terakhir', [
                'status' => $this->status,
                'waktu_pengerjaan_terakhir' => $this->waktu_pengerjaan_terakhir,
            ]);
            return $this->waktu_pengerjaan_terakhir; // Gunakan nilai terakhir
        }
    
        if (!$this->tanggal_ambil) {
            \Log::info('Tanggal ambil tidak ada - Mengembalikan null');
            return null; // Jika belum ada tanggal ambil, return null
        }
    
        $tanggalMulai = Carbon::parse($this->tanggal_ambil);
        $tanggalSelesai = now();
    
        $waktuPengerjaan = $tanggalMulai->diffInDays($tanggalSelesai);
    
        \Log::info('Menghitung waktu pengerjaan', [
            'tanggal_ambil' => $this->tanggal_ambil,
            'tanggal_sekarang' => now(),
            'waktu_pengerjaan' => $waktuPengerjaan,
        ]);
    
        return $waktuPengerjaan;
    }
    
    
    public function getStatusWithColorAttribute()
    {
       
        $sisaHari = $this->sisa_hari ?? 0;
    
        if (in_array($this->status, ['In Progress', 'Pending'])) {
           
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
        if (in_array($newStatus, ['Pending', 'Completed'])) {
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
        if (in_array($this->status, ['Pending', 'Completed'])) {
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
    
        $deadline = Carbon::parse($this->deadline);
    
        $sisaHari = $deadline->isPast() ? 0 : $deadline->diffInDays(now());
    
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

    





}