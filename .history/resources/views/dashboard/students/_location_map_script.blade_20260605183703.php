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
    const homeAddressInput = document.getElementById('home_address');
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

    function setHomeAddressValue(value) {
        if (homeAddressInput) {
            homeAddressInput.value = value;
        }
        if (landmarkInput) {
            landmarkInput.value = value;
        }
    }

    async function fillLandmarkFromMap(lat, lng) {
        if (!reverseUrl || (!homeAddressInput && !landmarkInput)) {
            return;
        }

        const requestId = ++reverseRequestId;
        const previousLandmark = homeAddressInput ? homeAddressInput.value : (landmarkInput ? landmarkInput.value : '');
        setHomeAddressValue(addressLoadingLabel);
        if (homeAddressInput) {
            homeAddressInput.disabled = true;
        }

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
                setHomeAddressValue(previousLandmark);
                return;
            }

            const json = await res.json();
            const data = json.data || {};
            if (data.address) {
                setHomeAddressValue(data.address);
            } else {
                setHomeAddressValue(previousLandmark);
            }
            if (districtInput && data.district) {
                districtInput.value = data.district;
            }
        } catch (e) {
            setHomeAddressValue(previousLandmark);
        } finally {
            if (requestId === reverseRequestId && homeAddressInput) {
                homeAddressInput.disabled = false;
            }
        }
    }

    if (homeAddressInput) {
        homeAddressInput.addEventListener('input', function () {
            const value = homeAddressInput.value;
            if (landmarkInput) {
                landmarkInput.value = value;
            }
            if (districtInput && !String(districtInput.value || '').trim()) {
                districtInput.value = value;
            }
        });
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

    window.studentMapSetLocation = function (newLat, newLng, landmark) {
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
        if (typeof landmark === 'string' && landmark.trim() !== '') {
            setHomeAddressValue(landmark);
        } else {
            fillLandmarkFromMap(parsedLat, parsedLng);
        }
    };
})();
</script>
