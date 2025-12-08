<?php

namespace App\Exceptions\Tenant;

use App\Exceptions\Central\FederationException as CentralFederationException;

/**
 * FederationException (Tenant)
 *
 * Extends central federation exception with tenant-specific methods.
 */
class FederationException extends CentralFederationException
{
    // Inherits all methods from Central\FederationException
    // Add tenant-specific exception methods here if needed
}
