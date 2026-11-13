<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\State;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class IndiaStateCitySeeder extends Seeder
{
    /**
     * Canonical Indian states and union territories.
     */
    private const STATES = [
        'Andaman and Nicobar Islands',
        'Andhra Pradesh',
        'Arunachal Pradesh',
        'Assam',
        'Bihar',
        'Chandigarh',
        'Chhattisgarh',
        'Dadra and Nagar Haveli and Daman and Diu',
        'Delhi',
        'Goa',
        'Gujarat',
        'Haryana',
        'Himachal Pradesh',
        'Jammu and Kashmir',
        'Jharkhand',
        'Karnataka',
        'Kerala',
        'Ladakh',
        'Lakshadweep',
        'Madhya Pradesh',
        'Maharashtra',
        'Manipur',
        'Meghalaya',
        'Mizoram',
        'Nagaland',
        'Odisha',
        'Puducherry',
        'Punjab',
        'Rajasthan',
        'Sikkim',
        'Tamil Nadu',
        'Telangana',
        'Tripura',
        'Uttar Pradesh',
        'Uttarakhand',
        'West Bengal',
    ];

    /**
     * Normalize the dataset's older spellings to current official names.
     */
    private const STATE_ALIASES = [
        'Dadra and Nagar Haveli' => 'Dadra and Nagar Haveli and Daman and Diu',
        'Himachal Praddesh' => 'Himachal Pradesh',
    ];

    public function run(): void
    {
        $path = database_path('data/india_cities.json');

        if (! file_exists($path)) {
            throw new RuntimeException("India cities dataset not found at {$path}.");
        }

        $cities = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        DB::transaction(function () use ($cities) {
            $states = [];

            foreach (self::STATES as $stateName) {
                $states[$stateName] = $this->upsertState($stateName);
            }

            foreach ($cities as $cityData) {
                if (! is_array($cityData) || ! isset($cityData['name'], $cityData['state'])) {
                    continue;
                }

                $stateName = $this->normalizeStateName((string) $cityData['state']);
                $cityName = $this->normalizeName((string) $cityData['name']);

                if ($stateName === '' || $cityName === '') {
                    continue;
                }

                if (! isset($states[$stateName])) {
                    $states[$stateName] = $this->upsertState($stateName);
                }

                $this->upsertCity($states[$stateName], $cityName);
            }
        });
    }

    private function normalizeStateName(string $name): string
    {
        $name = $this->normalizeName($name);

        return self::STATE_ALIASES[$name] ?? $name;
    }

    private function normalizeName(string $name): string
    {
        $name = trim($name);

        return preg_replace('/\s+/', ' ', $name) ?? $name;
    }

    private function upsertState(string $name): State
    {
        $state = State::withTrashed()->firstOrNew(['name' => $name]);
        $state->name = $name;

        if ($state->exists && $state->trashed()) {
            $state->restore();
        } else {
            $state->save();
        }

        return $state->refresh();
    }

    private function upsertCity(State $state, string $name): City
    {
        $city = City::withTrashed()->firstOrNew([
            'state_id' => $state->id,
            'name' => $name,
        ]);

        $city->state_id = $state->id;
        $city->name = $name;

        if ($city->exists && $city->trashed()) {
            $city->restore();
        } else {
            $city->save();
        }

        return $city->refresh();
    }
}
