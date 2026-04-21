@extends('dashboard.layout')

@section('title', __('dashboard.menu_users'))

@section('content')
    @php($title = __('dashboard.menu_users'))
    @component('dashboard.partials.shell', ['title' => $title])
        <div style="display:flex;justify-content:flex-end;margin-bottom:14px;">
            <a href="{{ route('dashboard.users.create') }}" class="btn-primary" style="width:auto;padding:10px 14px;text-decoration:none;">{{ __('dashboard.add_user') }}</a>
        </div>

        <section class="card">
            <div style="overflow:auto;">
                <table class="table">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('dashboard.name') }}</th>
                        <th>{{ __('dashboard.image') }}</th>
                        <th>{{ __('dashboard.phone') }}</th>
                        <th>{{ __('dashboard.city') }}</th>
                        <th>{{ __('dashboard.licence_number') }}</th>
                        <th>{{ __('dashboard.votes') }}</th>
                        <th>{{ __('dashboard.rate') }}</th>
                        <th>{{ __('dashboard.verified') }}</th>
                        <th>{{ __('dashboard.status') }}</th>
                        <th>{{ __('dashboard.actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($users as $user)
                        <tr>
                            <td>{{ $user->id }}</td>
                            <td>{{ $user->name ?: '—' }}</td>
                            <td>
                                @if($user->image)
                                    <a class="link" target="_blank" href="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($user->image) }}">{{ __('dashboard.image') }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $user->phone }}</td>
                            <td>{{ $user->city ?: '—' }}</td>
                            <td>{{ $user->licence_number ?: '—' }}</td>
                            <td>{{ $user->votes ?? 0 }}</td>
                            <td>{{ number_format((float) ($user->rate ?? 0), 1) }}</td>
                            <td>
                                <span class="badge {{ $user->is_verified ? 'ok' : 'off' }}">
                                    {{ $user->is_verified ? __('dashboard.verified') : __('dashboard.not_verified') }}
                                </span>
                            </td>
                            <td>
                                <span class="badge {{ $user->is_active ? 'ok' : 'off' }}">
                                    {{ $user->is_active ? __('dashboard.active') : __('dashboard.inactive') }}
                                </span>
                            </td>
                            <td style="display:flex;gap:8px;">
                                <a href="{{ route('dashboard.users.edit', $user) }}" class="btn-muted" style="text-decoration:none;">{{ __('dashboard.edit') }}</a>

                                <form method="post" action="{{ route('dashboard.users.destroy', $user) }}" onsubmit="return confirm('{{ __('dashboard.confirm_delete') }}')">
                                    @csrf
                                    @method('delete')
                                    <button type="submit" class="btn-muted">{{ __('dashboard.delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10">{{ __('dashboard.no_users') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top:16px;">{{ $users->links() }}</div>
        </section>
    @endcomponent
@endsection
