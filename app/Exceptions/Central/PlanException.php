<?php

namespace App\Exceptions\Central;

use Exception;

class PlanException extends Exception
{
    public function __construct(string $message = 'Plan operation failed', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
