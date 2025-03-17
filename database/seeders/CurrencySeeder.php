<?php

namespace Database\Seeders;

use App\Models\Currency;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Currency::firstOrCreate([
            'symbol' => '$',
            'name' => 'Pesos',
        ]);
        Currency::firstOrCreate([
            'symbol' => 'US$',
            'name' => 'Dólares',
        ]);
    }
}
