@extends('dashboard.layout')

@section('title', __('dashboard.menu_chat_reports'))

@section('content')
    @php($title = __('dashboard.menu_chat_reports'))
    @component('dashboard.partials.shell', ['title' => $title])
        <p style="color: var(--text-muted); margin: 0 0 20px;">{{ __('dashboard.chat_reports_page_intro') }}</p>

        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;">
            <a href="{{ route('dashboard.chat_reports.index') }}" class="btn-muted {{ empty($statusFilter) ? 'is-active' : '' }}" style="text-decoration:none;padding:8px 12px;">{{ __('dashboard.chat_reports_filter_all') }}</a>
            <a href="{{ route('dashboard.chat_reports.index', ['status' => 'pending']) }}" class="btn-muted {{ $statusFilter === 'pending' ? 'is-active' : '' }}" style="text-decoration:none;padding:8px 12px;">{{ __('dashboard.chat_report_status_pending') }}</a>
            <a href="{{ route('dashboard.chat_reports.index', ['status' => 'reviewed']) }}" class="btn-muted {{ $statusFilter === 'reviewed' ? 'is-active' : '' }}" style="text-decoration:none;padding:8px 12px;">{{ __('dashboard.chat_report_status_reviewed') }}</a>
            <a href="{{ route('dashboard.chat_reports.index', ['status' => 'resolved']) }}" class="btn-muted {{ $statusFilter === 'resolved' ? 'is-active' : '' }}" style="text-decoration:none;padding:8px 12px;">{{ __('dashboard.chat_report_status_resolved') }}</a>
            <a href="{{ route('dashboard.support_chat.index') }}" class="link" style="margin-inline-start:auto;">{{ __('dashboard.menu_support_chat') }}</a>
        </div>

        <section class="card">
            <div style="overflow:auto;">
                <table class="table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>{{ __('dashboard.chat_report_col_conversation') }}</th>
                        <th>{{ __('dashboard.table_col_user') }}</th>
                        <th>{{ __('dashboard.chat_report_col_reporter') }}</th>
                        <th>{{ __('dashboard.chat_report_col_reason') }}</th>
                        <th>{{ __('dashboard.chat_report_col_status') }}</th>
                        <th>{{ __('dashboard.table_col_created') }}</th>
                        <th>{{ __('dashboard.actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($reports as $report)
                        @php($conv = $report->conversation)
                        @php($appUser = $conv?->user)
                        <tr>
                            <td>{{ $report->id }}</td>
                            <td>#{{ $report->chat_conversation_id }}</td>
                            <td>{{ $appUser?->name ?? '—' }}</td>
                            <td>{{ $report->reporter?->name ?? '—' }}</td>
                            <td style="max-width:240px;">{{ \Illuminate\Support\Str::limit($report->reason, 80) }}</td>
                            <td>{{ $report->status }}</td>
                            <td>{{ $report->created_at?->toDateTimeString() ?? '—' }}</td>
                            <td style="white-space:nowrap;">
                                @if ($conv && $conv->deleted_at === null)
                                    <a href="{{ route('dashboard.support_chat.show', $conv) }}" class="btn-muted" style="text-decoration:none;">{{ __('dashboard.chat_report_open_chat') }}</a>
                                @endif
                                <form method="post" action="{{ route('dashboard.chat_reports.update_status', $report) }}" style="display:inline;margin-inline-start:6px;">
                                    @csrf
                                    <input type="hidden" name="status" value="{{ $report->status === 'pending' ? 'reviewed' : ($report->status === 'reviewed' ? 'resolved' : 'pending') }}">
                                    <button type="submit" class="btn-muted">
                                        @if ($report->status === 'pending')
                                            {{ __('dashboard.chat_report_mark_reviewed') }}
                                        @elseif ($report->status === 'reviewed')
                                            {{ __('dashboard.chat_report_mark_resolved') }}
                                        @else
                                            {{ __('dashboard.chat_report_mark_pending') }}
                                        @endif
                                    </button>
                                </form>
                            </td>
                        </tr>
                        @if ($report->details)
                            <tr>
                                <td colspan="8" style="font-size:13px;color:var(--text-muted);padding-top:0;">{{ $report->details }}</td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="8">{{ __('dashboard.chat_reports_empty') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($reports->total() > 0)
                <div style="margin-top:16px;">{{ $reports->links() }}</div>
            @endif
        </section>
    @endcomponent
@endsection
