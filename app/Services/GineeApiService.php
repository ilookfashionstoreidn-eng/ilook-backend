<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
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

        try {
            // connectTimeout pendek supaya tidak menggantung ~130 detik (default TCP Linux)
            // jika server tidak bisa menjangkau api.ginee.com
            $response = Http::withOptions(['connect_timeout' => (int) env('GINEE_CONNECT_TIMEOUT', 15)])
                ->timeout((int) env('GINEE_TIMEOUT', 90))
                ->retry(3, 3000, fn ($e) => $e instanceof ConnectionException)
                ->withHeaders($headers)
                ->post($this->host . $endpoint, $payload);
        } catch (RequestException $e) {
            // Dengan retry() Laravel 8 melempar exception untuk response non-2xx
            $response = $e->response;
        } catch (ConnectionException $e) {
            Log::error('Ginee API Connection Error: ' . $e->getMessage());
            throw new \Exception(
                'Server tidak bisa terhubung ke ' . parse_url($this->host, PHP_URL_HOST)
                . ' (koneksi timeout). Cek koneksi keluar / firewall server ke port 443, '
                . 'atau IP server mungkin belum di-whitelist oleh Ginee.'
            );
        }

        if ($response->failed()) {
            Log::error('Ginee API Error: ' . $response->body());
            throw new \Exception('Gagal mengambil data dari Ginee API: ' . $response->body());
        }

        return $response->json();
    }
}
