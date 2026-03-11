<?php

namespace App\Http\Controllers;

use App\Models\SpkSample;
use App\Models\TukangSample;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class SpkSampleController extends Controller
{
    public function index()
    {
        $spkSamples = SpkSample::with('tukangSample')->latest()->get();

        return response()->json([
            'status' => 'success',
            'data' => $spkSamples,
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_sample' => 'required|string|max:255',
            'kategori_sample' => 'required|string|max:255',
            'detail' => 'nullable|string',
            'status_spk' => 'required|string|max:100',
            'status_proses' => 'nullable|string|max:100',
            'tahap_proses' => 'nullable|string|max:100',
            'keterangan_sample' => 'nullable|string',
            'foto' => 'nullable|file|image|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        if ($request->hasFile('foto')) {
            $data['foto'] = $request->file('foto')->store('spk-sample', 'public');
        }

        $spkSample = SpkSample::create($data);

        return response()->json([
            'status' => 'success',
            'message' => 'SPK Sample berhasil dibuat',
            'data' => $spkSample->load('tukangSample'),
        ], 201);
    }

    public function show($id)
    {
        $spkSample = SpkSample::with('tukangSample')->find($id);

        if (!$spkSample) {
            return response()->json([
                'status' => 'error',
                'message' => 'SPK Sample tidak ditemukan',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $spkSample,
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $spkSample = SpkSample::find($id);

        if (!$spkSample) {
            return response()->json([
                'status' => 'error',
                'message' => 'SPK Sample tidak ditemukan',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nama_sample' => 'sometimes|required|string|max:255',
            'kategori_sample' => 'sometimes|required|string|max:255',
            'detail' => 'nullable|string',
            'status_spk' => 'sometimes|required|string|max:100',
            'status_proses' => 'nullable|string|max:100',
            'tahap_proses' => 'nullable|string|max:100',
            'keterangan_sample' => 'nullable|string',
            'foto' => 'nullable|file|image|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        if ($request->hasFile('foto')) {
            if ($spkSample->foto && Storage::disk('public')->exists($spkSample->foto)) {
                Storage::disk('public')->delete($spkSample->foto);
            }

            $data['foto'] = $request->file('foto')->store('spk-sample', 'public');
        }

        $spkSample->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'SPK Sample berhasil diperbarui',
            'data' => $spkSample->load('tukangSample'),
        ], 200);
    }

    public function assignTukang(Request $request, $id)
    {
        $spkSample = SpkSample::find($id);

        if (!$spkSample) {
            return response()->json([
                'status' => 'error',
                'message' => 'SPK Sample tidak ditemukan',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'tukang_sample_id' => 'nullable|exists:tukang_samples,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $spkSample->update(['tukang_sample_id' => $request->tukang_sample_id]);

        return response()->json([
            'status' => 'success',
            'message' => 'Tukang pola berhasil ditetapkan',
            'data' => $spkSample->load('tukangSample'),
        ], 200);
    }

    public function updateStatusProses(Request $request, $id)
    {
        $spkSample = SpkSample::find($id);

        if (!$spkSample) {
            return response()->json([
                'status' => 'error',
                'message' => 'SPK Sample tidak ditemukan',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status_proses' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $spkSample->update(['status_proses' => $request->status_proses]);

        return response()->json([
            'status' => 'success',
            'message' => 'Status proses berhasil diperbarui',
            'data' => $spkSample->load('tukangSample'),
        ], 200);
    }

    public function updateTahapProses(Request $request, $id)
    {
        $spkSample = SpkSample::find($id);

        if (!$spkSample) {
            return response()->json([
                'status' => 'error',
                'message' => 'SPK Sample tidak ditemukan',
            ], 404);
        }

        if ($spkSample->status_proses !== 'ACC') {
            return response()->json([
                'status' => 'error',
                'message' => 'Tahap proses hanya bisa diubah jika status proses sudah ACC.',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'tahap_proses' => 'required|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $spkSample->update(['tahap_proses' => $request->tahap_proses]);

        return response()->json([
            'status' => 'success',
            'message' => 'Tahap proses berhasil diperbarui',
            'data' => $spkSample->load('tukangSample'),
        ], 200);
    }

    public function destroy($id)
    {
        $spkSample = SpkSample::find($id);

        if (!$spkSample) {
            return response()->json([
                'status' => 'error',
                'message' => 'SPK Sample tidak ditemukan',
            ], 404);
        }

        if ($spkSample->foto && Storage::disk('public')->exists($spkSample->foto)) {
            Storage::disk('public')->delete($spkSample->foto);
        }

        $spkSample->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'SPK Sample berhasil dihapus',
        ], 200);
    }
}
