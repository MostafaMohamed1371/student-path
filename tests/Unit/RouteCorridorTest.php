<?php

namespace Tests\Unit;

use App\Support\Geo\RouteCorridor;
use PHPUnit\Framework\TestCase;

class RouteCorridorTest extends TestCase
{
    public function test_point_on_segment_has_low_distance(): void
    {
        $result = RouteCorridor::pointToSegment(
            33.3105,
            44.3605,
            33.311,
            44.361,
            33.31,
            44.36,
        );

        $this->assertLessThan(500, $result['distance_meters']);
        $this->assertGreaterThan(0.1, $result['projection_t']);
        $this->assertLessThan(0.9, $result['projection_t']);
    }

    public function test_point_far_from_segment_is_off_corridor(): void
    {
        $this->assertFalse(RouteCorridor::isOnCorridor(
            33.40,
            44.50,
            33.311,
            44.361,
            33.31,
            44.36,
            3000,
        ));
    }
}
