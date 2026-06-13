@extends('dashboard.layout')

@section('title', __('dashboard.edit_trip_request'))

@section('content')
    @php($title = __('dashboard.edit_trip_request').' #'.$trip_request->id)
    @component('dashboard.partials.shell', ['title' => $title])
        <p style="color: var(--text-muted); margin: 0 0 20px;">{{ __('dashboard.trip_request_edit_intro') }}</p>

        <section class="card">
            <form method="post" action="{{ route('dashboard.trip_requests.update', $trip_request) }}">
                @csrf
                @method('PUT')

                @if ($errors->any())
                    <div class="alert" style="margin-bottom: 16px;">
                        <ul style="margin: 0; padding-left: 18px;">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <p style="margin:0 0 16px;color:var(--text-muted);">
                    {{ __('dashboard.table_col_parent') }}:
                    <strong>{{ $trip_request->parentDisplayName() }}</strong>
                    (<span class="mono">{{ $trip_request->parentDisplayPhone() }}</span>)
                </p>

                <label class="field-label">{{ __('dashboard.table_col_status') }}</label>
                <select name="status" class="field-like" style="width:100%;max-width:420px;margin-bottom:12px;" required>
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}" @selected(old('status', $trip_request->status) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('status')<p style="color:#c00;">{{ $message }}</p>@enderror

                <label class="field-label">{{ __('dashboard.table_col_student') }}</label>
                <select name="student_id" class="field-like" style="width:100%;max-width:420px;margin-bottom:12px;" required>
                    @foreach ($students as $s)
                        <option value="{{ $s->id }}" @selected(old('student_id', $trip_request->student_id) == $s->id)>{{ $s->full_name }} (#{{ $s->id }})</option>
                    @endforeach
                </select>
                @error('student_id')<p style="color:#c00;">{{ $message }}</p>@enderror

                <label class="field-label">{{ __('dashboard.driver') }}</label>
                <select name="driver_id" class="field-like" style="width:100%;max-width:420px;margin-bottom:12px;">
                    <option value="">—</option>
                    @foreach ($drivers as $d)
                        @php($driverLabel = trim(implode(' ', array_filter([$d->first_name, $d->father_name, $d->last_name]))))
                        <option value="{{ $d->id }}" @selected(old('driver_id', $trip_request->driver_id) == $d->id)>
                            {{ $driverLabel !== '' ? $driverLabel : __('dashboard.driver') }} (#{{ $d->id }})
                        </option>
                    @endforeach
                </select>
                @error('driver_id')<p style="color:#c00;">{{ $message }}</p>@enderror

                <label class="field-label">{{ __('dashboard.table_col_trip') }} ({{ __('dashboard.optional') }})</label>
                <select name="trip_history_id" class="field-like" style="width:100%;max-width:420px;margin-bottom:12px;">
                    <option value="">—</option>
                    @foreach ($trips as $t)
                        <option value="{{ $t->id }}" @selected(old('trip_history_id', $trip_request->trip_history_id) == $t->id)>
                            #{{ $t->id }} — {{ $t->route_title ?: $t->bus_number }} ({{ $t->trip_type }})
                        </option>
                    @endforeach
                </select>
                @error('trip_history_id')<p style="color:#c00;">{{ $message }}</p>@enderror

                <label class="field-label">{{ __('dashboard.order_present_type') }}</label>
                <input type="text" name="present_type" class="field-like" style="width:100%;max-width:420px;margin-bottom:12px;" value="{{ old('present_type', $trip_request->present_type) }}">
                @error('present_type')<p style="color:#c00;">{{ $message }}</p>@enderror

                <label class="field-label">{{ __('dashboard.order_moving_point') }}</label>
                <input type="text" name="moving_point" class="field-like" style="width:100%;max-width:520px;margin-bottom:12px;" value="{{ old('moving_point', $trip_request->moving_point) }}">
                @error('moving_point')<p style="color:#c00;">{{ $message }}</p>@enderror

                <label class="field-label">{{ __('dashboard.order_stop_point') }}</label>
                <input type="text" name="stop_point" class="field-like" style="width:100%;max-width:520px;margin-bottom:12px;" value="{{ old('stop_point', $trip_request->stop_point) }}">
                @error('stop_point')<p style="color:#c00;">{{ $message }}</p>@enderror

                <label class="field-label">{{ __('dashboard.order_subscribe_price') }}</label>
                <input type="number" step="0.01" min="0" name="subscribe_price" class="field-like" style="width:100%;max-width:220px;margin-bottom:12px;" value="{{ old('subscribe_price', $trip_request->subscribe_price) }}">
                @error('subscribe_price')<p style="color:#c00;">{{ $message }}</p>@enderror

                <label class="field-label">{{ __('dashboard.table_col_notes') }}</label>
                <textarea name="notes" rows="4" class="field-like" style="width:100%;max-width:520px;">{{ old('notes', $trip_request->notes) }}</textarea>
                @error('notes')<p style="color:#c00;">{{ $message }}</p>@enderror

                <div style="margin-top:16px;">
                    <button type="submit" class="btn-primary" style="width:auto;padding:10px 14px;">{{ __('dashboard.update') }}</button>
                    <a href="{{ route('dashboard.trip_requests.show', $trip_request) }}" class="link" style="margin-inline-start:12px;">{{ __('dashboard.cancel') }}</a>
                </div>
            </form>
        </section>
    @endcomponent
@endsection
