<?php

namespace Katorymnd\PesapalPhpSdk\Tests\Unit\Config;

use Katorymnd\PesapalPhpSdk\Config\PesapalConfig;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PesapalConfig.
 *
 * This test suite verifies the behavior of PesapalConfig in various scenarios, including:
 * - Retrieving API URLs for different environments.
 * - Loading and saving dynamic configurations to a file.
 * - Managing access tokens and IPN details.
 * - Handling cases where configuration files are missing.
 */
class PesapalConfigTest extends TestCase
{
    /**
     * @var string Path to the temporary configuration file used during tests.
     */
    private $configPath;

    /**
     * Sets up the test environment by creating a temporary configuration path.
     *
     * @return void
     */
    protected function setUp(): void
    {
        // Use a temporary path for dynamic configuration testing
        $this->configPath = sys_get_temp_dir() . '/pesapal_dynamic_test.json';
    }

    /**
     * Cleans up the test environment by removing the temporary configuration file.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        // Clean up the temporary configuration file
        if (file_exists($this->configPath)) {
            unlink($this->configPath);
        }
    }

    /**
     * Tests the retrieval of API URLs for sandbox and production environments.
     *
     * @return void
     */
    public function testGetApiUrl()
    {
        $config = new PesapalConfig('test_key', 'test_secret');

        $sandboxUrl = $config->getApiUrl('sandbox');
        $productionUrl = $config->getApiUrl('production');

        $this->assertEquals('https://cybqa.pesapal.com/pesapalv3/api', $sandboxUrl);
        $this->assertEquals('https://pay.pesapal.com/v3/api', $productionUrl);
    }

    /**
     * Tests saving and loading dynamic configuration data.
     *
     * @return void
     */
    public function testLoadAndSaveDynamicConfig()
    {
        $config = new PesapalConfig('test_key', 'test_secret', $this->configPath);

        // Save dynamic config
        $dynamicData = ['access_token' => 'test_token', 'expires_at' => '2024-12-31 23:59:59'];
        $config->saveDynamicConfig($dynamicData);

        // Load dynamic config and validate
        $loadedData = $config->loadDynamicConfig();
        $this->assertEquals($dynamicData, $loadedData);
    }

    /**
     * Tests setting and retrieving the access token and its expiry time.
     *
     * @return void
     */
    public function testSetAndGetAccessToken()
    {
        $config = new PesapalConfig('test_key', 'test_secret', $this->configPath);

        $token = 'test_access_token';
        $expiresAt = '2024-12-31 23:59:59';

        $config->setAccessToken($token, $expiresAt);

        $this->assertEquals($token, $config->getAccessToken());
        $this->assertEquals($expiresAt, $config->getAccessTokenExpiry());
    }

    /**
     * Tests setting and retrieving IPN (Instant Payment Notification) details.
     *
     * @return void
     */
    public function testSetAndGetIpnDetails()
    {
        $config = new PesapalConfig('test_key', 'test_secret', $this->configPath);

        $ipnUrl = 'https://example.com/ipn';
        $notificationId = 'test_notification_id';

        $config->setIpnDetails($ipnUrl, $notificationId);

        $ipnDetails = $config->getIpnDetails();

        $this->assertEquals($ipnUrl, $ipnDetails['ipn_url']);
        $this->assertEquals($notificationId, $ipnDetails['notification_id']);
    }

    /**
     * Tests the behavior when the dynamic configuration file is missing.
     *
     * @return void
     */
    public function testMissingDynamicConfigFileReturnsEmptyArray()
    {
        $config = new PesapalConfig('test_key', 'test_secret', $this->configPath);

        $loadedData = $config->loadDynamicConfig();

        $this->assertEquals([], $loadedData);
    }
}