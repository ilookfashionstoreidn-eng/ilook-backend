<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SpkCuttingDistribusi;

class SpkCuttingDistribusiController extends Controller
{
    public function index()
{
    $data = SpkCuttingDistribusi::orderBy('created_at', 'desc')->get();

    return response()->json([
        'data' => $data
    ]);
}

}
