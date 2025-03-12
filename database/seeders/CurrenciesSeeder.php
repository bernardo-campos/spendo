<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CurrenciesSeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['name' => 'Pesos', 'symbol' => 'AR$'],
            ['name' => 'Dólares', 'symbol' => 'US$'],
        ];

        foreach ($currencies as $currency) {
            DB::table('currencies')->updateOrInsert(
                [
                    'symbol' => $currency['symbol'],
                ],[
                    'name' => $currency['name'],
                ]);

        }
    }
}
