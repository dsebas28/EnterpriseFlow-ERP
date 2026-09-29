<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when tenant-owned data is accessed without an active company.
 * Indicates a programming error (missing middleware, job without context).
 */
class MissingTenantContext extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('No active company in the current context; tenant-scoped data cannot be accessed.');
    }
}
