@extends('dashboard.layout')

@section('title', __('dashboard.edit_support_complaint'))

@section('content')
    @php($title = __('dashboard.edit_support_complaint'))
    @component('dashboard.partials.shell', ['title' => $title])
        <p style="margin:0 0 12px;"><strong>{{ __('dashboard.table_col_complaint_number') }}:</strong> {{ $complaint->complaint_number }}</p>
        <p style="margin:0 0 16px;"><strong>{{ __('dashboard.table_col_user') }}:</strong> {{ $complaint->user?->name }} ({{ $complaint->user?->phone }})</p>

        <section class="card">
            <form method="post" action="{{ route('dashboard.support_complaints.update', $complaint) }}">
                @csrf
                @method('PUT')

                <label class="field-label">{{ __('dashboard.table_col_category') }}</label>
                <select name="category_id" class="field-like" style="width:100%;max-width:320px;margin-bottom:12px;" required>
                    @foreach ($categories as $c)
                        <option value="{{ $c['id'] }}" @selected(old('category_id', $complaint->category_id) == $c['id'])>{{ $c['label'] }}</option>
                    @endforeach
                </select>
                @error('category_id')<p style="color:#c00;">{{ $message }}</p>@enderror

                <label class="field-label">{{ __('dashboard.support_complaint_details') }}</label>
                <textarea name="details" rows="6" class="field-like" style="width:100%;max-width:560px;" required>{{ old('details', $complaint->details) }}</textarea>
                @error('details')<p style="color:#c00;">{{ $message }}</p>@enderror

                <label class="field-label">{{ __('dashboard.support_complaint_status') }}</label>
                <select name="status" class="field-like" style="width:100%;max-width:280px;margin-bottom:12px;" required>
                    @foreach (['RECEIVED', 'IN_REVIEW', 'RESOLVED', 'CLOSED'] as $st)
                        <option value="{{ $st }}" @selected(old('status', $complaint->status) === $st)>{{ $st }}</option>
                    @endforeach
                </select>
                @error('status')<p style="color:#c00;">{{ $message }}</p>@enderror

                <div style="margin-top:16px;">
                    <button type="submit" class="btn-primary" style="width:auto;padding:10px 14px;">{{ __('dashboard.update') }}</button>
                    <a href="{{ route('dashboard.support_complaints.show', $complaint) }}" class="link" style="margin-inline-start:12px;">{{ __('dashboard.cancel') }}</a>
                </div>
            </form>
        </section>
    @endcomponent
@endsection
