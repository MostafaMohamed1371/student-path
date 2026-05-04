@extends('dashboard.layout')

@section('title', __('dashboard.support_complaint_show_title'))

@section('content')
    @php($title = __('dashboard.support_complaint_show_title').' '.$complaint->complaint_number)
    @component('dashboard.partials.shell', ['title' => $title])
        @php($u = $complaint->user)

        <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px;">
            <a href="{{ route('dashboard.support_complaints.edit', $complaint) }}" class="btn-primary" style="width:auto;padding:10px 14px;text-decoration:none;">{{ __('dashboard.edit') }}</a>
            <form method="post" action="{{ route('dashboard.support_complaints.destroy', $complaint) }}" onsubmit="return confirm('{{ __('dashboard.confirm_delete') }}')" style="display:inline;">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn-muted">{{ __('dashboard.delete') }}</button>
            </form>
        </div>

        <section class="card" style="margin-bottom: 20px;">
            <p style="margin: 0 0 8px;"><strong>{{ __('dashboard.support_complaint_status') }}:</strong> {{ $complaint->status }}</p>
            <p style="margin: 0 0 8px;"><strong>{{ __('dashboard.table_col_user') }}:</strong> {{ $u?->name ?? '—' }}</p>
            <p style="margin: 0 0 8px;"><strong>{{ __('dashboard.table_col_phone') }}:</strong> <span class="mono">{{ $u?->phone ?? '—' }}</span></p>
            <p style="margin: 0 0 8px;"><strong>{{ __('dashboard.table_col_complaint_number') }}:</strong> <span class="mono">{{ $complaint->complaint_number ?? '—' }}</span></p>
            <p style="margin: 0 0 8px;"><strong>{{ __('dashboard.table_col_category') }}:</strong> {{ $complaint->category_id }}</p>
            <p style="margin: 0;"><strong>{{ __('dashboard.table_col_created') }}:</strong> {{ $complaint->created_at?->toDateTimeString() ?? '—' }}</p>
        </section>

        <section class="card" style="margin-bottom: 20px;">
            <h3 style="margin: 0 0 12px;">{{ __('dashboard.support_complaint_details') }}</h3>
            <p style="margin: 0; white-space: pre-wrap;">{{ $complaint->details }}</p>
        </section>

        <p style="margin-top: 16px;">
            <a href="{{ route('dashboard.support_complaints.index') }}" class="link">{{ __('dashboard.support_complaints_back') }}</a>
        </p>
    @endcomponent
@endsection
