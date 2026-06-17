<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Helpers\GineeSignature;

class GineeApiService
{
    protected $accessKey;
    protected $secretKey;
    protected $host;
    protected $country;

    public function __construct()
    {
        $this->accessKey = env('GINEE_ACCESS_KEY');
        $this->secretKey = env('GINEE_SECRET_KEY');
        $this->country = env('GINEE_COUNTRY', 'ID');
        $this->host = env('GINEE_API_HOST', 'https://api.ginee.com');
    }

    /**
     * Fetch Master Products from Ginee
     */
    public function getProducts($page = 1, $size = 50)
    {
        if (!$this->accessKey || !$this->secretKey) {
            throw new \Exception("Ginee Access Key atau Secret Key belum dikonfigurasi di file .env");
        }

        $endpoint = '/openapi/product/master/v1/list';
        
        $signature = str_replace(["\r", "\n"], '', GineeSignature::generate('POST', $endpoint, $this->secretKey));

        $headers = [
            'Content-Type' => 'application/json',
            'X-Advai-Country' => $this->country,
            'Authorization' => $this->accessKey . ':' . $signature
        ];

        $payload = [
            'page' => $page,
            'size' => $size,
        ];

        $response = Http::withHeaders($headers)
            ->post($this->host . $endpoint, $payload);

        if ($response->failed()) {
            Log::error('Ginee API Error: ' . $response->body());
            throw new \Exception('Gagal mengambil data dari Ginee API: ' . $response->body());
        }

        return $response->json();
    }
}
