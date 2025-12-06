<?php

namespace App\Exceptions\Central;

use Exception;

class AddonException extends Exception
{
    public function __construct(string $message = 'Addon operation failed', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
