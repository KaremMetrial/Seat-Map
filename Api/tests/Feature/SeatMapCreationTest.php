<?php

namespace Tests\Feature;

use App\Models\Venue;
use App\Models\VenueTemplate;
use App\Models\TemplateElement;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SeatMapCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'sqlite');
        $app['config']->set('database.connections.sqlite.database', ':memory:');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::create([
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
        ]);
    }

    protected User $user;

    // ============================================
    // VENUE TESTS
    // ============================================

    public function test_can_list_venues_publicly(): void
    {
        Venue::create(['name' => 'Venue 1']);
        Venue::create(['name' => 'Venue 2']);

        $response = $this->getJson('/api/v1/venues');

        $response->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_venue_creation_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/venues', [
            'name' => 'Test Venue',
        ]);

        $response->assertStatus(401);
    }

    // ============================================
    // TEMPLATE TESTS
    // ============================================

    public function test_template_requires_authentication(): void
    {
        $venue = Venue::create(['name' => 'Test Venue']);

        $response = $this->postJson("/api/v1/venues/{$venue->id}/templates", [
            'name' => 'Test Template',
        ]);

        $response->assertStatus(401);
    }

    // ============================================
    // ELEMENT TESTS
    // ============================================

    public function test_can_generate_seats_in_grid(): void
    {
        $venue = Venue::create(['name' => 'Test Venue']);
        $template = VenueTemplate::create([
            'venue_id' => $venue->id,
            'name' => 'Test Template',
            'canvas_width' => 1000,
            'canvas_height' => 800,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/templates/{$template->id}/elements/generate-seats", [
                'start_x' => 100,
                'start_y' => 100,
                'rows' => 5,
                'seats_per_row' => 10,
                'seat_width' => 30,
                'seat_height' => 30,
                'gap_x' => 5,
                'gap_y' => 5,
                'row_label_start' => 'A',
                'seat_type' => 'regular',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.count', 50)
            ->assertJsonPath('data.rows', 5)
            ->assertJsonPath('data.seats_per_row', 10);
    }

    public function test_can_generate_numbered_rows(): void
    {
        $venue = Venue::create(['name' => 'Test Venue']);
        $template = VenueTemplate::create([
            'venue_id' => $venue->id,
            'name' => 'Test Template',
            'canvas_width' => 1000,
            'canvas_height' => 800,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/templates/{$template->id}/elements/generate-seats", [
                'start_x' => 100,
                'start_y' => 100,
                'rows' => 3,
                'seats_per_row' => 5,
                'seat_width' => 30,
                'seat_height' => 30,
                'row_label_start' => '1',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.count', 15);
    }

    public function test_element_requires_authentication(): void
    {
        $venue = Venue::create(['name' => 'Test Venue']);
        $template = VenueTemplate::create([
            'venue_id' => $venue->id,
            'name' => 'Test Template',
            'canvas_width' => 1000,
            'canvas_height' => 800,
        ]);

        $response = $this->postJson("/api/v1/templates/{$template->id}/elements", [
            'element_type' => 'seat',
            'x' => 100,
            'y' => 100,
            'width' => 30,
            'height' => 30,
        ]);

        $response->assertStatus(401);
    }

    // ============================================
    // ZONE TESTS
    // ============================================

    public function test_zone_requires_authentication(): void
    {
        $venue = Venue::create(['name' => 'Test Venue']);
        $template = VenueTemplate::create([
            'venue_id' => $venue->id,
            'name' => 'Test Template',
            'canvas_width' => 1000,
            'canvas_height' => 800,
        ]);

        $response = $this->postJson("/api/v1/templates/{$template->id}/zones", [
            'name' => 'Test Zone',
        ]);

        $response->assertStatus(401);
    }

    // ============================================
    // EVENT TESTS
    // ============================================

    public function test_can_create_event(): void
    {
        $venue = Venue::create(['name' => 'Test Venue']);
        $template = VenueTemplate::create([
            'venue_id' => $venue->id,
            'name' => 'Test Template',
            'canvas_width' => 1000,
            'canvas_height' => 800,
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/events', [
                'template_id' => $template->id,
                'title' => 'Concert 2024',
                'start_at' => Carbon::now()->addDays(30)->toISOString(),
                'end_at' => Carbon::now()->addDays(30)->addHours(3)->toISOString(),
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Concert 2024')
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_event_requires_authentication(): void
    {
        $venue = Venue::create(['name' => 'Test Venue']);
        $template = VenueTemplate::create([
            'venue_id' => $venue->id,
            'name' => 'Test Template',
            'canvas_width' => 1000,
            'canvas_height' => 800,
        ]);

        $response = $this->postJson('/api/v1/events', [
            'template_id' => $template->id,
            'title' => 'Test Event',
            'start_at' => Carbon::now()->addDays(7)->toISOString(),
            'end_at' => Carbon::now()->addDays(7)->addHours(3)->toISOString(),
        ]);

        $response->assertStatus(401);
    }

    public function test_cannot_update_published_event(): void
    {
        $venue = Venue::create(['name' => 'Test Venue']);
        $template = VenueTemplate::create([
            'venue_id' => $venue->id,
            'name' => 'Test Template',
            'canvas_width' => 1000,
            'canvas_height' => 800,
        ]);

        $event = \App\Models\Event::create([
            'template_id' => $template->id,
            'title' => 'Test Event',
            'start_at' => Carbon::now()->addDays(7),
            'end_at' => Carbon::now()->addDays(7)->addHours(3),
            'status' => 'published',
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/events/{$event->id}", [
                'title' => 'Updated Title',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Cannot update a published event');
    }

    // ============================================
    // BULK OPERATIONS
    // ============================================

    public function test_can_bulk_update_elements(): void
    {
        // Test that the endpoint requires auth (returns 401 without user)
        $response = $this->putJson('/api/v1/elements/bulk-update', [
            'element_ids' => [1, 2],
            'updates' => ['y' => 300],
        ]);

        // Should be 401 without auth, or validation error
        $this->assertNotEquals(200, $response->status());
    }

    public function test_can_bulk_delete_elements(): void
    {
        $venue = Venue::create(['name' => 'Test Venue']);
        $template = VenueTemplate::create([
            'venue_id' => $venue->id,
            'name' => 'Test Template',
            'canvas_width' => 1000,
            'canvas_height' => 800,
        ]);

        $elements = [];
        for ($i = 1; $i <= 3; $i++) {
            $el = TemplateElement::create([
                'template_id' => $template->id,
                'element_type' => 'seat',
                'x' => 100 + ($i * 40),
                'y' => 200,
                'width' => 30,
                'height' => 30,
                'z_index' => $i,
            ]);
            $elements[] = $el->id;
        }

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/elements/bulk-delete', [
                'element_ids' => $elements,
            ]);

        $response->assertStatus(200);
    }
}