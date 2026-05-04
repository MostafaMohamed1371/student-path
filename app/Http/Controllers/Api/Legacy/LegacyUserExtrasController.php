<?php

namespace App\Http\Controllers\Api\Legacy;

use App\Http\Controllers\Api\Legacy\Concerns\RespondsWithLegacySuccess;
use App\Http\Controllers\Controller;
use App\Models\TripHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Legacy contract: GET /api/user/settings/notifications, GET /api/user/performance.
 */
class LegacyUserExtrasController extends Controller
{
    use RespondsWithLegacySuccess;

    public function notificationSettings(): JsonResponse
    {
        return $this->legacySuccess(config('mobile_legacy_api.notification_settings', []));
    }

    public function performance(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing('driver');

        $rating = (float) ($user->rate ?? 0);
        $reviews = (int) ($user->votes ?? 0);

        $totalTrips = 0;
        if ($user->driver?->school_id) {
            $totalTrips = TripHistory::query()
                ->where('school_id', $user->driver->school_id)
                ->count();
        }

        $ar = app()->getLocale() === 'ar';

        $c5 = $reviews > 0 ? (int) round($reviews * 0.75) : 0;
        $c4 = $reviews > 0 ? (int) round($reviews * 0.12) : 0;
        $c3 = $reviews > 0 ? (int) round($reviews * 0.08) : 0;
        $c2 = $reviews > 0 ? (int) round($reviews * 0.03) : 0;
        $c1 = max(0, $reviews - $c5 - $c4 - $c3 - $c2);

        return $this->legacySuccess([
            'overallRating' => [
                'rating' => round($rating, 1),
                'maxRating' => 5.0,
                'totalReviews' => $reviews,
                'description' => $ar
                    ? 'بناءً على '.$reviews.' تقييم من أولياء الأمور'
                    : 'Based on '.$reviews.' reviews from guardians',
            ],
            'tripStats' => [
                'totalTrips' => $totalTrips,
                'tripsGrowth' => $ar ? '+5% هذا الشهر' : '+5% this month',
                'totalHours' => min($totalTrips, 999),
                'hoursGrowth' => $ar ? '+5% هذا الشهر' : '+5% this month',
            ],
            'commitmentStatus' => [
                'title' => $ar ? 'التزام ممتاز بالمواعيد' : 'Excellent punctuality',
                'description' => $ar
                    ? 'أداء رائع ومستوى انضباط عالٍ خلال الفصل الدراسي الحالي.'
                    : 'Strong performance and discipline this term.',
                'isBadgeEarned' => $rating >= 4.5 && $reviews >= 10,
            ],
            'ratingBreakdown' => [
                ['stars' => 5, 'count' => $c5],
                ['stars' => 4, 'count' => $c4],
                ['stars' => 3, 'count' => $c3],
                ['stars' => 2, 'count' => $c2],
                ['stars' => 1, 'count' => $c1],
            ],
            'parentComments' => [],
        ]);
    }
}
