<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Page;

class PageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 30 dummy pages insert karenge
        for ($i = 1; $i <= 30; $i++) {
            Page::create([
                'owner_id'          => rand(1, 10), // random user id
                'page_name'         => "Page $i",
                'page_description'  => "This is the description for Page $i.",
                'page_profile_photo'=> null,
                'page_cover_photo'  => null,
                'page_category'     => "Category " . rand(1, 5),
                'page_location'     => "Location " . rand(1, 5),
                'page_type'         => "Type " . rand(1, 3),
            ]);
        }
    }
}
