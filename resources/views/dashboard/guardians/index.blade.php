@extends('dashboard.layout')

@section('title', __('dashboard.menu_guardians'))

@section('content')
    @php($title = __('dashboard.menu_guardians'))
    @component('dashboard.partials.shell', ['title' => $title])
        @if(auth()->user()?->is_admin)
        <div style="display:flex;justify-content:flex-end;margin-bottom:14px;">
            <a href="{{ route('dashboard.guardians.create') }}" class="btn-primary" style="width:auto;padding:10px 14px;text-decoration:none;">{{ __('dashboard.add_guardian') }}</a>
        </div>
        @endif

        <section class="card">
            <div style="overflow:auto;">
                <table class="table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>{{ __('dashboard.guardian_name') }}</th>
                        <th>{{ __('dashboard.school') }}</th>
                        <th>{{ __('dashboard.phone') }}</th>
                        <th>{{ __('dashboard.id_card_number') }}</th>
                        <th>{{ __('dashboard.children_count') }}</th>
                        <th>{{ __('dashboard.status') }}</th>
                        @if(auth()->user()?->is_admin)
                            <th>{{ __('dashboard.actions') }}</th>
                        @endif
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($guardians as $guardian)
                        <tr>
                            <td>{{ $guardian->id }}</td>
                            <td>{{ $guardian->full_name }}</td>
                            <td>{{ $guardian->school?->name_en ?: '—' }}</td>
                            <td>{{ $guardian->phone }}</td>
                            <td>{{ $guardian->id_card_number ?: '—' }}</td>
                            <td>{{ $guardian->students_count }}</td>
                            <td>
                                <span class="badge {{ $guardian->status === 'active' ? 'ok' : 'off' }}">
                                    {{ $guardian->status === 'active' ? __('dashboard.active') : __('dashboard.inactive') }}
                                </span>
                            </td>
                            @if(auth()->user()?->is_admin)
                            <td style="display:flex;gap:8px;">
                                <a href="{{ route('dashboard.guardians.edit', $guardian) }}" class="btn-muted" style="text-decoration:none;">{{ __('dashboard.edit') }}</a>
                                <form method="post" action="{{ route('dashboard.guardians.destroy', $guardian) }}" onsubmit="return confirm('{{ __('dashboard.confirm_delete') }}')">
                                    @csrf
                                    @method('delete')
                                    <button type="submit" class="btn-muted">{{ __('dashboard.delete') }}</button>
                                </form>
                            </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()?->is_admin ? 8 : 7 }}">{{ __('dashboard.no_guardians') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top:16px;">{{ $guardians->links() }}</div>
        </section>
    @endcomponent
@endsection
