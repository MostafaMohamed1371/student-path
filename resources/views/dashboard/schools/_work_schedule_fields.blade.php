@php
    use App\Services\Trips\DriverShiftResolver;

    $selectedWorkDays = old('work_days', $school->work_days ?? []);
    if (! is_array($selectedWorkDays)) {
        $selectedWorkDays = [];
    }

    $selectedShift = old('shift_period', $school->shift_period ?? DriverShiftResolver::MORNING);
    $isBothShift = $selectedShift === 'BOTH';
    $isEveningOnly = $selectedShift === DriverShiftResolver::EVENING;
@endphp

<div class="form-grid">
    <div>
        <label class="field-label" for="shift_period">{{ __('dashboard.shift_period') }}</label>
        <select class="input" id="shift_period" name="shift_period" required>
            <option value="{{ DriverShiftResolver::MORNING }}" @selected($selectedShift === DriverShiftResolver::MORNING)>{{ __('dashboard.shift_period_morning') }}</option>
            <option value="{{ DriverShiftResolver::EVENING }}" @selected($selectedShift === DriverShiftResolver::EVENING)>{{ __('dashboard.shift_period_evening') }}</option>
            <option value="BOTH" @selected($selectedShift === 'BOTH')>{{ __('dashboard.shift_period_both') }}</option>
        </select>
    </div>
    <div></div>

    <div style="grid-column: 1 / -1;">
        <span class="field-label">{{ __('dashboard.work_days') }}</span>
        <div style="display:flex;flex-wrap:wrap;gap:12px 18px;margin-top:8px;">
            @foreach(\App\Support\SchoolWorkDay::keys() as $dayKey)
                <label style="display:inline-flex;align-items:center;gap:8px;">
                    <input
                        type="checkbox"
                        name="work_days[]"
                        value="{{ $dayKey }}"
                        @checked(\App\Support\SchoolWorkDay::isSelected($selectedWorkDays, $dayKey))
                    >
                    <span>{{ \App\Support\SchoolWorkDay::label($dayKey) }}</span>
                </label>
            @endforeach
        </div>
    </div>

    <div id="primary_shift_times_wrap" style="display: contents;">
        <div style="grid-column: 1 / -1;" id="primary_shift_times_heading">
            <h4 style="margin: 8px 0 0; font-size: 15px;" id="primary_shift_times_label">
                @if($isBothShift)
                    {{ __('dashboard.school_work_schedule_morning_times') }}
                @elseif($isEveningOnly)
                    {{ __('dashboard.school_work_schedule_evening_times') }}
                @else
                    {{ __('dashboard.school_work_schedule_times') }}
                @endif
            </h4>
        </div>

        <div>
            <label class="field-label" for="work_time_from" id="work_time_from_label">
                @if($isBothShift)
                    {{ __('dashboard.work_time_from') }} ({{ __('dashboard.shift_period_morning') }})
                @elseif($isEveningOnly)
                    {{ __('dashboard.work_time_from') }} ({{ __('dashboard.shift_period_evening') }})
                @else
                    {{ __('dashboard.work_time_from') }}
                @endif
            </label>
            <input
                class="input"
                id="work_time_from"
                name="work_time_from"
                type="time"
                value="{{ old('work_time_from', \App\Support\SchoolWorkDay::formatTimeForInput($school->work_time_from ?? null)) }}"
            />
        </div>

        <div>
            <label class="field-label" for="work_time_to" id="work_time_to_label">
                @if($isBothShift)
                    {{ __('dashboard.work_time_to') }} ({{ __('dashboard.shift_period_morning') }})
                @elseif($isEveningOnly)
                    {{ __('dashboard.work_time_to') }} ({{ __('dashboard.shift_period_evening') }})
                @else
                    {{ __('dashboard.work_time_to') }}
                @endif
            </label>
            <input
                class="input"
                id="work_time_to"
                name="work_time_to"
                type="time"
                value="{{ old('work_time_to', \App\Support\SchoolWorkDay::formatTimeForInput($school->work_time_to ?? null)) }}"
            />
        </div>
    </div>

    <div id="evening_shift_times_wrap" style="display: {{ $isBothShift ? 'contents' : 'none' }};">
        <div style="grid-column: 1 / -1;">
            <h4 style="margin: 8px 0 0; font-size: 15px;">{{ __('dashboard.school_work_schedule_evening_times') }}</h4>
        </div>

        <div>
            <label class="field-label" for="evening_work_time_from">{{ __('dashboard.work_time_from') }} ({{ __('dashboard.shift_period_evening') }})</label>
            <input
                class="input"
                id="evening_work_time_from"
                name="evening_work_time_from"
                type="time"
                value="{{ old('evening_work_time_from', \App\Support\SchoolWorkDay::formatTimeForInput($school->evening_work_time_from ?? null)) }}"
            />
        </div>

        <div>
            <label class="field-label" for="evening_work_time_to">{{ __('dashboard.work_time_to') }} ({{ __('dashboard.shift_period_evening') }})</label>
            <input
                class="input"
                id="evening_work_time_to"
                name="evening_work_time_to"
                type="time"
                value="{{ old('evening_work_time_to', \App\Support\SchoolWorkDay::formatTimeForInput($school->evening_work_time_to ?? null)) }}"
            />
        </div>
    </div>
</div>

<script>
(function () {
    const shiftSelect = document.getElementById('shift_period');
    const eveningWrap = document.getElementById('evening_shift_times_wrap');
    const primaryLabel = document.getElementById('primary_shift_times_label');
    const fromLabel = document.getElementById('work_time_from_label');
    const toLabel = document.getElementById('work_time_to_label');
    const eveningFrom = document.getElementById('evening_work_time_from');
    const eveningTo = document.getElementById('evening_work_time_to');

    if (!shiftSelect || !eveningWrap) {
        return;
    }

    const labels = {
        morning: @json(__('dashboard.shift_period_morning')),
        evening: @json(__('dashboard.shift_period_evening')),
        times: @json(__('dashboard.school_work_schedule_times')),
        morningTimes: @json(__('dashboard.school_work_schedule_morning_times')),
        eveningTimes: @json(__('dashboard.school_work_schedule_evening_times')),
        from: @json(__('dashboard.work_time_from')),
        to: @json(__('dashboard.work_time_to')),
    };

    function syncShiftUi() {
        const shift = shiftSelect.value;
        const isBoth = shift === 'BOTH';
        const isEvening = shift === 'EVENING';

        eveningWrap.style.display = isBoth ? 'contents' : 'none';

        if (eveningFrom) {
            eveningFrom.required = isBoth;
        }
        if (eveningTo) {
            eveningTo.required = isBoth;
        }

        if (primaryLabel) {
            if (isBoth) {
                primaryLabel.textContent = labels.morningTimes;
            } else if (isEvening) {
                primaryLabel.textContent = labels.eveningTimes;
            } else {
                primaryLabel.textContent = labels.times;
            }
        }

        const suffix = isBoth ? ' (' + labels.morning + ')' : (isEvening ? ' (' + labels.evening + ')' : '');
        if (fromLabel) {
            fromLabel.textContent = labels.from + suffix;
        }
        if (toLabel) {
            toLabel.textContent = labels.to + suffix;
        }
    }

    shiftSelect.addEventListener('change', syncShiftUi);
    syncShiftUi();
})();
</script>
