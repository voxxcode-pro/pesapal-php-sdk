<?php
/**
 * check_transaction_status.php
 * ────────────────────────────────────────────────────────────────────────────
 * 1.  Sets content type to JSON
 * 2.  Autoloads Composer dependencies
 * 3.  Loads environment variables & Pesapal keys
 * 4.  Configures Pesapal client
 * 5.  Sets up Monolog logging
 * 6.  Ensures access token matches the current environment
 * 7.  Parses incoming JSON payload
 * 8.  Retrieves and returns full transaction status
 *
 * 2025-06-30 • Katorymnd Freelancer
 */
declare(strict_types=1);

/** Set response content type to JSON */
header('Content-Type: application/json');

/** Autoload Composer dependencies */
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    echo json_encode([
        'success' => false,
        'error'   => 'Autoloader not found. Please run composer install.'
    ]);
    exit;
}
require_once $autoloadPath;

/** Import necessary classes */
use Dotenv\Dotenv;
use Katorymnd\PesapalPhpSdk\Api\PesapalClient;
use Katorymnd\PesapalPhpSdk\Config\PesapalConfig;
use Katorymnd\PesapalPhpSdk\Exceptions\PesapalException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;

/** Initialize Whoops for pretty error pages (development only) */
$whoops = new Run();
$whoops->pushHandler(new PrettyPageHandler());
$whoops->register();

try {
    /** Load environment variables */
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();

    /** Retrieve Pesapal credentials from environment */
    $consumerKey    = $_ENV['PESAPAL_CONSUMER_KEY']    ?? null;
    $consumerSecret = $_ENV['PESAPAL_CONSUMER_SECRET'] ?? null;
    if (!$consumerKey || !$consumerSecret) {
        throw new PesapalException('Consumer key or secret missing in environment variables.');
    }

    /* ─── 5) Config + client ───────────────────────────────────────────────── */
    $configPath  = __DIR__ . '/../pesapal_dynamic.json';
    $config      = new PesapalConfig($consumerKey, $consumerSecret, $configPath);
    $environment = 'sandbox';   /* ← switch to 'production' when live */
    $sslVerify   = false;       /* ← true in production */
    $clientApi   = new PesapalClient($config, $environment, $sslVerify);

    /* ─── 6) Logger ─────────────────────────────────────────────────────────── */
    $log = new Logger('pawaPayLogger');
    $log->pushHandler(new StreamHandler(__DIR__ . '/../logs/payment_success.log', Logger::INFO));
    $log->pushHandler(new StreamHandler(__DIR__ . '/../logs/payment_failed.log',  Logger::ERROR));

    /* ─── 7) Token & environment sanity ─────────────────────────────────────── */
    if ($config->getTokenEnvironment() !== $environment) {
        $config->clearAccessToken();  // wipe stale token + recreate file if missing
    }
    $accessToken = $clientApi->getAccessToken(true);  // always refresh once on boot

    /** Read and validate incoming payload */
    $rawData = file_get_contents('php://input');
    $data    = json_decode($rawData, true);
    if (!$data) {
        throw new PesapalException('Invalid JSON data received.');
    }
    if (empty($data['order_tracking_id'])) {
        throw new PesapalException('Order tracking ID is required.');
    }
    $orderTrackingId = $data['order_tracking_id'];

    /** Retrieve transaction status from Pesapal */
    $response = $clientApi->getTransactionStatus($orderTrackingId);
    if ($response['status'] === 200 && isset($response['response'])) {
        $tsData = $response['response'];
        $code   = $tsData['status_code'] ?? null;
        $map    = [
            0 => 'INVALID',
            1 => 'COMPLETED',
            2 => 'FAILED',
            3 => 'REVERSED',
        ];
        $msg = $map[$code] ?? 'UNKNOWN STATUS';

        $log->info('Transaction status retrieved successfully', [
            'order_tracking_id' => $orderTrackingId,
            'status_code'       => $code,
            'status_message'    => $msg,
        ]);

        echo json_encode([
            'success'             => true,
            'transaction_status'  => array_merge($tsData, [
                'status_code'    => $code,
                'status_message' => $msg,
            ]),
        ]);
        exit;
    }

    /** Handle unexpected response */
    $errMsg = $response['response']['error']['message'] 
        ?? 'Unknown error occurred while retrieving transaction status.';
    $log->error('Transaction status retrieval failed', [
        'order_tracking_id' => $orderTrackingId,
        'error'             => $errMsg,
    ]);
    throw new PesapalException($errMsg);

} catch (PesapalException $e) {
    $log->error('Error in checking transaction status', [
        'error'   => $e->getMessage(),
        'details' => $e->getErrorDetails(),
    ]);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
        'details' => $e->getErrorDetails(),
    ]);
} catch (\Exception $e) {
    $log->error('Unexpected error', ['error' => $e->getMessage()]);
    echo json_encode([
        'success' => false,
        'error'   => 'An unexpected error occurred. Please try again later.',
    ]);
}