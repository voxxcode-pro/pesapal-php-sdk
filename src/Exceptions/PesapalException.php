<?php
/**
 * src/Exceptions/PesapalException.php
 * ────────────────────────────────────────────────────────────────────────────
 * Resilient wrapper around \Exception for the Pesapal SDK.
 *
 * • Accepts array|object|string in slot #1, stringifies automatically.
 * • getErrorDetails()   → returns a consistent associative array.
 *     – Handles PSR-7 Response objects
 *     – Handles plain array|object payloads
 *     – Falls back to simple message + code
 *
 * © 2025 Katorymnd Freelancer · MIT
 */

namespace Katorymnd\PesapalPhpSdk\Exceptions;

class PesapalException extends \Exception
{
    /** @var mixed|null */
    protected $response;

    /**
     * @param string|array|object $message
     * @param int                 $code
     * @param mixed|null          $response
     * @param \Exception|null     $previous
     */
    public function __construct($message = '', int $code = 0, $response = null, \Exception $previous = null)
    {
        // Allow an array|object to be passed as message for convenience.
        if (\is_array($message) || \is_object($message)) {
            $response = $message;
            $message  = 'Pesapal error: ' . \json_encode($response);
        }

        $this->response = $response;
        parent::__construct((string) $message, $code, $previous);
    }

    /** Return the raw response (array, object, PSR-7, …). */
    public function getResponse()
    {
        return $this->response;
    }

    /** Produce a structured error array safe for logs / API output. */
    public function getErrorDetails(): array
    {
        /* A) PSR-7 style object (has getBody()) */
        if ($this->response && \is_object($this->response) && \method_exists($this->response, 'getBody')) {
            $body = $this->response->getBody()->getContents();
            $json = \json_decode($body, true);

            return [
                'error'    => $this->getMessage(),
                'code'     => $this->getCode(),
                'response' => \json_last_error() === JSON_ERROR_NONE ? $json : $body,
            ];
        }

        /* B) Plain array|object payload */
        if (\is_array($this->response) || \is_object($this->response)) {
            return [
                'error'    => $this->getMessage(),
                'code'     => $this->getCode(),
                'response' => $this->response,
            ];
        }

        /* C) Nothing attached – bare minimum */
        return [
            'error' => $this->getMessage(),
            'code'  => $this->getCode(),
        ];
    }

    /** JSON helper */
    public function getErrorDetailsAsJson(): string
    {
        return \json_encode($this->getErrorDetails(), JSON_UNESCAPED_SLASHES);
    }
}