<?php

namespace App\Exceptions\Tenant;

use Exception;

class TeamException extends Exception
{
    public function __construct(string $message = 'Team operation failed', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
