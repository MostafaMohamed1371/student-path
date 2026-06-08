@extends('dashboard.layout')

@section('title', __('dashboard.menu_school_work_schedule'))

@section('content')
    @php($title = __('dashboard.menu_school_work_schedule'))
    @component('dashboard.partials.shell', ['title' => $title])
        <p style="color: var(--text-muted); margin: 0 0 16px;">{{ __('dashboard.school_work_schedule_report_intro') }}</p>

        <section class="card">
            <div style="margin-bottom: 16px;">
                <h3 style="margin: 0 0 6px; font-size: 18px;">{{ $school->name_en }}</h3>
                @if($school->name_ar)
                    <p style="margin: 0; color: var(--text-muted);">{{ $school->name_ar }}</p>
                @endif
            </div>

            @if ($errors->any())
                <div class="alert" style="margin-bottom: 16px;">{{ $errors->first() }}</div>
            @endif

            <form method="post" action="{{ route('dashboard.school_work_schedule.update') }}">
                @csrf
                @method('put')

                <h4 style="margin: 0 0 8px; font-size: 16px;">{{ __('dashboard.school_work_schedule') }}</h4>
                <p class="help" style="margin: 0 0 14px;">{{ __('dashboard.school_work_schedule_help') }}</p>

                @include('dashboard.schools._work_schedule_fields', ['school' => $school])

                <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;">
                    <button type="submit" class="btn-primary" style="width:auto;padding-inline:16px;">{{ __('dashboard.save') }}</button>
                </div>
            </form>
        </section>
    @endcomponent
@endsection
