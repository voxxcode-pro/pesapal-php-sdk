<?php

namespace Katorymnd\PesapalPhpSdk\Tests\Integration\Api;

use Katorymnd\PesapalPhpSdk\Api\PesapalClient;
use Katorymnd\PesapalPhpSdk\Config\PesapalConfig;
use Dotenv\Dotenv;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for PesapalClient.
 *
 * These tests interact with the actual Pesapal sandbox API to validate the SDK's functionality.
 */
class PesapalClientIntegrationTest extends TestCase
{
    /**
     * @var PesapalClient
     */
    private $client;

    /**
     * @var PesapalConfig
     */
    private $config;

    /**
     * Sets up the integration test environment with real sandbox credentials.
     *
     * @return void
     */
    protected function setUp(): void
    {
        // Load environment variables from .env
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../../');
        $dotenv->load();

        // Retrieve sandbox credentials from environment variables
        $consumerKey = $_ENV['PESAPAL_CONSUMER_KEY'] ?? null;
        $consumerSecret = $_ENV['PESAPAL_CONSUMER_SECRET'] ?? null;

        if (!$consumerKey || !$consumerSecret) {
            $this->markTestSkipped('Sandbox credentials are not set in the .env file.');
        }

        $configPath = __DIR__ . '/../../../pesapal_dynamic.json';
        $this->config = new PesapalConfig($consumerKey, $consumerSecret, $configPath);
        $this->client = new PesapalClient($this->config, 'sandbox', false);
    }

    /**
     * Tests retrieving an access token from the sandbox API.
     *
     * @return void
     */
    public function testGetAccessToken()
    {
        $token = $this->client->getAccessToken();

        $this->assertNotEmpty($token, 'Access token should not be empty.');
        $this->assertIsString($token, 'Access token should be a string.');

        $expiry = $this->config->getAccessTokenExpiry();
        $this->assertNotEmpty($expiry, 'Access token expiry should not be empty.');
    }

    /**
     * Tests submitting an order request to the sandbox API.
     *
     * @return void
     */
    public function testSubmitOrderRequest()
    {
        $this->client->getAccessToken(); // Ensure the access token is valid

        $ipnDetails = $this->config->getIpnDetails();
        $notificationId = $ipnDetails['notification_id'] ?? null;

        if (!$notificationId) {
            $this->markTestSkipped('Notification ID is missing in pesapal_dynamic.json.');
        }

        $orderData = [
            'id' => uniqid('test_order_'),
            'currency' => 'KES',
            'amount' => 1000.00,
            'description' => 'Test order payment',
            'callback_url' => $_ENV['PESAPAL_CALLBACK_URL'] ?? 'https://www.example.com/payment-callback',
            'notification_id' => $notificationId,
            'branch' => 'TestBranch',
            'billing_address' => [
                'first_name' => 'Test',
                'last_name' => 'User',
                'email_address' => 'testuser@example.com',
                'phone_number' => '254700000000',
                'country_code' => 'KE',
                'city' => 'Nairobi',
                'state' => 'Nairobi',
                'postal_code' => '00100',
                'line_1' => 'Test Street',
                'line_2' => ''
            ]
        ];

        $response = $this->client->submitOrderRequest($orderData);

        $this->assertEquals(200, $response['status'], 'API should return HTTP 200 status.');
        $this->assertArrayHasKey('redirect_url', $response['response'], 'Response should contain a redirect URL.');
        $this->assertArrayHasKey('order_tracking_id', $response['response'], 'Response should contain an order tracking ID.');

        // Save order_tracking_id in pesapal_dynamic.json for later use
        $orderTrackingId = $response['response']['order_tracking_id'];
        $dynamicConfig = $this->config->loadDynamicConfig();
        $dynamicConfig['order_tracking_id'] = $orderTrackingId;
        $this->config->saveDynamicConfig($dynamicConfig);

        $this->assertNotEmpty($orderTrackingId, 'Order tracking ID should not be empty.');
    }

    /**
 * Tests retrieving a transaction's status from the sandbox API.
 *
 * @return void
 */
    public function testGetTransactionStatus()
    {
        $this->client->getAccessToken(); // Ensure the access token is valid

        // Load order_tracking_id from pesapal_dynamic.json
        $dynamicConfig = $this->config->loadDynamicConfig();
        $orderTrackingId = $dynamicConfig['order_tracking_id'] ?? null;

        if (!$orderTrackingId) {
            $this->markTestSkipped('Order tracking ID is missing in pesapal_dynamic.json.');
        }

        // Fetch transaction status
        $response = $this->client->getTransactionStatus($orderTrackingId);

        // Log the response for debugging
        echo "\nDebugging Response: " . print_r($response, true) . "\n";

        // Validate HTTP response status
        $this->assertEquals(200, $response['status'], 'API should return HTTP 200 status.');

        // Ensure response contains the 'response' key
        $this->assertArrayHasKey('response', $response, 'Response should contain the "response" key.');

        $transactionData = $response['response'];

        // Map status_code to status_message
        $status_code = $transactionData['status_code'] ?? null;
        $status_messages = [
            0 => 'INVALID',
            1 => 'COMPLETED',
            2 => 'FAILED',
            3 => 'REVERSED',
        ];
        $status_message = $status_messages[$status_code] ?? 'UNKNOWN STATUS';

        // Log the mapped status for debugging
        echo "\nMapped Status Message: $status_message\n";

        // Check if the transaction status is valid
        if (in_array($status_message, ['COMPLETED', 'FAILED', 'REVERSED'])) {
            $this->assertNotEmpty($transactionData['order_tracking_id'], 'Order tracking ID should not be empty.');
            $this->assertEquals($orderTrackingId, $transactionData['order_tracking_id'], 'Order tracking ID should match the requested ID.');
            $this->assertContains(
                $status_message,
                ['COMPLETED', 'FAILED', 'REVERSED'],
                "Payment status should be one of: COMPLETED, FAILED, REVERSED."
            );
        } else {
            // Inform the user if the transaction status is not valid
            echo "\nTransaction status is not valid for testing. Current status: $status_message\n";
            $this->markTestSkipped('Transaction status is not valid for testing. Ensure the tracking ID corresponds to a completed or failed transaction.');
        }
    }





    /**
     * Tests registering an IPN URL on the sandbox API.
     *
     * @return void
     */
    public function testRegisterIpnUrl()
    {
        $this->client->getAccessToken(); // Ensure the access token is valid

        $ipnUrl = $_ENV['PESAPAL_IPN_URL'] ?? null;

        if (!$ipnUrl) {
            $this->markTestSkipped('IPN URL is missing in the .env file.');
        }

        $response = $this->client->registerIpnUrl($ipnUrl, 'POST');

        $this->assertEquals(200, $response['status'], 'API should return HTTP 200 status.');
        $this->assertArrayHasKey('ipn_id', $response['response'], 'Response should contain an IPN ID.');
    }
}