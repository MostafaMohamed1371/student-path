@extends('dashboard.layout')

@section('title', __('dashboard.delete'))

@section('content')
    @php($title = __('dashboard.delete'))
    @component('dashboard.partials.shell', ['title' => $title])
        <section class="card" style="max-width:640px;">
            <p style="color:var(--text-muted);margin:0 0 16px;">
                {{ __('dashboard.guardian_confirm_delete_help', [
                    'name' => $guardian->full_name,
                    'school' => $record->school?->name_en ?: ($record->school?->name_ar ?: __('dashboard.school')),
                ]) }}
            </p>

            <form method="post" action="{{ route('dashboard.guardians.destroy', $record) }}" onsubmit="return confirm('{{ __('dashboard.confirm_delete') }}')">
                @csrf
                @method('delete')
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <button type="submit" class="btn-primary" style="width:auto;padding:10px 16px;">{{ __('dashboard.delete') }}</button>
                    <a href="{{ route('dashboard.guardians.index') }}" class="btn-muted" style="text-decoration:none;">{{ __('dashboard.cancel') }}</a>
                </div>
            </form>
        </section>
    @endcomponent
@endsection
