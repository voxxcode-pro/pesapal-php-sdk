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

    public function __construct(PesapalConfig $config, $environment = 'sandbox', $sslVerify = false)
    {
        $this->config = $config;
        $this->baseUrl = $this->config->getApiUrl($environment);
        $this->httpClient = new Client();
        $this->sslVerify = $sslVerify;
    }

    /**
     * Retrieves an access token for authorization, using stored token if not expired.
     *
     * @return string
     * @throws PesapalException
     */
    public function getAccessToken()
    {
        // Check if an access token is already set and not expired
        $token = $this->config->getAccessToken();
        $expiresAt = $this->config->getAccessTokenExpiry();

        if ($token && $expiresAt && strtotime($expiresAt) > time()) {
            return $token;
        }

        // Fetch a new token if none exists or if expired
        $url = $this->baseUrl . '/Auth/RequestToken';
        $payload = [
            'consumer_key' => $this->config->getConsumerKey(),
            'consumer_secret' => $this->config->getConsumerSecret()
        ];
        $options = [
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json'
            ],
            'json' => $payload,
            'verify' => $this->sslVerify,
        ];

        try {
            $response = $this->httpClient->post($url, $options);
            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['token']) && isset($data['expiryDate'])) {
                $expiresAt = $data['expiryDate'];
                $this->config->setAccessToken($data['token'], $expiresAt);
                return $data['token'];
            } else {
                throw new PesapalException('Access token not found in response', 0, $data);
            }

        } catch (RequestException $e) {
            $responseBody = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : null;
            throw new PesapalException('Error getting access token: ' . $e->getMessage(), $e->getCode(), $responseBody, $e);
        }
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