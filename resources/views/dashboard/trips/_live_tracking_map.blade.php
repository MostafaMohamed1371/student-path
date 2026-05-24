@if($canTrackTrip && $trip->driver_id)
<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin=""
/>
<style>
    #trip_live_map {
        height: 380px;
        width: 100%;
        border-radius: 8px;
        border: 1px solid var(--border, #e5e7eb);
    }
    .trip-map-marker {
        width: 14px;
        height: 14px;
        margin-left: -7px;
        margin-top: -7px;
        border-radius: 50%;
        border: 2px solid #fff;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.35);
    }
    .trip-map-marker--driver { background: #dc2626; width: 18px; height: 18px; margin-left: -9px; margin-top: -9px; }
    .trip-map-marker--school { background: #16a34a; }
    .trip-map-marker--student { background: #2563eb; }
    .trip-tracking-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 12px 20px;
        margin: 0 0 12px;
        font-size: 14px;
    }
    .trip-tracking-meta strong { color: var(--text, #111); }
</style>

<section class="card" style="margin-bottom: 16px;" id="trip_live_tracking_panel">
    <h3 style="margin: 0 0 8px;">{{ __('dashboard.trip_live_tracking_title') }}</h3>
    <p style="color: var(--text-muted); margin: 0 0 12px; font-size: 14px;">{{ __('dashboard.trip_live_tracking_intro') }}</p>

    <div class="trip-tracking-meta">
        <span><strong>{{ __('dashboard.trip_live_status') }}:</strong>
            <span id="trip_tracking_status_label">—</span>
        </span>
        <span><strong>{{ __('dashboard.trip_live_coords') }}:</strong>
            <span id="trip_tracking_coords" class="mono">—</span>
        </span>
        <span><strong>{{ __('dashboard.trip_live_distance') }}:</strong>
            <span id="trip_tracking_distance">—</span>
        </span>
        <span><strong>{{ __('dashboard.trip_live_updated') }}:</strong>
            <span id="trip_tracking_updated">—</span>
        </span>
    </div>

    <div id="trip_live_map" role="region" aria-label="{{ __('dashboard.trip_live_tracking_title') }}"></div>
    <p class="help" style="margin: 8px 0 0;" id="trip_tracking_connection_hint"></p>
</section>

<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""
></script>
<script>
(function () {
    const mapEl = document.getElementById('trip_live_map');
    if (!mapEl || typeof L === 'undefined') {
        return;
    }

    const tripId = @json((int) $trip->id);
    const tripIsLive = @json($tripIsLive);
    const pollUrl = @json($trackingPollUrl);
    const initial = @json($tripTrackingInitial);
    const markers = @json($mapMarkers);
    const pusherEnabled = @json($pusherEnabled);
    const pusherKey = @json($pusherKey);
    const pusherCluster = @json($pusherCluster);
    const locationEvent = @json($locationBroadcastEvent);
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const labels = {
        active: @json(__('dashboard.trip_live_active')),
        inactive: @json(__('dashboard.trip_live_inactive')),
        notStarted: @json(__('dashboard.trip_live_not_started')),
        polling: @json(__('dashboard.trip_live_polling')),
        echo: @json(__('dashboard.trip_live_echo')),
        noLocation: @json(__('dashboard.trip_live_no_location')),
    };

    const defaultLat = 33.3128;
    const defaultLng = 44.3615;
    let centerLat = defaultLat;
    let centerLng = defaultLng;

    if (markers.school) {
        centerLat = markers.school.latitude;
        centerLng = markers.school.longitude;
    } else if (markers.students.length > 0) {
        centerLat = markers.students[0].latitude;
        centerLng = markers.students[0].longitude;
    }

    const map = L.map(mapEl).setView([centerLat, centerLng], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    const markerLayers = [];
    let driverMarker = null;

    function divMarker(className) {
        return L.divIcon({
            className: 'trip-map-marker ' + className,
            iconSize: className.includes('driver') ? [18, 18] : [14, 14],
            iconAnchor: className.includes('driver') ? [9, 9] : [7, 7],
        });
    }

    if (markers.school) {
        const m = L.marker([markers.school.latitude, markers.school.longitude], {
            icon: divMarker('trip-map-marker--school'),
            title: markers.school.label,
        }).addTo(map);
        m.bindPopup('<strong>' + escapeHtml(markers.school.label) + '</strong><br>' + @json(__('dashboard.school')));
        markerLayers.push(m);
    }

    markers.students.forEach(function (s) {
        const m = L.marker([s.latitude, s.longitude], {
            icon: divMarker('trip-map-marker--student'),
            title: s.name,
        }).addTo(map);
        m.bindPopup('<strong>' + escapeHtml(s.name) + '</strong><br>' + escapeHtml(s.status || ''));
        markerLayers.push(m);
    });

    function escapeHtml(text) {
        const d = document.createElement('div');
        d.textContent = text || '';
        return d.innerHTML;
    }

    function fitAll() {
        const points = markerLayers.map(function (m) { return m.getLatLng(); });
        if (driverMarker) {
            points.push(driverMarker.getLatLng());
        }
        if (points.length >= 2) {
            map.fitBounds(L.latLngBounds(points), { padding: [40, 40] });
        } else if (points.length === 1) {
            map.setView(points[0], 14);
        }
    }

    function setStatusLabel(text) {
        const el = document.getElementById('trip_tracking_status_label');
        if (el) {
            el.textContent = text;
        }
    }

    function applyTrackingPayload(data) {
        const loc = data?.location;
        const active = !!data?.tracking_active && loc && loc.latitude != null && loc.longitude != null;

        if (!tripIsLive) {
            setStatusLabel(labels.notStarted);
        } else if (active) {
            setStatusLabel(labels.active);
        } else {
            setStatusLabel(labels.inactive);
        }

        const coordsEl = document.getElementById('trip_tracking_coords');
        const distEl = document.getElementById('trip_tracking_distance');
        const updatedEl = document.getElementById('trip_tracking_updated');

        if (active) {
            const lat = Number(loc.latitude);
            const lng = Number(loc.longitude);
            if (coordsEl) {
                coordsEl.textContent = lat.toFixed(5) + ', ' + lng.toFixed(5);
            }
            if (updatedEl) {
                updatedEl.textContent = loc.recorded_at || '—';
            }
            if (distEl) {
                if (data.distance && data.distance.km != null) {
                    distEl.textContent = data.distance.km + ' km (' + Math.round(data.distance.meters) + ' m)';
                } else {
                    distEl.textContent = '—';
                }
            }

            if (driverMarker) {
                driverMarker.setLatLng([lat, lng]);
            } else {
                driverMarker = L.marker([lat, lng], {
                    icon: divMarker('trip-map-marker--driver'),
                    title: data?.driver?.name || 'Driver',
                }).addTo(map);
                driverMarker.bindPopup('<strong>' + escapeHtml(data?.driver?.name || 'Driver') + '</strong>');
                markerLayers.push(driverMarker);
            }
            fitAll();
        } else {
            if (coordsEl) {
                coordsEl.textContent = labels.noLocation;
            }
            if (distEl) {
                distEl.textContent = '—';
            }
            if (updatedEl) {
                updatedEl.textContent = '—';
            }
        }
    }

    async function pollTracking() {
        try {
            const res = await fetch(pollUrl, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });
            if (!res.ok) {
                return;
            }
            const json = await res.json();
            if (json.data) {
                applyTrackingPayload(json.data);
            }
        } catch (e) {
            console.error(e);
        }
    }

    if (initial) {
        applyTrackingPayload(initial);
    } else {
        pollTracking();
    }

    const hintEl = document.getElementById('trip_tracking_connection_hint');
    let pollInterval = null;

    function startPolling() {
        if (pollInterval) {
            return;
        }
        pollInterval = window.setInterval(pollTracking, 8000);
        if (hintEl && !pusherEnabled) {
            hintEl.textContent = labels.polling;
        }
    }

    if (tripIsLive) {
        startPolling();
    }

    if (pusherEnabled && tripIsLive && pusherKey) {
        const pusherScript = document.createElement('script');
        pusherScript.src = 'https://js.pusher.com/8.4.0-rc2/pusher.min.js';
        pusherScript.onload = function () {
            const echoScript = document.createElement('script');
            echoScript.src = 'https://cdn.jsdelivr.net/npm/laravel-echo@1.19.0/dist/echo.iife.js';
            echoScript.onload = function () {
                window.Pusher = Pusher;
                const echo = new Echo({
                    broadcaster: 'pusher',
                    key: pusherKey,
                    cluster: pusherCluster,
                    forceTLS: true,
                    authEndpoint: @json(url('/broadcasting/auth')),
                    auth: {
                        headers: {
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json',
                        },
                    },
                });
                echo.private('trip.' + tripId)
                    .listen('.' + locationEvent, function (event) {
                        if (!event) {
                            return;
                        }
                        const payload = {
                            tracking_active: !!event.active && event.location,
                            location: event.location || null,
                        };
                        applyTrackingPayload(Object.assign({}, initial || {}, payload));
                    });
                if (hintEl) {
                    hintEl.textContent = labels.echo + ' · ' + labels.polling;
                }
            };
            document.body.appendChild(echoScript);
        };
        document.body.appendChild(pusherScript);
    }

    setTimeout(function () {
        map.invalidateSize();
        fitAll();
    }, 150);
})();
</script>
@endif
