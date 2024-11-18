<?php

// check_transaction_status.php

// Set content type to JSON
header('Content-Type: application/json');

// Include Composer's autoloader
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    echo json_encode([
        'success' => false,
        'error' => 'Autoloader not found. Please run composer install.'
    ]);
    exit;
}
require_once $autoloadPath;

// Use necessary namespaces
use Katorymnd\PesapalPhpSdk\Api\PesapalClient;
use Katorymnd\PesapalPhpSdk\Config\PesapalConfig;
use Katorymnd\PesapalPhpSdk\Exceptions\PesapalException;
use Dotenv\Dotenv;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Whoops\Run;
use Whoops\Handler\PrettyPageHandler;

// Initialize Whoops error handler for development
$whoops = new Run();
$whoops->pushHandler(new PrettyPageHandler());
$whoops->register();

try {
    // Load environment variables
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();

    // Retrieve consumer key and secret from environment variables
    $consumerKey = $_ENV['PESAPAL_CONSUMER_KEY'] ?? null;
    $consumerSecret = $_ENV['PESAPAL_CONSUMER_SECRET'] ?? null;

    if (!$consumerKey || !$consumerSecret) {
        throw new PesapalException('Consumer key or secret missing in environment variables.');
    }

    // Initialize PesapalConfig and PesapalClient
    $configPath = __DIR__ . '/../pesapal_dynamic.json';
    $config = new PesapalConfig($consumerKey, $consumerSecret, $configPath);
    $environment = 'sandbox'; // or 'production' based on your environment
    $sslVerify = false; // Set to true in production

    $clientApi = new PesapalClient($config, $environment, $sslVerify);

    // Initialize Monolog for logging
    $log = new Logger('pawaPayLogger');
    $log->pushHandler(new StreamHandler(__DIR__ . '/../logs/payment_success.log', \Monolog\Level::Info));
    $log->pushHandler(new StreamHandler(__DIR__ . '/../logs/payment_failed.log', \Monolog\Level::Error));

    // Get the raw POST data
    $rawData = file_get_contents('php://input');
    $data = json_decode($rawData, true);

    if (!$data) {
        throw new PesapalException('Invalid JSON data received.');
    }

    if (empty($data['order_tracking_id'])) {
        throw new PesapalException('Order Tracking ID is required.');
    }

    $orderTrackingId = $data['order_tracking_id'];

    // Obtain a valid access token
    $accessToken = $clientApi->getAccessToken();
    if (!$accessToken) {
        throw new PesapalException('Failed to obtain access token');
    }

    // Get the transaction status
    $response = $clientApi->getTransactionStatus($orderTrackingId);

    if ($response['status'] === 200 && isset($response['response'])) {
        $transactionStatusData = $response['response'];

        // Map status_code to status_message
        $status_code = $transactionStatusData['status_code'] ?? null;
        $status_messages = [
            0 => 'INVALID',
            1 => 'COMPLETED',
            2 => 'FAILED',
            3 => 'REVERSED',
        ];
        $status_message = isset($status_messages[$status_code]) ? $status_messages[$status_code] : 'UNKNOWN STATUS';

        // Log the transaction status
        $log->info('Transaction status retrieved successfully', [
            'order_tracking_id' => $orderTrackingId,
            'status_code' => $status_code,
            'status_message' => $status_message,
        ]);

        // Output all required transaction details, including status_message
        echo json_encode([
            'success' => true,
            'transaction_status' => [
                'payment_method' => $transactionStatusData['payment_method'] ?? null,
                'amount' => $transactionStatusData['amount'] ?? null,
                'created_date' => $transactionStatusData['created_date'] ?? null,
                'confirmation_code' => $transactionStatusData['confirmation_code'] ?? null,
                'order_tracking_id' => $transactionStatusData['order_tracking_id'] ?? null,
                'payment_status_description' => $transactionStatusData['payment_status_description'] ?? null,
                'description' => $transactionStatusData['description'] ?? null,
                'message' => $transactionStatusData['message'] ?? null,
                'payment_account' => $transactionStatusData['payment_account'] ?? null,
                'call_back_url' => $transactionStatusData['call_back_url'] ?? null,
                'status_code' => $status_code,
                'status_message' => $status_message,
                'merchant_reference' => $transactionStatusData['merchant_reference'] ?? null,
                'account_number' => $transactionStatusData['account_number'] ?? null,
                'payment_status_code' => $transactionStatusData['payment_status_code'] ?? null,
                'currency' => $transactionStatusData['currency'] ?? null,
                'error' => [
                    'error_type' => $transactionStatusData['error']['error_type'] ?? null,
                    'code' => $transactionStatusData['error']['code'] ?? null,
                    'message' => $transactionStatusData['error']['message'] ?? null
                ]
            ]
        ]);
    } else {
        $errorMessage = $response['response']['error']['message'] ?? 'Unknown error occurred while retrieving transaction status.';

        $log->error('Transaction status retrieval failed', [
            'error' => $errorMessage,
            'order_tracking_id' => $orderTrackingId
        ]);

        throw new PesapalException($errorMessage);
    }

} catch (PesapalException $e) {
    // Log the error
    $log->error('Error in checking transaction status', [
        'error' => $e->getMessage(),
        'details' => $e->getErrorDetails()
    ]);

    // Return the detailed error message
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'details' => $e->getErrorDetails()
    ]);
} catch (Exception $e) {
    // Handle any unexpected exceptions
    $log->error('Unexpected error', ['error' => $e->getMessage()]);

    echo json_encode([
        'success' => false,
        'error' => 'An unexpected error occurred. Please try again later.'
    ]);
}