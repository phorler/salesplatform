<?php

namespace App\Enums;

/**
 * Lifecycle of a single listing on a marketplace. Submission to channels like
 * Amazon is asynchronous, so a listing moves Pending -> Active/Error as the
 * channel processes it.
 */
enum ListingStatus: string
{
    case Draft = 'draft';         // not yet submitted
    case Pending = 'pending';     // submitted, awaiting channel processing
    case Active = 'active';       // live on the marketplace
    case Error = 'error';         // channel rejected it (see issues)
    case Inactive = 'inactive';   // withdrawn / closed

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Draft, self::Pending, self::Active], true);
    }
}
