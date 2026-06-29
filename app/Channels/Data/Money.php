<?php

namespace App\Channels\Data;

/**
 * A minimal money value object. Amounts are decimal strings to avoid float
 * rounding; arithmetic uses bcmath.
 */
readonly class Money
{
    public function __construct(
        public string $amount,
        public string $currency = 'GBP',
    ) {}

    public static function of(string|float|int $amount, string $currency = 'GBP'): self
    {
        return new self(number_format((float) $amount, 2, '.', ''), $currency);
    }

    public function multiply(float|string $factor): self
    {
        return new self(bcmul($this->amount, (string) $factor, 2), $this->currency);
    }

    public function subtract(string|float|int $other): self
    {
        return new self(bcsub($this->amount, (string) $other, 2), $this->currency);
    }

    public function isLessThan(self $other): bool
    {
        return bccomp($this->amount, $other->amount, 2) < 0;
    }

    public function __toString(): string
    {
        return $this->amount.' '.$this->currency;
    }
}
