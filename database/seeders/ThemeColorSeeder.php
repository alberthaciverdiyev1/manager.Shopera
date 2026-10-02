<?php

namespace Database\Seeders;

use App\Services\ThemeService;
use Illuminate\Database\Seeder;

class ThemeColorSeeder extends Seeder
{
    public function run(): void
    {
        app(ThemeService::class)->ensureDefaults();
    }
}
