@extends('dashboard.layout')

@section('title', __('dashboard.menu_support_complaints'))

@section('content')
    @php($title = __('dashboard.menu_support_complaints'))
    @component('dashboard.partials.shell', ['title' => $title])
        <p style="color: var(--text-muted); margin: 0 0 20px;">{{ __('dashboard.support_complaints_page_intro') }}</p>

        @include('dashboard.partials.school_driver_filter')

        <div style="display:flex;justify-content:flex-end;margin-bottom:14px;">
            <a href="{{ route('dashboard.support_complaints.create') }}" class="btn-primary" style="width:auto;padding:10px 14px;text-decoration:none;">{{ __('dashboard.add_support_complaint') }}</a>
        </div>

        <section class="card">
            <div style="overflow:auto;">
                <table class="table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>{{ __('dashboard.table_col_complaint_number') }}</th>
                        <th>{{ __('dashboard.table_col_user') }}</th>
                        <th>{{ __('dashboard.table_col_phone') }}</th>
                        <th>{{ __('dashboard.table_col_category') }}</th>
                        <th>{{ __('dashboard.table_col_status') }}</th>
                        <th>{{ __('dashboard.table_col_created') }}</th>
                        <th>{{ __('dashboard.actions') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($complaints as $c)
                        @php($u = $c->user)
                        <tr>
                            <td>{{ $c->id }}</td>
                            <td class="mono">{{ $c->complaint_number ?? '—' }}</td>
                            <td>{{ $u?->name ?? '—' }}</td>
                            <td class="mono">{{ $u?->phone ?? '—' }}</td>
                            <td>{{ $c->category_id }}</td>
                            <td>{{ $c->status }}</td>
                            <td>{{ $c->created_at?->toDateTimeString() ?? '—' }}</td>
                            <td style="white-space:nowrap;">
                                <a href="{{ route('dashboard.support_complaints.show', $c) }}" class="btn-muted" style="text-decoration:none;">{{ __('dashboard.action_view') }}</a>
                                <a href="{{ route('dashboard.support_complaints.edit', $c) }}" class="btn-muted" style="text-decoration:none;margin-inline-start:6px;">{{ __('dashboard.edit') }}</a>
                                <form method="post" action="{{ route('dashboard.support_complaints.destroy', $c) }}" style="display:inline;margin-inline-start:6px;" onsubmit="return confirm('{{ __('dashboard.confirm_delete') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn-muted">{{ __('dashboard.delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">{{ __('dashboard.table_empty') }}</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            @if ($complaints->total() > 0)
                <div style="margin-top:16px;">{{ $complaints->links() }}</div>
            @endif
        </section>
    @endcomponent
@endsection
