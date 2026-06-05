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
    const mapEl = document.getElementById('student-location-map');
    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');
    const landmarkInput = document.getElementById('nearest_landmark');
    const districtInput = document.getElementById('district_area');
    const reverseUrl = @json(route('dashboard.geocode.reverse'));
    const addressLoadingLabel = @json(__('dashboard.school_map_address_loading'));

    if (!mapEl || !latInput || !lngInput || typeof L === 'undefined') {
        return;
    }

    const defaultLat = 33.3128;
    const defaultLng = 44.3615;
    const hasValues = latInput.value !== '' && lngInput.value !== '';
    const lat = hasValues ? parseFloat(latInput.value) : defaultLat;
    const lng = hasValues ? parseFloat(lngInput.value) : defaultLng;

    const map = L.map(mapEl).setView([lat, lng], hasValues ? 14 : 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors',
    }).addTo(map);

    let marker = L.marker([lat, lng], { draggable: true }).addTo(map);

    const setLocation = (newLat, newLng) => {
        marker.setLatLng([newLat, newLng]);
        latInput.value = Number(newLat).toFixed(7);
        lngInput.value = Number(newLng).toFixed(7);
    };

    let reverseRequestId = 0;

    async function fillLandmarkFromMap(lat, lng) {
        if (!landmarkInput || !reverseUrl) {
            return;
        }

        const requestId = ++reverseRequestId;
        const previousLandmark = landmarkInput.value;
        landmarkInput.value = addressLoadingLabel;
        landmarkInput.disabled = true;

        try {
            const params = new URLSearchParams({
                latitude: String(lat),
                longitude: String(lng),
            });
            const res = await fetch(reverseUrl + '?' + params.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            });

            if (requestId !== reverseRequestId) {
                return;
            }

            if (!res.ok) {
                landmarkInput.value = previousLandmark;
                return;
            }

            const json = await res.json();
            const data = json.data || {};
            if (data.address) {
                landmarkInput.value = data.address;
            } else {
                landmarkInput.value = previousLandmark;
            }
            if (districtInput && !districtInput.value.trim() && data.district) {
                districtInput.value = data.district;
            }
        } catch (e) {
            landmarkInput.value = previousLandmark;
        } finally {
            if (requestId === reverseRequestId) {
                landmarkInput.disabled = false;
            }

        }
    }

    function onLocationPicked(newLat, newLng, skipLandmark) {
        setLocation(newLat, newLng);
        if (!skipLandmark) {
            fillLandmarkFromMap(newLat, newLng);
        }
    }

    map.on('click', function (event) {
        onLocationPicked(event.latlng.lat, event.latlng.lng);
    });

    marker.on('dragend', function () {
        const pos = marker.getLatLng();
        onLocationPicked(pos.lat, pos.lng);
    });

    window.studentMapSetLocation = function (newLat, newLng, landmark, district, skipReverseGeocode) {
        if (newLat === null || newLng === null || newLat === '' || newLng === '') {
            return;
        }
        const parsedLat = parseFloat(newLat);
        const parsedLng = parseFloat(newLng);
        if (Number.isNaN(parsedLat) || Number.isNaN(parsedLng)) {
            return;
        }
        setLocation(parsedLat, parsedLng);
        map.setView([parsedLat, parsedLng], 14);

        const landmarkValue = typeof landmark === 'string' ? landmark.trim() : '';
        const districtValue = typeof district === 'string' ? district.trim() : '';

        if (districtInput && districtValue !== '') {
            districtInput.value = districtValue;
        }
        if (landmarkInput && landmarkValue !== '') {
            landmarkInput.value = landmarkValue;
        } else if (!skipReverseGeocode) {
            fillLandmarkFromMap(parsedLat, parsedLng);
        }
    };
})();
</script>
