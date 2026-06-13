@extends('dashboard.layout')

@section('title', __('dashboard.guardian_choose_school_to_delete'))

@section('content')
    @php($title = __('dashboard.guardian_choose_school_to_delete'))
    @component('dashboard.partials.shell', ['title' => $title])
        <section class="card" style="max-width:640px;">
            <p style="color:var(--text-muted);margin:0 0 16px;">
                {{ __('dashboard.guardian_choose_school_to_delete_help', ['name' => $guardian->full_name]) }}
            </p>

            <div style="display:flex;flex-direction:column;gap:10px;">
                @foreach($records as $record)
                    <form
                        method="post"
                        action="{{ route('dashboard.guardians.destroy', $record) }}"
                        onsubmit="return confirm('{{ __('dashboard.confirm_delete') }}')"
                        style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding:12px 14px;border:1px solid #e2e8f0;border-radius:8px;background:#fff;"
                    >
                        @csrf
                        @method('delete')
                        <div>
                            <div>{{ $record->school?->name_en ?: ($record->school?->name_ar ?: __('dashboard.school')) }}</div>
                            <div style="color:var(--text-muted);font-size:13px;margin-top:4px;">
                                {{ __('dashboard.children_count') }}: {{ (int) ($record->students_count ?? 0) }}
                            </div>
                        </div>
                        <button type="submit" class="btn-muted">{{ __('dashboard.delete') }}</button>
                    </form>
                @endforeach
            </div>

            <div style="margin-top:16px;">
                <a href="{{ route('dashboard.guardians.index') }}" class="btn-muted" style="text-decoration:none;">{{ __('dashboard.cancel') }}</a>
            </div>
        </section>
    @endcomponent
@endsection
