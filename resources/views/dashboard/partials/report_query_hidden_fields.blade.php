@foreach(request()->only($keys ?? ['school_id', 'driver_id', 'guardian_id', 'user_role', 'notification_type', 'unread_only', 'page']) as $key => $value)
    @if(is_scalar($value) && (string) $value !== '')
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
    @endif
@endforeach
