<?php

// configure_ipn.php

header("Content-Type: application/json");

// Include Composer's autoloader
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    echo json_encode([
        'success' => false,
        'errorMessage' => 'Autoloader not found. Please run composer install.'
    ]);
    exit;
}
require_once $autoloadPath;

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

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Retrieve consumer key and secret from environment variables
$consumerKey = $_ENV['PESAPAL_CONSUMER_KEY'] ?? null;
$consumerSecret = $_ENV['PESAPAL_CONSUMER_SECRET'] ?? null;

if (!$consumerKey || !$consumerSecret) {
    echo json_encode([
        'success' => false,
        'errorMessage' => 'Consumer key or secret missing in environment variables.'
    ]);
    exit;
}

// Initialize PesapalConfig and PesapalClient
$configPath = __DIR__ . '/../pesapal_dynamic.json';
$config = new PesapalConfig($consumerKey, $consumerSecret, $configPath); // Provide all required arguments
$environment = 'sandbox'; // Change to 'production' when ready for live transactions
$sslVerify = false; // Enable SSL verification for production


$clientApi = new PesapalClient($config, $environment, $sslVerify);

// Initialize Monolog for logging
$log = new Logger('pawaPayLogger');
$log->pushHandler(new StreamHandler(__DIR__ . '/../logs/payment_success.log', \Monolog\Level::Info));
$log->pushHandler(new StreamHandler(__DIR__ . '/../logs/payment_failed.log', \Monolog\Level::Error));

// Check if the request is POST and contains IPN URL
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Capture the JSON input from the request body
    $input = json_decode(file_get_contents('php://input'), true);
    $ipnUrl = $input['ipn_url'] ?? null;

    // Check if IPN URL was provided
    if (!$ipnUrl) {
        echo json_encode(['error' => 'IPN URL is required']);
        exit;
    }

    try {
        // Step 1: Ensure we have a valid access token
        $accessToken = $clientApi->getAccessToken(); // Handles token retrieval and refresh

        if (!$accessToken) {
            throw new PesapalException('Failed to obtain access token');
        }

        // Step 2: Register IPN URL with Pesapal using the valid access token
        $response = $clientApi->registerIpnUrl($ipnUrl, 'POST'); // 'POST' or 'GET' based on your preference

        if (isset($response['response']['ipn_id'])) {
            $ipnData = $response['response'];

            $notificationId = $ipnData['ipn_id'];
            $createdDate = $ipnData['created_date'];


            // Save IPN details in dynamic config
            $config->setIpnDetails($ipnUrl, $notificationId);

            // Log the success to payment_success.log
            $log->info('IPN URL registered successfully', [
                'ipn_url' => $ipnUrl,
                'notification_id' => $notificationId,
                'created_date' => $createdDate,

            ]);

            // Send a success response with the IPN details
            echo json_encode([
                'message' => 'IPN URL registered successfully',
                'ipn_url' => $ipnUrl,
                'notification_id' => $notificationId,
                'created_date' => $createdDate,

            ]);
        } else {
            throw new PesapalException('Failed to register IPN URL with Pesapal.');
        }
    } catch (PesapalException $e) {
        // Log the error to payment_failed.log
        $log->error('Error registering IPN URL', [
            'error' => $e->getMessage(),
            'details' => $e->getErrorDetails()
        ]);

        // Return the detailed error message
        echo $e->getErrorDetailsAsJson();
    }
} else {
    // Handle invalid request method
    echo json_encode(['error' => 'Invalid request method']);
}