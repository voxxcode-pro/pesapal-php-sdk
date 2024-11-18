<?php

// Set content type to JSON
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

// Include the libphonenumber library
use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\NumberParseException;
use Katorymnd\PesapalPhpSdk\Api\PesapalClient;
use Katorymnd\PesapalPhpSdk\Config\PesapalConfig;
use Katorymnd\PesapalPhpSdk\Exceptions\PesapalException;
use Katorymnd\PesapalPhpSdk\Utils\PesapalHelpers;
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

try {
    // Retrieve consumer key and secret from environment variables
    $consumerKey = $_ENV['PESAPAL_CONSUMER_KEY'] ?? null;
    $consumerSecret = $_ENV['PESAPAL_CONSUMER_SECRET'] ?? null;

    if (!$consumerKey || !$consumerSecret) {
        throw new PesapalException('Consumer key or secret missing in environment variables.');
    }

    // Initialize PesapalConfig and PesapalClient
    $configPath = __DIR__ . '/../pesapal_dynamic.json';
    $config = new PesapalConfig($consumerKey, $consumerSecret, $configPath);
    $environment = 'sandbox';
    $sslVerify = false; // Enable SSL verification for production


    $clientApi = new PesapalClient($config, $environment, $sslVerify);


    // Initialize Monolog for logging
    $log = new Logger('pawaPayLogger');
    $log->pushHandler(new StreamHandler(__DIR__ . '/../logs/payment_success.log', \Monolog\Level::Info));
    $log->pushHandler(new StreamHandler(__DIR__ . '/../logs/payment_failed.log', \Monolog\Level::Error));

    // Get the raw POST data
    $rawData = file_get_contents("php://input");
    $data = json_decode($rawData, true);

    if (!$data) {
        throw new PesapalException('Invalid JSON data received.');
    }

    // Validate required fields and default to generated merchant reference if not provided
    $requiredFields = ['amount', 'currency', 'description'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            throw new PesapalException("The field '$field' is required.");
        }
    }

    $merchantReference = PesapalHelpers::generateMerchantReference();

    // Validate required fields (both phone and email must be provided)
    if (empty($data['email_address']) || empty($data['phone_number'])) {
        throw new PesapalException('Both email and phone number must be provided.');
    }


    // Validate description length (must be 100 characters or less)
    if (strlen($data['description']) > 100) {
        throw new PesapalException('Description must be 100 characters or fewer.');
    }

    // Retrieve IPN details from dynamic config
    $ipnDetails = $config->getIpnDetails();
    $notificationId = $ipnDetails['notification_id'] ?? null;

    if (!$notificationId) {
        throw new PesapalException('Notification ID (IPN) is missing. Please configure IPN first.');
    }

    // Prepare order data
    $orderData = [
        "id" => $merchantReference,
        "currency" => $data['currency'],
        "amount" => (float) $data['amount'],
        "description" => $data['description'],
        "callback_url" => "https://www.example.com/payment-callback",
        "notification_id" => $notificationId,
        "branch" => "Katorymnd Freelancer",
        "payment_method" => "card", // Restrict payment option to CARD only
        "billing_address" => []
    ];

    // Map the billing details from $data['billing_details'] to the expected keys in $orderData['billing_address']
    $billingDetails = $data['billing_details'];

    // Ensure country code is uppercase
    $countryCode = isset($billingDetails['country']) ? strtoupper($billingDetails['country']) : '';

    $orderData['billing_address'] = array_merge($orderData['billing_address'], [
        "country_code" => $countryCode,
        "first_name" => isset($billingDetails['first_name']) ? $billingDetails['first_name'] : '',
        "middle_name" => '', // Assuming no middle name is provided
        "last_name" => isset($billingDetails['last_name']) ? $billingDetails['last_name'] : '',
        "line_1" => isset($billingDetails['address_line1']) ? $billingDetails['address_line1'] : '',
        "line_2" => isset($billingDetails['address_line2']) ? $billingDetails['address_line2'] : '',
        "city" => isset($billingDetails['city']) ? $billingDetails['city'] : '',
        "state" => isset($billingDetails['state']) ? $billingDetails['state'] : '',
        "postal_code" => isset($billingDetails['postal_code']) ? $billingDetails['postal_code'] : ''
    ]);

    // Include contact information provided
    if (!empty($data['email_address'])) {
        $orderData['billing_address']['email_address'] = $data['email_address'];
    }

    if (!empty($data['phone_number'])) {
        // Use libphonenumber to parse and format the phone number into national format
        $phoneUtil = PhoneNumberUtil::getInstance();

        try {
            // Parse the phone number in international format
            $numberProto = $phoneUtil->parse($data['phone_number'], null);

            // Format the number into national format (without country code)
            $nationalNumber = $phoneUtil->format($numberProto, PhoneNumberFormat::NATIONAL);

            // Remove any spaces, dashes, or parentheses
            $nationalNumber = preg_replace('/[\s()-]/', '', $nationalNumber);

            $orderData['billing_address']['phone_number'] = $nationalNumber;
        } catch (NumberParseException $e) {
            // Log the error
            $log->error('Phone number parsing failed', [
                'error' => $e->getMessage(),
                'phone_number' => $data['phone_number']
            ]);

            // Return an error response
            throw new PesapalException('Invalid phone number format.');
        }
    }

    // Obtain a valid access token
    $accessToken = $clientApi->getAccessToken();
    if (!$accessToken) {
        throw new PesapalException('Failed to obtain access token');
    }

    // Submit order request to Pesapal
    $response = $clientApi->submitOrderRequest($orderData);

    if ($response['status'] === 200 && isset($response['response']['redirect_url'])) {
        $redirectUrl = $response['response']['redirect_url'];
        $orderTrackingId = $response['response']['order_tracking_id'];

        $log->info('Order submitted successfully', [
            'redirect_url' => $redirectUrl,
            'order_tracking_id' => $orderTrackingId,
            'merchant_reference' => $merchantReference
        ]);

        echo json_encode([
            "success" => true,
            "message" => "Order submitted successfully!",
            "redirect_url" => $redirectUrl,
            "order_tracking_id" => $orderTrackingId,
            "merchant_reference" => $merchantReference
        ]);

    } else {
        $errorMessage = $response['response']['error'] ?? 'Unknown error occurred during order submission.';

        $log->error('Order submission failed', [
            'error' => $errorMessage,
            'merchant_reference' => $merchantReference
        ]);

        throw new PesapalException($errorMessage);
    }

} catch (PesapalException $e) {
    // Log the error to payment_failed.log
    $log->error('Error in processing payment', [
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