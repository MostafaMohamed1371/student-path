<?php

namespace Tests\Concerns;

use App\Models\Area;
use App\Models\Neighborhood;
use Database\Seeders\IraqLocationsSeeder;

trait ProvidesDashboardIraqLocationFields
{
    /**
     * @return array{district_id: int, area_id: int, neighborhood_id: int}
     */
    protected function dashboardIraqLocationFields(string $fieldPrefix = ''): array
    {
        $this->seed(IraqLocationsSeeder::class);

        $neighborhood = Neighborhood::query()->orderBy('id')->firstOrFail();
        $area = Area::query()->findOrFail((int) $neighborhood->area_id);

        return [
            $fieldPrefix.'district_id' => (int) $area->district_id,
            $fieldPrefix.'area_id' => (int) $area->id,
            $fieldPrefix.'neighborhood_id' => (int) $neighborhood->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function withStudentIraqLocation(array $payload): array
    {
        return array_merge($payload, $this->dashboardIraqLocationFields());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function withGuardianHomeIraqLocation(array $payload): array
    {
        return array_merge($payload, $this->dashboardIraqLocationFields('home_'));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function withSchoolIraqLocation(array $payload): array
    {
        return array_merge($payload, $this->dashboardIraqLocationFields());
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function withTripStartIraqLocation(array $payload): array
    {
        return array_merge($payload, $this->dashboardIraqLocationFields('start_'));
    }
}
