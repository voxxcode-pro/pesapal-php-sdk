<?php
/**
 * recurring_order_handler.php
 * ────────────────────────────────────────────────────────────────────────────
 * One-stop endpoint that:
 *   1. Loads keys from .env.
 *   2. Creates / updates pesapal_dynamic.json automatically when switching
 *      between sandbox ⇆ production.
 *   3. Validates the cached notification_id against the live IPN list and
 *      registers a fresh IPN URL if missing or wrong.
 *   4. Handles one-shot or recurring orders.
 *   5. Submits the order (with a single automatic retry on InvalidIpnId).
 *
 * Flip these two lines when you go live:
 *      $environment = 'production';
 *      $sslVerify   = true;
 *
 * 2025-06-30 • Katorymnd Freelancer – MIT
 */

declare(strict_types=1);

header('Content-Type: application/json');

/* ─── 1) Autoloader ──────────────────────────────────────────────────────── */
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    echo json_encode([
        'success'      => false,
        'errorMessage' => 'Autoloader not found. Run composer install.',
    ]);
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

/* ─── 3) Dev-friendly error page ─────────────────────────────────────────── */
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

$environment = 'sandbox';   /* ← switch to 'production' when live */
$sslVerify   = false;       /* ← true in production */

$clientApi   = new PesapalClient($config, $environment, $sslVerify);

/* ─── 6) Logger ──────────────────────────────────────────────────────────── */
$log = new Logger('pawaPayLogger');
$log->pushHandler(new StreamHandler(__DIR__ . '/../logs/payment_success.log', Logger::INFO));
$log->pushHandler(new StreamHandler(__DIR__ . '/../logs/payment_failed.log',  Logger::ERROR));

/* ─── 7) Token & environment sanity ──────────────────────────────────────── */
try {
    if ($config->getTokenEnvironment() !== $environment) {
        $config->clearAccessToken();                     // wipe stale token + recreate file if missing
    }
    $accessToken = $clientApi->getAccessToken(true);     // always refresh once on boot
} catch (PesapalException $e) {
    $log->error('Token acquisition failed', ['error' => $e->getMessage()]);
    echo $e->getErrorDetailsAsJson();
    exit;
}

/**
 * 7b) IPN URL validation / auto-registration
 * --------------------------------------------------------------------------
 * Ensures we have a valid notification_id tied to this merchant + environment.
 */
$ipnCfg        = $config->getIpnDetails();
$ipnUrlDesired = $ipnCfg['ipn_url'] ?? 'https://www.example.com/ipn';
$notificationId = $ipnCfg['notification_id'] ?? null;
$ipnNeedsUpdate = false;

try {
    $ipnListResp = $clientApi->getRegisteredIpns();
    $validIds    = array_column($ipnListResp['response'], 'ipn_id');
    $ipnNeedsUpdate = !$notificationId || !in_array($notificationId, $validIds, true);
} catch (PesapalException $e) {
    $ipnNeedsUpdate = true;  // safest path
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

/* ─── 8) Parse & validate JSON payload ───────────────────────────────────── */
$payload = json_decode(file_get_contents('php://input'), true);
if (!$payload) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON payload']);
    exit;
}

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

/* ─── 9) Build order body (supports recurring) ───────────────────────────── */
$merchantRef = PesapalHelpers::generateMerchantReference();

$billing      = $payload['billing_details']     ?? [];
$country      = strtoupper($billing['country']  ?? '');
$subDetails   = $payload['subscription_details']?? [];
$isRecurring  = !empty($subDetails);
$accountNo    = $payload['account_number']      ?? null;

$order = [
    'id'              => $merchantRef,
    'currency'        => $payload['currency'],
    'amount'          => (float) $payload['amount'],
    'description'     => $payload['description'],
    'callback_url'    => 'https://www.example.com/payment-callback',
    'notification_id' => $notificationId,
    'payment_method'  => 'card',
    'billing_address' => [
        'country_code'  => $country,
        'first_name'    => $billing['first_name']    ?? '',
        'middle_name'   => $billing['middle_name']   ?? '',
        'last_name'     => $billing['last_name']     ?? '',
        'line_1'        => $billing['address_line1'] ?? '',
        'line_2'        => $billing['address_line2'] ?? '',
        'city'          => $billing['city']          ?? '',
        'state'         => $billing['state']         ?? '',
        'postal_code'   => $billing['postal_code']   ?? '',
        'email_address' => $payload['email_address'],
    ],
];

/* Pretty phone number */
try {
    $pn   = PhoneNumberUtil::getInstance();
    $proto= $pn->parse($payload['phone_number'], null);
    $order['billing_address']['phone_number'] = preg_replace(
        '/[\s()-]/',
        '',
        $pn->format($proto, PhoneNumberFormat::NATIONAL)
    );
} catch (NumberParseException $e) {
    $log->error('Phone parse fail', ['error' => $e->getMessage()]);
    echo json_encode(['success' => false, 'error' => 'Invalid phone number']);
    exit;
}

/* Optional: recurring block */
if ($isRecurring) {
    if (!$accountNo) {
        echo json_encode(['success' => false, 'error' => 'Account number required for recurring payments.']);
        exit;
    }

    foreach (['start_date', 'end_date', 'frequency'] as $field) {
        if (empty($subDetails[$field])) {
            echo json_encode(['success' => false, 'error' => "Subscription field '{$field}' required"]);
            exit;
        }
    }

    $sDate = DateTime::createFromFormat('Y-m-d', $subDetails['start_date']);
    $eDate = DateTime::createFromFormat('Y-m-d', $subDetails['end_date']);
    if (!$sDate || !$eDate || $eDate <= $sDate) {
        echo json_encode(['success' => false, 'error' => 'Invalid subscription date range']);
        exit;
    }

    $order['account_number']     = $accountNo;
    $order['subscription_type']  = 'AUTO';
    $order['subscription_details'] = [
        'start_date' => $sDate->format('d-m-Y'),
        'end_date'   => $eDate->format('d-m-Y'),
        'frequency'  => $subDetails['frequency'],
    ];
}

/* ─── 10) Submit order – retry once on InvalidIpnId ─────────────────────── */
$didRetry = false;
RETRY:
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

    $errorCode = $resp['response']['code'] ?? null;
    if (!$didRetry && $errorCode === 'InvalidIpnId') {
        $log->warning('Invalid IPN ID – fixing & retrying once');
        $config->setIpnDetails(null, null);
        $fix                = $clientApi->registerIpnUrl($ipnUrlDesired, 'POST');
        $notificationId     = $fix['response']['ipn_id'] ?? null;
        $order['notification_id'] = $notificationId;
        $didRetry = true;
        goto RETRY;
    }

    throw new PesapalException(
        $resp['response']['message'] ?? 'Unknown submission error',
        $resp['status'] ?? 0,
        $resp
    );

} catch (PesapalException $e) {
    $log->error('Order failed', ['error' => $e->getMessage(), 'details' => $e->getErrorDetails()]);
    echo $e->getErrorDetailsAsJson();
    exit;
} catch (\Exception $e) {
    $log->error('Unexpected', ['error' => $e->getMessage()]);
    echo json_encode(['success' => false, 'error' => 'Unexpected server error']);
    exit;
}