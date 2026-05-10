@extends('dashboard.layout')

@section('title', __('dashboard.menu_drivers'))

@section('content')
    @php($title = __('dashboard.menu_drivers'))
    @component('dashboard.partials.shell', ['title' => $title])
        @if(auth()->user()?->is_admin)
        <div style="display:flex;justify-content:flex-end;margin-bottom:14px;">
            <a href="{{ route('dashboard.drivers.create') }}" class="btn-primary" style="width:auto;padding:10px 14px;text-decoration:none;">{{ __('dashboard.add_driver') }}</a>
        </div>
        @endif

        <section class="card">
            <div style="overflow:auto;">
                <table class="table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>{{ __('dashboard.driver_name') }}</th>
                        <th>{{ __('dashboard.id_card_number') }}</th>
                        <th>{{ __('dashboard.school') }}</th>
                        <th>{{ __('dashboard.vehicle') }}</th>
                        <th>{{ __('dashboard.phone') }}</th>
                        <th>{{ __('dashboard.monthly_subscription_price') }}</th>
                        <th>{{ __('dashboard.status') }}</th>
                        @if(auth()->user()?->is_admin)
                            <th>{{ __('dashboard.actions') }}</th>
                        @endif
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($drivers as $driver)
                        <tr>
                            <td>{{ $driver->user_id ?: $driver->id }}</td>
                            <td>{{ $driver->first_name }} {{ $driver->father_name }} {{ $driver->last_name }}</td>
                            <td>{{ $driver->id_card_number }}</td>
                            <td>{{ $driver->school?->name_en ?: '—' }}</td>
                            <td>{{ $driver->bus?->name ?: '—' }}</td>
                            <td>{{ $driver->primary_phone }}</td>
                            <td>{{ $driver->monthly_subscription_price !== null ? number_format((int) $driver->monthly_subscription_price).' '.__('dashboard.currency_iqd_short') : '—' }}</td>
                            <td>
                                <span class="badge {{ $driver->status === 'active' ? 'ok' : 'off' }}">
                                    {{ $driver->status === 'active' ? __('dashboard.active') : __('dashboard.inactive') }}
                                </span>
                            </td>
                            @if(auth()->user()?->is_admin)
                            <td style="display:flex;gap:8px;">
                                <a href="{{ route('dashboard.drivers.edit', $driver) }}" class="btn-muted" style="text-decoration:none;">{{ __('dashboard.edit') }}</a>
                                <form method="post" action="{{ route('dashboard.drivers.destroy', $driver) }}" onsubmit="return confirm('{{ __('dashboard.confirm_delete') }}')">
                                    @csrf
                                    @method('delete')
                                    <button type="submit" class="btn-muted">{{ __('dashboard.delete') }}</button>
                                </form>
                            </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ auth()->user()?->is_admin ? 9 : 8 }}">{{ __('dashboard.no_drivers') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div style="margin-top:16px;">{{ $drivers->links() }}</div>
        </section>
    @endcomponent
@endsection
