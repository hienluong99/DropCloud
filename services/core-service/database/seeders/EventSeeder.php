<?php

namespace Database\Seeders;

use App\Models\Event;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Event::updateOrCreate(
            ['id' => 1],
            [
                'title' => 'Đại Nhạc Hội Flash Sale 2026',
                'total_tickets' => 20,
                'available_tickets' => 20,
                'price' => 500000,
                'status' => 'ACTIVE',
            ]
        );
    }
}
