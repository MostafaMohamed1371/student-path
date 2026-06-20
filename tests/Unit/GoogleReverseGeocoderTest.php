<?php

namespace Tests\Unit;

use App\Services\Geo\GoogleReverseGeocoder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleReverseGeocoderTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_reverse_geocoder_maps_formatted_address(): void
    {
        config(['google.geocoding_api_key' => 'test-key']);

        Http::fake([
            'maps.googleapis.com/maps/api/geocode/json*' => Http::response([
                'status' => 'OK',
                'results' => [[
                    'formatted_address' => 'Example Road, Baghdad, Iraq',
                    'address_components' => [
                        [
                            'long_name' => 'Baghdad',
                            'types' => ['administrative_area_level_1'],
                        ],
                        [
                            'long_name' => 'Karrada',
                            'types' => ['sublocality'],
                        ],
                    ],
                ]],
            ]),
        ]);

        $result = app(GoogleReverseGeocoder::class)->resolve(33.3128, 44.3615, 'en');

        $this->assertSame('Example Road, Baghdad, Iraq', $result['address']);
        $this->assertSame('Baghdad', $result['province']);
        $this->assertSame('Karrada', $result['district']);
    }
}
