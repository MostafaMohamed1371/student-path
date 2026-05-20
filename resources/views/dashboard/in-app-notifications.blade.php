@extends('dashboard.layout')

@section('title', __('dashboard.menu_in_app_notifications'))

@section('content')
    @php($title = __('dashboard.menu_in_app_notifications'))
    @component('dashboard.partials.shell', ['title' => $title])
        <p style="color: var(--text-muted); margin: 0 0 20px;">{{ __('dashboard.notifications_page_intro') }}</p>

        @include('dashboard.partials.school_driver_filter')

        <section class="card">
            <div style="overflow:auto;">
                <table class="table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>{{ __('dashboard.table_col_user') }}</th>
                        <th>{{ __('dashboard.table_col_phone') }}</th>
                        <th>{{ __('dashboard.table_col_title') }}</th>
                        <th>{{ __('dashboard.table_col_body') }}</th>
                        <th>{{ __('dashboard.table_col_read_at') }}</th>
                        <th>{{ __('dashboard.table_col_created') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($notifications as $n)
                        @php($u = $n->user)
                        @php($typeUser = $u ? \App\Support\LoginTypeUser::resolve($u) : null)
                        <tr>
                            <td>{{ $n->id }}</td>
                            <td>
                                {{ $u?->name ?? '—' }}
                                @if($typeUser)
                                    <span class="mono" style="font-size: 12px; color: var(--text-muted);">({{ $typeUser }})</span>
                                @endif
                            </td>
                            <td class="mono">{{ $u?->phone ?? '—' }}</td>
                            <td>{{ $n->title }}</td>
                            <td style="max-width: 280px;">{{ \Illuminate\Support\Str::limit($n->body ?? '', 120) }}</td>
                            <td>{{ $n->read_at?->toDateTimeString() ?? '—' }}</td>
                            <td>{{ $n->created_at?->toDateTimeString() ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7">{{ __('dashboard.table_empty') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($notifications->total() > 0)
                <div style="margin-top:16px;">{{ $notifications->links() }}</div>
            @endif
        </section>
    @endcomponent
@endsection
