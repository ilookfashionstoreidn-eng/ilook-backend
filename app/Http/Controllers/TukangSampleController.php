<?php

namespace App\Http\Controllers;

use App\Models\TukangSample;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TukangSampleController extends Controller
{
    public function index()
    {
        $tukangSamples = TukangSample::orderBy('id', 'desc')->get();
        return response()->json([
            'status' => 'success',
            'data' => $tukangSamples
        ], 200);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama_tukang_sample' => 'required|string|max:255',
            'nomor_hp' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $tukangSample = TukangSample::create($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Tukang Sample created successfully',
            'data' => $tukangSample
        ], 201);
    }

    public function show($id)
    {
        $tukangSample = TukangSample::find($id);

        if (!$tukangSample) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tukang Sample not found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $tukangSample
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $tukangSample = TukangSample::find($id);

        if (!$tukangSample) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tukang Sample not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nama_tukang_sample' => 'sometimes|required|string|max:255',
            'nomor_hp' => 'nullable|string|max:20',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        $tukangSample->update($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Tukang Sample updated successfully',
            'data' => $tukangSample
        ], 200);
    }

    public function destroy($id)
    {
        $tukangSample = TukangSample::find($id);

        if (!$tukangSample) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tukang Sample not found'
            ], 404);
        }

        $tukangSample->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Tukang Sample deleted successfully'
        ], 200);
    }
}
