@extends('dashboard.layout')

@section('title', __('dashboard.menu_trip_requests'))

@section('content')
    @php($title = __('dashboard.menu_trip_requests').' #'.$tripRequest->id)
    @php($isDriverUser = auth()->user()?->driver !== null)
    @component('dashboard.partials.shell', ['title' => $title])
        @php($u = $tripRequest->user)
        @php($s = $tripRequest->student)
        @php($d = $tripRequest->driver)
        @php($t = $tripRequest->tripHistory)

        <p style="color: var(--text-muted); margin: 0 0 20px;">{{ __('dashboard.trip_request_show_intro') }}</p>

        <section class="card" style="margin-bottom: 20px;">
            <p style="margin: 0 0 8px;"><strong>{{ __('dashboard.table_col_status') }}:</strong> {{ $tripRequest->status }}</p>
            <p style="margin: 0 0 8px;"><strong>{{ __('dashboard.table_col_user') }}:</strong> {{ $u?->name ?? '—' }}</p>
            <p style="margin: 0 0 8px;"><strong>{{ __('dashboard.table_col_phone') }}:</strong> <span class="mono">{{ $u?->phone ?? '—' }}</span></p>
            <p style="margin: 0 0 8px;"><strong>{{ __('dashboard.table_col_student') }}:</strong> {{ $s?->full_name ?? '—' }}</p>
            <p style="margin: 0 0 8px;"><strong>{{ __('dashboard.driver') }}:</strong> {{ trim(($d?->first_name ?? '').' '.($d?->last_name ?? '')) ?: '—' }}</p>
            <p style="margin: 0 0 8px;"><strong>{{ __('dashboard.table_col_trip') }}:</strong> {{ $tripRequest->trip_history_id ?? '—' }} @if($t) ({{ $t->bus_number ?? '' }}) @endif</p>
            <p style="margin: 0 0 8px;"><strong>{{ __('dashboard.table_col_created') }}:</strong> {{ $tripRequest->created_at?->toDateTimeString() ?? '—' }}</p>
            @if($tripRequest->cancelled_at)
                <p style="margin: 0;"><strong>{{ __('dashboard.trip_request_cancelled_at') }}:</strong> {{ $tripRequest->cancelled_at->toDateTimeString() }}</p>
            @endif
        </section>

        <section class="card" style="margin-bottom: 20px;">
            <h3 style="margin: 0 0 12px;">{{ __('dashboard.table_col_notes') }}</h3>
            <p style="margin: 0; white-space: pre-wrap;">{{ $tripRequest->notes ?: '—' }}</p>
        </section>

        <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px;">
            @if(! $isDriverUser)
                <a href="{{ route('dashboard.trip_requests.edit', $tripRequest) }}" class="btn-primary" style="width:auto;padding:10px 14px;text-decoration:none;">{{ __('dashboard.edit') }}</a>
            @endif
            @if(! $isDriverUser && $tripRequest->status === 'pending')
                <form method="post" action="{{ route('dashboard.trip_requests.destroy', $tripRequest) }}" onsubmit="return confirm('{{ __('dashboard.confirm_delete') }}')" style="display:inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-muted">{{ __('dashboard.delete') }}</button>
                </form>
            @endif
        </div>

        @if($tripRequest->status === 'pending')
            <section class="card">
                <h3 style="margin: 0 0 12px;">{{ __('dashboard.trip_request_staff_decision') }}</h3>
                <form method="post" action="{{ route('dashboard.trip_requests.update_status', $tripRequest) }}" style="display: flex; flex-wrap: wrap; gap: 10px;">
                    @csrf
                    @method('PUT')
                    <button type="submit" name="status" value="accepted" class="btn-primary" style="width:auto;padding:10px 14px;">{{ __('dashboard.trip_request_approve') }}</button>
                    <button type="submit" name="status" value="rejected" class="btn-muted" style="width:auto;padding:10px 14px;">{{ __('dashboard.trip_request_reject') }}</button>
                </form>
            </section>
        @endif

        <p style="margin-top: 16px;">
            <a href="{{ route('dashboard.trip_requests.index') }}" class="link">{{ __('dashboard.trip_requests_back') }}</a>
        </p>
    @endcomponent
@endsection
