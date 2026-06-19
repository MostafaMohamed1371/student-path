@extends('dashboard.layout')

@section('title', __('dashboard.add_support_complaint'))

@section('content')
    @php($title = __('dashboard.add_support_complaint'))
    @component('dashboard.partials.shell', ['title' => $title])
        <section class="card">
            <form method="post" action="{{ route('dashboard.support_complaints.store') }}" enctype="multipart/form-data">
                @csrf

                <label class="field-label">{{ __('dashboard.support_complaint_field_user') }}</label>
                <select name="user_id" class="field-like" style="width:100%;max-width:420px;margin-bottom:12px;" required>
                    <option value="">—</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" @selected(old('user_id') == $u->id)>{{ $u->name }} ({{ $u->phone }})</option>
                    @endforeach
                </select>
                @error('user_id')<p style="color:#c00;">{{ $message }}</p>@enderror

                <label class="field-label">{{ __('dashboard.table_col_category') }}</label>
                <select name="category_id" class="field-like" style="width:100%;max-width:320px;margin-bottom:12px;" required>
                    @foreach ($categories as $c)
                        <option value="{{ $c['id'] }}" @selected(old('category_id') == $c['id'])>{{ $c['label'] }}</option>
                    @endforeach
                </select>
                @error('category_id')<p style="color:#c00;">{{ $message }}</p>@enderror

                <label class="field-label">{{ __('dashboard.support_complaint_details') }}</label>
                <textarea name="details" rows="6" class="field-like" style="width:100%;max-width:560px;" required>{{ old('details') }}</textarea>
                @error('details')<p style="color:#c00;">{{ $message }}</p>@enderror

                <label class="field-label">{{ __('dashboard.image') }} ({{ __('dashboard.optional') }})</label>
                <input type="file" name="attachments[]" accept=".jpg,.jpeg,.png" multiple style="margin-bottom:12px;">
                @error('attachments')<p style="color:#c00;">{{ $message }}</p>@enderror
                @error('attachments.*')<p style="color:#c00;">{{ $message }}</p>@enderror

                <div style="margin-top:16px;">
                    <button type="submit" class="btn-primary" style="width:auto;padding:10px 14px;">{{ __('dashboard.create') }}</button>
                    <a href="{{ route('dashboard.support_complaints.index') }}" class="link" style="margin-inline-start:12px;">{{ __('dashboard.support_complaints_back') }}</a>
                </div>
            </form>
        </section>
    @endcomponent
@endsection
