@php
    $replacementRows = collect();

    $oldDates = old('replacement_dates');
    $oldDriverIds = old('replacement_driver_ids');

    if (is_array($oldDates) && is_array($oldDriverIds)) {
        $count = max(count($oldDates), count($oldDriverIds));
        for ($index = 0; $index < $count; $index++) {
            $date = trim((string) ($oldDates[$index] ?? ''));
            $driverId = (int) ($oldDriverIds[$index] ?? 0);
            if ($date === '' && $driverId <= 0) {
                continue;
            }

            $replacementRows->push([
                'service_date' => $date,
                'replacement_driver_id' => $driverId > 0 ? $driverId : null,
            ]);
        }
    } elseif (($replacementDrivers ?? collect())->isNotEmpty()) {
        foreach ($replacementDrivers as $replacement) {
            $replacementRows->push([
                'service_date' => optional($replacement->service_date)->format('Y-m-d') ?? '',
                'replacement_driver_id' => (int) ($replacement->replacement_driver_id ?? 0) ?: null,
            ]);
        }
    }

    if ($replacementRows->isEmpty()) {
        $replacementRows->push([
            'service_date' => '',
            'replacement_driver_id' => null,
        ]);
    }

    $driverOptions = $allDrivers ?? $drivers ?? collect();
@endphp

<section class="card" id="trip_replacement_drivers_section" style="grid-column:1 / -1;padding:14px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;">
    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;flex-wrap:wrap;margin-bottom:12px;">
        <div>
            <strong style="display:block;font-size:15px;">{{ __('dashboard.trip_replacement_drivers_title') }}</strong>
            <p class="help" style="margin:6px 0 0;">{{ __('dashboard.trip_replacement_drivers_help') }}</p>
        </div>
        <button type="button" class="btn-muted" id="trip_replacement_add_row" style="padding:6px 12px;font-size:12px;">
            {{ __('dashboard.trip_replacement_add_row') }}
        </button>
    </div>

    <div id="trip_replacement_rows">
        @foreach($replacementRows as $index => $row)
            <div class="trip-replacement-row" data-index="{{ $index }}" style="display:grid;grid-template-columns:1fr 1fr auto;gap:10px;align-items:end;margin-bottom:10px;">
                <label>
                    <span>{{ __('dashboard.trip_replacement_date') }}</span>
                    <input
                        type="date"
                        name="replacement_dates[]"
                        value="{{ $row['service_date'] ?? '' }}"
                    >
                </label>

                <label>
                    <span>{{ __('dashboard.trip_replacement_driver') }}</span>
                    <select name="replacement_driver_ids[]">
                        <option value="">—</option>
                        @foreach($driverOptions as $driverOption)
                            <option
                                value="{{ $driverOption->id }}"
                                @selected((string) ($row['replacement_driver_id'] ?? '') === (string) $driverOption->id)
                            >
                                {{ trim(($driverOption->first_name ?? '').' '.($driverOption->last_name ?? '')) }} (#{{ $driverOption->id }})
                            </option>
                        @endforeach
                    </select>
                </label>

                <button
                    type="button"
                    class="btn-muted trip-replacement-remove"
                    style="padding:8px 12px;font-size:12px;margin-bottom:2px;"
                    @if($replacementRows->count() <= 1) hidden @endif
                >
                    {{ __('dashboard.trip_replacement_remove_row') }}
                </button>
            </div>
        @endforeach
    </div>

    @error('replacement_dates')
        <p class="help" style="color:#b91c1c;margin:8px 0 0;">{{ $message }}</p>
    @enderror
    @error('replacement_driver_ids')
        <p class="help" style="color:#b91c1c;margin:8px 0 0;">{{ $message }}</p>
    @enderror
</section>

<template id="trip_replacement_row_template">
    <div class="trip-replacement-row" data-index="__INDEX__" style="display:grid;grid-template-columns:1fr 1fr auto;gap:10px;align-items:end;margin-bottom:10px;">
        <label>
            <span>{{ __('dashboard.trip_replacement_date') }}</span>
            <input type="date" name="replacement_dates[]" value="">
        </label>

        <label>
            <span>{{ __('dashboard.trip_replacement_driver') }}</span>
            <select name="replacement_driver_ids[]">
                <option value="">—</option>
                @foreach($driverOptions as $driverOption)
                    <option value="{{ $driverOption->id }}">
                        {{ trim(($driverOption->first_name ?? '').' '.($driverOption->last_name ?? '')) }} (#{{ $driverOption->id }})
                    </option>
                @endforeach
            </select>
        </label>

        <button type="button" class="btn-muted trip-replacement-remove" style="padding:8px 12px;font-size:12px;margin-bottom:2px;">
            {{ __('dashboard.trip_replacement_remove_row') }}
        </button>
    </div>
</template>

<script>
    (function () {
        const container = document.getElementById('trip_replacement_rows');
        const template = document.getElementById('trip_replacement_row_template');
        const addButton = document.getElementById('trip_replacement_add_row');

        if (!container || !template || !addButton) {
            return;
        }

        function refreshRemoveButtons() {
            const rows = container.querySelectorAll('.trip-replacement-row');
            rows.forEach(function (row) {
                const removeButton = row.querySelector('.trip-replacement-remove');
                if (removeButton) {
                    removeButton.hidden = rows.length <= 1;
                }
            });
        }

        function bindRemoveButton(row) {
            const removeButton = row.querySelector('.trip-replacement-remove');
            if (!removeButton || removeButton.dataset.bound === '1') {
                return;
            }

            removeButton.dataset.bound = '1';
            removeButton.addEventListener('click', function () {
                const rows = container.querySelectorAll('.trip-replacement-row');
                if (rows.length <= 1) {
                    return;
                }

                row.remove();
                refreshRemoveButtons();
            });
        }

        addButton.addEventListener('click', function () {
            const index = container.querySelectorAll('.trip-replacement-row').length;
            const html = template.innerHTML.replace(/__INDEX__/g, String(index));
            const wrapper = document.createElement('div');
            wrapper.innerHTML = html.trim();
            const row = wrapper.firstElementChild;
            if (!row) {
                return;
            }

            container.appendChild(row);
            bindRemoveButton(row);
            refreshRemoveButtons();
        });

        container.querySelectorAll('.trip-replacement-row').forEach(bindRemoveButton);
        refreshRemoveButtons();
    })();
</script>
