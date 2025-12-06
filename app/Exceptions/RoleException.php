<?php

namespace App\Exceptions;

use Exception;

class RoleException extends Exception
{
    public function __construct(string $message = 'Role operation failed', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
