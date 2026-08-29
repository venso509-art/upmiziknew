<?php
/**
 * UpMizik - MonCash Payment Gateway Service (Sandbox / Production)
 * 
 * Jere tout entegrasyon peman MonCash pou donasyon ak enskripsyon atis.
 */

require_once dirname(__DIR__) . '/config/database.php';

class MonCashService {
    private string $clientId;
    private string $clientSecret;
    private string $environment;
    private string $returnUrl;
    private string $cancelUrl;
    private string $webhookSecret;

    public function __construct() {
        $this->clientId = env('MONCASH_CLIENT_ID', '');
        $this->clientSecret = env('MONCASH_CLIENT_SECRET', '');
        $this->environment = strtolower(env('MONCASH_ENVIRONMENT', 'sandbox'));
        $this->returnUrl = env('MONCASH_RETURN_URL', SITE_URL . '/backend/api/donations.php?action=return');
        $this->cancelUrl = env('MONCASH_CANCEL_URL', SITE_URL . '/backend/api/donations.php?action=cancel');
        $this->webhookSecret = env('MONCASH_WEBHOOK_SECRET', '');
    }

    public function isSandbox(): bool {
        return $this->environment === 'sandbox' || empty($this->clientId);
    }

    private function getApiBaseUrl(): string {
        return $this->isSandbox()
            ? 'https://sandbox.moncashbutton.digicelgroup.com/Api'
            : 'https://moncashbutton.digicelgroup.com/Api';
    }

    private function getPaymentGatewayUrl(string $token): string {
        return $this->isSandbox()
            ? "https://sandbox.moncashbutton.digicelgroup.com/Moncash-middleware/Payment/Redirect?token={$token}"
            : "https://moncashbutton.digicelgroup.com/Moncash-middleware/Payment/Redirect?token={$token}";
    }

    /**
     * Rekipere OAuth Bearer Token soti nan MonCash
     */
    public function getAccessToken(): ?string {
        if (empty($this->clientId) || empty($this->clientSecret)) {
            // Si pa gen vrè credentials, retounen yon mock token pou sandbox
            return $this->isSandbox() ? 'sandbox_mock_token_' . time() : null;
        }

        $url = $this->getApiBaseUrl() . '/oauth/token';
        $credentials = base64_encode($this->clientId . ':' . $this->clientSecret);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'scope=read,write&grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Authorization: Basic ' . $credentials
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            return $data['access_token'] ?? null;
        }

        return null;
    }

    /**
     * Kreye yon demann peman (Payment Order)
     */
    public function createPayment(string $orderId, float $amount, string $description = 'Donasyon UpMizik'): array {
        // Asire montan an se yon nonb antye oswa 2 chif apre vigil
        $amountFormatted = number_format($amount, 2, '.', '');

        if (empty($this->clientId) || empty($this->clientSecret)) {
            // Mock sandbox payment pou dev & tès lokal
            $mockToken = 'sandbox_token_' . bin2hex(random_bytes(8));
            return [
                'success' => true,
                'mode' => 'sandbox',
                'payment_token' => $mockToken,
                'redirect_url' => SITE_URL . "/don.php?mock_pay=1&order_id={$orderId}&amount={$amount}",
                'message' => 'Sandbox mode: Peman an simule ak siksè.'
            ];
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return [
                'success' => false,
                'mode' => $this->environment,
                'message' => 'Echèk otantifikasyon MonCash API.'
            ];
        }

        $url = $this->getApiBaseUrl() . '/v1/CreatePayment';
        $payload = json_encode([
            'amount' => (float)$amountFormatted,
            'orderId' => $orderId
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 || $httpCode === 202) {
            $data = json_decode($response, true);
            $token = $data['payment_token']['token'] ?? null;
            if ($token) {
                return [
                    'success' => true,
                    'mode' => $this->environment,
                    'payment_token' => $token,
                    'redirect_url' => $this->getPaymentGatewayUrl($token),
                    'message' => 'Lòd peman kreye avèk siksè.'
                ];
            }
        }

        return [
            'success' => false,
            'mode' => $this->environment,
            'message' => 'Erè pandan kreyasyon lòd peman MonCash: ' . ($response ?: 'Koneksyon echwe')
        ];
    }

    /**
     * Verifye estati yon tranzaksyon MonCash pa Transaction ID oswa Order ID
     */
    public function verifyPayment(string $transactionId, ?string $orderId = null): array {
        if ($this->isSandbox() && (str_starts_with($transactionId, 'mock_') || empty($this->clientId))) {
            return [
                'success' => true,
                'mode' => 'sandbox',
                'status' => 'paid',
                'transaction_id' => $transactionId,
                'order_id' => $orderId,
                'message' => 'Sandbox verification: Peman konfime.'
            ];
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return [
                'success' => false,
                'message' => 'Echèk rekipere token MonCash.'
            ];
        }

        // Tcheke pa TransactionId
        $url = $this->getApiBaseUrl() . '/v1/RetrieveTransactionPayment';
        $payload = json_encode(['transactionId' => $transactionId]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $accessToken
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            $paymentStatus = $data['payment']['status'] ?? $data['status'] ?? 'unknown';
            $isPaid = (strtolower($paymentStatus) === 'successful' || strtolower($paymentStatus) === 'paid' || $paymentStatus === '200');

            return [
                'success' => true,
                'is_paid' => $isPaid,
                'status' => $isPaid ? 'paid' : 'pending',
                'details' => $data
            ];
        }

        return [
            'success' => false,
            'is_paid' => false,
            'status' => 'unverified',
            'message' => 'Tranzaksyon an poko valide pa MonCash.'
        ];
    }
}
