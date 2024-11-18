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

    // Extract variables from $data
    $amount = $data['amount'] ?? null;
    $currency = $data['currency'] ?? 'USD';
    $description = $data['description'] ?? null;
    $emailAddress = $data['email_address'] ?? null;
    $phoneNumber = $data['phone_number'] ?? null;
    $merchantReference = PesapalHelpers::generateMerchantReference();
    $accountNumber = $data['account_number'] ?? null;
    $subscriptionDetails = $data['subscription_details'] ?? null;

    // Validate required fields
    if (!$amount || !$description) {
        throw new PesapalException('Amount and description are required.');
    }


    // Validate contact information
    if (!$emailAddress || !$phoneNumber) {
        throw new PesapalException('Both email address and phone number must be provided.');
    }


    // Validate description length
    if (strlen($description) > 100) {
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
        "currency" => $currency,
        "amount" => (float) $amount,
        "description" => $description,
        "callback_url" => "https://www.example.com/payment-callback",
        "notification_id" => $notificationId,
        "billing_address" => []
    ];


    // Include contact information provided
    if (!empty($emailAddress)) {
        $orderData['billing_address']['email_address'] = $emailAddress;
    }

    if (!empty($phoneNumber)) {
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

    // Include new billing details
    if (isset($data['billing_details'])) {
        $billingDetails = $data['billing_details'];
        $orderData['billing_address']['country_code'] = $billingDetails['country'] ?? '';
        $orderData['billing_address']['first_name'] = $billingDetails['first_name'] ?? '';
        $orderData['billing_address']['middle_name'] = $billingDetails['middle_name'] ?? ''; // Assuming this field is available in $data
        $orderData['billing_address']['last_name'] = $billingDetails['last_name'] ?? '';
        $orderData['billing_address']['line_1'] = $billingDetails['address_line1'] ?? '';
        $orderData['billing_address']['line_2'] = $billingDetails['address_line2'] ?? '';
        $orderData['billing_address']['city'] = $billingDetails['city'] ?? '';
        $orderData['billing_address']['state'] = $billingDetails['state'] ?? '';
        $orderData['billing_address']['postal_code'] = $billingDetails['postal_code'] ?? '';
        $orderData['billing_address']['zip_code'] = ''; // Assuming no specific field in $data, use blank
    }

    // Handle Recurring Payments
    $isRecurring = isset($subscriptionDetails) && !empty($subscriptionDetails);
    if ($isRecurring) {
        if (!$accountNumber) {
            throw new PesapalException('Account number is required for recurring payments.');
        }

        // Validate subscription details
        $requiredSubscriptionFields = ['start_date', 'end_date', 'frequency'];
        foreach ($requiredSubscriptionFields as $field) {
            if (empty($subscriptionDetails[$field])) {
                throw new PesapalException("The field '$field' is required in subscription details.");
            }
        }

        // Validate date formats (assuming 'YYYY-MM-DD' format from the front-end)
        $startDate = DateTime::createFromFormat('Y-m-d', $subscriptionDetails['start_date']);
        $endDate = DateTime::createFromFormat('Y-m-d', $subscriptionDetails['end_date']);
        if (!$startDate || !$endDate) {
            throw new PesapalException('Invalid date format in subscription details. Use YYYY-MM-DD.');
        }
        if ($endDate <= $startDate) {
            throw new PesapalException('End date must be after start date in subscription details.');
        }

        // Include recurring payment details with reformatted dates
        $orderData['account_number'] = $accountNumber;
        $orderData['subscription_type'] = 'AUTO'; // Include subscription_type
        $orderData['subscription_details'] = [
            'start_date' => $startDate->format('d-m-Y'), // Reformat date to 'DD-MM-YYYY'
            'end_date' => $endDate->format('d-m-Y'),     // Reformat date to 'DD-MM-YYYY'
            'frequency' => $subscriptionDetails['frequency']
        ];
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
        // Handle error response
        $errorResponse = $response['response']['error'] ?? 'Unknown error occurred during order submission.';

        // If $errorResponse is an array, convert it to a string
        if (is_array($errorResponse)) {
            $errorMessage = $errorResponse['message'] ?? json_encode($errorResponse);
        } else {
            $errorMessage = $errorResponse;
        }

        $log->error('Order submission failed', [
            'error' => $errorMessage,
            'full_error_response' => $errorResponse,
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