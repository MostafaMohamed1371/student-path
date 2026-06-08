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
        $this->assertSame('12 School Street, Mansour', $result['address']);
        $this->assertSame('Baghdad', $result['province']);
        $this->assertSame('Mansour', $result['district']);
    }

    public function test_resolve_uses_root_name_and_address_amenity_for_schools(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/reverse*' => Http::response([
                'name' => 'UFUQ Primary School - مدرسة أفق الابتدائية الأهلية',
                'display_name' => 'UFUQ Primary School - مدرسة أفق الابتدائية الأهلية, 609-2, المنصور, بغداد, العراق',
                'address' => [
                    'amenity' => 'UFUQ Primary School - مدرسة أفق الابتدائية الأهلية',
                    'road' => '609-2',
                    'quarter' => 'المنصور',
                    'state' => 'محافظة بغداد',
                ],
                'namedetails' => [
                    'name' => 'UFUQ Primary School - مدرسة أفق الابتدائية الأهلية',
                ],
            ]),
        ]);

        $result = app(NominatimReverseGeocoder::class)->resolve(33.3082, 44.3416, 'ar');

        $this->assertNotNull($result);
        $this->assertSame(
            'UFUQ Primary School - مدرسة أفق الابتدائية الأهلية, 609-2, المنصور',
            $result['address'],
        );
    }

    public function test_resolve_falls_back_to_poi_layer_when_address_has_only_street(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/reverse*layer=poi*' => Http::response([
                'name' => 'روضة رودينا الكوخ',
                'display_name' => 'روضة رودينا الكوخ, شارع 14 رمضان, المنصور, بغداد, العراق',
                'address' => [
                    'amenity' => 'روضة رودينا الكوخ',
                    'road' => 'شارع 14 رمضان',
                    'quarter' => 'المنصور',
                    'state' => 'محافظة بغداد',
                ],
            ]),
            'nominatim.openstreetmap.org/reverse*' => Http::response([
                'name' => '',
                'display_name' => '28, 609-4, محلة 609, المنصور, بغداد, العراق',
                'address' => [
                    'house_number' => '28',
                    'road' => '609-4',
                    'quarter' => 'المنصور',
                    'state' => 'محافظة بغداد',
                ],
            ]),
        ]);

        $result = app(NominatimReverseGeocoder::class)->resolve(33.3105, 44.3450, 'ar');

        $this->assertNotNull($result);
        $this->assertStringStartsWith('روضة رودينا الكوخ', $result['address']);
        $this->assertStringContainsString('28 609-4', $result['address']);
    }
}
