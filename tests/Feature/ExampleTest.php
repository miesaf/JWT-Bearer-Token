<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_welcome_page_loads(): void
    {
        $this->get('/')->assertOk()->assertSee(config('app.name'));
    }
}
