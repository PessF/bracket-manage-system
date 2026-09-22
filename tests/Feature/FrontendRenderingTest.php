<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FrontendRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_unbuilt_pages_use_the_shared_responsive_styles_and_helpers(): void
    {
        // Point only this test's asset lookup at an absent directory. Never move
        // or delete the developer's real build output to exercise the fallback.
        $this->app->usePublicPath(sys_get_temp_dir().'/easykids-unbuilt-'.uniqid());

        $this->get('/events')->assertOk()
            ->assertSee('One restrained dark palette', false)
            ->assertSee('prepareResponsiveContent', false)
            ->assertSee('skip-link', false);

        $this->actingAs(User::factory()->create(['role' => UserRole::ADMIN]))
            ->get('/tournaments/create')->assertOk()
            ->assertSee('class="date-time-grid"', false)
            ->assertSee('smart-select-fallback.js', false);
    }
}
