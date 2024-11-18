<?php

namespace Katorymnd\PesapalPhpSdk\Tests\Unit\Api;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\RequestException;
use Katorymnd\PesapalPhpSdk\Api\PesapalClient;
use Katorymnd\PesapalPhpSdk\Config\PesapalConfig;
use Katorymnd\PesapalPhpSdk\Exceptions\PesapalException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for PesapalClient.
 *
 * This test suite verifies the behavior of the getAccessToken() method
 * in various scenarios including:
 * - Using an existing valid token.
 * - Fetching a new token when the current token is expired or missing.
 * - Handling API errors and invalid responses.
 */
class PesapalClientTest extends TestCase
{
    /**
     * @var PesapalConfig|\PHPUnit\Framework\MockObject\MockObject Mocked PesapalConfig instance.
     */
    private $pesapalConfig;

    /**
     * @var Client|\PHPUnit\Framework\MockObject\MockObject Mocked HTTP client.
     */
    private $httpClient;

    /**
     * Sets up the test environment by initializing mocked dependencies.
     *
     * @return void
     */
    protected function setUp(): void
    {
        // Mock the PesapalConfig
        $this->pesapalConfig = $this->createMock(PesapalConfig::class);

        // Mock the HTTP client
        $this->httpClient = $this->createMock(Client::class);
    }

    /**
     * Tests if getAccessToken() uses an existing valid token when available.
     *
     * @return void
     */
    public function testGetAccessToken_UsesExistingTokenIfValid()
    {
        $this->pesapalConfig->method('getAccessToken')->willReturn('valid_token');
        $this->pesapalConfig->method('getAccessTokenExpiry')->willReturn(date('Y-m-d H:i:s', strtotime('+1 hour')));

        $client = new PesapalClient($this->pesapalConfig);
        $this->setPrivateProperty($client, 'httpClient', $this->httpClient);

        $token = $client->getAccessToken();

        $this->assertEquals('valid_token', $token, 'Should return the existing valid token.');
    }

    /**
     * Tests if getAccessToken() fetches a new token when the current one is expired or missing.
     *
     * @return void
     */
    public function testGetAccessToken_FetchesNewTokenIfExpired()
    {
        $this->pesapalConfig->method('getAccessToken')->willReturn(null);
        $this->pesapalConfig->method('getAccessTokenExpiry')->willReturn(null);
        $this->pesapalConfig->method('getConsumerKey')->willReturn('consumer_key');
        $this->pesapalConfig->method('getConsumerSecret')->willReturn('consumer_secret');

        $mockResponse = new Response(200, [], json_encode([
            'token' => 'new_valid_token',
            'expiryDate' => date('Y-m-d H:i:s', strtotime('+1 hour')),
        ]));

        $this->httpClient->method('post')->willReturn($mockResponse);

        $client = new PesapalClient($this->pesapalConfig);
        $this->setPrivateProperty($client, 'httpClient', $this->httpClient);

        $token = $client->getAccessToken();

        $this->assertEquals('new_valid_token', $token, 'Should fetch a new token when none exists or is expired.');
    }

    /**
     * Tests if getAccessToken() throws a PesapalException when an API error occurs.
     *
     * @return void
     */
    public function testGetAccessToken_ThrowsPesapalExceptionOnApiError()
    {
        $this->pesapalConfig->method('getAccessToken')->willReturn(null);
        $this->pesapalConfig->method('getAccessTokenExpiry')->willReturn(null);
        $this->pesapalConfig->method('getConsumerKey')->willReturn('consumer_key');
        $this->pesapalConfig->method('getConsumerSecret')->willReturn('consumer_secret');

        // Mock a RequestInterface
        $mockRequest = $this->createMock(\Psr\Http\Message\RequestInterface::class);

        // Simulate a RequestException with the mocked RequestInterface
        $this->httpClient->method('post')->willThrowException(new RequestException(
            'Network error',
            $mockRequest
        ));

        $client = new PesapalClient($this->pesapalConfig);
        $this->setPrivateProperty($client, 'httpClient', $this->httpClient);

        $this->expectException(PesapalException::class);
        $this->expectExceptionMessage('Error getting access token: Network error');

        $client->getAccessToken();
    }

    /**
     * Tests if getAccessToken() throws a PesapalException when the API response is invalid.
     *
     * @return void
     */
    public function testGetAccessToken_ThrowsPesapalExceptionOnInvalidResponse()
    {
        $this->pesapalConfig->method('getAccessToken')->willReturn(null);
        $this->pesapalConfig->method('getAccessTokenExpiry')->willReturn(null);
        $this->pesapalConfig->method('getConsumerKey')->willReturn('consumer_key');
        $this->pesapalConfig->method('getConsumerSecret')->willReturn('consumer_secret');

        $mockResponse = new Response(200, [], json_encode(['invalid_field' => 'no_token']));
        $this->httpClient->method('post')->willReturn($mockResponse);

        $client = new PesapalClient($this->pesapalConfig);
        $this->setPrivateProperty($client, 'httpClient', $this->httpClient);

        $this->expectException(PesapalException::class);
        $this->expectExceptionMessage('Access token not found in response');

        $client->getAccessToken();
    }

    /**
     * Sets a private property in an object using reflection.
     *
     * @param object $object The object instance.
     * @param string $propertyName The name of the private property.
     * @param mixed $value The value to set.
     * @return void
     */
    private function setPrivateProperty($object, $propertyName, $value)
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue($object, $value);
    }
}