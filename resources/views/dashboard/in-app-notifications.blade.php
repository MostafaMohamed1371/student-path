@extends('dashboard.layout')

@section('title', __('dashboard.menu_in_app_notifications'))

@section('content')
    @php($title = __('dashboard.menu_in_app_notifications'))
    @component('dashboard.partials.shell', ['title' => $title])
        <p style="color: var(--text-muted); margin: 0 0 20px;">{{ __('dashboard.notifications_page_intro') }}</p>

        @include('dashboard.partials.notification_hub_nav')

        @include('dashboard.partials.school_driver_filter')

        <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px;">
            <form method="post" action="{{ route('dashboard.in_app_notifications.mark_all_read') }}" style="margin:0;">
                @csrf
                @include('dashboard.partials.report_query_hidden_fields')
                <button type="submit" class="btn-primary" style="width:auto;padding:10px 16px;">
                    {{ __('dashboard.notification_staff_mark_all_read_btn') }}
                </button>
            </form>
            <p style="margin:0;color:var(--text-muted);font-size:0.9rem;align-self:center;">
                {{ __('dashboard.notification_staff_actions_hint') }}
            </p>
        </div>

        <section class="card">
            <div style="overflow:auto;">
                <table class="table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>{{ __('dashboard.table_col_user') }}</th>
                        <th>{{ __('dashboard.table_col_phone') }}</th>
                        <th>{{ __('dashboard.table_col_notification_type') }}</th>
                        <th>{{ __('dashboard.table_col_trip') }}</th>
                        <th>{{ __('dashboard.table_col_title') }}</th>
                        <th>{{ __('dashboard.table_col_body') }}</th>
                        <th>{{ __('dashboard.table_col_read_status') }}</th>
                        <th>{{ __('dashboard.table_col_created') }}</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($notifications as $n)
                        @php($u = $n->user)
                        @php($typeUser = $u ? \App\Support\LoginTypeUser::resolve($u) : null)
                        @php($dataType = \App\Support\Dashboard\InAppNotificationPresenter::dataType($n))
                        @php($tripRef = \App\Support\Dashboard\InAppNotificationPresenter::tripReference($n))
                        <tr>
                            <td>
                                <a href="{{ route('dashboard.in_app_notifications.show', array_merge(['notification' => $n->id], request()->only(['school_id', 'driver_id', 'guardian_id', 'user_role', 'notification_type', 'unread_only', 'page']))) }}" class="link mono">
                                    {{ $n->id }}
                                </a>
                            </td>
                            <td>
                                {{ $u?->name ?? '—' }}
                                @if($typeUser)
                                    <span class="mono" style="font-size: 12px; color: var(--text-muted);">({{ $typeUser }})</span>
                                @endif
                            </td>
                            <td class="mono">{{ $u?->phone ?? '—' }}</td>
                            <td>
                                @if($dataType)
                                    <span class="mono" style="font-size: 12px;">{{ \App\Support\Dashboard\InAppNotificationPresenter::typeLabel($dataType) }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="mono">{{ $tripRef ?? '—' }}</td>
                            <td>{{ $n->title }}</td>
                            <td style="max-width: 280px;">{{ \Illuminate\Support\Str::limit($n->body ?? '', 120) }}</td>
                            <td>
                                @if($n->read_at)
                                    <span style="color: var(--text-muted);">{{ $n->read_at->toDateTimeString() }}</span>
                                @else
                                    <span style="color: var(--accent, #2563eb); font-weight: 600;">{{ __('dashboard.notification_unread') }}</span>
                                @endif
                            </td>
                            <td>{{ $n->created_at?->toDateTimeString() ?? '—' }}</td>
                            <td>
                                @if(!$n->read_at)
                                    <form method="post"
                                          action="{{ route('dashboard.in_app_notifications.mark_read', $n->id) }}"
                                          style="margin:0;">
                                        @csrf
                                        @include('dashboard.partials.report_query_hidden_fields')
                                        <button type="submit" class="btn-muted" style="width:auto;padding:6px 10px;font-size:12px;">
                                            {{ __('dashboard.notification_staff_mark_read_btn') }}
                                        </button>
                                    </form>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10">{{ __('dashboard.table_empty') }}</td>
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
