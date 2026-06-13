@extends('dashboard.layout')

@section('title', __('dashboard.guardian_choose_school_to_edit'))

@section('content')
    @php($title = __('dashboard.guardian_choose_school_to_edit'))
    @component('dashboard.partials.shell', ['title' => $title])
        <section class="card" style="max-width:640px;">
            <p style="color:var(--text-muted);margin:0 0 16px;">
                {{ __('dashboard.guardian_choose_school_to_edit_help', ['name' => $guardian->full_name]) }}
            </p>

            <div style="display:flex;flex-direction:column;gap:10px;">
                @foreach($records as $record)
                    <a
                        href="{{ route('dashboard.guardians.edit', $record) }}"
                        class="btn-muted"
                        style="display:flex;justify-content:space-between;align-items:center;padding:12px 14px;text-decoration:none;"
                    >
                        <span>{{ $record->school?->name_en ?: ($record->school?->name_ar ?: __('dashboard.school')) }}</span>
                        <span style="color:var(--text-muted);font-size:13px;">
                            {{ __('dashboard.children_count') }}: {{ (int) ($record->students_count ?? 0) }}
                        </span>
                    </a>
                @endforeach
            </div>

            <div style="margin-top:16px;">
                <a href="{{ route('dashboard.guardians.index') }}" class="btn-muted" style="text-decoration:none;">{{ __('dashboard.cancel') }}</a>
            </div>
        </section>
    @endcomponent
@endsection
