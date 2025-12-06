<?php

namespace App\Exceptions;

class AddonLimitExceededException extends AddonException
{
    public function __construct(string $message = 'Addon limit exceeded', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
