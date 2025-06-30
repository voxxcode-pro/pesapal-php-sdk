<?php
/**
 * RefundRequestProcessor.php
 * ────────────────────────────────────────────────────────────────────────────
 * 1.  Sets content type to JSON
 * 2.  Autoloads Composer dependencies
 * 3.  Loads environment variables & Pesapal keys
 * 4.  Configures Pesapal client
 * 5.  Sets up Monolog logging
 * 6.  Ensures access token matches the current environment
 * 7.  Parses incoming JSON payload, checks transaction status, then requests a refund
 *
 * 2025-06-30 • Katorymnd Freelancer
 */
declare(strict_types=1);

/** Set response content type */
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

/** Initialize Whoops for development */
$whoops = new Run();
$whoops->pushHandler(new PrettyPageHandler());
$whoops->register();

try {
    /** Load environment variables */
    $dotenv = Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();

    /** Retrieve Pesapal credentials */
    $consumerKey    = $_ENV['PESAPAL_CONSUMER_KEY']    ?? null;
    $consumerSecret = $_ENV['PESAPAL_CONSUMER_SECRET'] ?? null;
    if (! $consumerKey || ! $consumerSecret) {
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

    /** Read and validate incoming JSON payload */
    $rawData = file_get_contents('php://input');
    $data    = json_decode($rawData, true);
    if (! $data) {
        throw new PesapalException('Invalid JSON data received.');
    }
    if (empty($data['order_tracking_id']) ||
        empty($data['amount']) ||
        empty($data['username']) ||
        empty($data['remarks'])
    ) {
        throw new PesapalException('All fields are required.');
    }

    $orderTrackingId = $data['order_tracking_id'];
    $refundAmount    = $data['amount'];
    $refundUsername  = $data['username'];
    $refundRemarks   = $data['remarks'];

    if (! is_numeric($refundAmount) || $refundAmount <= 0) {
        throw new PesapalException('Invalid refund amount. The amount must be a positive number.');
    }

    /** Check transaction status */
    $respStatus = $clientApi->getTransactionStatus($orderTrackingId);
    if ($respStatus['status'] !== 200 || ! isset($respStatus['response'])) {
        $err = $respStatus['response']['error']['message']
             ?? 'Unknown error occurred while retrieving transaction status.';
        throw new PesapalException($err);
    }

    $txData = $respStatus['response'];
    $code   = $txData['status_code'] ?? null;
    $map    = [0 => 'INVALID', 1 => 'COMPLETED', 2 => 'FAILED', 3 => 'REVERSED'];
    $msg    = $map[$code] ?? 'UNKNOWN STATUS';

    $log->info('Transaction status retrieved successfully', [
        'order_tracking_id' => $orderTrackingId,
        'status_code'       => $code,
        'status_message'    => $msg,
    ]);

    $responseData = [
        'success'            => true,
        'transaction_status' => array_merge($txData, [
            'status_code'    => $code,
            'status_message' => $msg,
        ]),
    ];

    /** If we have a confirmation code, proceed to refund */
    $confirmationCode = $txData['confirmation_code'] ?? null;
    if ($confirmationCode) {
        $refundData = [
            'confirmation_code' => $confirmationCode,
            'amount'            => $refundAmount,
            'username'          => $refundUsername,
            'remarks'           => $refundRemarks,
        ];

        try {
            $refundResp = $clientApi->requestRefund($refundData);
            if ($refundResp['status'] === 200 && isset($refundResp['response'])) {
                $log->info('Refund requested successfully', [
                    'refund_data'     => $refundData,
                    'refund_response' => $refundResp['response'],
                ]);
                $responseData['refund_response'] = $refundResp['response'];
            } else {
                $err = $refundResp['response']['error']['message']
                     ?? 'Unknown error occurred while requesting refund.';
                throw new PesapalException($err);
            }
        } catch (PesapalException $e) {
            $log->error('Error in requesting refund', [
                'error'       => $e->getMessage(),
                'details'     => $e->getErrorDetails(),
                'refund_data' => $refundData,
            ]);
            $responseData['refund_error'] = [
                'error'   => $e->getMessage(),
                'details' => $e->getErrorDetails(),
            ];
        }
    } else {
        $log->error('Confirmation code not available, cannot process refund.', [
            'order_tracking_id' => $orderTrackingId,
        ]);
        $responseData['refund_error'] = [
            'error' => 'Confirmation code not available, cannot process refund.',
        ];
    }

    /** Output combined response */
    echo json_encode($responseData);
    exit;

} catch (PesapalException $e) {
    $log->error('Error in processing refund request', [
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