<?php

namespace App\Enums;

enum InventoryStatus: string
{
    case Draft = 'draft';       // captured, not yet listed anywhere
    case Listed = 'listed';     // has at least one active marketplace listing
    case Sold = 'sold';         // all quantity sold
    case Inactive = 'inactive'; // withdrawn / not for sale

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
