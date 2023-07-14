<?php 

namespace Exception;

use RuntimeException;
use Throwable;

final class HttpException extends RuntimeException
{
    private $httpStatusCode;

    public function __construct($httpStatusCode = 500, $message = '', $code = 0, Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->httpStatusCode = $httpStatusCode;
    }

    public function getStatusCode()
    {
        return $this->httpStatusCode;
    }
}
