<?php

//src/Api/PesapalClient.php

namespace Katorymnd\PesapalPhpSdk\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Katorymnd\PesapalPhpSdk\Config\PesapalConfig;
use Katorymnd\PesapalPhpSdk\Exceptions\PesapalException;

class PesapalClient
{
    private $config;
    private $baseUrl;
    private $httpClient;
    private $sslVerify;
    private $environment;

     public function __construct(PesapalConfig $config,
                                string        $environment = 'sandbox',
                                bool          $sslVerify   = false)
    {
        $this->config      = $config;
        $this->environment = $environment;          // ← remember it
        $this->baseUrl     = $this->config->getApiUrl($environment);
        $this->httpClient  = new Client();
        $this->sslVerify   = $sslVerify;
    }

   /**
     * Retrieves a valid OAuth2 access-token.
     *
     * Caching rules:
     *   • token not expired AND
     *   • token minted for the current environment  ⟹ reuse
     * Pass $forceRefresh = true to ignore cache.
     *
     * @param  bool $forceRefresh Force renewal even if cache looks valid.
     * @return string             Bearer token.
     * @throws PesapalException   Wrapped network / validation failure.
     */
    public function getAccessToken(bool $forceRefresh = false): string
    {
        $token      = $this->config->getAccessToken();
        $expiresAt  = $this->config->getAccessTokenExpiry();
        $tokenEnv   = $this->config->getTokenEnvironment();

        $tokenValid = $token
                   && $expiresAt
                   && strtotime($expiresAt) > time()
                   && $tokenEnv === $this->environment;

        if (!$forceRefresh && $tokenValid) {
            return $token;           // 🙌 still good
        }

        /* ––––– Request a fresh token ––––– */
        $endpoint = $this->baseUrl . '/Auth/RequestToken';

        try {
            $resp = $this->httpClient->post($endpoint, [
                'json'    => [
                    'consumer_key'    => $this->config->getConsumerKey(),
                    'consumer_secret' => $this->config->getConsumerSecret(),
                ],
                'headers' => [
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'verify'  => $this->sslVerify,
            ]);

            $data = json_decode($resp->getBody()->getContents(), true);

            if (!isset($data['token'], $data['expiryDate'])) {
                throw new PesapalException(
                    'Access token not found in response',
                    0,
                    $data
                );
            }

            /* Persist new token and environment */
            $this->config->setAccessToken(
                $data['token'],
                $data['expiryDate'],
                $this->environment
            );

            return $data['token'];
        } catch (RequestException $e) {
            $body = $e->hasResponse()
                ? $e->getResponse()->getBody()->getContents()
                : null;

            throw new PesapalException(
                'Error getting access token: ' . $e->getMessage(),
                (int) $e->getCode(),
                $body,
                $e
            );
        }
    }

    /**
     * Wipe any cached token – handy straight after a 401.
     */
    public function clearAccessToken(): void
    {
        $this->config->clearAccessToken();
    }

    /**
     * Makes an API request to the Pesapal API.
     *
     * @param string $endpoint
     * @param string $method
     * @param array|null $data
     * @return array
     * @throws PesapalException
     */
    private function makeApiRequest($endpoint, $method = 'POST', $data = null)
    {
        $url = $this->baseUrl . $endpoint;
        $options = [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->getAccessToken(),
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'verify' => $this->sslVerify,
        ];

        if ($method === 'POST' && $data) {
            $options['json'] = $data;
        } elseif ($method === 'GET' && $data) {
            $options['query'] = $data;
        }

        try {
            $response = $this->httpClient->request($method, $url, $options);
            return [
                'status' => $response->getStatusCode(),
                'response' => json_decode($response->getBody()->getContents(), true),
            ];
        } catch (RequestException $e) {
            $responseBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null;
            throw new PesapalException('Request Error: ' . $e->getMessage(), $e->getCode(), $responseBody, $e);
        }
    }

    /**
     * Submits an order request to the Pesapal API.
     *
     * @param array $paymentData
     * @return array
     * @throws PesapalException
     */
    public function submitOrderRequest(array $paymentData)
    {
        return $this->makeApiRequest('/Transactions/SubmitOrderRequest', 'POST', $paymentData);
    }

    /**
 * Retrieves the transaction status from the Pesapal API.
 *
 * @param string $orderTrackingId
 * @return array
 * @throws PesapalException
 */
    public function getTransactionStatus(string $orderTrackingId)
    {
        // Corrected parameter name
        $data = ['orderTrackingId' => $orderTrackingId];
        return $this->makeApiRequest('/Transactions/GetTransactionStatus', 'GET', $data);
    }

    /**
     * Cancels an order on the Pesapal API.
     *
     * @param string $orderTrackingId
     * @return array
     * @throws PesapalException
     */
    public function cancelOrder(string $orderTrackingId)
    {
        $data = ['order_tracking_id' => $orderTrackingId];
        return $this->makeApiRequest('/Transactions/CancelOrder', 'POST', $data);
    }

    /**
     * Requests a refund from the Pesapal API.
     *
     * @param array $refundData
     * @return array
     * @throws PesapalException
     */
    public function requestRefund(array $refundData)
    {
        return $this->makeApiRequest('/Transactions/RefundRequest', 'POST', $refundData);
    }

    /**
     * Registers an IPN URL and saves the IPN details in the configuration.
     *
     * @param string $ipnUrl
     * @param string $notificationType
     * @return array
     * @throws PesapalException
     */
    public function registerIpnUrl(string $ipnUrl, string $notificationType = 'POST')
    {
        $data = [
            'url' => $ipnUrl,
            'ipn_notification_type' => $notificationType
        ];
        $response = $this->makeApiRequest('/URLSetup/RegisterIPN', 'POST', $data);

        if (isset($response['response']['ipn_id'])) {
            $this->config->setIpnDetails($ipnUrl, $response['response']['ipn_id']);
        }

        return $response;
    }

    /**
     * Retrieves the list of registered IPNs.
     *
     * @return array
     * @throws PesapalException
     */
    public function getRegisteredIpns()
    {
        return $this->makeApiRequest('/URLSetup/GetIpnList', 'GET');
    }
}