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
            'bahan_utama' => 'nullable|string',
            'bahan_kombinasi' => 'nullable|string',
            'aksesoris' => 'nullable|string',
            'warna_yang_akan_dikeluarkan' => 'nullable|string',
            'harga_potong' => 'nullable|numeric',
            'harga_cmt' => 'nullable|numeric',
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

        $jsonFields = ['bahan_utama', 'bahan_kombinasi', 'aksesoris', 'warna_yang_akan_dikeluarkan'];
        foreach ($jsonFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = json_decode($data[$field], true);
            }
        }

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
            'bahan_utama' => 'nullable|string',
            'bahan_kombinasi' => 'nullable|string',
            'aksesoris' => 'nullable|string',
            'warna_yang_akan_dikeluarkan' => 'nullable|string',
            'harga_potong' => 'nullable|numeric',
            'harga_cmt' => 'nullable|numeric',
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

        $jsonFields = ['bahan_utama', 'bahan_kombinasi', 'aksesoris', 'warna_yang_akan_dikeluarkan'];
        foreach ($jsonFields as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = json_decode($data[$field], true);
            }
        }

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
