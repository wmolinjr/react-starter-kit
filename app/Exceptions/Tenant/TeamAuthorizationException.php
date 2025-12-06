<?php

namespace App\Exceptions\Tenant;

use Illuminate\Auth\Access\AuthorizationException;

/**
 * TeamAuthorizationException
 *
 * Thrown when a user is not authorized to perform a team operation.
 * Results in a 403 Forbidden response.
 */
class TeamAuthorizationException extends AuthorizationException
{
    public function __construct(string $message = 'This action is unauthorized.', ?\Throwable $previous = null)
    {
        parent::__construct($message, $previous);
    }
}
