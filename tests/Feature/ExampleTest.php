<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Buat user dummy untuk login saat test
        $this->user = User::factory()->create();
    }

    /**
     * Test that root redirects to dashboard.
     */
    public function test_root_redirects_to_dashboard(): void
    {
        $response = $this->get('/');
        $response->assertStatus(302);
        $response->assertRedirect('/dashboard');
    }

    /**
     * Test dashboard returns successful response.
     */
    public function test_dashboard_returns_success(): void
    {
        $response = $this->actingAs($this->user)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertSee('Dashboard');
    }

    /**
     * Test drive explorer returns successful response.
     */
    public function test_drive_returns_success(): void
    {
        $response = $this->actingAs($this->user)->get('/drive');
        $response->assertStatus(200);
        $response->assertSee('Drive Explorer');
    }

    /**
     * Test monitoring returns successful response.
     */
    public function test_monitoring_returns_success(): void
    {
        $response = $this->actingAs($this->user)->get('/monitoring');
        $response->assertStatus(200);
        $response->assertSee('Monitoring');
    }

    /**
     * Test navigate route redirects to index when parameters are missing.
     */
    public function test_navigate_route_redirects_on_failure(): void
    {
        $response = $this->actingAs($this->user)->get('/drive/navigate');
        $response->assertStatus(302);
        $response->assertRedirect('/drive');
    }

    /**
     * Test folder status update route.
     */
    public function test_folder_update_status_route(): void
    {
        $response = $this->actingAs($this->user)->post('/drive/folder/update-status', [
            'folder_id' => 'dummy-folder-id',
            'status'    => 'complete',
        ]);
        $response->assertStatus(302);
        $response->assertRedirect('/drive/dummy-folder-id');
    }
}
