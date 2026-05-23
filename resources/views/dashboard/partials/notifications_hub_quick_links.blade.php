<section class="card" style="margin-bottom: 24px;">
    <h3 style="margin: 0 0 12px;">{{ __('dashboard.notifications_hub_views_title') }}</h3>
    <div style="display: flex; flex-wrap: wrap; gap: 10px;">
        <a href="{{ route('dashboard.in_app_notifications', request()->only(['school_id', 'driver_id'])) }}"
           class="btn-primary"
           style="width: auto; padding: 10px 14px; text-decoration: none;">
            {{ __('dashboard.menu_in_app_notifications') }}
        </a>
        <a href="{{ route('dashboard.fcm_tokens.index', request()->only(['school_id', 'driver_id'])) }}"
           class="btn-primary"
           style="width: auto; padding: 10px 14px; text-decoration: none;">
            {{ __('dashboard.menu_fcm_tokens') }}
        </a>
        <a href="{{ route('dashboard.delay_alerts', request()->only(['school_id', 'driver_id'])) }}"
           class="btn-muted"
           style="width: auto; padding: 10px 14px; text-decoration: none;">
            {{ __('dashboard.menu_delay_alerts') }}
        </a>
        <a href="{{ route('dashboard.sos_alerts', request()->only(['school_id', 'driver_id'])) }}"
           class="btn-muted"
           style="width: auto; padding: 10px 14px; text-decoration: none;">
            {{ __('dashboard.menu_sos_alerts') }}
        </a>
        <a href="{{ route('dashboard.trip_finalization_reports', request()->only(['school_id', 'driver_id'])) }}"
           class="btn-muted"
           style="width: auto; padding: 10px 14px; text-decoration: none;">
            {{ __('dashboard.menu_trip_finalization_reports') }}
        </a>
    </div>
</section>
