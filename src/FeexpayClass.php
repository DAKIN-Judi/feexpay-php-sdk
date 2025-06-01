<?php

declare(strict_types=1);

namespace Feexpay\FeexpayPhp;

use GuzzleHttp\Client;
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
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ]
        ]);
    }

    public function init(
        float $amount,
        string $componentId,
        bool $use_custom_button = false,
        string $custom_button_id = "",
        string $description = "",
        string $callback_info = ""
    ): void {
        echo "
        <script src='https://api.feexpay.me/feexpay-javascript-sdk/index.js'></script>
        <script type='text/javascript'>
            FeexPayButton.init('$componentId', {
                id: '$this->id',
                amount: $amount,
                token: '$this->token',
                callback_url: '$this->callback_url',
                mode: 'LIVE',
                custom_button: '$use_custom_button',
                id_custom_button: '$custom_button_id',
                description: '$description',
                callback_info: '$callback_info',
                error_callback_url: '$this->error_callback_url',
            })
        </script>";
    }

    public function getIdAndMarchanName(): ?object
    {
        try {
            $response = $this->httpClient->get("/api/shop/{$this->id}/get_shop");
            return json_decode($response->getBody()->getContents());
        } catch (GuzzleException $e) {
            Log::error("Failed to get merchant info", ['error' => $e->getMessage()]);
            throw new RuntimeException("Id Request not sent: " . $e->getMessage());
        }
    }

    private function makeApiRequest(string $endpoint, array $data): object
    {
        try {
            $response = $this->httpClient->post($endpoint, [
                'json' => $data, // Utilisation de 'json' au lieu de 'form_params'
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token, // Ajout du token dans le header si nécessaire
                ]
            ]);

            $responseData = json_decode($response->getBody()->getContents());

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException("Invalid JSON response from API");
            }

            if (!isset($responseData->success) || !$responseData->success) {
                $errorMsg = $responseData->message ?? 'Unknown error from API';
                throw new RuntimeException("API Error: " . $errorMsg);
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
            $operatorName = str_replace(' ', '', $operatorName);

            $responseData = $this->makeApiRequest('/api/transactions/requesttopay/integration', [
                'phoneNumber' => $phoneNumber,
                'amount' => $amount,
                'reseau' => $operatorName,
                'token' => $this->token,
                'shop' => $this->id,
                'first_name' => $fullname,
                'email' => $email,
                'callback_info' => $callback_info,
                'reference' => $custom_id,
                'otp' => $otp
            ]);

            return $responseData->reference;
        } catch (RuntimeException $e) {
            Log::error("Payment failed", ['error' => $e->getMessage()]);
            throw new RuntimeException("Payment request not sent: " . $e->getMessage());
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
        $merchantInfo = $this->getIdAndMarchanName();

        if (!isset($merchantInfo->name)) {
            throw new RuntimeException("Merchant information not found");
        }

        try {
            $responseData = $this->makeApiRequest('/api/transactions/requesttopay/integration', [
                'phoneNumber' => $phoneNumber,
                'amount' => $amount,
                'reseau' => $operatorName,
                'token' => $this->token,
                'shop' => $this->id,
                'first_name' => $fullname,
                'email' => $email,
                'callback_info' => $callback_info,
                'reference' => $custom_id,
                'return_url' => $return_url,
                'cancel_url' => $cancel_url
            ]);

            if ($responseData->status === "FAILED") {
                throw new RuntimeException("Incorrect parameters: " . ($responseData->message ?? ''));
            }

            return [
                'payment_url' => $responseData->payment_url,
                'reference' => $responseData->reference,
                'order_id' => $responseData->order_id
            ];
        } catch (RuntimeException $e) {
            Log::error("Web payment failed", ['error' => $e->getMessage()]);
            throw new RuntimeException("Payment request not sent: " . $e->getMessage());
        }
    }

    public function paiementCard(
        float $amount,
        string $phoneNumber,
        string $typeCard,
        string $firstName,
        string $lastName,
        string $email,
        string $country,
        string $address,
        string $district,
        string $currency,
        string $callback_info,
        string $custom_id
    ): array {
        $merchantInfo = $this->getIdAndMarchanName();
        $systemCardPay = $merchantInfo->systemCardPay ?? null;

        try {
            $responseData = $this->makeApiRequest('/api/transactions/card/inittransact/integration', [
                'phone' => $phoneNumber,
                'amount' => $amount,
                'reseau' => $typeCard,
                'token' => $this->token,
                'shop' => $this->id,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'country' => $country,
                'address1' => $address,
                'district' => $district,
                'currency' => $currency,
                'callback_info' => $callback_info,
                'reference' => $custom_id,
                'systemCardPay' => $systemCardPay,
            ]);

            if (!isset($responseData->url)) {
                throw new RuntimeException("Unexpected API response: " . ($responseData->message ?? ''));
            }

            return [
                'url' => $responseData->url,
                'reference' => $responseData->reference,
            ];
        } catch (RuntimeException $e) {
            Log::error("Card payment failed", ['error' => $e->getMessage()]);
            throw new RuntimeException("Payment request not sent: " . $e->getMessage());
        }
    }

    public function getPaiementStatus(string $paiementRef): array
    {
        try {
            $response = $this->httpClient->get("/api/transactions/getrequesttopay/integration/$paiementRef", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token,
                ]
            ]);

            $statusData = json_decode($response->getBody()->getContents());

            if (!isset($statusData->status)) {
                throw new RuntimeException("Invalid payment status response");
            }

            $payer = $statusData->payer ?? (object)['partyId' => 'N/A'];

            return [
                "amount" => $statusData->amount ?? 0,
                "clientNum" => $payer->partyId,
                "status" => $statusData->status,
                "reference" => $statusData->reference ?? $paiementRef
            ];
        } catch (GuzzleException $e) {
            Log::error("Failed to get payment status", ['reference' => $paiementRef, 'error' => $e->getMessage()]);
            throw new RuntimeException("Status request not sent: " . $e->getMessage());
        }
    }
}
