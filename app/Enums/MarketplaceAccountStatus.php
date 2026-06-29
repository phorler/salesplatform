<?php

namespace App\Enums;

enum MarketplaceAccountStatus: string
{
    case Connected = 'connected';
    case Disconnected = 'disconnected';
    case Error = 'error';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
