<?php

namespace App\Channels\Data;

use App\Enums\ListingStatus;

readonly class ListingStatusResult
{
    public function __construct(
        public ListingStatus $status,
        /** @var array<int, mixed> */
        public array $issues = [],
        public ?string $externalId = null,
    ) {}
}
