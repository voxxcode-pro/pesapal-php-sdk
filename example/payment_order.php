<?php
/**
 * payment_order.php
 * ────────────────────────────────────────────────────────────────────────────
 * 1.  Loads keys from .env, decides which Pesapal environment (sandbox | production)
 * 2.  Ensures the cached OAuth token belongs to *that* environment
 * 3.  Automatically (re-)registers the IPN URL if it is missing, invalid, or tied
 *     to a different environment
 * 4.  Submits an order request (with one automatic retry on InvalidIpnId) and
 *     returns the redirect URL
 *
 * 2025-06-30 • Katorymnd Freelancer
 */

declare(strict_types=1);

header('Content-Type: application/json');

/* ─── 1) Auto-loader ─────────────────────────────────────────────────────── */
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    echo json_encode(['success' => false, 'errorMessage' => 'Autoloader missing (composer install)']);
    exit;
}
require_once $autoloadPath;

/* ─── 2) Imports ─────────────────────────────────────────────────────────── */
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

/* ─── 3) Pretty errors (dev only) ────────────────────────────────────────── */
$whoops = new Run();
$whoops->pushHandler(new PrettyPageHandler());
$whoops->register();

/* ─── 4) .env & keys ─────────────────────────────────────────────────────── */
Dotenv::createImmutable(__DIR__ . '/../')->load();

$consumerKey    = $_ENV['PESAPAL_CONSUMER_KEY']    ?? null;
$consumerSecret = $_ENV['PESAPAL_CONSUMER_SECRET'] ?? null;

if (!$consumerKey || !$consumerSecret) {
    echo json_encode(['success' => false, 'errorMessage' => 'PESAPAL keys missing in .env']);
    exit;
}

/* ─── 5) Config + client ─────────────────────────────────────────────────── */
$configPath  = __DIR__ . '/../pesapal_dynamic.json';
$config      = new PesapalConfig($consumerKey, $consumerSecret, $configPath);

$environment = 'production';           /* ← flip to 'sandbox' when testing */
$sslVerify   = false;

$clientApi   = new PesapalClient($config, $environment, $sslVerify);

/* ─── 6) Logger ──────────────────────────────────────────────────────────── */
$log = new Logger('pawaPayLogger');
$log->pushHandler(new StreamHandler(__DIR__ . '/../logs/payment_success.log', \Monolog\Level::Info));
$log->pushHandler(new StreamHandler(__DIR__ . '/../logs/payment_failed.log',  \Monolog\Level::Error));

/* ─── 7) Token – ensure it matches environment ───────────────────────────── */
try {
    if ($config->getTokenEnvironment() !== $environment) {
        $config->clearAccessToken();                         // purge stale token
    }
    $accessToken = $clientApi->getAccessToken(true);         // force refresh
} catch (PesapalException $e) {
    $log->error('Token acquisition failed', ['error' => $e->getMessage()]);
    echo $e->getErrorDetailsAsJson();
    exit;
}

/**
 * 7b) Validate / (re-)register IPN URL
 * --------------------------------------------------------------------------
 * – We fetch the merchant’s IPN list and check if the cached notification_id
 *   is still valid for this environment. If not, we transparently register a
 *   new URL and persist the fresh ID.
 */
$ipnCfg        = $config->getIpnDetails();
$ipnUrlDesired = $ipnCfg['ipn_url'] ?? 'https://www.example.com/ipn';
$notificationId = $ipnCfg['notification_id'] ?? null;
$ipnNeedsUpdate = false;

try {
    $ipnListResp = $clientApi->getRegisteredIpns();                       // ← NEW
    $validIds    = array_column($ipnListResp['response'], 'ipn_id');

    $ipnNeedsUpdate = !$notificationId || !in_array($notificationId, $validIds, true);
} catch (PesapalException $e) {
    /* Couldn’t fetch list – safest option is to force a new IPN */
    $ipnNeedsUpdate = true;
}

if ($ipnNeedsUpdate) {
    try {
        $resp           = $clientApi->registerIpnUrl($ipnUrlDesired, 'POST');
        $notificationId = $resp['response']['ipn_id'] ?? null;

        $log->info('IPN auto-registered', [
            'ipn_url'         => $ipnUrlDesired,
            'notification_id' => $notificationId,
            'env'             => $environment,
        ]);
    } catch (PesapalException $e) {
        $log->error('Auto IPN registration failed', ['error' => $e->getMessage()]);
        echo $e->getErrorDetailsAsJson();
        exit;
    }
}

/* ─── 8) Parse request payload ───────────────────────────────────────────── */
$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON payload']);
    exit;
}

/* Validate essentials */
foreach (['amount', 'currency', 'description', 'email_address', 'phone_number'] as $field) {
    if (empty($payload[$field])) {
        echo json_encode(['success' => false, 'error' => "Field '{$field}' is required"]);
        exit;
    }
}
if (strlen($payload['description']) > 100) {
    echo json_encode(['success' => false, 'error' => 'Description >100 characters']);
    exit;
}

/* ─── 9) Craft order body ────────────────────────────────────────────────── */
$merchantRef = PesapalHelpers::generateMerchantReference();

$billing = $payload['billing_details'] ?? [];
$country = strtoupper($billing['country'] ?? '');

$order = [
    'id'              => $merchantRef,
    'currency'        => $payload['currency'],
    'amount'          => (float) $payload['amount'],
    'description'     => $payload['description'],
    'callback_url'    => 'https://www.example.com/payment-callback',
    'notification_id' => $notificationId,
    'branch'          => 'Katorymnd Freelancer',
    'payment_method'  => 'card',
    'billing_address' => [
        'country_code'  => $country,
        'first_name'    => $billing['first_name']    ?? '',
        'middle_name'   => '',
        'last_name'     => $billing['last_name']     ?? '',
        'line_1'        => $billing['address_line1'] ?? '',
        'line_2'        => $billing['address_line2'] ?? '',
        'city'          => $billing['city']          ?? '',
        'state'         => $billing['state']         ?? '',
        'postal_code'   => $billing['postal_code']   ?? '',
        'email_address' => $payload['email_address'],
    ],
];

/* Phone-number prettifier */
try {
    $pnUtil   = PhoneNumberUtil::getInstance();
    $proto    = $pnUtil->parse($payload['phone_number'], null);
    $national = preg_replace('/[\s()-]/', '', $pnUtil->format($proto, PhoneNumberFormat::NATIONAL));
    $order['billing_address']['phone_number'] = $national;
} catch (NumberParseException $e) {
    $log->error('Phone parse fail', ['error' => $e->getMessage()]);
    echo json_encode(['success' => false, 'error' => 'Invalid phone number']);
    exit;
}

/* ─── 10) Submit order – automatic retry on InvalidIpnId ─────────────────── */
$didRetry = false;
RETRY:                                   // ← label for single retry
try {
    $resp = $clientApi->submitOrderRequest($order);

    if ($resp['status'] === 200 && isset($resp['response']['redirect_url'])) {
        $log->info('Order OK', [
            'merchant_reference' => $merchantRef,
            'order_tracking_id'  => $resp['response']['order_tracking_id'],
        ]);

        echo json_encode([
            'success'            => true,
            'redirect_url'       => $resp['response']['redirect_url'],
            'order_tracking_id'  => $resp['response']['order_tracking_id'],
            'merchant_reference' => $merchantRef,
        ]);
        exit;
    }

    /* If Pesapal says “InvalidIpnId”, regenerate & retry once */
    $errorCode = $resp['response']['code'] ?? null;
    if (!$didRetry && $errorCode === 'InvalidIpnId') {
        $log->warning('Invalid IPN ID – auto-repairing & retrying once');

        $config->setIpnDetails(null, null);                 // wipe bad ID
        $fix = $clientApi->registerIpnUrl($ipnUrlDesired, 'POST');
        $notificationId                = $fix['response']['ipn_id'] ?? null;
        $order['notification_id']      = $notificationId;
        $didRetry                      = true;
        goto RETRY;
    }

    /* still failure → throw */
    throw new PesapalException(
        $resp['response']['message'] ?? 'Unknown submission error',
        $resp['status'] ?? 0,
        $resp
    );

} catch (PesapalException $e) {
    $log->error('Order failed', [
        'error'   => $e->getMessage(),
        'details' => $e->getErrorDetails(),
    ]);
    echo $e->getErrorDetailsAsJson();
    exit;
} catch (\Exception $e) {
    $log->error('Unexpected', ['error' => $e->getMessage()]);
    echo json_encode(['success' => false, 'error' => 'Unexpected server error']);
    exit;
}
