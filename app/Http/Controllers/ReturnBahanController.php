<?php

namespace App\Http\Controllers;

use App\Models\PembelianBahanReturn;
use App\Models\PembelianBahanRol;
use App\Models\PembelianBahan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReturnBahanController extends Controller
{
    /**
     * Create return/refund untuk barang rusak
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'pembelian_bahan_id' => 'required|exists:pembelian_bahan,id',
                'pembelian_bahan_rol_id' => 'nullable|exists:pembelian_bahan_rol,id',
                'tipe_return' => 'required|in:refund,return_barang',
                'jumlah_rol' => 'required|integer|min:1',
                'total_refund' => 'nullable|numeric|min:0|required_if:tipe_return,refund',
                'keterangan' => 'nullable|string',
                'tanggal_return' => 'required|date',
                'foto_bukti' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5000',
            ], [
                'pembelian_bahan_id.required' => 'ID Pembelian Bahan wajib diisi.',
                'pembelian_bahan_id.exists' => 'ID Pembelian Bahan tidak valid.',
                'pembelian_bahan_rol_id.exists' => 'ID Rol tidak valid.',
                'tipe_return.required' => 'Tipe return wajib dipilih.',
                'tipe_return.in' => 'Tipe return harus berupa refund atau return barang.',
                'jumlah_rol.required' => 'Jumlah rol wajib diisi.',
                'jumlah_rol.integer' => 'Jumlah rol harus berupa angka bulat.',
                'jumlah_rol.min' => 'Jumlah rol minimal 1.',
                'total_refund.numeric' => 'Total refund harus berupa angka.',
                'total_refund.min' => 'Total refund tidak boleh kurang dari 0.',
                'total_refund.required_if' => 'Total refund wajib diisi jika tipe return adalah refund.',
                'tanggal_return.required' => 'Tanggal return wajib diisi.',
                'tanggal_return.date' => 'Format tanggal return tidak valid.',
                'foto_bukti.file' => 'Foto bukti harus berupa file.',
                'foto_bukti.mimes' => 'Format foto bukti harus jpg, jpeg, png, atau pdf.',
                'foto_bukti.max' => 'Ukuran foto bukti maksimal 5MB.',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('Validasi return/refund gagal: ' . json_encode($e->errors()));
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        }

        DB::beginTransaction();

        try {
            $data = $validated;

            // Upload foto bukti jika ada
            if ($request->hasFile('foto_bukti')) {
                $data['foto_bukti'] = $request->file('foto_bukti')
                    ->store('return_bukti', 'public');
            }

            // Hapus foto_bukti dari $data jika tidak ada file (karena nullable)
            if (!isset($data['foto_bukti'])) {
                $data['foto_bukti'] = null;
            }

            // Pastikan pembelian_bahan_rol_id null jika kosong
            if (empty($data['pembelian_bahan_rol_id'])) {
                $data['pembelian_bahan_rol_id'] = null;
            }

            // ===== VALIDASI JUMLAH ROL =====
            $pembelian = PembelianBahan::with(['warna', 'returns'])->findOrFail($validated['pembelian_bahan_id']);
            
            // 1. Hitung total rol yang dibeli di pembelian ini
            $totalBeli = $pembelian->warna->sum('jumlah_rol');
            
            // 2. Hitung total rol yang SUDAH direturn (kecuali rejected)
            $totalSudahReturn = $pembelian->returns->where('status', '!=', 'rejected')->sum('jumlah_rol');
            
            // 3. Sisa yang boleh direturn
            $sisaBolehReturn = $totalBeli - $totalSudahReturn;

            if ($validated['jumlah_rol'] > $sisaBolehReturn) {
                throw new \Exception(
                    "Jumlah rol melebihi sisa yang bisa direturn. Total Beli: $totalBeli, Sudah Return: $totalSudahReturn, Sisa: $sisaBolehReturn."
                );
            }
            // ===============================

            Log::info('Creating return/refund with data: ' . json_encode($data));

            $return = PembelianBahanReturn::create($data);

            // Update status rol menjadi 'rusak' jika rol_id diisi
            if (!empty($validated['pembelian_bahan_rol_id'])) {
                PembelianBahanRol::where('id', $validated['pembelian_bahan_rol_id'])
                    ->update(['status' => 'rusak']);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Return/Refund berhasil dicatat',
                'data' => $return->load(['pembelianBahan', 'rol'])
            ], 201);

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Create return/refund gagal: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mencatat return/refund',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Get list return/refund
     */
    public function index(Request $request)
    {
        try {
            $query = PembelianBahanReturn::with(['pembelianBahan', 'rol']);

            // Filter by pembelian_bahan_id jika ada query parameter
            if ($request->has('pembelian_bahan_id')) {
                $query->where('pembelian_bahan_id', $request->pembelian_bahan_id);
            }

            // Search functionality
            if ($request->has('search') && !empty($request->search)) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('keterangan', 'like', "%{$search}%")
                      ->orWhere('status', 'like', "%{$search}%")
                      ->orWhereHas('pembelianBahan', function($q2) use ($search) {
                          $q2->where('no_surat_jalan', 'like', "%{$search}%");
                      });
                });
            }

            $query->orderBy('tanggal_return', 'desc');

            if ($request->has('per_page')) {
                $perPage = $request->input('per_page', 20);
                $returns = $query->paginate($perPage);
            } else {
                $returns = $query->get();
            }

            return response()->json([
                'success' => true,
                'data' => $returns
            ]);
        } catch (\Throwable $e) {
            Log::error('Get returns gagal: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil data return/refund',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update status return/refund
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'status' => 'required|in:pending,approved,rejected,completed',
            ]);

            $return = PembelianBahanReturn::findOrFail($id);
            $return->update(['status' => $validated['status']]);

            Log::info("Return/Refund ID {$id} status diupdate menjadi: {$validated['status']}");

            return response()->json([
                'success' => true,
                'message' => 'Status return/refund berhasil diupdate',
                'data' => $return->load(['pembelianBahan', 'rol'])
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Return/Refund tidak ditemukan'
            ], 404);
        } catch (\Throwable $e) {
            Log::error('Update return status gagal: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengupdate status return/refund',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
