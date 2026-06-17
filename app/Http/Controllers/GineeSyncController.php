<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\GineeApiService;
use App\Models\GineeProduct;
use Illuminate\Support\Facades\Log;

class GineeSyncController extends Controller
{
    protected $gineeService;

    public function __construct(GineeApiService $gineeService)
    {
        $this->gineeService = $gineeService;
    }

    /**
     * Sync data dari Ginee Open API ke database lokal
     */
    public function sync()
    {
        try {
            $page = 0;
            $hasMore = true;
            $syncedCount = 0;

            while ($hasMore) {
                // Panggil Ginee API
                $response = $this->gineeService->getProducts($page, 100);
                
                // Coba ambil dari 'content', atau fallback ke 'list', atau langsung 'data' jika data adalah array list
                $products = [];
                if (isset($response['data']['content']) && is_array($response['data']['content'])) {
                    $products = $response['data']['content'];
                } elseif (isset($response['data']['list']) && is_array($response['data']['list'])) {
                    $products = $response['data']['list'];
                } elseif (isset($response['data']) && is_array($response['data']) && isset($response['data'][0])) {
                    $products = $response['data'];
                }
                
                foreach ($products as $masterProduct) {
                    $productName = $masterProduct['name'] ?? $masterProduct['product_name'] ?? $masterProduct['productName'] ?? '';
                    $images = $masterProduct['images'] ?? [];
                    $imageUrl = !empty($images) ? $images[0] : null;

                    $category = !empty($masterProduct['fullCategoryName']) ? implode(' > ', $masterProduct['fullCategoryName']) : null;
                    $status = $masterProduct['masterProductStatus'] ?? null;
                    $description = $masterProduct['description'] ?? $masterProduct['shortDescription'] ?? null;
                    $createdAtGinee = isset($masterProduct['createDatetime']) ? \Carbon\Carbon::parse($masterProduct['createDatetime'])->format('Y-m-d H:i:s') : null;
                    $updatedAtGinee = isset($masterProduct['updateDatetime']) ? \Carbon\Carbon::parse($masterProduct['updateDatetime'])->format('Y-m-d H:i:s') : null;

                    $variations = $masterProduct['variationBriefs'] ?? [];
                    
                    if (empty($variations)) {
                        // Jika tidak ada variasi, gunakan sku dari master
                        $sku = $masterProduct['sku'] ?? $masterProduct['masterSku'] ?? null;
                        if ($sku) {
                            GineeProduct::updateOrCreate(
                                ['sku' => $sku],
                                [
                                    'product_name' => $productName,
                                    'color' => null,
                                    'size' => null,
                                    'image_url' => $imageUrl,
                                    'category' => $category,
                                    'status' => $status,
                                    'description' => $description,
                                    'created_at_ginee' => $createdAtGinee,
                                    'updated_at_ginee' => $updatedAtGinee,
                                    'last_synced_at' => now(),
                                ]
                            );
                            $syncedCount++;
                        }
                    } else {
                        // Looping setiap variasi
                        foreach ($variations as $variation) {
                            $sku = $variation['sku'] ?? null;
                            if (!$sku) continue;

                            // Option values biasanya warna dan ukuran, misal: ["COKLAT", "S"]
                            $optionValues = $variation['optionValues'] ?? [];
                            $color = $optionValues[0] ?? null;
                            $size = $optionValues[1] ?? null;

                            GineeProduct::updateOrCreate(
                                ['sku' => $sku],
                                [
                                    'product_name' => $productName,
                                    'color' => $color,
                                    'size' => $size,
                                    'image_url' => $imageUrl,
                                    'category' => $category,
                                    'status' => $status,
                                    'description' => $description,
                                    'created_at_ginee' => $createdAtGinee,
                                    'updated_at_ginee' => $updatedAtGinee,
                                    'last_synced_at' => now(),
                                ]
                            );
                            $syncedCount++;
                        }
                    }
                }

                $page++;
                // Ginee mengembalikan 'total' (jumlah master product), bukan 'totalPages'
                $total = $response['data']['total'] ?? 0;
                $totalPages = ceil($total / 100);
                
                if ($page >= $totalPages) {
                    $hasMore = false;
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Berhasil sinkronisasi $syncedCount produk dari Ginee."
            ]);

        } catch (\Exception $e) {
            Log::error('Ginee Sync Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Endpoint untuk Autocomplete/Pencarian SKU di Frontend
     */
    public function searchSku(Request $request)
    {
        $keyword = $request->query('q');

        $query = GineeProduct::query();

        if ($keyword) {
            $query->where('sku', 'like', "%{$keyword}%")
                  ->orWhere('product_name', 'like', "%{$keyword}%");
        }

        $products = $query->orderBy('last_synced_at', 'desc')->limit(200)->get();

        return response()->json($products);
    }

    /**
     * Endpoint untuk mendapatkan semua varian berdasarkan product_name
     */
    public function getVariants(Request $request)
    {
        $productName = $request->query('product_name');

        if (!$productName) {
            return response()->json([]);
        }

        $variants = GineeProduct::where('product_name', $productName)
            ->orderBy('sku', 'asc')
            ->get();

        return response()->json($variants);
    }
}
