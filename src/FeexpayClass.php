<?php

declare(strict_types=1);

namespace Feexpay\FeexpayPhp;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;

class FeexpayClass
{
    private Client $httpClient;
    public string $id;
    public string $token;
    public string $callback_url;
    public string $error_callback_url;
    public string $mode;

    public function __construct(
        string $id,
        string $token,
        string $callback_url,
        string $mode = 'LIVE',
        string $error_callback_url = ''
    ) {
        $this->id = $id;
        $this->token = $token;
        $this->callback_url = $callback_url;
        $this->error_callback_url = $error_callback_url;
        $this->mode = $mode;

        $this->httpClient = new Client([
            'base_uri' => 'https://api.feexpay.me',
            'verify' => __DIR__ . DIRECTORY_SEPARATOR . 'certificats/IXRCERT.crt',
            'timeout' => 30,
        ]);
    }

    private function makeJsonRequest(string $method, string $endpoint, array $data): array
    {
        $headers = [
            'Content-Type' => 'application/json',
        ];

        $body = json_encode($data);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Failed to encode JSON: " . json_last_error_msg());
        }

        $request = new Request($method, $endpoint, $headers, $body);

        try {
            $response = $this->httpClient->send($request);
            $responseData = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException("Invalid JSON response from API");
            }

            return $responseData;
        } catch (GuzzleException $e) {
            Log::error("API request failed", [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw new RuntimeException("API request failed: " . $e->getMessage());
        }
    }

    public function paiementLocal(
        float $amount,
        string $phoneNumber,
        string $operatorName,
        string $fullname,
        string $email,
        string $callback_info,
        string $custom_id,
        string $otp = ""
    ): string {
        try {
            $data = [
                'shop' => $this->id,
                'token' => $this->token,
                'callback_url' => $this->callback_url,
                'error_callback_url' => $this->error_callback_url,
                'mode' => $this->mode,
                'first_name' => $fullname,
                'email' => $email,
                'phoneNumber' => $phoneNumber,
                'reseau' => $operatorName,
                'amount' => $amount,
                'callback_info' => $callback_info,
                'reference' => $custom_id,
            ];

            if (!empty($otp)) {
                $data['otp'] = $otp;
            }

            $responseData = $this->makeJsonRequest(
                'POST',
                '/api/transactions/requesttopay/integration',
                $data
            );

            if (!isset($responseData['reference'])) {
                throw new RuntimeException("Missing reference in API response");
            }

            return $responseData['reference'];
        } catch (RuntimeException $e) {
            Log::error("Payment failed", ['error' => $e->getMessage()]);
            throw new RuntimeException("Payment request failed: " . $e->getMessage());
        }
    }

    public function requestToPayWeb(
        float $amount,
        string $phoneNumber,
        string $operatorName,
        string $fullname,
        string $email,
        string $callback_info,
        string $custom_id,
        string $cancel_url = "",
        string $return_url = ""
    ): array {
        try {
            $data = [
                'shop' => $this->id,
                'token' => $this->token,
                'callback_url' => $this->callback_url,
                'error_callback_url' => $this->error_callback_url,
                'mode' => $this->mode,
                'first_name' => $fullname,
                'email' => $email,
                'phoneNumber' => $phoneNumber,
                'reseau' => $operatorName,
                'amount' => $amount,
                'callback_info' => $callback_info,
                'reference' => $custom_id,
                'return_url' => $return_url,
                'cancel_url' => $cancel_url
            ];

            $responseData = $this->makeJsonRequest(
                'POST',
                '/api/transactions/requesttopay/integration',
                $data
            );

            if (isset($responseData['status']) && $responseData['status'] === "FAILED") {
                throw new RuntimeException($responseData['message'] ?? "Payment failed");
            }

            if (!isset($responseData['payment_url'])) {
                throw new RuntimeException("Missing payment_url in API response");
            }

            return [
                'payment_url' => $responseData['payment_url'],
                'reference' => $responseData['reference'],
                'order_id' => $responseData['order_id'] ?? null
            ];
        } catch (RuntimeException $e) {
            Log::error("Web payment failed", ['error' => $e->getMessage()]);
            throw new RuntimeException("Web payment failed: " . $e->getMessage());
        }
    }

    // ... (les autres méthodes restent similaires avec adaptation du format JSON)
}
