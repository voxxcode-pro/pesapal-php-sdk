<?php
/**
 * configure_ipn.php
 * ─────────────────────────────────────────────────────────────────────────
 * Registers a Pesapal IPN URL.
 *
 * • Uses the **environment-aware** PesapalClient (2025-06-30 edition).
 * • Forces a fresh token on every call (`getAccessToken(true)`) so switching
 *   between sandbox ⇆ production cannot leak a stale JWT.
 * • Demonstrates how to wipe the cache manually with `$config->clearAccessToken()`.
 *
 * @author  Katorymnd Freelancer
 * @license MIT
 */

header('Content-Type: application/json');

/* ───────────────────────────────────────────────────────────────────────── */
/* 1.  Autoload + dependencies                                              */
/* ───────────────────────────────────────────────────────────────────────── */
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    echo json_encode([
        'success'      => false,
        'errorMessage' => 'Autoloader not found. Please run composer install.',
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

/* ───────────────────────────────────────────────────────────────────────── */
/* 2.  Error prettifier (dev only)                                          */
/* ───────────────────────────────────────────────────────────────────────── */
$whoops = new Run();
$whoops->pushHandler(new PrettyPageHandler());
$whoops->register();

/* ───────────────────────────────────────────────────────────────────────── */
/* 3.  Environment variables (.env)                                         */
/* ───────────────────────────────────────────────────────────────────────── */
Dotenv::createImmutable(__DIR__ . '/../')->load();

$consumerKey    = $_ENV['PESAPAL_CONSUMER_KEY']    ?? null;
$consumerSecret = $_ENV['PESAPAL_CONSUMER_SECRET'] ?? null;

if (!$consumerKey || !$consumerSecret) {
    echo json_encode([
        'success'      => false,
        'errorMessage' => 'Consumer key or secret missing in environment variables.',
    ]);
    exit;
}

/* ───────────────────────────────────────────────────────────────────────── */
/* 4.  SDK config + client                                                  */
/* ───────────────────────────────────────────────────────────────────────── */
$configPath  = __DIR__ . '/../pesapal_dynamic.json';
$config      = new PesapalConfig($consumerKey, $consumerSecret, $configPath);
$environment = 'sandbox';   // ← switch to 'sandbox' when testing
$sslVerify   = false;           // true in production

$clientApi = new PesapalClient($config, $environment, $sslVerify);

/* ───────────────────────────────────────────────────────────────────────── */
/* 5.  Logging setup                                                        */
/* ───────────────────────────────────────────────────────────────────────── */

$log = new Logger('pawaPayLogger');
$log->pushHandler(new StreamHandler(__DIR__ . '/../logs/payment_success.log', \Monolog\Level::Info));
$log->pushHandler(new StreamHandler(__DIR__ . '/../logs/payment_failed.log', \Monolog\Level::Error));

/* ───────────────────────────────────────────────────────────────────────── */
/* 6.  POST handler                                                         */
/* ───────────────────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit;
}

$input  = json_decode(file_get_contents('php://input'), true);
$ipnUrl = $input['ipn_url'] ?? null;

if (!$ipnUrl) {
    echo json_encode(['error' => 'IPN URL is required']);
    exit;
}

try {
    /**
     * If you previously received a 401 and want to be absolutely sure the
     * cache is wiped, call:
     *     $config->clearAccessToken();
     * BEFORE fetching the next token.
     *
     * Here we simply force-refresh on every registration call:
     */
    $accessToken = $clientApi->getAccessToken(true);   // ← fresh token

    if (!$accessToken) {
        throw new PesapalException('Failed to obtain access token');
    }

    /* Register IPN URL */
    $response = $clientApi->registerIpnUrl($ipnUrl, 'POST');

    if (!isset($response['response']['ipn_id'])) {
        throw new PesapalException('Failed to register IPN URL with Pesapal.');
    }

    $ipnData        = $response['response'];
    $notificationId = $ipnData['ipn_id'];
    $createdDate    = $ipnData['created_date'];

    /* Persist & log */
    $config->setIpnDetails($ipnUrl, $notificationId);

    $log->info('IPN URL registered successfully', [
        'ipn_url'         => $ipnUrl,
        'notification_id' => $notificationId,
        'created_date'    => $createdDate,
    ]);

    echo json_encode([
        'message'         => 'IPN URL registered successfully',
        'ipn_url'         => $ipnUrl,
        'notification_id' => $notificationId,
        'created_date'    => $createdDate,
    ]);

} catch (PesapalException $e) {
    /* — optional automatic “self-heal” —
       If the error is a 401, wipe cache so the next attempt starts clean. */
    if (str_contains($e->getMessage(), '401')) {
        $config->clearAccessToken();
    }

    $log->error('Error registering IPN URL', [
        'error'   => $e->getMessage(),
        'details' => $e->getErrorDetails(),
    ]);

    echo $e->getErrorDetailsAsJson();
}