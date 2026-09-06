<?php

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DuitkuService
{
    protected string $merchantCode;
    protected string $apiKey;
    protected string $environment;

    public function __construct()
    {
        $this->merchantCode = (string) (AppSetting::get('duitku_merchant_code') ?: config('services.duitku.merchant_code', env('DUITKU_MERCHANT_CODE', '')));
        $this->apiKey = (string) (AppSetting::get('duitku_api_key') ?: config('services.duitku.api_key', env('DUITKU_API_KEY', '')));
        $this->environment = (string) (AppSetting::get('duitku_environment') ?: config('services.duitku.environment', env('DUITKU_ENV', 'sandbox')));
    }

    public function isConfigured(): bool
    {
        return !empty($this->merchantCode) && !empty($this->apiKey);
    }

    public function isSandbox(): bool
    {
        return strtolower($this->environment) !== 'production';
    }

    public function getJsUrl(): string
    {
        return $this->isSandbox()
            ? 'https://app-sandbox.duitku.com/lib/js/duitku.js'
            : 'https://app-prod.duitku.com/lib/js/duitku.js';
    }

    public function getCreateInvoiceUrl(): string
    {
        return $this->isSandbox()
            ? 'https://api-sandbox.duitku.com/api/merchant/createInvoice'
            : 'https://api-prod.duitku.com/api/merchant/createInvoice';
    }

    public function getMerchantCode(): string
    {
        return $this->merchantCode;
    }

    /**
     * Create invoice request for Duitku POP.
     * Returns array with reference, paymentUrl, and statusCode.
     */
    public function createInvoice(array $params): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Duitku payment gateway is not configured. Silakan hubungi admin.');
        }

        $timestamp = (string) round(microtime(true) * 1000);
        $signature = hash_hmac('sha256', $this->merchantCode . $timestamp, $this->apiKey);

        $payload = [
            'paymentAmount' => (int) $params['amount'],
            'merchantOrderId' => (string) $params['merchantOrderId'],
            'productDetails' => (string) ($params['productDetails'] ?? 'Pembayaran Template Undangan'),
            'email' => (string) ($params['email'] ?? 'customer@ayohadir.id'),
            'additionalParam' => (string) ($params['additionalParam'] ?? ''),
            'paymentMethod' => (string) ($params['paymentMethod'] ?? ''),
            'customerVaName' => (string) ($params['customerVaName'] ?? 'Pelanggan AyoHadir'),
            'callbackUrl' => (string) ($params['callbackUrl'] ?? route('api.payment.duitku.callback')),
            'returnUrl' => (string) ($params['returnUrl'] ?? url('/app/dashboard')),
            'expiryPeriod' => (int) ($params['expiryPeriod'] ?? 1440),
        ];

        if (!empty($params['phoneNumber'])) {
            $payload['phoneNumber'] = $params['phoneNumber'];
        }

        $url = $this->getCreateInvoiceUrl();

        Log::info('Duitku createInvoice request', [
            'url' => $url,
            'merchantOrderId' => $payload['merchantOrderId'],
            'amount' => $payload['paymentAmount'],
        ]);

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'x-duitku-signature' => $signature,
            'x-duitku-timestamp' => $timestamp,
            'x-duitku-merchantcode' => $this->merchantCode,
        ])->timeout(30)->post($url, $payload);

        $data = $response->json();

        if (!$response->successful() || empty($data) || ($data['statusCode'] ?? '') !== '00') {
            Log::error('Duitku createInvoice failed', [
                'status' => $response->status(),
                'response' => $data,
                'body' => $response->body(),
            ]);

            $message = $data['statusMessage'] ?? 'Gagal membuat invoice pembayaran Duitku.';
            throw new \Exception($message);
        }

        return $data;
    }

    /**
     * Validate webhook/callback signature from Duitku.
     */
    public function verifyCallbackSignature(string $merchantOrderId, string|int $amount, string $signature): bool
    {
        if (empty($this->apiKey) || empty($this->merchantCode)) {
            return false;
        }

        // SHA256 HMAC (Official Duitku POP Callback signature)
        $expectedSha256 = hash_hmac('sha256', $this->merchantCode . $amount . $merchantOrderId, $this->apiKey);
        if (hash_equals($expectedSha256, $signature)) {
            return true;
        }

        // Legacy MD5 fallback (if account uses MD5)
        $expectedMd5 = md5($this->merchantCode . $amount . $merchantOrderId . $this->apiKey);
        if (hash_equals($expectedMd5, $signature)) {
            return true;
        }

        return false;
    }
}
