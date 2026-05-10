<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SeatmapSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a venue
        $venueId = DB::table('venues')->insertGetId([
            'name' => 'Grand Theater',
            'slug' => 'grand-theater',
            'description' => 'A beautiful theater with 200 seats',
            'venue_type' => 'theater',
            'default_width' => 800,
            'default_height' => 600,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create a template
        $templateId = DB::table('venue_templates')->insertGetId([
            'venue_id' => $venueId,
            'name' => 'Main Hall Layout',
            'slug' => 'main-hall',
            'description' => 'Standard theater layout',
            'canvas_width' => 800,
            'canvas_height' => 600,
            'background_color' => '#1a1a2e',
            'grid_size' => 10,
            'show_grid' => true,
            'is_default' => true,
            'is_active' => true,
            'scale_factor' => 0.05,
            'units' => 'meters',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create zones
        $vipZoneId = DB::table('template_zones')->insertGetId([
            'template_id' => $templateId,
            'name' => 'VIP Section',
            'code' => 'VIP',
            'description' => 'Premium seats with best view',
            'color' => '#ffd700',
            'priority' => 1,
            'base_price' => 25.00,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $standardZoneId = DB::table('template_zones')->insertGetId([
            'template_id' => $templateId,
            'name' => 'Standard Section',
            'code' => 'STD',
            'description' => 'Regular seats',
            'color' => '#3b82f6',
            'priority' => 2,
            'base_price' => 0.00,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create seats (10 rows x 20 seats = 200 seats)
        $elements = [];
        $seatWidth = 30;
        $seatHeight = 25;
        $paddingX = 50;
        $paddingY = 50;
        $gapX = 5;
        $gapY = 5;
        
        for ($row = 0; $row < 10; $row++) {
            for ($col = 0; $col < 20; $col++) {
                $rowLabel = chr(65 + $row); // A-J
                $seatNumber = $col + 1;
                
                // VIP = first 3 rows
                $isVip = $row < 3;
                $zoneId = $isVip ? $vipZoneId : $standardZoneId;
                
                $elements[] = [
                    'template_id' => $templateId,
                    'element_type' => 'seat',
                    'x' => $paddingX + $col * ($seatWidth + $gapX),
                    'y' => $paddingY + $row * ($seatHeight + $gapY),
                    'width' => $seatWidth,
                    'height' => $seatHeight,
                    'rotation' => 0,
                    'z_index' => 10,
                    'data_json' => json_encode([
                        'label' => "{$rowLabel}-{$seatNumber}",
                        'row' => $rowLabel,
                        'seat_number' => $seatNumber,
                        'seat_type' => $isVip ? 'vip' : 'standard',
                    ]),
                    'style_json' => json_encode([
                        'fill' => $isVip ? '#ffd700' : '#10b981',
                        'stroke' => '#ffffff',
                        'strokeWidth' => 1,
                    ]),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Add a stage
        $elements[] = [
            'template_id' => $templateId,
            'element_type' => 'stage',
            'x' => 50,
            'y' => 400,
            'width' => 700,
            'height' => 80,
            'rotation' => 0,
            'z_index' => 5,
            'data_json' => json_encode(['label' => 'Stage']),
            'style_json' => json_encode(['fill' => '#64748b', 'stroke' => '#94a3b8']),
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Add an entrance
        $elements[] = [
            'template_id' => $templateId,
            'element_type' => 'entrance',
            'x' => 350,
            'y' => 520,
            'width' => 100,
            'height' => 30,
            'rotation' => 0,
            'z_index' => 5,
            'data_json' => json_encode(['label' => 'Main Entrance']),
            'style_json' => json_encode(['fill' => '#ef4444', 'stroke' => '#fca5a5']),
            'is_active' => false, // entrances are not bookable
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Insert all elements
        foreach (array_chunk($elements, 50) as $chunk) {
            DB::table('template_elements')->insert($chunk);
        }

        // Create an event
        $eventId = DB::table('events')->insertGetId([
            'template_id' => $templateId,
            'title' => 'Summer Concert 2026',
            'slug' => 'summer-concert-2026',
            'description' => 'An amazing summer concert',
            'start_at' => now()->addDays(30),
            'end_at' => now()->addDays(30)->addHours(3),
            'booking_open_at' => now(),
            'booking_close_at' => now()->addDays(29),
            'status' => 'draft',
            'base_price' => 50.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Seatmap test data seeded successfully!');
        $this->command->info("Venue ID: {$venueId}");
        $this->command->info("Template ID: {$templateId}");
        $this->command->info("Event ID: {$eventId}");
        $this->command->info("Created " . count($elements) . " elements (200 seats + stage + entrance)");
    }
}
