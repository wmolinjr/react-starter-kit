<?php

namespace App\Exceptions;

use Exception;

class SettingsException extends Exception
{
    public function __construct(string $message = 'Settings operation failed', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
