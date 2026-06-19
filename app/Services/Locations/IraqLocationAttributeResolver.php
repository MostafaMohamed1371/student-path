<?php

namespace App\Services\Locations;

use App\Models\Area;
use App\Models\District;
use App\Models\Neighborhood;

final class IraqLocationAttributeResolver
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{
     *   district_id: int|null,
     *   area_id: int|null,
     *   neighborhood_id: int|null,
     *   district_area: string|null
     * }
     */
    public function resolve(array $input, string $fieldPrefix = ''): array
    {
        $districtId = (int) ($input[$fieldPrefix.'district_id'] ?? 0);
        $areaId = (int) ($input[$fieldPrefix.'area_id'] ?? 0);
        $neighborhoodId = (int) ($input[$fieldPrefix.'neighborhood_id'] ?? 0);

        if ($neighborhoodId > 0) {
            $neighborhood = Neighborhood::query()->with('area.district')->find($neighborhoodId);
            if ($neighborhood?->area) {
                $areaId = (int) $neighborhood->area_id;
                $districtId = (int) $neighborhood->area->district_id;
            }
        } elseif ($areaId > 0) {
            $area = Area::query()->find($areaId);
            if ($area) {
                $districtId = (int) $area->district_id;
            } else {
                $areaId = 0;
            }
        } elseif ($districtId > 0 && ! District::query()->whereKey($districtId)->exists()) {
            $districtId = 0;
        }

        return [
            'district_id' => $districtId > 0 ? $districtId : null,
            'area_id' => $areaId > 0 ? $areaId : null,
            'neighborhood_id' => $neighborhoodId > 0 ? $neighborhoodId : null,
            'district_area' => $this->label($districtId, $areaId, $neighborhoodId),
        ];
    }

    public function label(int $districtId, int $areaId, int $neighborhoodId): ?string
    {
        $parts = [];

        if ($neighborhoodId > 0) {
            $name = Neighborhood::query()->whereKey($neighborhoodId)->value('name');
            if (is_string($name) && trim($name) !== '') {
                $parts[] = trim($name);
            }
        }

        if ($areaId > 0) {
            $name = Area::query()->whereKey($areaId)->value('name');
            if (is_string($name) && trim($name) !== '') {
                $parts[] = trim($name);
            }
        }

        if ($districtId > 0) {
            $name = District::query()->whereKey($districtId)->value('name');
            if (is_string($name) && trim($name) !== '') {
                $parts[] = trim($name);
            }
        }

        if ($parts === []) {
            return null;
        }

        return implode(', ', $parts);
    }
}
