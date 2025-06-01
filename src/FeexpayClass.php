<?php

declare(strict_types=1);

namespace Feexpay\FeexpayPhp;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
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
            'timeout' => 30
        ]);
    }

    private function arrayToJsonString(array $data): string
    {
        $jsonParts = [];
        foreach ($data as $key => $value) {
            $escapedValue = addslashes((string) $value);
            $jsonParts[] = "\"$key\": \"$escapedValue\"";
        }
        return '{' . implode(', ', $jsonParts) . '}';
    }


    private function makeJsonRequest(string $method, string $endpoint, array $data): array
    {
        $body = $this->arrayToJsonString($data);

        Log::debug('API data sent', [
                'data' => $body,
        ]);

        if (!$body) {
            throw new RuntimeException("Failed to encode JSON body: " . json_last_error_msg());
        }

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json'
        ];

        $request = new Request($method, $endpoint, $headers, $body);

        try {
            $response = $this->httpClient->send($request);
            $responseData = json_decode($response->getBody()->getContents(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException("Invalid JSON response: " . json_last_error_msg());
            }

            Log::debug('API Response', [
                'endpoint' => $endpoint,
                'status' => $response->getStatusCode(),
                'response' => $responseData
            ]);

            return $responseData;
        } catch (GuzzleException $e) {
            $errorResponse = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : 'No response';
            Log::error("API request failed", [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
                'response' => $errorResponse,
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
            'otp' => $otp
        ];

        $responseData = $this->makeJsonRequest(
            'POST',
            '/api/transactions/requesttopay/integration',
            $data
        );

        if (!isset($responseData['reference'])) {
            throw new RuntimeException("Missing reference in API response: " . json_encode($responseData));
        }

        return $responseData['reference'];
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
            throw new RuntimeException("Missing payment_url in API response: " . json_encode($responseData));
        }

        return [
            'payment_url' => $responseData['payment_url'],
            'reference' => $responseData['reference'],
            'order_id' => $responseData['order_id'] ?? null
        ];
    }

    public function getPaiementStatus(string $paiementRef): array
    {
        try {
            $response = $this->httpClient->get("/api/transactions/getrequesttopay/integration/$paiementRef");
            $statusData = json_decode($response->getBody()->getContents(), true);

            if (!isset($statusData['status'])) {
                throw new RuntimeException("Invalid payment status response");
            }

            $payer = $statusData['payer'] ?? ['partyId' => 'N/A'];

            return [
                "amount" => $statusData['amount'] ?? 0,
                "clientNum" => $payer['partyId'],
                "status" => $statusData['status'],
                "reference" => $statusData['reference'] ?? $paiementRef
            ];
        } catch (GuzzleException $e) {
            Log::error("Failed to get payment status", [
                'reference' => $paiementRef,
                'error' => $e->getMessage()
            ]);
            throw new RuntimeException("Failed to get payment status: " . $e->getMessage());
        }
    }
}
