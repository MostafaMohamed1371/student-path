@extends('dashboard.layout')

@section('title', __('dashboard.notification_view_title', ['id' => $notification->id]))

@section('content')
    @php($title = __('dashboard.notification_view_title', ['id' => $notification->id]))
    @php($u = $notification->user)
    @php($dataType = \App\Support\Dashboard\InAppNotificationPresenter::dataType($notification))
    @php($tripRef = \App\Support\Dashboard\InAppNotificationPresenter::tripReference($notification))
    @php($contractType = \App\Services\Notifications\NotificationContractMapper::toContractType(is_array($notification->data) ? $notification->data : []))
    @component('dashboard.partials.shell', ['title' => $title])
        <p style="margin: 0 0 16px;">
            <a href="{{ route('dashboard.in_app_notifications', $listQuery) }}" class="link">
                ← {{ __('dashboard.notification_view_back') }}
            </a>
        </p>

        @include('dashboard.partials.notification_hub_nav')

        <section class="card" style="margin-bottom: 20px;">
            <div style="display:flex;flex-wrap:wrap;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:16px;">
                <div>
                    @if($notification->read_at)
                        <span style="color:var(--text-muted);">{{ __('dashboard.notification_read') }} — {{ $notification->read_at->toDateTimeString() }}</span>
                    @else
                        <span style="color:var(--accent, #2563eb);font-weight:600;">{{ __('dashboard.notification_unread') }}</span>
                    @endif
                </div>
                @if(!$notification->read_at)
                    <form method="post"
                          action="{{ route('dashboard.in_app_notifications.mark_read', $notification->id) }}"
                          style="margin:0;">
                        @csrf
                        <input type="hidden" name="return_to_show" value="1">
                        @foreach($listQuery as $key => $value)
                            @if(is_scalar($value))
                                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                            @endif
                        @endforeach
                        <button type="submit" class="btn-primary" style="width:auto;padding:10px 16px;">
                            {{ __('dashboard.notification_staff_mark_read_btn') }}
                        </button>
                    </form>
                @endif
            </div>

            <dl style="display:grid;grid-template-columns:minmax(140px, auto) 1fr;gap:10px 16px;margin:0;">
                <dt style="color:var(--text-muted);">ID</dt>
                <dd style="margin:0;" class="mono">{{ $notification->id }}</dd>

                <dt style="color:var(--text-muted);">{{ __('dashboard.table_col_user') }}</dt>
                <dd style="margin:0;">{{ $u?->name ?? '—' }} <span class="mono" style="color:var(--text-muted);">{{ $u?->phone ?? '' }}</span></dd>

                <dt style="color:var(--text-muted);">{{ __('dashboard.table_col_notification_type') }}</dt>
                <dd style="margin:0;">{{ $dataType ? \App\Support\Dashboard\InAppNotificationPresenter::typeLabel($dataType) : '—' }}
                    @if($dataType)
                        <span class="mono" style="font-size:12px;color:var(--text-muted);">({{ $dataType }})</span>
                    @endif
                </dd>

                <dt style="color:var(--text-muted);">{{ __('dashboard.notification_view_contract_type') }}</dt>
                <dd style="margin:0;" class="mono">{{ $contractType }}</dd>

                <dt style="color:var(--text-muted);">{{ __('dashboard.table_col_trip') }}</dt>
                <dd style="margin:0;" class="mono">{{ $tripRef ?? '—' }}</dd>

                <dt style="color:var(--text-muted);">{{ __('dashboard.table_col_created') }}</dt>
                <dd style="margin:0;">{{ $notification->created_at?->toDateTimeString() ?? '—' }}
                    <span class="mono" style="font-size:12px;color:var(--text-muted);">
                        ({{ \App\Services\Notifications\NotificationContractMapper::formatCreatedAtUtc($notification->created_at ?? now()) }})
                    </span>
                </dd>
            </dl>
        </section>

        <section class="card" style="margin-bottom: 20px;">
            <h3 style="margin:0 0 12px;">{{ __('dashboard.table_col_title') }}</h3>
            <p style="margin:0 0 16px;">{{ $notification->title }}</p>
            <h3 style="margin:0 0 12px;">{{ __('dashboard.table_col_body') }}</h3>
            <p style="margin:0;white-space:pre-wrap;">{{ $notification->body ?? '—' }}</p>
        </section>

        @if(is_array($notification->data) && $notification->data !== [])
            <section class="card">
                <h3 style="margin:0 0 12px;">{{ __('dashboard.notification_view_payload') }}</h3>
                <p style="margin:0 0 8px;color:var(--text-muted);font-size:0.9rem;">{{ __('dashboard.notification_view_payload_hint') }}</p>
                <pre class="mono" style="margin:0;padding:12px;background:var(--bg-muted, #f4f4f5);border-radius:8px;overflow:auto;font-size:12px;">{{ json_encode($notification->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </section>
        @endif
    @endcomponent
@endsection
