@csrf
@if(($method ?? 'post') !== 'post')
    @method($method)
@endif

@if ($errors->any())
    <div class="alert" style="margin-top: 0; margin-bottom: 16px;">{{ $errors->first() }}</div>
@endif

<div class="form-grid">
    <div>
        <label class="field-label" for="name_ar">{{ __('dashboard.school_name_ar') }}</label>
        <input class="input" id="name_ar" name="name_ar" value="{{ old('name_ar', $school->name_ar ?? '') }}" required />
    </div>

    <div>
        <label class="field-label" for="name_en">{{ __('dashboard.school_name_en') }}</label>
        <input class="input" id="name_en" name="name_en" value="{{ old('name_en', $school->name_en ?? '') }}" required />
    </div>

    <div>
        <label class="field-label" for="province">{{ __('dashboard.province') }}</label>
        <input class="input" id="province" name="province" value="{{ old('province', $school->province ?? '') }}" required />
    </div>

    <div>
        <label class="field-label" for="district">{{ __('dashboard.district') }}</label>
        <input class="input" id="district" name="district" value="{{ old('district', $school->district ?? '') }}" required />
    </div>

    <div>
        <label class="field-label" for="address">{{ __('dashboard.address') }}</label>
        <input class="input" id="address" name="address" value="{{ old('address', $school->address ?? '') }}" required />
    </div>

    <div>
        <label class="field-label">&nbsp;</label>
        <p class="help" style="margin-top: 10px;">Click on map to set school location.</p>
    </div>
</div>

<div style="margin-top: 8px;">
    <div id="school-map" style="height: 260px; border: 1px solid #cbd5e1; border-radius: 10px;"></div>
</div>

<div class="form-grid" style="margin-top: 16px;">
    <div>
        <label class="field-label" for="latitude">{{ __('dashboard.latitude') }}</label>
        <input class="input" id="latitude" name="latitude" type="number" step="0.0000001" min="-90" max="90" value="{{ old('latitude', $school->latitude ?? '') }}" />
    </div>

    <div>
        <label class="field-label" for="longitude">{{ __('dashboard.longitude') }}</label>
        <input class="input" id="longitude" name="longitude" type="number" step="0.0000001" min="-180" max="180" value="{{ old('longitude', $school->longitude ?? '') }}" />
    </div>

    <div>
        <label class="field-label" for="status">{{ __('dashboard.status') }}</label>
        <select class="input" id="status" name="status" required>
            <option value="active" @selected(old('status', $school->status ?? 'active') === 'active')>{{ __('dashboard.active') }}</option>
            <option value="inactive" @selected(old('status', $school->status ?? 'active') === 'inactive')>{{ __('dashboard.inactive') }}</option>
        </select>
    </div>
</div>

<div style="margin-top: 12px;">
    <h3 style="margin: 0 0 10px; font-size: 18px;">{{ __('dashboard.contact_information') }}</h3>
</div>

<div class="form-grid">
    <div>
        <label class="field-label" for="principal_name">{{ __('dashboard.principal_name') }}</label>
        <input class="input" id="principal_name" name="principal_name" value="{{ old('principal_name', $school->principal_name ?? '') }}" />
    </div>

    <div>
        <label class="field-label" for="admin_phone">{{ __('dashboard.admin_phone') }}</label>
        <input class="input" id="admin_phone" name="admin_phone" value="{{ old('admin_phone', $school->admin_phone ?? '') }}" />
    </div>

    <div>
        <label class="field-label" for="authorized_person_name">{{ __('dashboard.authorized_person_name') }}</label>
        <input class="input" id="authorized_person_name" name="authorized_person_name" value="{{ old('authorized_person_name', $school->authorized_person_name ?? '') }}" />
    </div>

    <div>
        <label class="field-label" for="authorized_person_phone">{{ __('dashboard.authorized_person_phone') }}</label>
        <input class="input" id="authorized_person_phone" name="authorized_person_phone" value="{{ old('authorized_person_phone', $school->authorized_person_phone ?? '') }}" />
    </div>
</div>

<div style="margin-top: 12px;">
    <label class="field-label" for="notes">{{ __('dashboard.notes') }}</label>
    <textarea class="input" id="notes" name="notes" rows="4">{{ old('notes', $school->notes ?? '') }}</textarea>
</div>

<div style="margin-top: 12px;">
    <label class="field-label" for="attachment">{{ __('dashboard.attachment') }}</label>
    <input class="input" id="attachment" name="attachment" type="file" />
</div>

<div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;">
    <a class="btn-muted" href="{{ route('dashboard.schools.index') }}">{{ __('dashboard.cancel') }}</a>
    <button class="btn-primary" type="submit" style="width:auto;padding-inline:16px;">{{ $submitLabel ?? __('dashboard.save') }}</button>
</div>

<link
    rel="stylesheet"
    href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
    crossorigin=""
/>
<script
    src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
    crossorigin=""
></script>
<script>
    (function () {
        const mapEl = document.getElementById('school-map');
        const latInput = document.getElementById('latitude');
        const lngInput = document.getElementById('longitude');
        if (!mapEl || !latInput || !lngInput || typeof L === 'undefined') {
            return;
        }

        const defaultLat = 33.3128;
        const defaultLng = 44.3615;
        const hasValues = latInput.value !== '' && lngInput.value !== '';
        const lat = hasValues ? parseFloat(latInput.value) : defaultLat;
        const lng = hasValues ? parseFloat(lngInput.value) : defaultLng;

        const map = L.map(mapEl).setView([lat, lng], 12);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors',
        }).addTo(map);

        let marker = L.marker([lat, lng]).addTo(map);

        const setLocation = (newLat, newLng) => {
            marker.setLatLng([newLat, newLng]);
            latInput.value = Number(newLat).toFixed(7);
            lngInput.value = Number(newLng).toFixed(7);
        };

        map.on('click', function (event) {
            setLocation(event.latlng.lat, event.latlng.lng);
        });

        latInput.addEventListener('change', function () {
            const newLat = parseFloat(latInput.value);
            const newLng = parseFloat(lngInput.value);
            if (!Number.isNaN(newLat) && !Number.isNaN(newLng)) {
                marker.setLatLng([newLat, newLng]);
                map.panTo([newLat, newLng]);
            }
        });

        lngInput.addEventListener('change', function () {
            const newLat = parseFloat(latInput.value);
            const newLng = parseFloat(lngInput.value);
            if (!Number.isNaN(newLat) && !Number.isNaN(newLng)) {
                marker.setLatLng([newLat, newLng]);
                map.panTo([newLat, newLng]);
            }
        });
    })();
</script>
