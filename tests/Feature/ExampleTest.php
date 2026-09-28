<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $signedUrl = URL::signedRoute('app.panel', ['client' => 555001]);
        $response = $this->get($signedUrl);

        $response->assertStatus(200);
    }
}
