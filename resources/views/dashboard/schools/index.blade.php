@extends('dashboard.layout')

@section('title', __('dashboard.menu_schools'))

@section('content')
    @php($title = __('dashboard.menu_schools'))
    @component('dashboard.partials.shell', ['title' => $title])
        @if(auth()->user()?->is_admin)
            <div style="display:flex;justify-content:flex-end;margin-bottom:14px;">
                <a href="{{ route('dashboard.schools.create') }}" class="btn-primary" style="width:auto;padding:10px 14px;text-decoration:none;">{{ __('dashboard.add_school') }}</a>
            </div>
        @endif

        <section class="card">
            <div style="overflow:auto;">
                <table class="table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>{{ __('dashboard.school_name') }}</th>
                        <th>{{ __('dashboard.address') }}</th>
                        <th>{{ __('dashboard.phone') }}</th>
                        <th>{{ __('dashboard.buses') }}</th>
                        <th>{{ __('dashboard.status') }}</th>
                        @if(auth()->user()?->is_admin)
                            <th>{{ __('dashboard.actions') }}</th>
                        @endif
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($schools as $school)
                        <tr>
                            <td>{{ $school->id }}</td>
                            <td>{{ $school->name_en }}</td>
                            <td>{{ $school->address }}</td>
                            <td>{{ $school->admin_phone ?: '—' }}</td>
                            <td>{{ (int) ($school->buses_count ?? 0) }}</td>
                            <td>
                                <span class="badge {{ $school->status === 'active' ? 'ok' : 'off' }}">
                                    {{ $school->status === 'active' ? __('dashboard.active') : __('dashboard.inactive') }}
                                </span>
                            </td>
                            @if(auth()->user()?->is_admin)
                            <td style="display:flex;gap:8px;flex-wrap:wrap;">
                                <a href="{{ route('dashboard.schools.edit', $school) }}" class="btn-muted" style="text-decoration:none;">{{ __('dashboard.edit') }}</a>
                                <form method="post" action="{{ route('dashboard.schools.destroy', $school) }}" onsubmit="return confirm('{{ __('dashboard.confirm_delete') }}')">
                                    @csrf
                                    @method('delete')
                                    <button type="submit" class="btn-muted">{{ __('dashboard.delete') }}</button>
                                </form>
                            </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()?->is_admin ? 7 : 6 }}">{{ __('dashboard.no_schools') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top:16px;">{{ $schools->links() }}</div>
        </section>
    @endcomponent
@endsection
