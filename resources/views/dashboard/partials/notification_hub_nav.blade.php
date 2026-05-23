<nav style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px;" aria-label="{{ __('dashboard.notifications_hub_nav_label') }}">
    <a href="{{ route('dashboard.notifications.hub') }}"
       class="btn-muted"
       style="width:auto;padding:8px 12px;text-decoration:none;{{ request()->routeIs('dashboard.notifications.hub') ? 'opacity:1;font-weight:600;' : '' }}">
        {{ __('dashboard.menu_notifications_hub') }}
    </a>
    <a href="{{ route('dashboard.in_app_notifications') }}"
       class="btn-muted"
       style="width:auto;padding:8px 12px;text-decoration:none;{{ request()->routeIs('dashboard.in_app_notifications') ? 'opacity:1;font-weight:600;' : '' }}">
        {{ __('dashboard.menu_in_app_notifications') }}
    </a>
    <a href="{{ route('dashboard.delay_alerts') }}"
       class="btn-muted"
       style="width:auto;padding:8px 12px;text-decoration:none;{{ request()->routeIs('dashboard.delay_alerts') ? 'opacity:1;font-weight:600;' : '' }}">
        {{ __('dashboard.menu_delay_alerts') }}
    </a>
    <a href="{{ route('dashboard.sos_alerts') }}"
       class="btn-muted"
       style="width:auto;padding:8px 12px;text-decoration:none;{{ request()->routeIs('dashboard.sos_alerts') ? 'opacity:1;font-weight:600;' : '' }}">
        {{ __('dashboard.menu_sos_alerts') }}
    </a>
    <a href="{{ route('dashboard.trip_finalization_reports') }}"
       class="btn-muted"
       style="width:auto;padding:8px 12px;text-decoration:none;{{ request()->routeIs('dashboard.trip_finalization_reports') ? 'opacity:1;font-weight:600;' : '' }}">
        {{ __('dashboard.menu_trip_finalization_reports') }}
    </a>
</nav>
