<?php                                                                                                                                                                                            
               
namespace App\Http\Controllers;

use App\Models\PembelianB;
use App\Models\PembelianA;
use App\Models\StokAksesoris;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Milon\Barcode\Facades\DNS1D;
use SimpleSoftwareIO\QrCode\Facades\QrCode;



class PembelianBController extends Controller
{
    public function index()
    {
        $pembelianB = PembelianB::with(['pembelianA.aksesoris', 'user'])->get();
        foreach ($pembelianB as $item) {
            $item->user_name = $item->user->name; 
        }
    
        return response()->json($pembelianB);
    }
    

   
   public function store(Request $request)
    {
        $request->validate([
            'pembelian_a_id' => 'required|exists:pembelian_aksesoris_a,id',
            'user_id' => 'required|exists:users,id',
            'jumlah_terverifikasi' => 'required|integer',
        ]);

        $pembelianA = PembelianA::findOrFail($request->pembelian_a_id);

    
        if ((int) $request->jumlah_terverifikasi !== (int) $pembelianA->jumlah) {
            return response()->json([
                'message' => 'Jumlah terverifikasi tidak sesuai.'
            ], 422);
        }
        $pembelianB = PembelianB::create([
            'pembelian_a_id' => $request->pembelian_a_id,
            'user_id' => $request->user_id,
            'jumlah_terverifikasi' => $request->jumlah_terverifikasi,
            'status_verifikasi' => 'valid',
        ]);

        $this->generateBarcodeForPembelianB($pembelianB);

        return response()->json([
            'message' => 'Pembelian B berhasil disimpan dan status valid.',
            'data' => $pembelianB
        ], 201);
    }

    private function generateBarcodeForPembelianB($pembelianB)
    {
        
        $aksesoris = $pembelianB->pembelianA->aksesoris; 
    
        for ($i = 0; $i < $pembelianB->jumlah_terverifikasi; $i++) {
           
            $barcode = 'barcode-' . uniqid();
    

            StokAksesoris::create([
                'pembelian_aksesoris_b_id' => $pembelianB->id,
                'aksesoris_id' => $aksesoris->id,
                'barcode' => $barcode,
                'status' => 'tersedia',
            ]);
        }
    }

       public function downloadBarcodes($id)
    {
        $pembelianB = PembelianB::with(['stokAksesoris', 'pembelianA.aksesoris'])->findOrFail($id);
        $barcodes = $pembelianB->stokAksesoris;
        $pdf = Pdf::loadView('pdf.barcode_stok_aksesoris', [
            'barcodes'    => $barcodes,
            'pembelianB'  => $pembelianB,
        ])->setPaper([0, 0, 141.73, 141.73], 'portrait');

        $pembelianB->update(['barcode_downloaded' => true]);

        return $pdf->download("barcode-{$pembelianB->id}.pdf");
    }

    


    public function show($id)
    {
        //
    }

   
    public function update(Request $request, $id)
    {
        //
    }

    public function destroy($id)
    {
        //
    }
}
