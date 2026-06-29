<?php

namespace App\Channels\Data;

use App\Enums\ListingStatus;

/**
 * Outcome of submitting a listing to a channel. Submission is typically async, so
 * status is usually Pending with a submissionId to poll later.
 */
readonly class SubmissionResult
{
    public function __construct(
        public ListingStatus $status,
        public ?string $submissionId = null,
        public ?string $externalId = null,
        /** @var array<int, mixed> */
        public array $issues = [],
    ) {}
}
