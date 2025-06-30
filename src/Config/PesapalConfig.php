<?php


// src/Config/PesapalConfig.php

namespace Katorymnd\PesapalPhpSdk\Config;

/**
 * The Config class provides configuration settings for different environments (sandbox and production).
 *
 * - 'sandbox': Used for testing and development purposes. Points to Pesapal's sandbox API.
 * - 'production': Used for real, live transactions. Points to Pesapal's live API.
 *
 * These settings are accessed by the ClientApi class to determine the correct API URL based on the environment
 * (either 'sandbox' for testing or 'production' for live usage).
 */
class PesapalConfig
{
    private $consumerKey;
    private $consumerSecret;
    private $dynamicConfigPath;

    // Static settings for sandbox and production environments
    public static $settings = [
        'sandbox' => [
            'api_url' => 'https://cybqa.pesapal.com/pesapalv3/api'  // Sandbox URL for testing
        ],
        'production' => [
            'api_url' => 'https://pay.pesapal.com/v3/api'  // Production URL for live transactions
        ]
    ];

    /**
     * Config constructor.
     * @param string $consumerKey
     * @param string $consumerSecret
     * @param string $dynamicConfigPath Path to the dynamic configuration JSON file.
     */
    public function __construct($consumerKey, $consumerSecret, $dynamicConfigPath = __DIR__ . '/pesapal_dynamic.json')
    {
        $this->consumerKey = $consumerKey;
        $this->consumerSecret = $consumerSecret;
        $this->dynamicConfigPath = $dynamicConfigPath;
    }

    /**
     * Get the consumer key.
     */
    public function getConsumerKey()
    {
        return $this->consumerKey;
    }

    /**
     * Get the consumer secret.
     */
    public function getConsumerSecret()
    {
        return $this->consumerSecret;
    }

    /**
     * Retrieve the API URL based on the environment ('sandbox' or 'production').
     *
     * @param string $environment
     * @return string|null
     */
    public function getApiUrl($environment = 'sandbox')
    {
        return self::$settings[$environment]['api_url'] ?? null;
    }

    /**
     * Load dynamic configuration data from JSON.
     */
    public function loadDynamicConfig()
    {
        if (file_exists($this->dynamicConfigPath)) {
            return json_decode(file_get_contents($this->dynamicConfigPath), true);
        }
        return [];
    }

    /**
     * Save dynamic configuration data to JSON.
     */
    public function saveDynamicConfig($data)
    {
        file_put_contents($this->dynamicConfigPath, json_encode($data, JSON_PRETTY_PRINT));
    }

    /**
     * Get the access token from the dynamic config file.
     */
    public function getAccessToken()
    {
        $config = $this->loadDynamicConfig();
        return $config['access_token'] ?? null;
    }

    /**
     * Get the access token expiry date from the dynamic config file.
     */
    public function getAccessTokenExpiry()
    {
        $config = $this->loadDynamicConfig();
        return $config['expires_at'] ?? null;
    }

    /**
     * Set the access token and expiration in the dynamic config.
     *
     * @param string $token
     * @param string $expiresAt Expiration time in a parseable format
     */
    public function setAccessToken($token, $expiresAt, $environment)
    {
        $config                     = $this->loadDynamicConfig();
        $config['access_token']     = $token;
        $config['expires_at']       = $expiresAt;
        $config['token_env']        = $environment;   // ← here
        $this->saveDynamicConfig($config);
    }
    
    public function clearAccessToken()
    {
        $config = $this->loadDynamicConfig();
        unset($config['access_token'], $config['expires_at'], $config['token_env']);
        $this->saveDynamicConfig($config);
    }

     public function getTokenEnvironment()
    {
        $config = $this->loadDynamicConfig();
        return $config['token_env'] ?? null;
    }

    /**
     * Get IPN details including the URL and notification ID from the dynamic config.
     *
     * @return array IPN URL and notification ID
     */
    public function getIpnDetails()
    {
        $config = $this->loadDynamicConfig();
        return [
            'ipn_url' => $config['ipn_url'] ?? null,
            'notification_id' => $config['notification_id'] ?? null
        ];
    }

    /**
     * Set the IPN URL and notification ID in the dynamic config.
     *
     * @param string $ipnUrl
     * @param string $notificationId
     */
    public function setIpnDetails($ipnUrl, $notificationId)
    {
        $config = $this->loadDynamicConfig();
        $config['ipn_url'] = $ipnUrl;
        $config['notification_id'] = $notificationId;
        $this->saveDynamicConfig($config);
    }
}