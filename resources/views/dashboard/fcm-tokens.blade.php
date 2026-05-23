@extends('dashboard.layout')

@section('title', __('dashboard.menu_fcm_tokens'))

@section('content')
    @php($title = __('dashboard.menu_fcm_tokens'))
    @component('dashboard.partials.shell', ['title' => $title])
        <p style="color: var(--text-muted); margin: 0 0 20px;">{{ __('dashboard.fcm_tokens_page_intro') }}</p>

        @include('dashboard.partials.notification_hub_nav')

        @include('dashboard.partials.school_driver_filter', [
            'showNotificationTypeFilter' => false,
            'showUnreadFilter' => false,
        ])

        <section class="card">
            <div style="overflow:auto;">
                <table class="table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>{{ __('dashboard.table_col_user') }}</th>
                        <th>{{ __('dashboard.table_col_phone') }}</th>
                        <th>{{ __('dashboard.fcm_col_platform') }}</th>
                        <th>{{ __('dashboard.fcm_col_device_id') }}</th>
                        <th>{{ __('dashboard.fcm_col_token') }}</th>
                        <th>{{ __('dashboard.fcm_col_last_used') }}</th>
                        <th>{{ __('dashboard.table_col_created') }}</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($tokens as $row)
                        @php($u = $row->user)
                        @php($token = (string) $row->token)
                        @php($masked = strlen($token) > 24 ? substr($token, 0, 12).'…'.substr($token, -8) : $token)
                        <tr>
                            <td>{{ $row->id }}</td>
                            <td>{{ $u?->name ?? '—' }}</td>
                            <td class="mono">{{ $u?->phone ?? '—' }}</td>
                            <td class="mono">{{ $row->platform ?? '—' }}</td>
                            <td class="mono">{{ $row->device_id ?? '—' }}</td>
                            <td class="mono" title="{{ $token }}">{{ $masked }}</td>
                            <td>{{ $row->last_used_at?->toDateTimeString() ?? '—' }}</td>
                            <td>{{ $row->created_at?->toDateTimeString() ?? '—' }}</td>
                            <td>
                                <form method="post"
                                      action="{{ route('dashboard.fcm_tokens.destroy', $row->id) }}"
                                      onsubmit="return confirm(@json(__('dashboard.fcm_token_remove_confirm')));"
                                      style="margin:0;">
                                    @csrf
                                    @method('DELETE')
                                    @include('dashboard.partials.report_query_hidden_fields', ['keys' => ['school_id', 'driver_id', 'guardian_id', 'user_role']])
                                    <button type="submit" class="btn-muted" style="width:auto;padding:6px 10px;font-size:12px;">
                                        {{ __('dashboard.fcm_token_remove') }}
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">{{ __('dashboard.table_empty') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($tokens->total() > 0)
                <div style="margin-top:16px;">{{ $tokens->links() }}</div>
            @endif
        </section>
    @endcomponent
@endsection
