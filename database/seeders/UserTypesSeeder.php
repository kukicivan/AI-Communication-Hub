<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\UserType;

class UserTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $types = [
            [
                'name' => 'super_admin',
                'description' => 'Super Administrator with full system access',
            ],
            [
                'name' => 'admin',
                'description' => 'Administrator with limited system access',
            ],
            [
                'name' => 'tourism_organization',
                'description' => 'Tourism Organization account',
            ],
            [
                'name' => 'travel_agency',
                'description' => 'Travel Agency account',
            ],
            [
                'name' => 'hotel',
                'description' => 'Hotel account',
            ],
        ];

        foreach ($types as $type) {
            UserType::create($type);
        }
    }
}
