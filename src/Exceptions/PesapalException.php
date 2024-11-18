<?php

namespace Katorymnd\PesapalPhpSdk\Exceptions;

class PesapalException extends \Exception
{
    protected $response;

    /**
     * PesapalException constructor.
     *
     * @param string $message
     * @param int $code
     * @param mixed|null $response
     * @param \Exception|null $previous
     */
    public function __construct($message = "", $code = 0, $response = null, \Exception $previous = null)
    {
        $this->response = $response;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the API response associated with the exception, if any.
     *
     * @return mixed|null
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Get detailed error information, if available, as an array.
     *
     * @return array
     */
    public function getErrorDetails()
    {
        // Attempt to parse and return details from the response body, if available
        if ($this->response && method_exists($this->response, 'getBody')) {
            $responseBody = $this->response->getBody()->getContents();
            $decodedResponse = json_decode($responseBody, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                return [
                    'error' => $this->getMessage(),
                    'code' => $this->getCode(),
                    'response' => $decodedResponse,
                ];
            }
        }

        // Default error details if response cannot be parsed
        return [
            'error' => $this->getMessage(),
            'code' => $this->getCode(),
        ];
    }

    /**
     * Get error details as a JSON string for easier logging or API response.
     *
     * @return string
     */
    public function getErrorDetailsAsJson()
    {
        return json_encode($this->getErrorDetails());
    }
}