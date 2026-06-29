<?php

namespace Tests\Unit;

use App\Support\Isbn;
use PHPUnit\Framework\TestCase;

class IsbnTest extends TestCase
{
    public function test_normalize_strips_hyphens_and_spaces(): void
    {
        $this->assertSame('9780140328721', Isbn::normalize('978-0-14-032872-1'));
        $this->assertSame('014032872X', Isbn::normalize('0-14-032872-x'));
    }

    public function test_validates_isbn13_and_isbn10(): void
    {
        $this->assertTrue(Isbn::isValid('9780140328721'));
        $this->assertTrue(Isbn::isValid('0140328726'));
        $this->assertFalse(Isbn::isValid('9780140328720')); // bad check digit
        $this->assertFalse(Isbn::isValid('123'));
    }

    public function test_converts_isbn10_to_isbn13(): void
    {
        $this->assertSame('9780140328721', Isbn::toIsbn13('0140328726'));
        $this->assertSame('9780140328721', Isbn::toIsbn13('978-0-14-032872-1'));
        $this->assertNull(Isbn::toIsbn13('not-an-isbn'));
    }
}
