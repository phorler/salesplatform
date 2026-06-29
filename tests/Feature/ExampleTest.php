<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The root path sends visitors into the app (login when unauthenticated).
     */
    public function test_root_redirects_into_the_app(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('dashboard'));
    }
}
