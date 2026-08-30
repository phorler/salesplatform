<?php

namespace App\Enums;

enum InventoryStatus: string
{
    case Draft = 'draft';                 // captured, not yet graded/priced
    case ReadyToList = 'ready_to_list';   // graded + priced, awaiting export to a marketplace
    case Listed = 'listed';               // exported / live on a marketplace
    case Sold = 'sold';                   // all quantity sold
    case Inactive = 'inactive';           // withdrawn / not for sale

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::ReadyToList => 'Ready to list',
            self::Listed => 'Listed',
            self::Sold => 'Sold',
            self::Inactive => 'Inactive',
        };
    }
}
