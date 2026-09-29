<?php

namespace App\Exceptions;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;

class TenantMismatch extends RuntimeException
{
    public static function forModel(Model $model): self
    {
        return new self(sprintf('Cannot create %s for a company other than the active one.', class_basename($model)));
    }

    public static function immutable(Model $model): self
    {
        return new self(sprintf('The company of %s cannot be changed.', class_basename($model)));
    }
}
