<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        // Force SQLite for testing environment
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
    }

    public function test_unauthenticated_user_cannot_access_protected_routes(): void
    {
        // Test protected endpoint returns 401
        $response = $this->postJson('/api/v1/bookings', [
            'event_id' => 1,
            'element_ids' => [1],
            'customer_name' => 'John Doe',
            'customer_email' => 'john@example.com',
        ]);

        $response->assertStatus(401)
            ->assertJsonStructure(['message']);
    }

    public function test_api_returns_json_error_for_unauthenticated(): void
    {
        $response = $this->postJson('/api/v1/bookings', []);

        $response->assertJson([
            'message' => 'Unauthenticated.',
        ]);
    }

    public function test_public_endpoints_accessible_without_auth(): void
    {
        // Public endpoints should not return 401
        $response = $this->getJson('/api/v1/events/1/seatmap');

        // Should be 404 (event not found) or 200, but NOT 401
        $this->assertNotEquals(401, $response->status());
    }

    public function test_booking_requires_auth_returns_401(): void
    {
        // Another protected endpoint test
        $response = $this->postJson('/api/v1/bookings/lock', [
            'element_ids' => [1, 2],
        ]);

        $this->assertEquals(401, $response->status());
    }
}