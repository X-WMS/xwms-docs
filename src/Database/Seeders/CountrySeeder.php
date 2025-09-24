<?php

namespace XWMS\Package\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = require __DIR__ . '/countriesArray.php';

        foreach ($countries as $country) {
            DB::table('countries')->updateOrInsert(
                ['id' => $country['id']],
                [
                    'short_name'   => $country['short_name'],
                    'name'         => $country['name'],
                    'phonecode'    => $country['phonecode'],
                    'is_eu_member' => $country['is_eu_member'],
                ]
            );
        }
    }
}
