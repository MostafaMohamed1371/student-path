<?php

namespace Tests\Unit;

use App\Services\Geo\NominatimReverseGeocoder;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NominatimReverseGeocoderTest extends TestCase
{
    public function test_resolve_maps_display_name_and_admin_areas(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/reverse*' => Http::response([
                'display_name' => 'School Street, Mansour, Baghdad, Iraq',
                'address' => [
                    'house_number' => '12',
                    'road' => 'School Street',
                    'suburb' => 'Mansour',
                    'state' => 'Baghdad',
                ],
            ]),
        ]);

        $result = app(NominatimReverseGeocoder::class)->resolve(33.3, 44.3, 'en');

        $this->assertNotNull($result);
        $this->assertSame('12, School Street, Mansour', $result['address']);
        $this->assertSame('Baghdad', $result['province']);
        $this->assertSame('Mansour', $result['district']);
    }
}
