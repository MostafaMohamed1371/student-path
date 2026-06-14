<?php

namespace App\Services\Trips;

use App\Enums\TripType;
use App\Models\Driver;
use App\Models\School;
use App\Models\TripDriverReplacement;
use App\Models\TripHistory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class TripDriverReplacementService
{
    public function __construct(
        private readonly PickupReturnTripPairPlanner $pairPlanner,
    ) {}

    public function canManageReplacements(TripHistory $trip): bool
    {
        return $trip->recurring_template_id === null
            && (bool) ($trip->auto_schedule_work_days || $trip->is_recurring_template);
    }

    /**
     * @return Collection<int, TripDriverReplacement>
     */
    public function replacementsForTemplate(TripHistory $trip): Collection
    {
        return TripDriverReplacement::query()
            ->where('template_trip_id', (int) $trip->id)
            ->with('replacementDriver')
            ->orderBy('service_date')
            ->get();
    }

    public function effectiveDriverId(TripHistory $template, Carbon $serviceDay): ?int
    {
        $replacement = TripDriverReplacement::query()
            ->where('template_trip_id', (int) $template->id)
            ->whereDate('service_date', $serviceDay->toDateString())
            ->value('replacement_driver_id');

        if ($replacement !== null && (int) $replacement > 0) {
            return (int) $replacement;
        }

        $pairedTemplate = $this->pairedTemplateTrip($template);
        if ($pairedTemplate instanceof TripHistory && (int) $pairedTemplate->id !== (int) $template->id) {
            $pairedReplacement = TripDriverReplacement::query()
                ->where('template_trip_id', (int) $pairedTemplate->id)
                ->whereDate('service_date', $serviceDay->toDateString())
                ->value('replacement_driver_id');

            if ($pairedReplacement !== null && (int) $pairedReplacement > 0) {
                return (int) $pairedReplacement;
            }
        }

        $driverId = (int) ($template->driver_id ?? 0);

        return $driverId > 0 ? $driverId : null;
    }

    /**
     * @param  array<int, array{service_date: string, replacement_driver_id: int}>  $rows
     */
    public function syncReplacements(TripHistory $templateTrip, array $rows): void
    {
        if (! $this->canManageReplacements($templateTrip)) {
            return;
        }

        $templateTrip->loadMissing('school');
        $normalized = $this->normalizeRows($templateTrip, $rows);

        TripDriverReplacement::query()
            ->where('template_trip_id', (int) $templateTrip->id)
            ->whereNotIn('service_date', collect($normalized)->pluck('service_date')->all())
            ->delete();

        foreach ($normalized as $row) {
            TripDriverReplacement::query()->updateOrCreate(
                [
                    'template_trip_id' => (int) $templateTrip->id,
                    'service_date' => $row['service_date'],
                ],
                [
                    'replacement_driver_id' => $row['replacement_driver_id'],
                ],
            );

            $this->applyReplacementToScheduledLegs(
                $templateTrip,
                Carbon::parse($row['service_date'])->startOfDay(),
                (int) $row['replacement_driver_id'],
            );
        }

        $this->reapplyPrimaryDriverToDatesWithoutReplacement($templateTrip);
    }

    /**
     * @param  array<int, string|null>  $dates
     * @param  array<int, string|null>  $driverIds
     * @return array<int, array{service_date: string, replacement_driver_id: int}>
     */
    public function rowsFromRequest(array $dates, array $driverIds): array
    {
        $rows = [];
        $count = max(count($dates), count($driverIds));

        for ($index = 0; $index < $count; $index++) {
            $date = trim((string) ($dates[$index] ?? ''));
            $driverId = (int) ($driverIds[$index] ?? 0);

            if ($date === '' || $driverId <= 0) {
                continue;
            }

            $rows[] = [
                'service_date' => $date,
                'replacement_driver_id' => $driverId,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<int, array{service_date: string, replacement_driver_id: int}>  $rows
     * @return array<int, array{service_date: string, replacement_driver_id: int}>
     */
    private function normalizeRows(TripHistory $templateTrip, array $rows): array
    {
        $schoolId = (int) ($templateTrip->school_id ?? 0);
        $primaryDriverId = (int) ($templateTrip->driver_id ?? 0);
        $normalized = [];

        foreach ($rows as $row) {
            $date = trim((string) ($row['service_date'] ?? ''));
            $replacementDriverId = (int) ($row['replacement_driver_id'] ?? 0);

            if ($date === '' || $replacementDriverId <= 0) {
                continue;
            }

            try {
                $serviceDate = Carbon::parse($date)->toDateString();
            } catch (\Throwable) {
                throw ValidationException::withMessages([
                    'replacement_dates' => [__('dashboard.trip_replacement_invalid_date')],
                ]);
            }

            if ($replacementDriverId === $primaryDriverId) {
                throw ValidationException::withMessages([
                    'replacement_driver_ids' => [__('dashboard.trip_replacement_same_as_primary')],
                ]);
            }

            $driver = Driver::query()->find($replacementDriverId);
            if (! $driver instanceof Driver || $driver->status !== 'active') {
                throw ValidationException::withMessages([
                    'replacement_driver_ids' => [__('dashboard.trip_replacement_driver_inactive')],
                ]);
            }

            if ((int) $driver->school_id !== $schoolId) {
                throw ValidationException::withMessages([
                    'replacement_driver_ids' => [__('dashboard.trip_replacement_driver_school_mismatch')],
                ]);
            }

            $normalized[$serviceDate] = [
                'service_date' => $serviceDate,
                'replacement_driver_id' => $replacementDriverId,
            ];
        }

        return array_values($normalized);
    }

    private function applyReplacementToScheduledLegs(
        TripHistory $templateTrip,
        Carbon $serviceDay,
        int $replacementDriverId,
    ): void {
        $this->applyDriverToTemplateLegForDate($templateTrip, $serviceDay, $replacementDriverId);

        $pairedTemplate = $this->pairedTemplateTrip($templateTrip);
        if ($pairedTemplate instanceof TripHistory) {
            $this->applyDriverToTemplateLegForDate($pairedTemplate, $serviceDay, $replacementDriverId);
        }
    }

    private function applyDriverToTemplateLegForDate(
        TripHistory $templateTrip,
        Carbon $serviceDay,
        int $driverId,
    ): void {
        $tz = (string) (config('app.timezone') ?: 'UTC');
        $day = $serviceDay->copy()->timezone($tz)->startOfDay();

        $tripForDay = $this->resolveTripInstanceForTemplateDay($templateTrip, $day, $tz);
        if (! $tripForDay instanceof TripHistory) {
            return;
        }

        if ((int) ($tripForDay->driver_id ?? 0) === $driverId) {
            return;
        }

        $tripForDay->forceFill(['driver_id' => $driverId])->save();
    }

    private function reapplyPrimaryDriverToDatesWithoutReplacement(TripHistory $templateTrip): void
    {
        $tz = (string) (config('app.timezone') ?: 'UTC');

        $templates = collect([$templateTrip]);
        $pairedTemplate = $this->pairedTemplateTrip($templateTrip);
        if ($pairedTemplate instanceof TripHistory) {
            $templates->push($pairedTemplate);
        }

        foreach ($templates as $template) {
            $primaryDriverId = (int) ($template->driver_id ?? 0);
            if ($primaryDriverId <= 0) {
                continue;
            }

            $replacementDates = TripDriverReplacement::query()
                ->where('template_trip_id', (int) $template->id)
                ->get()
                ->map(fn (TripDriverReplacement $row): string => $row->service_date?->toDateString() ?? '')
                ->filter(fn (string $date): bool => $date !== '')
                ->all();

            $instances = TripHistory::query()
                ->where(function ($query) use ($template): void {
                    $query->where('id', (int) $template->id)
                        ->orWhere('recurring_template_id', (int) $template->id);
                })
                ->get();

            foreach ($instances as $trip) {
                if ($trip->start_time === null) {
                    continue;
                }

                $dayKey = $trip->start_time->copy()->timezone($tz)->toDateString();
                if (in_array($dayKey, $replacementDates, true)) {
                    continue;
                }

                if ((int) ($trip->driver_id ?? 0) !== $primaryDriverId) {
                    $trip->forceFill(['driver_id' => $primaryDriverId])->save();
                }
            }
        }
    }

    private function resolveTripInstanceForTemplateDay(
        TripHistory $templateTrip,
        Carbon $serviceDay,
        string $tz,
    ): ?TripHistory {
        $anchorDay = $templateTrip->start_time instanceof Carbon
            ? $templateTrip->start_time->copy()->timezone($tz)->startOfDay()
            : ($templateTrip->start_time !== null
                ? Carbon::parse((string) $templateTrip->start_time, $tz)->startOfDay()
                : null);

        if ($anchorDay !== null && $anchorDay->equalTo($serviceDay)) {
            return $templateTrip;
        }

        $dayStart = $serviceDay->copy()->startOfDay();
        $dayEnd = $serviceDay->copy()->endOfDay();

        return TripHistory::query()
            ->where('recurring_template_id', (int) $templateTrip->id)
            ->whereBetween('start_time', [$dayStart, $dayEnd])
            ->orderBy('id')
            ->first();
    }

    private function pairedTemplateTrip(TripHistory $templateTrip): ?TripHistory
    {
        $templateTrip->loadMissing('school');
        $school = $templateTrip->school;
        if (! $school instanceof School) {
            $school = School::query()->find((int) $templateTrip->school_id);
        }
        if (! $school instanceof School || $templateTrip->start_time === null) {
            return null;
        }

        $tripType = trim((string) ($templateTrip->trip_type ?? ''));

        if (TripType::isPickup($tripType)) {
            return $this->pairPlanner->findReturnTripForPickup($templateTrip);
        }

        if (TripType::isReturn($tripType)) {
            return $this->pairPlanner->findPickupTripForReturn($templateTrip);
        }

        return null;
    }
}
