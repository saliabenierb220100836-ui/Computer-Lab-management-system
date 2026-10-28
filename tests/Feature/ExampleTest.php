<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_homepage_redirects_to_the_lab_dashboard_and_login_page_is_available(): void
    {
        $this->get('/')->assertRedirect('/dashboard');
        $this->get('/login')->assertOk()->assertSee('Your lab,');
    }
}
