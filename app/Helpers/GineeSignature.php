<?php
namespace App\Helpers;

class GineeSignature
{
    /**
     * Generate signature sesuai spec Ginee:
     * signatureString = UPPER(HTTP_METHOD) + '$' + REQUEST_URI + '$'
     * lalu HMAC-SHA256 dengan secret key dan base64 encode hasilnya.
     */
    public static function generate(string $httpMethod, string $requestUri, string $secretKey): string
    {
        $signatureString = strtoupper($httpMethod) . '$' . $requestUri . '$';
        $hash = hash_hmac('sha256', $signatureString, $secretKey, true);
        return base64_encode($hash);
    }
}
