<?php

namespace App\Channels\Exceptions;

use InvalidArgumentException;

class UnknownChannelException extends InvalidArgumentException
{
    public static function for(string $key): self
    {
        return new self("No marketplace channel registered for key [{$key}].");
    }
}
