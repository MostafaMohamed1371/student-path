@extends('dashboard.layout')

@section('title', __('dashboard.menu_support_chat'))

@section('content')
    @php($title = __('dashboard.menu_support_chat'))
    @component('dashboard.partials.shell', ['title' => $title])
        <p style="color: var(--text-muted); margin: 0 0 20px;">{{ __('dashboard.support_chat_page_intro') }}</p>

        <section class="card">
            <div style="overflow:auto;">
                <table class="table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>{{ __('dashboard.table_col_user') }}</th>
                        <th>{{ __('dashboard.table_col_phone') }}</th>
                        <th>{{ __('dashboard.support_chat_col_subject') }}</th>
                        <th>{{ __('dashboard.support_complaint_status') }}</th>
                        <th>{{ __('dashboard.support_chat_col_unread') }}</th>
                        <th>{{ __('dashboard.support_chat_col_last_message') }}</th>
                        <th>{{ __('dashboard.actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($conversations as $c)
                        @php($u = $c->user)
                        <tr>
                            <td>{{ $c->id }}</td>
                            <td>{{ $u?->name ?? '—' }}</td>
                            <td class="mono">{{ $u?->phone ?? '—' }}</td>
                            <td>{{ $c->subject ?? '—' }}</td>
                            <td>
                                @if ($c->status === 'open')
                                    <span class="badge ok">{{ __('dashboard.support_chat_status_open') }}</span>
                                @else
                                    <span class="badge off">{{ __('dashboard.support_chat_status_closed') }}</span>
                                @endif
                            </td>
                            <td>
                                @if (($c->unread_count ?? 0) > 0)
                                    <span class="badge off">{{ $c->unread_count }}</span>
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $c->last_message_at?->diffForHumans() ?? '—' }}</td>
                            <td>
                                <a href="{{ route('dashboard.support_chat.show', $c) }}" class="btn-primary" style="width:auto;padding:8px 12px;text-decoration:none;font-size:13px;">
                                    {{ __('dashboard.support_chat_open') }}
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">{{ __('dashboard.support_chat_empty') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($conversations->total() > 0)
                <div style="margin-top:16px;">{{ $conversations->links() }}</div>
            @endif
        </section>
    @endcomponent
@endsection
